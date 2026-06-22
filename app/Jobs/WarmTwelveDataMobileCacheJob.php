<?php

namespace App\Jobs;

use App\Services\TwelveDataService;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/** Keeps mobile v2 quote cache warm independent of admin Assets Live polling. */
class WarmTwelveDataMobileCacheJob
{
    use Queueable;

    public function handle(TwelveDataService $twelveData): void
    {
        if (! config('services.twelve_data.key')) {
            return;
        }

        if ($twelveData->isRateLimited(TwelveDataService::SCOPE_MOBILE)) {
            return;
        }

        $twelveData->getLiveDataForAssets(force: true, scope: TwelveDataService::SCOPE_MOBILE);

        $symbols = array_keys(TwelveDataService::SYMBOL_MAP);
        $index = (int) Cache::get('twelve_data:mobile:series_rotation', 0);
        $symbol = $symbols[$index % count($symbols)];
        Cache::put('twelve_data:mobile:series_rotation', ($index + 1) % count($symbols), 86_400);

        $twelveData->refreshPreviewCandles(
            $symbol,
            force: false,
            allowFetch: true,
            scope: TwelveDataService::SCOPE_MOBILE,
        );
    }
}
