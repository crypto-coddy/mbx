<?php

namespace Tests\Feature;

use App\Services\SuperAdminService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssetsLivePageTest extends TestCase
{
    public function test_super_admin_can_open_assets_live_page(): void
    {
        config(['services.twelve_data.key' => 'test-key']);

        Http::fake([
            'api.twelvedata.com/quote*' => Http::response([
                'BTC/USD' => [
                    'symbol' => 'BTC/USD',
                    'close' => '72868.00',
                    'open' => '73000.00',
                    'high' => '73100.00',
                    'low' => '72500.00',
                    'percent_change' => '-2.70',
                ],
            ]),
            'api.twelvedata.com/time_series*' => Http::response([
                'status' => 'ok',
                'meta' => ['symbol' => 'BTC/USD', 'interval' => '1min'],
                'values' => [
                    [
                        'datetime' => '2026-06-21 21:58:00',
                        'open' => '72700.00',
                        'high' => '72850.00',
                        'low' => '72650.00',
                        'close' => '72800.00',
                        'volume' => '10',
                    ],
                    [
                        'datetime' => '2026-06-21 21:59:00',
                        'open' => '72800.00',
                        'high' => '72900.00',
                        'low' => '72700.00',
                        'close' => '72868.00',
                        'volume' => '12',
                    ],
                ],
            ]),
        ]);

        $admin = app(SuperAdminService::class)->ensure();

        $this->actingAs($admin)
            ->get('/admin/assets-live')
            ->assertOk()
            ->assertSee('Assets Live')
            ->assertSee('Twelve Data')
            ->assertSee('Trade chart (admin web only)')
            ->assertSee('Stop trade chart')
            ->assertSee('Mobile trade chart preview')
            ->assertSee('<svg', false);
    }
}
