<?php

namespace Tests\Feature;

use App\Events\MarketsPricesUpdated;
use App\Events\PriceUpdated;
use App\Models\Asset;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastEventsTest extends TestCase
{
    public function test_price_updated_does_not_broadcast_when_disabled(): void
    {
        config(['broadcasting.enabled' => false]);

        Event::fake([PriceUpdated::class]);

        $asset = $this->testAsset([
            'symbol' => 'XAU',
            'name' => 'Gold',
            'display_name' => 'Gold',
            'live_price' => 4000,
        ]);

        $event = new PriceUpdated($asset, '4100.00000000', 'test');

        $this->assertFalse($event->broadcastWhen());
    }

    public function test_markets_prices_updated_broadcasts_when_enabled(): void
    {
        config(['broadcasting.enabled' => true]);

        $event = new MarketsPricesUpdated([
            ['asset_id' => 1, 'symbol' => 'XAU', 'live_price' => '4100', 'price_change_24h' => '0.5'],
        ]);

        $this->assertTrue($event->broadcastWhen());
    }
}
