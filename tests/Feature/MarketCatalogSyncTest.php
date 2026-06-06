<?php

namespace Tests\Feature;

use App\Models\Asset;
use Tests\TestCase;

class MarketCatalogSyncTest extends TestCase
{

    public function test_sync_markets_creates_assets_in_all_categories(): void
    {
        $this->artisan('mbx:sync-markets')->assertSuccessful();

        $this->assertGreaterThan(0, Asset::where('category', 'commodities')->count());
        $this->assertGreaterThan(0, Asset::where('category', 'crypto')->count());
        $this->assertGreaterThan(0, Asset::where('category', 'forex')->count());
        $this->assertGreaterThan(0, Asset::where('category', 'indices')->count());

        $this->getJson('/api/v1/prices?category=forex')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }
}
