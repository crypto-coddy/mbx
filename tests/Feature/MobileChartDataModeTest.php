<?php

namespace Tests\Feature;

use App\Models\PriceHistory;
use App\Models\TradeSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\ChartDataModeService;
use App\Services\MarketChartService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileChartDataModeTest extends TestCase
{
    public function test_custom_mode_uses_admin_chart_trend(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_CUSTOM],
        );

        $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'down',
            'price_change_24h' => -1.5,
        ]);

        $row = $this->priceRowForSymbol('XAU');
        $this->assertEquals('down', $row['chart_trend']);
        $this->assertEquals('global', $row['chart_scope']);
    }

    public function test_real_mode_uses_live_chart_and_scope(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );

        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'down',
            'price_change_24h' => 0.65,
            'admin_override_active' => true,
            'admin_price' => 9999,
        ]);

        PriceHistory::query()
            ->where('asset_id', $asset->id)
            ->where('source', 'live_api')
            ->delete();

        $base = now()->subMinutes(40);
        for ($i = 0; $i < 20; $i++) {
            PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => 2600 + ($i * 2),
                'close' => 2600 + ($i * 2),
                'source' => 'live_api',
                'interval' => '1m',
                'recorded_at' => $base->copy()->addMinutes($i),
            ]);
        }

        $row = $this->priceRowForSymbol('XAU');
        $this->assertEquals('real', $row['chart_scope']);
        $this->assertEquals('up', $row['chart_trend']);
        $this->assertFalse($row['admin_override_active']);
        $this->assertEquals('2650.00000000', $row['current_price']);
    }

    public function test_user_profile_custom_override_when_platform_is_real(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );

        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'down',
            'price_change_24h' => -1.5,
        ]);

        $user = User::factory()->create();
        $profile = UserProfile::create([
            'user_id' => $user->id,
            'country' => 'India',
            'mobile_chart_data_source' => ChartDataModeService::MODE_CUSTOM,
        ]);

        app(MarketChartService::class)->setTrendForProfile($profile, $asset, 'down');

        Sanctum::actingAs($user);

        $row = $this->priceRowForSymbol('XAU');
        $this->assertEquals('user', $row['chart_scope']);
        $this->assertEquals('down', $row['chart_trend']);
    }

    public function test_user_profile_real_override_when_platform_is_custom(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_CUSTOM],
        );

        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
            'chart_trend' => 'down',
            'price_change_24h' => 0.65,
            'admin_override_active' => true,
            'admin_price' => 9999,
        ]);

        PriceHistory::query()
            ->where('asset_id', $asset->id)
            ->where('source', 'live_api')
            ->delete();

        $base = now()->subMinutes(40);
        for ($i = 0; $i < 20; $i++) {
            PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => 2600 + ($i * 2),
                'close' => 2600 + ($i * 2),
                'source' => 'live_api',
                'interval' => '1m',
                'recorded_at' => $base->copy()->addMinutes($i),
            ]);
        }

        $user = User::factory()->create();
        UserProfile::create([
            'user_id' => $user->id,
            'country' => 'India',
            'mobile_chart_data_source' => ChartDataModeService::MODE_REAL,
        ]);

        Sanctum::actingAs($user);

        $row = $this->priceRowForSymbol('XAU');
        $this->assertEquals('real', $row['chart_scope']);
        $this->assertEquals('2650.00000000', $row['current_price']);
    }

    public function test_real_mode_prices_endpoint_does_not_call_external_apis(): void
    {
        Http::fake();

        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_REAL],
        );

        $asset = $this->testAsset([
            'name' => 'Bitcoin',
            'symbol' => 'BTC',
            'display_name' => 'Bitcoin',
            'live_price' => 97500,
            'chart_trend' => 'up',
            'price_change_24h' => 1.2,
        ]);

        $base = now()->subMinutes(20);
        for ($i = 0; $i < 15; $i++) {
            \App\Models\PriceHistory::create([
                'asset_id' => $asset->id,
                'price' => 97000 + ($i * 10),
                'close' => 97000 + ($i * 10),
                'source' => 'live_api',
                'interval' => '1m',
                'recorded_at' => $base->copy()->addMinutes($i),
            ]);
        }

        $started = microtime(true);
        $this->getJson('/api/v1/prices')->assertOk();
        $elapsed = microtime(true) - $started;

        Http::assertNothingSent();
        $this->assertLessThan(3.0, $elapsed, 'Real-mode prices should respond without blocking HTTP calls.');
    }
}
