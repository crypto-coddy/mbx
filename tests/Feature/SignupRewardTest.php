<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use App\Support\ReferralCodeGenerator;
use Tests\TestCase;

class SignupRewardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');
    }

    public function test_signup_reward_is_locked_and_tradeable(): void
    {
        $referrer = User::factory()->create(['referral_code' => 'REF12345']);
        $user = User::factory()->create(['referred_by' => $referrer->id]);
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'INR']);

        $service = app(WalletService::class);
        $service->grantSignupReward($user, '200', $referrer);

        $wallet = $user->wallet->fresh();
        $this->assertEquals('200.00000000', $wallet->balance);
        $this->assertEquals('200.00000000', $wallet->locked_balance);
        $this->assertEquals('200.00000000', $wallet->reward_balance);
        $this->assertEquals('0.00000000', $wallet->availableBalance());

        $service->debitForTrade($user, '50', 'trade_loss', 'Test buy');
        $wallet = $user->wallet->fresh();
        $this->assertEquals('150.00000000', $wallet->balance);
        $this->assertEquals('150.00000000', $wallet->reward_balance);
        $this->assertEquals('0.00000000', $wallet->availableBalance());
    }

    public function test_registration_with_referral_grants_reward(): void
    {
        $referrer = User::factory()->create([
            'referral_code' => ReferralCodeGenerator::generate(),
            'status' => 'active',
        ]);
        Wallet::create(['user_id' => $referrer->id, 'balance' => 0]);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'New User',
            'phone' => '8888888881',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'India',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
            'referral_code' => $referrer->referral_code,
        ]);

        $response->assertCreated();
        $user = User::where('phone', '8888888881')->first();
        $this->assertEquals($referrer->id, $user->referred_by);
        $this->assertEquals('200.00000000', $user->wallet->balance);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'signup_reward',
            'direction' => 'credit',
        ]);
    }

    public function test_registration_without_referral_grants_reward(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Solo User',
            'phone' => '8888888882',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'country' => 'India',
            'state' => 'Maharashtra',
            'city' => 'Mumbai',
        ]);

        $response->assertCreated();
        $user = User::where('phone', '8888888882')->first();
        $this->assertNull($user->referred_by);
        $this->assertEquals('200.00000000', $user->wallet->balance);
        $this->assertEquals('200.00000000', $user->wallet->reward_balance);
    }
}
