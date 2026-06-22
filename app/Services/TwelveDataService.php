<?php

namespace App\Services;

use App\Models\Asset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TwelveDataService
{
    public const CACHE_TTL_SECONDS = 60;

    public const SERIES_CACHE_TTL_SECONDS = 300;

    public const QUOTE_CACHE_KEY = 'twelve_data:quotes:v2';

    public const RATE_LIMIT_CACHE_KEY = 'twelve_data:rate_limited_until';

    /** @var array<string, array<string, mixed>>|null */
    protected ?array $requestLiveData = null;

    protected bool $allowSeriesFetch = true;

    /** @var array<string, string> Internal symbol → Twelve Data symbol */
    public const SYMBOL_MAP = [
        'XAU' => 'XAU/USD',
        'XAG' => 'XAG/USD',
        'WTI' => 'USO',
        'BTC' => 'BTC/USD',
        'ETH' => 'ETH/USD',
        'USDT' => 'USDT/USD',
        'EURUSD' => 'EUR/USD',
        'GBPUSD' => 'GBP/USD',
        'USDJPY' => 'USD/JPY',
        'SPX' => 'SPY',
        'NAS100' => 'QQQ',
        'US30' => 'DIA',
    ];

    /**
     * Table prices from batch quote only — max 2 API calls (8 symbols each).
     *
     * @return array<string, array<string, mixed>>
     */
    public function getLiveDataForAssets(?Collection $assets = null, bool $force = false): array
    {
        $assets ??= Asset::query()->orderBy('sort_order')->get();

        if ($force) {
            Cache::forget(self::QUOTE_CACHE_KEY);
        }

        $quotes = Cache::remember(self::QUOTE_CACHE_KEY, self::CACHE_TTL_SECONDS, function () use ($assets) {
            return $this->fetchBatchQuotes($assets);
        });

        return $this->mapQuotesToAssets($assets, $quotes);
    }

    /**
     * One time_series call for the chart preview symbol.
     *
     * @return array{values: list<array<string, mixed>>, status: string, message?: string, source: string}
     */
    public function refreshPreviewCandles(string $internalSymbol, bool $force = false): array
    {
        $cacheKey = 'twelve_data:series:'.Str::slug($internalSymbol).':30';

        if ($force) {
            Cache::forget($cacheKey);
        }

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ($cached['status'] ?? '') === 'ok') {
            return $cached;
        }

        if ($this->isRateLimited() || ! $this->allowSeriesFetch) {
            return is_array($cached) ? $cached : [
                'status' => 'error',
                'message' => 'Preview candles unavailable (cached only).',
                'values' => [],
            ];
        }

        $payload = $this->fetchTimeSeries($internalSymbol, 30);

        if (($payload['status'] ?? 'error') === 'ok') {
            Cache::put($cacheKey, $payload, self::SERIES_CACHE_TTL_SECONDS);

            return $payload;
        }

        if ($payload['rate_limited'] ?? false) {
            return is_array($cached) ? $cached : $payload;
        }

        Cache::put($cacheKey, $payload, 15);

        return $payload;
    }

    public function setAllowSeriesFetch(bool $allow): self
    {
        $this->allowSeriesFetch = $allow;

        return $this;
    }

    public function isRateLimited(): bool
    {
        return Cache::has(self::RATE_LIMIT_CACHE_KEY);
    }

    protected function markRateLimited(int $seconds = 60): void
    {
        Cache::put(self::RATE_LIMIT_CACHE_KEY, true, $seconds);
    }

    public function clearCaches(): void
    {
        Cache::forget(self::QUOTE_CACHE_KEY);
        Cache::forget(self::RATE_LIMIT_CACHE_KEY);
        $this->requestLiveData = null;
        $this->allowSeriesFetch = true;

        foreach (array_keys(self::SYMBOL_MAP) as $symbol) {
            Cache::forget('twelve_data:series:'.Str::slug($symbol).':30');
        }
    }

    /**
     * Warm quote cache once per request (mobile v2 list).
     *
     * @return array<string, array<string, mixed>>
     */
    public function warmLiveData(bool $force = false): array
    {
        if ($this->requestLiveData !== null && ! $force) {
            return $this->requestLiveData;
        }

        $this->requestLiveData = $this->getLiveDataForAssets(force: $force);

        return $this->requestLiveData;
    }

    /**
     * Build mobile chart + candles from Twelve Data (Markets Live / v2).
     *
     * @return array{
     *     chart: list<array{price: string, recorded_at: string, trend: string}>,
     *     chart_candles: list<array{open: string, high: string, low: string, close: string, recorded_at: string}>,
     *     live_price: string,
     *     price_change_24h: string,
     *     trend: string,
     *     source: string
     * }
     */
    public function mobileChartBundle(Asset $asset): array
    {
        $symbol = $asset->symbol;
        $row = $this->warmLiveData()[$symbol] ?? [];
        $series = $this->refreshPreviewCandles($symbol);
        $merged = $this->mergeSymbolPayload($symbol, $row, $series);

        $normalized = $this->normalizeCandlesForChart($merged['candles'] ?? []);
        $source = ($merged['source'] ?? '') === 'twelve_data_time_series'
            ? 'twelve_data_time_series'
            : 'twelve_data_quote';

        if (count($normalized) < 2) {
            $normalized = $this->syntheticCandlesFromQuote([
                'open' => $merged['open'] ?? null,
                'high' => $merged['high'] ?? null,
                'low' => $merged['low'] ?? null,
                'close' => $merged['close'] ?? $merged['live_price'] ?? null,
            ], 24);
            $source = count($normalized) >= 2 ? 'quote_fallback' : 'unavailable';
        }

        $firstClose = (float) ($normalized[0]['close'] ?? 0);
        $lastClose = (float) ($normalized[array_key_last($normalized)]['close'] ?? 0);
        $trend = $lastClose >= $firstClose ? 'up' : 'down';

        $chart = [];
        $chartCandles = [];

        foreach ($normalized as $candle) {
            $recordedAt = date('c', (int) $candle['time']);
            $close = number_format((float) $candle['close'], 8, '.', '');

            $chart[] = [
                'price' => $close,
                'recorded_at' => $recordedAt,
                'trend' => $trend,
            ];

            $chartCandles[] = [
                'open' => number_format((float) $candle['open'], 8, '.', ''),
                'high' => number_format((float) $candle['high'], 8, '.', ''),
                'low' => number_format((float) $candle['low'], 8, '.', ''),
                'close' => $close,
                'recorded_at' => $recordedAt,
            ];
        }

        $livePrice = number_format(
            (float) ($merged['live_price'] ?? $asset->live_price),
            8,
            '.',
            '',
        );

        return [
            'chart' => $chart,
            'chart_candles' => $chartCandles,
            'live_price' => $livePrice,
            'price_change_24h' => number_format(
                (float) ($merged['percent_change'] ?? $asset->price_change_24h),
                2,
                '.',
                '',
            ),
            'trend' => $trend,
            'source' => $source,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getTimeSeries(string $internalSymbol, int $outputSize = 30, bool $force = false): ?array
    {
        return $this->refreshPreviewCandles($internalSymbol, $force);
    }

    public function twelveDataSymbol(string $internalSymbol): ?string
    {
        return self::SYMBOL_MAP[$internalSymbol] ?? null;
    }

    /**
     * @param  list<array<string, mixed>>  $candles
     * @return list<array{time: int, open: float, high: float, low: float, close: float, volume: float|null}>
     */
    public function normalizeCandlesForChart(array $candles): array
    {
        return collect($candles)
            ->filter(fn (array $c) => isset($c['open'], $c['high'], $c['low'], $c['close']))
            ->map(function (array $candle) {
                $time = isset($candle['datetime']) ? strtotime((string) $candle['datetime']) : ($candle['time'] ?? false);

                return [
                    'time' => is_int($time) ? $time : (is_numeric($time) ? (int) $time : null),
                    'open' => (float) $candle['open'],
                    'high' => (float) $candle['high'],
                    'low' => (float) $candle['low'],
                    'close' => (float) $candle['close'],
                    'volume' => isset($candle['volume']) && is_numeric($candle['volume'])
                        ? (float) $candle['volume']
                        : null,
                ];
            })
            ->filter(fn (array $c) => $c['time'] !== null)
            ->sortBy('time')
            ->values()
            ->all();
    }

    /**
     * @return list<array{time: int, open: float, high: float, low: float, close: float, volume: float|null}>
     */
    public function syntheticCandlesFromQuote(array $quote, int $bars = 12): array
    {
        $close = $this->firstNumeric($quote['close'] ?? null);
        $open = $this->firstNumeric($quote['open'] ?? null, $close);
        $high = $this->firstNumeric($quote['high'] ?? null, max($open ?? 0, $close ?? 0));
        $low = $this->firstNumeric($quote['low'] ?? null, min($open ?? 0, $close ?? 0));

        if ($close === null || $open === null || $high === null || $low === null) {
            return [];
        }

        $candles = [];
        $now = time();

        for ($i = $bars - 1; $i >= 0; $i--) {
            $progress = ($bars - 1 - $i) / max($bars - 1, 1);
            $price = $open + (($close - $open) * $progress);
            $wick = max(($high - $low) * 0.15, abs($close - $open) * 0.05, 0.0001);

            $candles[] = [
                'time' => $now - ($i * 60),
                'open' => round($price - ($wick * 0.2), 8),
                'high' => round($price + $wick, 8),
                'low' => round($price - $wick, 8),
                'close' => round($price, 8),
                'volume' => null,
            ];
        }

        $candles[array_key_last($candles)]['close'] = $close;
        $candles[0]['open'] = $open;

        return $candles;
    }

    /**
     * Merge quote row + optional preview candles for one symbol.
     *
     * @param  array<string, mixed>  $quoteRow
     * @param  array{values?: list<array<string, mixed>>, status?: string, message?: string}  $series
     * @return array<string, mixed>
     */
    public function mergeSymbolPayload(string $symbol, array $quoteRow, array $series = []): array
    {
        $candles = $series['values'] ?? [];
        $latestCandle = $candles[0] ?? null;

        return array_merge($quoteRow, [
            'candles' => $candles,
            'candles_count' => count($candles),
            'candle_time' => $latestCandle['datetime'] ?? ($quoteRow['candle_time'] ?? null),
            'open' => $this->firstNumeric($latestCandle['open'] ?? null, $quoteRow['open'] ?? null),
            'high' => $this->firstNumeric($latestCandle['high'] ?? null, $quoteRow['high'] ?? null),
            'low' => $this->firstNumeric($latestCandle['low'] ?? null, $quoteRow['low'] ?? null),
            'close' => $this->firstNumeric($latestCandle['close'] ?? null, $quoteRow['close'] ?? null),
            'live_price' => $this->firstNumeric(
                $latestCandle['close'] ?? null,
                $quoteRow['live_price'] ?? null,
            ),
            'source' => $latestCandle ? 'twelve_data_time_series' : ($quoteRow['source'] ?? 'database'),
            'error' => ($series['status'] ?? 'ok') !== 'ok'
                ? ($series['message'] ?? $quoteRow['error'] ?? null)
                : ($quoteRow['error'] ?? null),
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $quotes
     * @return array<string, array<string, mixed>>
     */
    protected function mapQuotesToAssets(Collection $assets, array $quotes): array
    {
        $data = [];
        $fetchedAt = now()->toIso8601String();

        foreach ($assets as $asset) {
            $symbol = $asset->symbol;
            $tdSymbol = self::SYMBOL_MAP[$symbol] ?? null;
            $quote = $tdSymbol ? ($quotes[$tdSymbol] ?? null) : null;

            $data[$symbol] = [
                'td_symbol' => $tdSymbol,
                'live_price' => $this->firstNumeric($quote['close'] ?? null, $asset->live_price),
                'open' => $this->firstNumeric($quote['open'] ?? null),
                'high' => $this->firstNumeric($quote['high'] ?? null),
                'low' => $this->firstNumeric($quote['low'] ?? null),
                'close' => $this->firstNumeric($quote['close'] ?? null),
                'volume' => $quote['volume'] ?? null,
                'percent_change' => $this->firstNumeric(
                    $quote['percent_change'] ?? null,
                    $asset->price_change_24h,
                ),
                'candle_time' => $quote['datetime'] ?? null,
                'candles' => [],
                'candles_count' => 0,
                'source' => $quote ? 'twelve_data_quote' : 'database',
                'error' => $quote === null && $tdSymbol
                    ? ($quotes['_error'] ?? 'No live quote returned.')
                    : null,
                'fetched_at' => $fetchedAt,
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    protected function fetchTimeSeries(string $internalSymbol, int $outputSize = 30): array
    {
        $tdSymbol = self::SYMBOL_MAP[$internalSymbol] ?? null;

        if ($tdSymbol === null) {
            return [
                'status' => 'error',
                'message' => 'No Twelve Data mapping for '.$internalSymbol.'.',
                'values' => [],
            ];
        }

        $response = $this->request('time_series', [
            'symbol' => $tdSymbol,
            'interval' => '1min',
            'outputsize' => $outputSize,
            'timezone' => 'UTC',
        ]);

        if (($response['status'] ?? 'error') !== 'ok') {
            return [
                'status' => 'error',
                'message' => $response['message'] ?? 'Unable to load candles.',
                'meta' => ['symbol' => $tdSymbol],
                'values' => [],
                'rate_limited' => (bool) ($response['rate_limited'] ?? false),
            ];
        }

        return $response;
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<string, array<string, mixed>>
     */
    protected function fetchBatchQuotes(Collection $assets): array
    {
        $symbols = $assets
            ->map(fn (Asset $asset) => self::SYMBOL_MAP[$asset->symbol] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($symbols === []) {
            return [];
        }

        $quotes = [];
        $chunks = array_chunk($symbols, 8);

        foreach ($chunks as $index => $chunk) {
            if ($index > 0) {
                if ($this->isRateLimited()) {
                    break;
                }

                sleep(8);
            }

            $response = $this->request('quote', [
                'symbol' => implode(',', $chunk),
            ]);

            if (($response['rate_limited'] ?? false) === true) {
                $quotes['_error'] = $response['message'] ?? 'Twelve Data rate limit reached.';

                break;
            }

            if (! is_array($response)) {
                continue;
            }

            if (isset($response['symbol'])) {
                $quotes[$response['symbol']] = $response;

                continue;
            }

            foreach ($response as $symbol => $payload) {
                if (is_array($payload)) {
                    $quotes[$symbol] = $payload;
                }
            }
        }

        return $quotes;
    }

    protected function firstNumeric(mixed ...$values): ?float
    {
        foreach ($values as $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function request(string $endpoint, array $query = []): array
    {
        $apiKey = config('services.twelve_data.key');

        if (! $apiKey) {
            return [
                'status' => 'error',
                'message' => 'TWELVE_DATA_API_KEY is not configured.',
            ];
        }

        if ($this->isRateLimited()) {
            return [
                'status' => 'error',
                'message' => 'Twelve Data rate limit cooldown active.',
                'rate_limited' => true,
            ];
        }

        $response = Http::timeout(5)
            ->acceptJson()
            ->get('https://api.twelvedata.com/'.$endpoint, array_merge($query, [
                'apikey' => $apiKey,
            ]));

        $payload = $response->json();
        $payload = is_array($payload) ? $payload : [
            'status' => 'error',
            'message' => 'Invalid Twelve Data response.',
        ];

        if (($payload['code'] ?? null) === 429 || str_contains(strtolower((string) ($payload['message'] ?? '')), 'run out of api credits')) {
            $this->markRateLimited();

            return [
                'status' => 'error',
                'message' => 'Twelve Data rate limit (8/min on Basic). Wait 1 minute or upgrade plan.',
                'rate_limited' => true,
            ];
        }

        if (! $response->successful()) {
            return [
                'status' => 'error',
                'message' => 'Twelve Data HTTP '.$response->status(),
            ];
        }

        return $payload;
    }
}
