<?php

namespace Tests\Feature;

use App\Models\TradeSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TradeService;
use App\Services\WalletService;
use Tests\TestCase;

class TradeServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');
        TradeSetting::updateOrCreate(['key' => 'trading_enabled'], ['value' => 'true']);
    }

    public function test_buy_and_request_sell_then_admin_settles(): void
    {
        $user = User::factory()->create(['kyc_status' => 'approved']);
        $user->assignRole('user');
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $admin = User::factory()->create();

        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold (XAU/USD)',
            'live_price' => 2000,
        ]);

        app(WalletService::class)->credit($user, '500', 'deposit', 'Seed');

        $tradeService = app(TradeService::class);
        $buy = $tradeService->buy($user, $asset->id, '100');
        $trade = $buy['trade'];
        $this->assertEquals('open', $trade->status);

        $pending = $tradeService->requestSell($user, $trade->id);
        $this->assertEquals('pending_settlement', $pending['trade']->status);

        $closed = $tradeService->settleByAdmin($pending['trade'], '25', $admin, 'Manual profit');
        $this->assertEquals('closed', $closed['trade']->status);
        $this->assertEquals('25.00000000', (string) $closed['trade']->profit_loss);

        $wallet = app(WalletService::class)->getOrCreateWallet($user->fresh());
        $this->assertEquals('525.00000000', (string) $wallet->balance);
    }
}
