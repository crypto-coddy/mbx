<?php

namespace App\Services;

use App\Models\Asset;
use App\Jobs\WarmTwelveDataMobileCacheJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TwelveDataService
{
    public const CACHE_TTL_SECONDS = 60;

    /** @deprecated Use config('twelve_data.mobile_quote_max_age_seconds') */
    public const MOBILE_QUOTE_MAX_AGE_SECONDS = 12;

    /** @deprecated Use config('twelve_data.mobile_series_max_age_seconds') */
    public const MOBILE_SERIES_MAX_AGE_SECONDS = 45;

    public const SERIES_CACHE_TTL_SECONDS = 300;

    public const SCOPE_MOBILE = 'mobile';

    public const SCOPE_ADMIN = 'admin';

    /** Only jobs/schedulers set this true before outbound Twelve Data calls. */
    protected bool $networkFetchEnabled = false;

    /** @var array<string, array<string, array<string, mixed>>>|null */
    protected ?array $requestLiveData = null;

    public function enableNetworkFetch(): void
    {
        $this->networkFetchEnabled = true;
    }

    public function networkFetchEnabled(): bool
    {
        return $this->networkFetchEnabled;
    }

    /** Internal symbol → Twelve Data symbol */
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
    public function getLiveDataForAssets(
        ?Collection $assets = null,
        bool $force = false,
        string $scope = self::SCOPE_MOBILE,
    ): array {
        $assets ??= Asset::query()->orderBy('sort_order')->get();
        $cacheKey = $this->quoteCacheKey($scope);
        $staleKey = $cacheKey.':stale';
        $maxAge = $this->quoteMaxAgeSeconds($scope);

        if ($this->shouldServeCacheOnly($scope, $force)) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && $cached !== []) {
                return $this->mapQuotesToAssets($assets, $cached);
            }

            $fallback = Cache::get($staleKey);
            if (is_array($fallback) && $fallback !== []) {
                return $this->mapQuotesToAssets($assets, $fallback);
            }

            $this->queueMobileQuoteRefresh($scope);

            return $this->mapDbFallbackToAssets($assets);
        }

        if (! $force) {
            $cached = Cache::get($cacheKey);
            $cachedAt = (int) Cache::get($this->quoteCacheAgeKey($scope), 0);

            if (is_array($cached) && $cached !== [] && (time() - $cachedAt) < $maxAge) {
                return $this->mapQuotesToAssets($assets, $cached);
            }
        } else {
            Cache::forget($cacheKey);
            Cache::forget($this->quoteCacheAgeKey($scope));
        }

        if (
            ! $force
            && $scope === self::SCOPE_MOBILE
            && ! Cache::add($cacheKey.':fetch_lock', 1, 15)
        ) {
            $fallback = Cache::get($cacheKey) ?? Cache::get($staleKey);
            if (is_array($fallback) && $fallback !== []) {
                return $this->mapQuotesToAssets($assets, $fallback);
            }

            return $this->mapDbFallbackToAssets($assets);
        }

        $fresh = $this->fetchBatchQuotes($assets, $scope);

        if (($fresh['_rate_limited'] ?? false) === true) {
            $fallback = Cache::get($staleKey) ?? Cache::get($cacheKey);
            if (is_array($fallback) && $fallback !== []) {
                return $this->mapQuotesToAssets($assets, $fallback);
            }

            if ($scope === self::SCOPE_MOBILE) {
                return $this->mapDbFallbackToAssets($assets);
            }
        }

        unset($fresh['_rate_limited']);

        if ($fresh !== []) {
            $ttl = (int) config('twelve_data.quote_cache_ttl_seconds', self::CACHE_TTL_SECONDS);
            Cache::put($cacheKey, $fresh, $ttl);
            Cache::put($this->quoteCacheAgeKey($scope), time(), $ttl);
            Cache::put($staleKey, $fresh, $ttl * 10);
        }

        return $this->mapQuotesToAssets($assets, $fresh);
    }

    /**
     * One time_series call for chart candles.
     *
     * @return array{values: list<array<string, mixed>>, status: string, message?: string, source: string}
     */
    public function refreshPreviewCandles(
        string $internalSymbol,
        bool $force = false,
        bool $allowFetch = true,
        string $scope = self::SCOPE_MOBILE,
    ): array {
        $cacheKey = $this->seriesCacheKey($scope, $internalSymbol, 30);

        if ($force) {
            Cache::forget($cacheKey);
            Cache::forget($cacheKey.':at');
        }

        $cached = Cache::get($cacheKey);
        $cachedAt = (int) Cache::get($cacheKey.':at', 0);
        $seriesMaxAge = $this->seriesMaxAgeSeconds($scope);

        if (
            is_array($cached)
            && ($cached['status'] ?? '') === 'ok'
            && ($scope !== self::SCOPE_MOBILE || (time() - $cachedAt) < $seriesMaxAge)
        ) {
            return $cached;
        }

        if ($this->shouldServeCacheOnly($scope, false) || ($this->isRateLimited($scope) && ! $this->networkFetchEnabled)) {
            return is_array($cached) ? $cached : [
                'status' => 'error',
                'message' => 'Preview candles unavailable (cached only).',
                'values' => [],
            ];
        }

        if ($this->isRateLimited($scope) || ! $allowFetch) {
            return is_array($cached) ? $cached : [
                'status' => 'error',
                'message' => 'Preview candles unavailable (cached only).',
                'values' => [],
            ];
        }

        $payload = $this->fetchTimeSeries($internalSymbol, 30, $scope);

        if (($payload['status'] ?? 'error') === 'ok') {
            Cache::put($cacheKey, $payload, self::SERIES_CACHE_TTL_SECONDS);
            Cache::put($cacheKey.':at', time(), self::SERIES_CACHE_TTL_SECONDS);

            return $payload;
        }

        if ($payload['rate_limited'] ?? false) {
            return is_array($cached) ? $cached : $payload;
        }

        Cache::put($cacheKey, $payload, 15);

        return $payload;
    }

    public function isRateLimited(string $scope = self::SCOPE_MOBILE): bool
    {
        return Cache::has($this->rateLimitCacheKey($scope));
    }

    public function clearCaches(?string $scope = null): void
    {
        $scopes = $scope === null ? [self::SCOPE_MOBILE, self::SCOPE_ADMIN] : [$scope];

        foreach ($scopes as $targetScope) {
            Cache::forget($this->quoteCacheKey($targetScope));
            Cache::forget($this->rateLimitCacheKey($targetScope));

            foreach (array_keys(self::SYMBOL_MAP) as $symbol) {
                Cache::forget($this->seriesCacheKey($targetScope, $symbol, 30));
            }

            Cache::forget($this->quoteCacheKey($targetScope).':stale');
        }

        $this->requestLiveData = null;
    }

    /**
     * Warm quote cache once per request (mobile v2 list).
     *
     * @return array<string, array<string, mixed>>
     */
    public function warmLiveData(bool $force = false, string $scope = self::SCOPE_MOBILE): array
    {
        if (! isset($this->requestLiveData[$scope]) || $force) {
            $this->requestLiveData[$scope] = $this->getLiveDataForAssets(force: $force, scope: $scope);
        }

        return $this->requestLiveData[$scope];
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
    public function mobileChartBundle(
        Asset $asset,
        bool $allowSeriesFetch = true,
        string $scope = self::SCOPE_MOBILE,
    ): array {
        $symbol = $asset->symbol;
        $row = $this->warmLiveData(scope: $scope)[$symbol] ?? [];
        $series = $this->refreshPreviewCandles($symbol, allowFetch: $allowSeriesFetch, scope: $scope);
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
                'price' => $close,
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
    public function getTimeSeries(
        string $internalSymbol,
        int $outputSize = 30,
        bool $force = false,
        string $scope = self::SCOPE_MOBILE,
    ): ?array {
        return $this->refreshPreviewCandles($internalSymbol, $force, allowFetch: true, scope: $scope);
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

    protected function quoteCacheKey(string $scope): string
    {
        return 'twelve_data:quotes:'.$scope.':v2';
    }

    protected function quoteCacheAgeKey(string $scope): string
    {
        return $this->quoteCacheKey($scope).':at';
    }

    protected function quoteMaxAgeSeconds(string $scope): int
    {
        if ($scope === self::SCOPE_MOBILE) {
            return (int) config('twelve_data.mobile_quote_max_age_seconds', self::MOBILE_QUOTE_MAX_AGE_SECONDS);
        }

        return (int) config('twelve_data.quote_cache_ttl_seconds', self::CACHE_TTL_SECONDS);
    }

    protected function seriesMaxAgeSeconds(string $scope): int
    {
        if ($scope === self::SCOPE_MOBILE) {
            return (int) config('twelve_data.mobile_series_max_age_seconds', self::MOBILE_SERIES_MAX_AGE_SECONDS);
        }

        return (int) config('twelve_data.series_cache_ttl_seconds', self::SERIES_CACHE_TTL_SECONDS);
    }

    protected function shouldServeCacheOnly(string $scope, bool $force): bool
    {
        if ($force && $this->networkFetchEnabled) {
            return false;
        }

        return $scope === self::SCOPE_MOBILE
            && config('twelve_data.mobile_http_cache_only', true)
            && ! $this->networkFetchEnabled;
    }

    protected function seriesCacheKey(string $scope, string $internalSymbol, int $outputSize): string
    {
        return 'twelve_data:series:'.$scope.':'.Str::slug($internalSymbol).':'.$outputSize;
    }

    protected function rateLimitCacheKey(string $scope): string
    {
        return 'twelve_data:rate_limited:'.$scope;
    }

    protected function queueMobileQuoteRefresh(string $scope): void
    {
        if ($scope !== self::SCOPE_MOBILE || app()->environment('testing')) {
            return;
        }

        if (! Cache::add('twelve_data:mobile:refresh_lock', 1, 15)) {
            return;
        }

        WarmTwelveDataMobileCacheJob::dispatch()->afterResponse();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function mapDbFallbackToAssets(Collection $assets): array
    {
        return $this->mapQuotesToAssets($assets, $this->buildDbQuotes($assets));
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array<string, array<string, mixed>>
     */
    protected function buildDbQuotes(Collection $assets): array
    {
        $quotes = [];

        foreach ($assets as $asset) {
            $tdSymbol = self::SYMBOL_MAP[$asset->symbol] ?? null;
            if ($tdSymbol === null) {
                continue;
            }

            $price = (string) $asset->live_price;
            $quotes[$tdSymbol] = [
                'symbol' => $tdSymbol,
                'close' => $price,
                'open' => $price,
                'high' => $price,
                'low' => $price,
                'percent_change' => (string) $asset->price_change_24h,
            ];
        }

        return $quotes;
    }

    protected function markRateLimited(string $scope, int $seconds = 60): void
    {
        Cache::put($this->rateLimitCacheKey($scope), true, $seconds);
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
    protected function fetchTimeSeries(string $internalSymbol, int $outputSize = 30, string $scope = self::SCOPE_MOBILE): array
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
        ], $scope, (int) config('twelve_data.credit_cost.time_series', 1));

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
    protected function fetchBatchQuotes(Collection $assets, string $scope = self::SCOPE_MOBILE): array
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
        $plan = app(TwelveDataCreditBudget::class)->planConfig();
        $chunkSize = max(1, (int) ($plan['quote_chunk_size'] ?? 8));
        $chunks = array_chunk($symbols, $chunkSize);
        $rateLimited = false;
        $quoteCost = (int) config('twelve_data.credit_cost.quote', 1);

        foreach ($chunks as $index => $chunk) {
            if ($index > 0) {
                if ($this->isRateLimited($scope)) {
                    $rateLimited = true;
                    break;
                }

                $delay = (int) ($plan['chunk_delay_seconds'] ?? 0);
                if ($delay > 0) {
                    sleep($delay);
                }
            }

            if (! app(TwelveDataCreditBudget::class)->canSpend($quoteCost, $scope)) {
                $rateLimited = true;
                $quotes['_rate_limited'] = true;
                break;
            }

            $response = $this->request('quote', [
                'symbol' => implode(',', $chunk),
            ], $scope, $quoteCost);

            if (($response['rate_limited'] ?? false) === true) {
                $quotes['_error'] = $response['message'] ?? 'Twelve Data rate limit reached.';
                $rateLimited = true;

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

        if ($rateLimited) {
            $quotes['_rate_limited'] = true;
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
    protected function request(string $endpoint, array $query = [], string $scope = self::SCOPE_MOBILE, int $creditCost = 1): array
    {
        $apiKey = config('services.twelve_data.key');

        if (! $apiKey) {
            return [
                'status' => 'error',
                'message' => 'TWELVE_DATA_API_KEY is not configured.',
            ];
        }

        if ($this->shouldServeCacheOnly($scope, false)) {
            return [
                'status' => 'error',
                'message' => 'Twelve Data fetch blocked — mobile cache-only mode.',
                'rate_limited' => true,
            ];
        }

        if ($this->isRateLimited($scope)) {
            return [
                'status' => 'error',
                'message' => 'Twelve Data rate limit cooldown active.',
                'rate_limited' => true,
            ];
        }

        $budget = app(TwelveDataCreditBudget::class);
        if ($creditCost > 0 && ! $budget->spend($creditCost, $scope)) {
            return [
                'status' => 'error',
                'message' => 'Twelve Data minute credit budget exhausted for '.$budget->plan().' plan.',
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

        $message = strtolower((string) ($payload['message'] ?? ''));

        if (($payload['code'] ?? null) === 429 || str_contains($message, 'run out of api credits')) {
            $cooldown = str_contains($message, 'for the day') ? 3600 : 60;
            $this->markRateLimited($scope, $cooldown);

            return [
                'status' => 'error',
                'message' => str_contains($message, 'for the day')
                    ? 'Twelve Data daily API credits exhausted. Using cached/stored chart data until reset.'
                    : 'Twelve Data rate limit (8/min on Basic). Wait 1 minute or upgrade plan.',
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
