<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\PriceHistory;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RealMarketChartService
{
    private const MAX_CHART_POINTS = 48;

    /** @var array<int, array<int, array{price: string, recorded_at: string, trend: string}>> */
    private array $preloadedCharts = [];

    /**
     * @param  iterable<int, Asset>  $assets
     */
    public function preloadLiveCharts(iterable $assets, int $points = 40): void
    {
        $assetIds = collect($assets)->pluck('id')->filter()->unique()->values()->all();
        if ($assetIds === []) {
            return;
        }

        $rowsByAsset = PriceHistory::query()
            ->whereIn('asset_id', $assetIds)
            ->where('source', 'live_api')
            ->where('interval', '1m')
            ->orderByDesc('recorded_at')
            ->get()
            ->groupBy('asset_id');

        foreach ($assets as $asset) {
            $rows = $rowsByAsset->get($asset->id, collect())
                ->sortBy('recorded_at')
                ->take(-$points)
                ->values();

            if ($rows->count() < 10) {
                continue;
            }

            $series = $rows
                ->map(fn (PriceHistory $row) => [
                    'price' => (string) ($row->close ?? $row->price),
                    'recorded_at' => $row->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
                    'trend' => 'up',
                ])
                ->all();

            $this->preloadedCharts[$asset->id] = $this->applyTrends($series);
        }
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    public function getChartForAsset(Asset $asset, int $points = 40): array
    {
        if (isset($this->preloadedCharts[$asset->id])) {
            return $this->preloadedCharts[$asset->id];
        }

        $stored = $this->loadLiveChart($asset, $points);
        if (count($stored) >= 10) {
            return $this->applyTrends($stored);
        }

        // Skip blocking outbound HTTP during web requests (shared hosts often timeout).
        if (! app()->runningInConsole()) {
            return $this->buildFallbackSeries($asset, $points);
        }

        $external = $this->fetchExternalSeries($asset, $points);
        if (count($external) >= 10) {
            $this->persistLiveSeries($asset, $external);

            return $this->applyTrends($external);
        }

        return $this->buildFallbackSeries($asset, $points);
    }

    public function resolveTrendFromChart(array $chart): string
    {
        if (count($chart) < 2) {
            return 'up';
        }

        $first = (float) $chart[0]['price'];
        $last = (float) $chart[array_key_last($chart)]['price'];

        return $last >= $first ? 'up' : 'down';
    }

    public function resolveTrendFromAsset(Asset $asset): string
    {
        $change = (float) $asset->price_change_24h;

        return $change >= 0 ? 'up' : 'down';
    }

    public function formatPriceChange(Asset $asset): string
    {
        return number_format((float) $asset->price_change_24h, 2, '.', '');
    }

    public function marketMessage(string $trend, ?array $summary = null): string
    {
        if ($trend === 'up') {
            return 'Market is moving up — good time to buy';
        }

        if (($summary['previous_trend'] ?? null) === 'up') {
            return 'Market was up, now moving down — avoid buying now';
        }

        return 'Market is moving down — avoid buying now';
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function loadLiveChart(Asset $asset, int $points): array
    {
        return PriceHistory::query()
            ->where('asset_id', $asset->id)
            ->where('source', 'live_api')
            ->where('interval', '1m')
            ->orderByDesc('recorded_at')
            ->limit($points)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (PriceHistory $row) => [
                'price' => (string) ($row->close ?? $row->price),
                'recorded_at' => $row->recorded_at?->toIso8601String() ?? now()->toIso8601String(),
                'trend' => 'up',
            ])
            ->all();
    }

    /**
     * @param  array<int, array{price: string, recorded_at: string, trend: string}>  $series
     */
    private function persistLiveSeries(Asset $asset, array $series): void
    {
        if (PriceHistory::where('asset_id', $asset->id)->where('source', 'live_api')->count() >= 10) {
            return;
        }

        foreach ($series as $point) {
            PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => $point['price'],
                'close' => $point['price'],
                'source' => 'live_api',
                'interval' => '1m',
                'segment_trend' => $point['trend'],
                'recorded_at' => Carbon::parse($point['recorded_at']),
            ]);
        }
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function fetchExternalSeries(Asset $asset, int $points): array
    {
        $config = $asset->api_config ?? [];
        $provider = $config['provider'] ?? null;

        if ($provider === 'binance' || $asset->symbol === 'USDT') {
            $series = $this->fetchBinanceKlines($config['pair'] ?? 'USDTUSD', $points);
            if (count($series) >= 10) {
                return $series;
            }
        }

        $apiKey = config('services.metals_api.key');
        if ($apiKey && ($provider === 'metals_api' || in_array($asset->symbol, ['XAU', 'XAG'], true))) {
            $series = $this->fetchMetalsApiTimeseries($asset, $apiKey, $points);
            if (count($series) >= 10) {
                return $series;
            }
        }

        return $this->fetchYahooChart($asset, $points);
    }

    public function resolveYahooSymbol(Asset $asset): ?string
    {
        $config = $asset->api_config ?? [];
        if (! empty($config['yahoo_symbol'])) {
            return (string) $config['yahoo_symbol'];
        }

        return match (strtoupper($asset->symbol)) {
            'XAU' => 'GC=F',
            'XAG' => 'SI=F',
            'WTI' => 'CL=F',
            'USDT' => 'USDT-USD',
            'BTC' => 'BTC-USD',
            'ETH' => 'ETH-USD',
            'EURUSD' => 'EURUSD=X',
            'GBPUSD' => 'GBPUSD=X',
            'USDJPY' => 'USDJPY=X',
            'SPX' => '^GSPC',
            'NAS100' => '^NDX',
            'US30' => '^DJI',
            default => null,
        };
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function fetchYahooChart(Asset $asset, int $points): array
    {
        $yahooSymbol = $this->resolveYahooSymbol($asset);

        if (! $yahooSymbol) {
            return [];
        }

        try {
            $encoded = rawurlencode($yahooSymbol);
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MBXZone/1.0)'])
                ->get("https://query1.finance.yahoo.com/v8/finance/chart/{$encoded}", [
                    'interval' => '5m',
                    'range' => '1d',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $result = $response->json('chart.result.0');
            $timestamps = $result['timestamp'] ?? [];
            $closes = $result['indicators']['quote'][0]['close'] ?? [];

            return $this->buildSeriesFromCloses($timestamps, $closes, $points);
        } catch (\Throwable $e) {
            Log::warning('Yahoo chart fetch failed', ['symbol' => $asset->symbol, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function fetchBinanceKlines(string $pair, int $points): array
    {
        $symbol = strtoupper(str_replace(['/', '-'], '', $pair));
        if ($symbol === 'USDTUSD') {
            $symbol = 'USDCUSDT';
        }

        try {
            $response = Http::timeout(15)->get('https://api.binance.com/api/v3/klines', [
                'symbol' => $symbol,
                'interval' => '5m',
                'limit' => min($points, 100),
            ]);

            if (! $response->successful() || ! is_array($response->json())) {
                return [];
            }

            $series = [];
            foreach ($response->json() as $candle) {
                $close = $candle[4] ?? null;
                $time = $candle[0] ?? null;
                if ($close === null || $time === null) {
                    continue;
                }
                $series[] = [
                    'price' => number_format((float) $close, 8, '.', ''),
                    'recorded_at' => Carbon::createFromTimestampMs((int) $time)->toIso8601String(),
                    'trend' => 'up',
                ];
            }

            return array_slice($series, -$points);
        } catch (\Throwable $e) {
            Log::warning('Binance klines fetch failed', ['pair' => $pair, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function fetchMetalsApiTimeseries(Asset $asset, string $apiKey, int $points): array
    {
        $config = $asset->api_config ?? [];
        $metalSymbol = $config['symbol'] ?? $asset->symbol;
        $end = now()->format('Y-m-d');
        $start = now()->subDay()->format('Y-m-d');

        try {
            $response = Http::timeout(15)->get('https://metals-api.com/api/timeseries', [
                'access_key' => $apiKey,
                'start_date' => $start,
                'end_date' => $end,
                'base' => $config['base'] ?? 'USD',
                'symbols' => $metalSymbol,
            ]);

            if (! $response->successful()) {
                return [];
            }

            $rates = $response->json('rates', []);
            $series = [];

            foreach ($rates as $date => $dayRates) {
                $rate = $dayRates[$metalSymbol] ?? null;
                if ($rate === null || (float) $rate <= 0) {
                    continue;
                }
                $price = bcdiv('1', (string) $rate, 8);
                $series[] = [
                    'price' => $price,
                    'recorded_at' => Carbon::parse($date)->endOfDay()->toIso8601String(),
                    'trend' => 'up',
                ];
            }

            if (count($series) < 10) {
                return [];
            }

            return array_slice($series, -$points);
        } catch (\Throwable $e) {
            Log::warning('Metals API timeseries failed', ['asset' => $asset->symbol, 'error' => $e->getMessage()]);

            return [];
        }
    }

    /**
     * @param  array<int, int>  $timestamps
     * @param  array<int, float|null>  $closes
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function buildSeriesFromCloses(array $timestamps, array $closes, int $points): array
    {
        $series = [];
        foreach ($timestamps as $i => $ts) {
            $close = $closes[$i] ?? null;
            if ($close === null) {
                continue;
            }
            $series[] = [
                'price' => number_format((float) $close, 8, '.', ''),
                'recorded_at' => Carbon::createFromTimestamp((int) $ts)->toIso8601String(),
                'trend' => 'up',
            ];
        }

        if (count($series) > $points) {
            $series = array_slice($series, -$points);
        }

        return $series;
    }

    /**
     * @param  array<int, array{price: string, recorded_at: string, trend: string}>  $series
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function applyTrends(array $series): array
    {
        if (count($series) < 2) {
            return $series;
        }

        $result = [];
        $prev = (float) $series[0]['price'];

        foreach ($series as $i => $point) {
            $price = (float) $point['price'];
            $trend = $i === 0 ? 'up' : ($price >= $prev ? 'up' : 'down');
            $result[] = [
                'price' => $point['price'],
                'recorded_at' => $point['recorded_at'],
                'trend' => $trend,
            ];
            $prev = $price;
        }

        if (count($result) > self::MAX_CHART_POINTS) {
            $result = array_slice($result, -self::MAX_CHART_POINTS);
        }

        return array_values($result);
    }

    /**
     * @return array<int, array{price: string, recorded_at: string, trend: string}>
     */
    private function buildFallbackSeries(Asset $asset, int $points): array
    {
        $trend = $this->resolveTrendFromAsset($asset);
        $end = (float) $asset->live_price;
        if ($end <= 0) {
            $end = match (strtoupper($asset->symbol)) {
                'XAU' => 2650.0,
                'XAG' => 31.0,
                'WTI' => 78.5,
                'USDT' => 1.0,
                'BTC' => 97500.0,
                'ETH' => 3650.0,
                'EURUSD' => 1.085,
                'GBPUSD' => 1.265,
                'USDJPY' => 156.2,
                'SPX' => 5900.0,
                'NAS100' => 21400.0,
                'US30' => 42500.0,
                default => 100.0,
            };
        }
        $start = $trend === 'up' ? $end * 0.98 : $end * 1.02;
        $startTime = Carbon::now()->subMinutes($points - 1);
        $series = [];

        for ($i = 0; $i < $points; $i++) {
            $progress = $points > 1 ? $i / ($points - 1) : 1;
            $base = $start + ($end - $start) * $progress;
            $price = max(0.0001, $base);
            $series[] = [
                'price' => number_format($price, 8, '.', ''),
                'recorded_at' => $startTime->copy()->addMinutes($i)->toIso8601String(),
                'trend' => $trend,
            ];
        }

        return $series;
    }
}
