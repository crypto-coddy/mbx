<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Models\User;
use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
use App\Services\TwelveDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwelveDataScopeIsolationTest extends TestCase
{
    public function test_admin_cache_clear_does_not_wipe_mobile_quote_cache(): void
    {
        config(['services.twelve_data.key' => 'test-key']);

        Http::fake([
            'api.twelvedata.com/quote*' => Http::response([
                'XAU/USD' => [
                    'symbol' => 'XAU/USD',
                    'close' => '4200.00',
                    'open' => '4199.00',
                    'high' => '4201.00',
                    'low' => '4198.00',
                    'percent_change' => '0.10',
                ],
            ]),
        ]);

        $this->testAsset([
            'symbol' => 'XAU',
            'name' => 'Gold',
            'display_name' => 'Gold',
            'live_price' => 4000,
        ]);

        $twelveData = app(TwelveDataService::class);
        $twelveData->getLiveDataForAssets(force: true, scope: TwelveDataService::SCOPE_MOBILE);
        $twelveData->getLiveDataForAssets(force: true, scope: TwelveDataService::SCOPE_ADMIN);

        $this->assertTrue(Cache::has('twelve_data:quotes:mobile:v2'));
        $this->assertTrue(Cache::has('twelve_data:quotes:admin:v2'));

        $twelveData->clearCaches(TwelveDataService::SCOPE_ADMIN);

        $this->assertTrue(Cache::has('twelve_data:quotes:mobile:v2'));
        $this->assertFalse(Cache::has('twelve_data:quotes:admin:v2'));
    }

    public function test_admin_rate_limit_does_not_block_mobile_prices_api(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );
        TradeSetting::updateOrCreate(
            ['key' => ChartDataVersionService::SETTING_KEY],
            ['value' => ChartDataVersionService::VERSION_V2],
        );

        config(['services.twelve_data.key' => 'test-key']);

        Cache::put('twelve_data:quotes:mobile:v2', [
            'XAU/USD' => [
                'symbol' => 'XAU/USD',
                'close' => '4300.00',
                'open' => '4299.00',
                'high' => '4301.00',
                'low' => '4298.00',
                'percent_change' => '0.20',
            ],
        ], 60);

        Cache::put('twelve_data:rate_limited:admin', true, 60);

        Http::fake([
            'api.twelvedata.com/*' => Http::response([
                'status' => 'error',
                'code' => 429,
                'message' => 'You have run out of API credits for the day.',
            ], 429),
        ]);

        $this->testAsset([
            'symbol' => 'XAU',
            'name' => 'Gold',
            'display_name' => 'Gold',
            'live_price' => 4000,
        ]);

        $user = User::factory()->create();
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'country' => 'India',
                'mobile_chart_data_source' => ChartDataModeService::MODE_REAL,
                'mobile_chart_data_version' => ChartDataVersionService::VERSION_V2,
            ],
        );

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/prices')->assertOk();
        $row = collect($response->json('data'))->firstWhere('symbol', 'XAU');

        $this->assertNotNull($row);
        $this->assertSame('4300.00000000', $row['current_price']);
    }
}
