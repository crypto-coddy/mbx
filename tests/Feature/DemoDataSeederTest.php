<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Models\TradeSetting;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\RoleSeeder;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    public function test_demo_seeder_creates_users_trades_and_recharges(): void
    {
        $this->seed(RoleSeeder::class);
        TradeSetting::updateOrCreate(['key' => 'trading_enabled'], ['value' => 'true']);

        $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 2650,
        ]);

        $this->testAsset([
            'name' => 'Silver',
            'symbol' => 'XAG',
            'display_name' => 'Silver',
            'live_price' => 31,
        ]);

        $this->testAsset([
            'name' => 'Tether',
            'symbol' => 'USDT',
            'display_name' => 'USDT',
            'live_price' => 1,
        ]);

        config(['mbx.demo_seed_force' => true]);
        $this->seed(DemoDataSeeder::class);
        config(['mbx.demo_seed_force' => false]);

        $this->assertEquals(10, User::where('phone', 'like', '91000000%')->count());
        $this->assertGreaterThan(15, Trade::count());
        $this->assertGreaterThan(10, Transaction::where('type', 'wallet_recharge')->count());

        $demo = User::where('phone', '9100000001')->first();
        $this->assertNotNull($demo);
        $this->assertEquals('approved', $demo->kyc_status);
        $this->assertNotNull($demo->wallet);
        $this->assertGreaterThan(0, Trade::where('user_id', $demo->id)->count());
    }
}
