<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Tests\TestCase;

class AdminWalletRechargeTest extends TestCase
{

    public function test_admin_recharge_increases_withdrawable_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 200,
            'reward_balance' => 200,
            'locked_balance' => 200,
        ]);

        $service = app(WalletService::class);
        $service->adminRecharge($user, '300', 'Customer requested recharge');

        $wallet = $user->wallet->fresh();
        $this->assertEquals('500.00000000', $wallet->balance);
        $this->assertEquals('300.00000000', $wallet->recharged_balance);
        $this->assertEquals('300.00000000', $wallet->withdrawableBalance());

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'wallet_recharge',
            'direction' => 'credit',
        ]);
    }

    public function test_reward_only_balance_cannot_withdraw(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 350,
            'reward_balance' => 200,
            'locked_balance' => 200,
            'recharged_balance' => 0,
        ]);

        $wallet = $user->wallet->fresh();
        $this->assertEquals('0.00000000', $wallet->withdrawableBalance());
    }
}
