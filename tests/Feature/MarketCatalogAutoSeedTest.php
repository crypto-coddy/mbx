<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Trade;
use Tests\TestCase;

class MarketCatalogAutoSeedTest extends TestCase
{
    public function test_prices_endpoint_seeds_default_markets_when_table_empty(): void
    {
        Trade::withTrashed()->forceDelete();
        Asset::query()->delete();
        $this->assertEquals(0, Asset::count());

        $response = $this->getJson('/api/v1/prices');

        $response->assertOk()->assertJsonCount(12, 'data');
        $this->assertGreaterThan(0, Asset::where('category', 'forex')->count());
    }
}
