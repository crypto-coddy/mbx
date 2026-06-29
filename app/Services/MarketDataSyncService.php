<?php

namespace App\Services;

use App\Events\MarketsPricesUpdated;
use App\Models\Asset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Central market-data sync: one Twelve Data fetch per symbol set, Redis cache, WebSocket broadcast.
 */
class MarketDataSyncService
{
    public function __construct(
        protected TwelveDataService $twelveData,
        protected TwelveDataCreditBudget $budget,
    ) {}

    public function syncMobileQuotes(bool $force = false): array
    {
        if (! config('services.twelve_data.key')) {
            return ['status' => 'skipped', 'reason' => 'no_api_key'];
        }

        if ($this->budget->availableCreditsThisMinute(TwelveDataService::SCOPE_MOBILE) < 1) {
            Log::info('Twelve Data sync skipped — minute credit budget exhausted', $this->budget->snapshot());

            return ['status' => 'skipped', 'reason' => 'credit_budget'];
        }

        $this->twelveData->enableNetworkFetch();

        $rows = $this->twelveData->getLiveDataForAssets(force: $force, scope: TwelveDataService::SCOPE_MOBILE);
        $assets = Asset::query()->where('is_active', true)->orderBy('sort_order')->get();
        $payload = [];

        foreach ($assets as $asset) {
            $row = $rows[$asset->symbol] ?? null;
            if ($row === null) {
                continue;
            }

            $price = number_format((float) ($row['live_price'] ?? $asset->live_price), 8, '.', '');
            $change = number_format((float) ($row['percent_change'] ?? $asset->price_change_24h), 2, '.', '');

            if ($price !== (string) $asset->live_price || $change !== (string) $asset->price_change_24h) {
                $asset->update([
                    'live_price' => $price,
                    'price_change_24h' => $change,
                    'price_updated_at' => now(),
                ]);
            }

            $payload[] = [
                'asset_id' => $asset->id,
                'symbol' => $asset->symbol,
                'live_price' => $price,
                'price_change_24h' => $change,
                'source' => $row['source'] ?? 'twelve_data_quote',
                'recorded_at' => $row['fetched_at'] ?? now()->toIso8601String(),
            ];
        }

        if ($payload !== []) {
            event(new MarketsPricesUpdated($payload));
        }

        Cache::put('market_data:last_sync_at', now()->toIso8601String(), 300);

        return [
            'status' => 'ok',
            'symbols' => count($payload),
            'budget' => $this->budget->snapshot(TwelveDataService::SCOPE_MOBILE),
        ];
    }

    public function syncNextMobileSeries(): ?string
    {
        if (! config('services.twelve_data.key')) {
            return null;
        }

        $cost = (int) config('twelve_data.credit_cost.time_series', 1);
        if (! $this->budget->canSpend($cost, TwelveDataService::SCOPE_MOBILE)) {
            return null;
        }

        $symbols = array_keys(TwelveDataService::SYMBOL_MAP);
        $index = (int) Cache::get('twelve_data:mobile:series_rotation', 0);
        $symbol = $symbols[$index % count($symbols)];
        Cache::put('twelve_data:mobile:series_rotation', ($index + 1) % count($symbols), 86_400);

        $this->twelveData->enableNetworkFetch();
        $this->twelveData->refreshPreviewCandles(
            $symbol,
            force: false,
            allowFetch: true,
            scope: TwelveDataService::SCOPE_MOBILE,
        );

        return $symbol;
    }
}
