<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Models\User;
use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
use App\Services\TwelveDataCreditBudget;
use App\Services\TwelveDataService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwelveDataMobileCacheOnlyTest extends TestCase
{
    public function test_mobile_prices_api_never_calls_twelve_data_when_cache_only(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );
        TradeSetting::updateOrCreate(
            ['key' => ChartDataVersionService::SETTING_KEY],
            ['value' => ChartDataVersionService::VERSION_V2],
        );

        config([
            'services.twelve_data.key' => 'test-key',
            'twelve_data.mobile_http_cache_only' => true,
        ]);

        Cache::put('twelve_data:quotes:mobile:v2', [
            'XAU/USD' => [
                'symbol' => 'XAU/USD',
                'close' => '4400.00',
                'open' => '4399.00',
                'high' => '4401.00',
                'low' => '4398.00',
                'percent_change' => '0.30',
            ],
        ], 60);
        Cache::put('twelve_data:quotes:mobile:v2:at', time(), 60);

        Http::fake([
            'api.twelvedata.com/*' => Http::response([
                'status' => 'error',
                'message' => 'Should not be called from mobile HTTP.',
            ], 500),
        ]);

        $this->testAsset([
            'symbol' => 'XAU',
            'name' => 'Gold',
            'display_name' => 'Gold',
            'live_price' => 4000,
        ]);

        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/v1/prices')->assertOk();
        $row = collect($response->json('data'))->firstWhere('symbol', 'XAU');

        $this->assertNotNull($row);
        $this->assertSame('4400.00000000', $row['current_price']);
        Http::assertNothingSent();
    }

    public function test_credit_budget_blocks_scheduler_fetch_when_exhausted(): void
    {
        config([
            'services.twelve_data.key' => 'test-key',
            'twelve_data.plan' => 'basic',
            'twelve_data.mobile_http_cache_only' => true,
        ]);

        $budget = app(TwelveDataCreditBudget::class);
        $limit = max(1, $budget->creditsPerMinute() - $budget->reservePerMinute());

        for ($i = 0; $i < $limit; $i++) {
            $budget->spend(1, TwelveDataService::SCOPE_MOBILE);
        }

        Http::fake([
            'api.twelvedata.com/*' => Http::response([
                'XAU/USD' => [
                    'symbol' => 'XAU/USD',
                    'close' => '4500.00',
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
        $twelveData->enableNetworkFetch();
        $twelveData->getLiveDataForAssets(force: true, scope: TwelveDataService::SCOPE_MOBILE);

        Http::assertNothingSent();
    }
}
