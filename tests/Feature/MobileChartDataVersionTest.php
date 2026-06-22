<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Models\User;
use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileChartDataVersionTest extends TestCase
{
    public function test_v2_profile_uses_twelve_data_candles_on_prices_api(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );
        TradeSetting::updateOrCreate(
            ['key' => ChartDataVersionService::SETTING_KEY],
            ['value' => ChartDataVersionService::VERSION_V1],
        );

        config(['services.twelve_data.key' => 'test-key']);

        Http::fake([
            'api.twelvedata.com/quote*' => Http::response([
                'XAU/USD' => [
                    'symbol' => 'XAU/USD',
                    'close' => '4156.61',
                    'open' => '4156.55',
                    'high' => '4156.73',
                    'low' => '4156.48',
                    'percent_change' => '0.12',
                ],
            ]),
            'api.twelvedata.com/time_series*' => Http::response([
                'status' => 'ok',
                'values' => [
                    [
                        'datetime' => '2026-06-21 22:00:00',
                        'open' => '4156.50',
                        'high' => '4156.80',
                        'low' => '4156.40',
                        'close' => '4156.61',
                    ],
                    [
                        'datetime' => '2026-06-21 22:01:00',
                        'open' => '4156.61',
                        'high' => '4156.90',
                        'low' => '4156.55',
                        'close' => '4156.75',
                    ],
                ],
            ]),
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
        $user->load('profile');

        Sanctum::actingAs($user);

        Cache::put('twelve_data:quotes:mobile:v2', [
            'XAU/USD' => [
                'symbol' => 'XAU/USD',
                'close' => '4156.61',
                'open' => '4156.55',
                'high' => '4156.73',
                'low' => '4156.48',
                'percent_change' => '0.12',
            ],
        ], 60);

        $response = $this->getJson('/api/v1/prices')->assertOk();
        $row = collect($response->json('data'))->firstWhere('symbol', 'XAU');

        $this->assertNotNull($row);
        $this->assertSame('v2', $response->json('meta.chart_data_version'));
        $this->assertSame('v2', $row['chart_data_version']);
        $this->assertSame('real', $row['chart_scope']);
        $this->assertNotEmpty($row['chart_candles']);
        $this->assertSame('4156.61000000', $row['current_price']);
    }

    public function test_user_v1_override_beats_global_v2(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );
        TradeSetting::updateOrCreate(
            ['key' => ChartDataVersionService::SETTING_KEY],
            ['value' => ChartDataVersionService::VERSION_V2],
        );

        $this->testAsset([
            'symbol' => 'XAU',
            'name' => 'Gold',
            'display_name' => 'Gold',
            'live_price' => 5426.83,
        ]);

        $user = User::factory()->create();
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'country' => 'India',
                'mobile_chart_data_source' => ChartDataModeService::MODE_REAL,
                'mobile_chart_data_version' => ChartDataVersionService::VERSION_V1,
            ],
        );
        $user->load('profile');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/prices')->assertOk();

        $this->assertSame('v1', $response->json('meta.chart_data_version'));
        $this->assertSame('user', $response->json('meta.chart_config_scope'));
        $this->assertSame('real', $response->json('meta.chart_source_override'));
        $this->assertSame('v1', $response->json('meta.chart_version_override'));

        $row = collect($response->json('data'))->firstWhere('symbol', 'XAU');
        $this->assertSame('v1', $row['chart_data_version']);
    }
}
