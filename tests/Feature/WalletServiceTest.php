<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Tests\TestCase;

class WalletServiceTest extends TestCase
{

    public function test_credit_and_debit_update_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);
        $service = app(WalletService::class);

        $service->adminRecharge($user, '100', 'Test credit');
        $this->assertEquals('100.00000000', $user->wallet->fresh()->balance);

        $service->debit($user, '40', 'admin_debit', 'Test debit');
        $this->assertEquals('60.00000000', $user->wallet->fresh()->balance);
    }

    public function test_debit_for_trade_uses_reward_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 200,
            'locked_balance' => 200,
            'reward_balance' => 200,
        ]);
        $service = app(WalletService::class);

        $service->debitForTrade($user, '75', 'trade_loss', 'Buy test');
        $wallet = $user->wallet->fresh();
        $this->assertEquals('125.00000000', $wallet->balance);
        $this->assertEquals('125.00000000', $wallet->reward_balance);
    }

    public function test_lock_and_unlock_balance(): void
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id' => $user->id,
            'balance' => 100,
            'locked_balance' => 0,
            'recharged_balance' => 100,
        ]);
        $service = app(WalletService::class);

        $service->lockForWithdrawal($user, '30');
        $wallet = $user->wallet->fresh();
        $this->assertEquals('100.00000000', $wallet->balance);
        $this->assertEquals('70.00000000', $wallet->recharged_balance);
        $this->assertEquals('30.00000000', $wallet->withdrawal_locked);

        $service->unlockForWithdrawal($user, '30');
        $wallet = $user->wallet->fresh();
        $this->assertEquals('100.00000000', $wallet->recharged_balance);
        $this->assertEquals('0.00000000', $wallet->withdrawal_locked);
    }
}
