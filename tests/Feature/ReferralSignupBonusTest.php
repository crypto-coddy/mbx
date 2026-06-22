<?php

namespace Tests\Feature;

use App\Models\ReferralCommission;
use App\Models\TradeSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Support\ReferralCodeGenerator;
use Tests\TestCase;

class ReferralSignupBonusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');

        TradeSetting::updateOrCreate(
            ['key' => 'referral_signup_bonus'],
            ['value' => '50', 'description' => 'Signup bonus'],
        );
        TradeSetting::updateOrCreate(
            ['key' => 'signup_referral_reward'],
            ['value' => '200', 'description' => 'Joiner reward'],
        );
    }

    public function test_direct_referrer_and_joiner_receive_signup_bonuses(): void
    {
        $john = User::factory()->create(['referral_code' => ReferralCodeGenerator::generate()]);
        Wallet::create(['user_id' => $john->id]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Akash',
            'phone' => '7777700001',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $john->referral_code,
        ])->assertCreated();

        $akash = User::where('phone', '7777700001')->firstOrFail();

        $this->assertEquals('200.00000000', $akash->wallet->balance);
        $this->assertEquals('50.00000000', $john->wallet->fresh()->balance);

        $this->assertDatabaseHas('referral_commissions', [
            'beneficiary_user_id' => $john->id,
            'source_user_id' => $akash->id,
            'referral_level' => 1,
            'commission_source' => 'signup',
            'commission_amount' => '50.00000000',
            'status' => 'credited',
        ]);
    }

    public function test_two_users_under_same_referrer_each_pay_bonus(): void
    {
        $john = User::factory()->create(['referral_code' => ReferralCodeGenerator::generate()]);
        Wallet::create(['user_id' => $john->id]);

        foreach (['7777700002', '7777700003'] as $phone) {
            $this->postJson('/api/v1/auth/register', [
                'name' => "User {$phone}",
                'phone' => $phone,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'referral_code' => $john->referral_code,
            ])->assertCreated();
        }

        $this->assertEquals('100.00000000', $john->wallet->fresh()->balance);
        $this->assertSame(2, ReferralCommission::where('beneficiary_user_id', $john->id)
            ->where('commission_source', 'signup')
            ->count());
    }

    public function test_three_level_chain_pays_l1_l2_l3_only(): void
    {
        $john = User::factory()->create(['referral_code' => 'JOHN0001']);
        Wallet::create(['user_id' => $john->id]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Akash',
            'phone' => '7777700010',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $john->referral_code,
        ])->assertCreated();
        $akash = User::where('phone', '7777700010')->firstOrFail();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sital',
            'phone' => '7777700011',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $akash->referral_code,
        ])->assertCreated();
        $sital = User::where('phone', '7777700011')->firstOrFail();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Aman',
            'phone' => '7777700012',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'referral_code' => $sital->referral_code,
        ])->assertCreated();
        $aman = User::where('phone', '7777700012')->firstOrFail();

        $this->assertDatabaseHas('referral_commissions', [
            'beneficiary_user_id' => $sital->id,
            'source_user_id' => $aman->id,
            'referral_level' => 1,
            'commission_source' => 'signup',
        ]);
        $this->assertDatabaseHas('referral_commissions', [
            'beneficiary_user_id' => $akash->id,
            'source_user_id' => $aman->id,
            'referral_level' => 2,
            'commission_source' => 'signup',
        ]);
        $this->assertDatabaseHas('referral_commissions', [
            'beneficiary_user_id' => $john->id,
            'source_user_id' => $aman->id,
            'referral_level' => 3,
            'commission_source' => 'signup',
        ]);
        $this->assertDatabaseMissing('referral_commissions', [
            'beneficiary_user_id' => $john->id,
            'source_user_id' => $aman->id,
            'referral_level' => 4,
        ]);

        $this->assertEquals('150.00000000', $john->wallet->fresh()->total_commission);
        $this->assertEquals('100.00000000', $akash->wallet->fresh()->total_commission);
        $this->assertEquals('50.00000000', $sital->wallet->fresh()->total_commission);
    }

    public function test_level_four_referrer_gets_nothing_when_fifth_user_joins(): void
    {
        $john = User::factory()->create(['referral_code' => 'JOHN0002']);
        Wallet::create(['user_id' => $john->id]);

        $codes = ['7777700020', '7777700021', '7777700022', '7777700023', '7777700024'];
        $referralCode = $john->referral_code;

        foreach ($codes as $index => $phone) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name' => "User {$index}",
                'phone' => $phone,
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'referral_code' => $referralCode,
            ])->assertCreated();

            $referralCode = User::where('phone', $phone)->value('referral_code');
        }

        $fifthUser = User::where('phone', '7777700024')->firstOrFail();

        $this->assertDatabaseMissing('referral_commissions', [
            'beneficiary_user_id' => $john->id,
            'source_user_id' => $fifthUser->id,
            'commission_source' => 'signup',
        ]);

        $this->assertEquals('150.00000000', $john->wallet->fresh()->total_commission);
    }
}
