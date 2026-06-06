<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PriceHistory;
use App\Models\UserProfile;
use App\Models\UserProfileAssetChart;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MarketChartService
{
    public function __construct(
        protected ChartDataModeService $chartMode,
        protected RealMarketChartService $realCharts,
    ) {}
    private const MAX_CHART_POINTS = 48;

    private const NEW_SEGMENT_POINTS = 16;

    /**
     * @return array<int, string> asset_id => chart_trend
     */
    public function chartTrendsForProfile(?UserProfile $profile): array
    {
        if (! $profile) {
            return [];
        }

        return UserProfileAssetChart::query()
            ->where('user_profile_id', $profile->id)
            ->pluck('chart_trend', 'asset_id')
            ->all();
    }

    public function resolveTrend(Asset $asset, ?UserProfile $profile, ?array $profileTrends = null): string
    {
        if ($profile) {
            $profileTrends ??= $this->chartTrendsForProfile($profile);
            if (isset($profileTrends[$asset->id])) {
                return $profileTrends[$asset->id];
            }
        }

        return $asset->chart_trend ?? 'up';
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function getChartForAsset(Asset $asset, int $points = 40, ?string $trend = null): array
    {
        $trend ??= $asset->chart_trend ?? 'up';

        $stored = $this->loadGlobalChart($asset);
        if (count($stored) >= 10) {
            return $this->trimChart($stored);
        }

        return $this->generateSeries($asset, $points, $trend);
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function getChartForProfile(UserProfile $profile, Asset $asset, string $trend): array
    {
        $row = UserProfileAssetChart::query()
            ->where('user_profile_id', $profile->id)
            ->where('asset_id', $asset->id)
            ->first();

        if ($row?->chart_data && is_array($row->chart_data) && count($row->chart_data) >= 10) {
            return $this->trimChart($row->chart_data);
        }

        return $this->getChartForAsset($asset, 40, $trend);
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function generateSeries(Asset $asset, int $points = 40, ?string $trend = null): array
    {
        $trend ??= $asset->chart_trend ?? 'up';
        $end = $this->resolveEndPrice($asset);
        $start = $trend === 'up' ? $end * 0.965 : $end * 1.035;

        return $this->buildSegment($start, $end, $points, $trend, Carbon::now()->subMinutes($points - 1));
    }

    /**
     * When admin flips trend, keep prior segment points and append the new direction.
     *
     * @param  array<int, array{price: string, recorded_at?: string, trend?: string}>  $existing
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function appendTrendSegment(array $existing, Asset $asset, string $previousTrend, string $newTrend): array
    {
        if ($previousTrend === $newTrend || count($existing) < 2) {
            return $this->generateSeries($asset, 40, $newTrend);
        }

        $normalized = $this->normalizeChartPoints($existing, $previousTrend);
        $lastPrice = (float) $normalized[array_key_last($normalized)]['price'];
        $end = $this->resolveEndPrice($asset);
        $targetEnd = $newTrend === 'up' ? $end * 1.008 : $end * 0.992;

        $append = $this->buildSegment($lastPrice, $targetEnd, self::NEW_SEGMENT_POINTS, $newTrend, Carbon::now());

        $merged = array_merge($normalized, $append);

        return $this->trimChart($merged);
    }

    /**
     * @param  array<int, array{price: string, recorded_at?: string, trend?: string}>  $series
     * @return array{overall_change_pct: float, up_leg_pct: ?float, down_leg_pct: ?float, transition_index: ?int, previous_trend: ?string}
     */
    public function chartSummary(array $series, string $currentTrend): array
    {
        $points = $this->normalizeChartPoints($series, $currentTrend);
        if (count($points) < 2) {
            return [
                'overall_change_pct' => 0.0,
                'up_leg_pct' => null,
                'down_leg_pct' => null,
                'transition_index' => null,
                'previous_trend' => null,
            ];
        }

        $first = (float) $points[0]['price'];
        $last = (float) $points[array_key_last($points)]['price'];
        $overall = $first > 0 ? (($last - $first) / $first) * 100 : 0.0;

        $transitionIndex = null;
        $previousTrend = null;
        for ($i = 1; $i < count($points); $i++) {
            if ($points[$i]['trend'] !== $points[$i - 1]['trend']) {
                $transitionIndex = $i;
                $previousTrend = $points[$i - 1]['trend'];
                break;
            }
        }

        $upLeg = null;
        $downLeg = null;
        if ($transitionIndex !== null) {
            $peak = (float) $points[$transitionIndex - 1]['price'];
            $upStart = (float) $points[0]['price'];
            $downEnd = $last;
            if ($upStart > 0) {
                $upLeg = (($peak - $upStart) / $upStart) * 100;
            }
            if ($peak > 0) {
                $downLeg = (($downEnd - $peak) / $peak) * 100;
            }
        }

        return [
            'overall_change_pct' => round($overall, 2),
            'up_leg_pct' => $upLeg !== null ? round($upLeg, 2) : null,
            'down_leg_pct' => $downLeg !== null ? round($downLeg, 2) : null,
            'transition_index' => $transitionIndex,
            'previous_trend' => $previousTrend,
        ];
    }

    public function persistSeries(Asset $asset, array $series): void
    {
        PriceHistory::where('asset_id', $asset->id)
            ->where('interval', '1m')
            ->where('source', 'admin_override')
            ->delete();

        foreach ($this->normalizeChartPoints($series, $asset->chart_trend ?? 'up') as $point) {
            PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => $point['price'],
                'close' => $point['price'],
                'source' => 'admin_override',
                'interval' => '1m',
                'segment_trend' => $point['trend'],
                'recorded_at' => Carbon::parse($point['recorded_at']),
            ]);
        }
    }

    /** Global default chart (all users without a per-profile override). */
    public function setTrend(Asset $asset, string $trend, ?int $adminUserId = null): Asset
    {
        $previousTrend = $asset->chart_trend ?? 'up';
        $existing = $this->loadGlobalChart($asset);

        $series = $this->appendTrendSegment($existing, $asset, $previousTrend, $trend);

        $change = $trend === 'up' ? '1.85' : '-1.85';
        $current = (float) $asset->effectivePrice();
        $adjusted = $trend === 'up' ? $current * 1.008 : $current * 0.992;

        $asset->update([
            'chart_trend' => $trend,
            'price_change_24h' => $change,
            'live_price' => number_format(max(0.0001, $adjusted), 8, '.', ''),
            'admin_price' => number_format(max(0.0001, $adjusted), 8, '.', ''),
            'admin_override_active' => true,
            'override_set_by' => $adminUserId,
            'override_set_at' => now(),
            'price_updated_at' => now(),
        ]);

        $this->persistSeries($asset->fresh(), $series);

        return $asset->fresh();
    }

    /** Per-user chart shown on mobile for this profile + asset. */
    public function setTrendForProfile(UserProfile $profile, Asset $asset, string $trend, ?int $adminUserId = null): UserProfileAssetChart
    {
        $row = UserProfileAssetChart::firstOrCreate(
            [
                'user_profile_id' => $profile->id,
                'asset_id' => $asset->id,
            ],
            [
                'chart_trend' => $asset->chart_trend ?? 'up',
            ]
        );

        $previousTrend = $row->chart_trend ?? $asset->chart_trend ?? 'up';
        $existing = is_array($row->chart_data) ? $row->chart_data : $this->getChartForAsset($asset, 40, $previousTrend);
        $series = $this->appendTrendSegment($existing, $asset, $previousTrend, $trend);

        $row->update([
            'chart_trend' => $trend,
            'chart_data' => $series,
            'set_by' => $adminUserId,
            'set_at' => now(),
        ]);

        return $row->fresh();
    }

    public function ensureProfileChartRows(UserProfile $profile): void
    {
        $assets = Asset::where('is_active', true)->orderBy('sort_order')->get();

        foreach ($assets as $asset) {
            UserProfileAssetChart::firstOrCreate(
                [
                    'user_profile_id' => $profile->id,
                    'asset_id' => $asset->id,
                ],
                [
                    'chart_trend' => $asset->chart_trend ?? 'up',
                    'chart_data' => $this->getChartForAsset($asset, 40, $asset->chart_trend ?? 'up'),
                ]
            );
        }
    }

    /**
     * @param  Collection<int, Asset>|iterable<int, Asset>  $assets
     */
    public function preloadRealChartsForProfile(?UserProfile $profile, Collection|iterable $assets): void
    {
        if (! $this->chartMode->isRealForProfile($profile)) {
            return;
        }

        $this->realCharts->preloadLiveCharts($assets);
    }

    public function formatAssetForApi(Asset $asset, ?UserProfile $profile = null, ?array $profileTrends = null): array
    {
        if ($this->chartMode->isRealForProfile($profile)) {
            return $this->formatAssetForApiReal($asset);
        }

        return $this->formatAssetForApiCustom($asset, $profile, $profileTrends);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAssetForApiReal(Asset $asset): array
    {
        $chart = $this->realCharts->getChartForAsset($asset, 40);
        $trend = $this->realCharts->resolveTrendFromChart($chart);
        $summary = $this->chartSummary($chart, $trend);
        $price = (string) $asset->live_price;
        $change = $this->realCharts->formatPriceChange($asset);
        $message = $this->realCharts->marketMessage($trend, $summary);

        return array_merge([
            'id' => $asset->id,
            'name' => $asset->name,
            'symbol' => $asset->symbol,
            'display_name' => $asset->display_name,
            'live_price' => (string) $asset->live_price,
            'admin_price' => $asset->admin_price !== null ? (string) $asset->admin_price : null,
            'admin_override_active' => false,
            'current_price' => $price,
            'price_change_24h' => $change,
            'price_updated_at' => $asset->price_updated_at?->toIso8601String(),
            'chart_trend' => $trend,
            'market_signal' => $trend === 'up' ? 'buy' : 'hold',
            'market_message' => $message,
            'trading_enabled' => (bool) $asset->trading_enabled,
            'min_trade_amount' => (string) $asset->min_trade_amount,
            'chart' => $chart,
            'chart_summary' => $summary,
            'chart_scope' => 'real',
        ], $this->quoteFields($price, $chart, $asset));
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatAssetForApiCustom(Asset $asset, ?UserProfile $profile = null, ?array $profileTrends = null): array
    {
        $profileTrends ??= $profile ? $this->chartTrendsForProfile($profile) : [];
        $trend = $this->resolveTrend($asset, $profile, $profileTrends);
        $hasUserOverride = $profile && array_key_exists($asset->id, $profileTrends);

        $chart = $hasUserOverride && $profile
            ? $this->getChartForProfile($profile, $asset, $trend)
            : $this->getChartForAsset($asset, 40, $trend);

        $summary = $this->chartSummary($chart, $trend);
        $price = $asset->effectivePrice();
        $change = $trend === 'up' ? abs((float) $asset->price_change_24h) : -abs((float) $asset->price_change_24h);
        if ((float) $asset->price_change_24h < 0 && $trend === 'up') {
            $change = abs((float) $asset->price_change_24h);
        }

        $message = $trend === 'up'
            ? 'Market is moving up — good time to buy'
            : ($summary['previous_trend'] === 'up'
                ? 'Market was up, now moving down — avoid buying now'
                : 'Market is moving down — avoid buying now');

        return array_merge([
            'id' => $asset->id,
            'name' => $asset->name,
            'symbol' => $asset->symbol,
            'display_name' => $asset->display_name,
            'live_price' => (string) $asset->live_price,
            'admin_price' => $asset->admin_price !== null ? (string) $asset->admin_price : null,
            'admin_override_active' => (bool) $asset->admin_override_active,
            'current_price' => $price,
            'price_change_24h' => number_format($change, 2, '.', ''),
            'price_updated_at' => $asset->price_updated_at?->toIso8601String(),
            'chart_trend' => $trend,
            'market_signal' => $trend === 'up' ? 'buy' : 'hold',
            'market_message' => $message,
            'trading_enabled' => (bool) $asset->trading_enabled,
            'min_trade_amount' => (string) $asset->min_trade_amount,
            'chart' => $chart,
            'chart_summary' => $summary,
            'chart_scope' => $hasUserOverride ? 'user' : 'global',
        ], $this->quoteFields($price, $chart, $asset));
    }

    /**
     * Octa-style bid/ask with realistic spread display per symbol.
     *
     * @return array{bid: string, ask: string, spread: string, day_low: string, day_high: string}
     */
    public function quoteFields(string $price, array $chart, ?Asset $asset = null): array
    {
        $mid = (float) $price;
        $symbol = strtoupper($asset?->symbol ?? '');

        $spreadPoints = match ($symbol) {
            'XAU' => 4.6,
            'XAG' => 0.35,
            'WTI' => 0.05,
            'BTC' => 3.4,
            'ETH' => 0.45,
            'USDT' => 0.0002,
            'EURUSD' => 0.00014,
            'GBPUSD' => 0.00016,
            'USDJPY' => 0.012,
            'NAS100' => 3.3,
            'US30' => 4.0,
            'SPX' => 0.4,
            default => max($mid * 0.0002, 0.00001),
        };

        $half = $spreadPoints / 2;
        $bid = max(0.00000001, $mid - $half);
        $ask = $mid + $half;

        $prices = array_map(fn (array $point) => (float) $point['price'], $chart);
        $low = count($prices) ? min($prices) : $mid * 0.998;
        $high = count($prices) ? max($prices) : $mid * 1.002;

        // Widen L/H slightly when chart is flat (demo data)
        if ($high - $low < $spreadPoints) {
            $low = $mid - ($spreadPoints * 2);
            $high = $mid + ($spreadPoints * 2);
        }

        $displaySpread = match ($symbol) {
            'XAU' => '4.6',
            'BTC' => '3.4',
            'ETH' => '0.4',
            'EURUSD' => '1.4',
            'GBPUSD' => '1.6',
            'USDJPY' => '1.2',
            'NAS100' => '3.3',
            'US30' => '4.0',
            default => number_format($spreadPoints >= 1 ? $spreadPoints : $spreadPoints * 10000, 1, '.', ''),
        };

        return [
            'bid' => number_format($bid, 8, '.', ''),
            'ask' => number_format($ask, 8, '.', ''),
            'spread' => $displaySpread,
            'day_low' => number_format($low, 8, '.', ''),
            'day_high' => number_format($high, 8, '.', ''),
        ];
    }

    public function seedAllActiveAssets(): void
    {
        Asset::where('is_active', true)->each(function (Asset $asset) {
            if (PriceHistory::where('asset_id', $asset->id)->where('source', 'admin_override')->count() < 10) {
                $this->persistSeries($asset, $this->generateSeries($asset));
            }
        });
    }

    /**
     * Nudge custom charts forward with small up/down steps (scheduled job).
     */
    public function tickAllCustomCharts(): void
    {
        if (! $this->chartMode->isReal()) {
            Asset::where('is_active', true)->each(fn (Asset $asset) => $this->tickGlobalChart($asset));
        }

        UserProfileAssetChart::with('asset')->chunkById(50, function ($rows) {
            foreach ($rows as $row) {
                if ($row->asset) {
                    $this->tickProfileChart($row);
                }
            }
        });
    }

    public function tickGlobalChart(Asset $asset): void
    {
        $trend = $asset->chart_trend ?? 'up';
        $existing = $this->loadGlobalChart($asset);
        $series = count($existing) >= 2
            ? $this->appendLiveTick($existing, $trend)
            : $this->generateSeries($asset, 40, $trend);

        $this->applyTickPrice($asset, $trend, $series, global: true);
    }

    public function tickProfileChart(UserProfileAssetChart $row): void
    {
        $asset = $row->asset;
        $trend = $row->chart_trend ?? $asset->chart_trend ?? 'up';
        $existing = is_array($row->chart_data) && count($row->chart_data) >= 2
            ? $row->chart_data
            : $this->getChartForAsset($asset, 40, $trend);

        $series = $this->appendLiveTick($existing, $trend);

        $row->update(['chart_data' => $series]);
    }

    /**
     * @param  array<int, array{price: string, recorded_at?: string, trend?: string}>  $series
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function appendLiveTick(array $series, string $trend): array
    {
        $normalized = $this->normalizeChartPoints($series, $trend);
        $last = (float) $normalized[array_key_last($normalized)]['price'];
        $deltaPct = $trend === 'up' ? 0.00035 : -0.00035;
        $noise = sin(microtime(true) * 10) * $last * 0.00008;
        $next = max(0.0001, $last * (1 + $deltaPct) + $noise);

        $normalized[] = [
            'price' => number_format($next, 8, '.', ''),
            'recorded_at' => now()->toIso8601String(),
            'trend' => $trend,
        ];

        return $this->trimChart($normalized);
    }

    /**
     * @param  array<int, array{price: string, recorded_at: string, trend: string}>  $series
     */
    protected function applyTickPrice(Asset $asset, string $trend, array $series, bool $global): void
    {
        $lastPrice = (float) $series[array_key_last($series)]['price'];
        $change = $trend === 'up' ? '0.15' : '-0.15';
        $currentChange = (float) ($asset->price_change_24h ?? 0);
        $newChange = $currentChange + (float) $change;
        $newChange = max(-99, min(99, $newChange));

        $asset->update([
            'live_price' => number_format($lastPrice, 8, '.', ''),
            'admin_price' => number_format($lastPrice, 8, '.', ''),
            'admin_override_active' => true,
            'price_change_24h' => number_format($newChange, 2, '.', ''),
            'price_updated_at' => now(),
        ]);

        if ($global) {
            $this->persistSeries($asset->fresh(), $series);
        }
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function loadGlobalChart(Asset $asset): array
    {
        return PriceHistory::where('asset_id', $asset->id)
            ->where('interval', '1m')
            ->where('source', 'admin_override')
            ->orderBy('recorded_at')
            ->get()
            ->map(fn (PriceHistory $row) => [
                'price' => (string) $row->price,
                'recorded_at' => $row->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
                'trend' => $row->segment_trend ?? $asset->chart_trend ?? 'up',
            ])
            ->all();
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function buildSegment(float $start, float $end, int $points, string $trend, Carbon $startTime): array
    {
        $series = [];
        for ($i = 0; $i < $points; $i++) {
            $progress = $points > 1 ? $i / ($points - 1) : 1;
            $base = $start + ($end - $start) * $progress;
            $noise = sin($i * 1.7) * ($end * 0.0025) + cos($i * 0.9) * ($end * 0.0015);
            $price = max(0.0001, $base + $noise);

            $series[] = [
                'price' => number_format($price, 8, '.', ''),
                'recorded_at' => $startTime->copy()->addMinutes($i)->toIso8601String(),
                'trend' => $trend,
            ];
        }

        return $series;
    }

    private function resolveEndPrice(Asset $asset): float
    {
        $end = (float) $asset->effectivePrice();
        if ($end > 0) {
            return $end;
        }

        return match (strtoupper($asset->symbol)) {
            'XAU' => 2650.0,
            'XAG' => 31.0,
            'USDT' => 1.0,
            default => 100.0,
        };
    }

    /**
     * @param  array<int, array{price: string, recorded_at?: string, trend?: string}>  $series
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function normalizeChartPoints(array $series, string $fallbackTrend): array
    {
        return array_values(array_map(function (array $point) use ($fallbackTrend) {
            return [
                'price' => (string) $point['price'],
                'recorded_at' => $point['recorded_at'] ?? now()->toIso8601String(),
                'trend' => in_array($point['trend'] ?? null, ['up', 'down'], true) ? $point['trend'] : $fallbackTrend,
            ];
        }, $series));
    }

    /**
     * @param  array<int, array{price: string, recorded_at: string, trend: string}>  $series
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function trimChart(array $series): array
    {
        if (count($series) <= self::MAX_CHART_POINTS) {
            return array_values($series);
        }

        return array_values(array_slice($series, -self::MAX_CHART_POINTS));
    }
}
