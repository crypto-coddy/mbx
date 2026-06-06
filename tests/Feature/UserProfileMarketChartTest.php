<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\ChartDataModeService;
use App\Services\MarketChartService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserProfileMarketChartTest extends TestCase
{
    public function test_authenticated_user_sees_profile_specific_chart_trend(): void
    {
        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 1000,
            'chart_trend' => 'up',
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
        $this->assertEquals('down', $row['chart_trend']);
        $this->assertEquals('hold', $row['market_signal']);
        $this->assertEquals('user', $row['chart_scope']);
    }

    public function test_guest_sees_global_asset_trend(): void
    {
        TradeSetting::updateOrCreate(
            ['key' => ChartDataModeService::SETTING_KEY],
            ['value' => ChartDataModeService::MODE_CUSTOM],
        );

        $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 1000,
            'chart_trend' => 'up',
        ]);

        $row = $this->priceRowForSymbol('XAU');
        $this->assertEquals('up', $row['chart_trend']);
        $this->assertEquals('global', $row['chart_scope']);
    }
}
