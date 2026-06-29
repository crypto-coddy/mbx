<?php

namespace App\Jobs;

use App\Services\MarketDataSyncService;
use Illuminate\Foundation\Queue\Queueable;

/** Central sync: one Twelve Data fetch per tick, Redis cache, WebSocket broadcast. */
class WarmTwelveDataMobileCacheJob
{
    use Queueable;

    public function handle(MarketDataSyncService $sync): void
    {
        if (! config('services.twelve_data.key')) {
            return;
        }

        $sync->syncMobileQuotes(force: false);
        $sync->syncNextMobileSeries();
    }
}
