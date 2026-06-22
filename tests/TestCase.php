<?php

namespace Tests;

use App\Models\Asset;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Spatie\Permission\Models\Role;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests use DB_DATABASE=mbx_test (see phpunit.xml), not your dev mbx database.
     * Never add RefreshDatabase to tests here — it runs migrate:fresh and wipes tables.
     */
    use DatabaseTransactions;

    protected function ensureRole(string $name = 'user'): Role
    {
        return Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
    }

    /** @param array<string, mixed> $attributes */
    protected function testAsset(array $attributes): Asset
    {
        $symbol = (string) ($attributes['symbol'] ?? 'XAU');

        return Asset::updateOrCreate(
            ['symbol' => $symbol],
            array_merge([
                'name' => $symbol,
                'display_name' => $symbol,
                'live_price' => 1000,
                'chart_trend' => 'up',
                'is_active' => true,
                'trading_enabled' => true,
                'min_trade_amount' => 10,
                'max_trade_amount' => 100000,
            ], $attributes),
        );
    }

    /** @return array<string, mixed> */
    protected function priceRowForSymbol(string $symbol): array
    {
        $response = $this->getJson('/api/v1/prices')->assertOk();
        $row = collect($response->json('data'))->firstWhere('symbol', $symbol);
        $this->assertNotNull($row, "Expected prices payload to include {$symbol}");

        return $row;
    }
}
