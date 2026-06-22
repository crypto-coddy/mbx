<?php

namespace Tests\Feature;

use App\Models\DepositRequest;
use App\Models\User;
use App\Services\WalletService;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepositRequestTest extends TestCase
{

    public function test_user_can_submit_deposit_request(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/deposits', [
            'amount' => 500,
            'payment_method' => 'upi',
            'payment_reference' => 'UTR123456',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('deposit_requests', [
            'user_id' => $user->id,
            'amount' => '500.00000000',
            'payment_method' => 'upi',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_submit_deposit_request_with_payment_screenshot(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->post('/api/v1/deposits', [
            'amount' => 500,
            'payment_method' => 'upi',
            'payment_reference' => 'UTR123456',
            'payment_screenshot' => \Illuminate\Http\UploadedFile::fake()->image('payment-proof.jpg'),
        ], [
            'Accept' => 'application/json',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $deposit = DepositRequest::firstOrFail();
        $this->assertNotNull($deposit->payment_screenshot_path);
        $this->assertNotNull($deposit->payment_screenshot_url);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('public')->exists($deposit->payment_screenshot_path));
    }

    public function test_deposit_instructions_are_available(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/deposits/instructions')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'min_deposit_amount',
                    'upi_id',
                    'upi_ids',
                    'bank_name',
                    'account_number',
                    'ifsc',
                    'bank_accounts',
                ],
            ]);
    }

    public function test_deposit_instructions_return_only_active_bank_accounts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        \App\Models\DepositBankAccount::query()->update(['is_active' => false]);

        \App\Models\DepositBankAccount::create([
            'label' => 'Primary',
            'bank_name' => 'HDFC Bank',
            'account_number' => '1111222233',
            'ifsc' => 'HDFC0001234',
            'account_holder' => 'QuantX Payments',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        \App\Models\DepositBankAccount::create([
            'label' => 'Hidden',
            'bank_name' => 'SBI',
            'account_number' => '9999888877',
            'ifsc' => 'SBIN0009999',
            'account_holder' => 'Old Account',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response = $this->getJson('/api/v1/deposits/instructions')->assertOk();

        $response->assertJsonPath('data.bank_name', 'HDFC Bank');
        $response->assertJsonPath('data.account_number', '1111222233');
        $response->assertJsonCount(1, 'data.bank_accounts');
        $response->assertJsonPath('data.bank_accounts.0.ifsc', 'HDFC0001234');
    }

    public function test_deposit_instructions_return_only_active_upi_ids(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        \App\Models\DepositUpiId::query()->update(['is_active' => false]);

        \App\Models\DepositUpiId::create([
            'label' => 'Primary',
            'upi_id' => 'active@upi',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        \App\Models\DepositUpiId::create([
            'label' => 'Hidden',
            'upi_id' => 'hidden@upi',
            'is_active' => false,
            'sort_order' => 5,
        ]);

        $response = $this->getJson('/api/v1/deposits/instructions')->assertOk();

        $response->assertJsonPath('data.upi_id', 'active@upi');
        $response->assertJsonCount(1, 'data.upi_ids');
        $response->assertJsonPath('data.upi_ids.0.upi_id', 'active@upi');
    }

    public function test_admin_can_approve_pending_deposit_via_service(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'approved',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/deposits', [
            'amount' => 500,
            'payment_method' => 'upi',
            'payment_reference' => 'UTR999',
        ])->assertCreated();

        $deposit = DepositRequest::firstOrFail();
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'deposit_request',
        ]);

        $service = app(\App\Services\DepositRequestService::class);
        $service->approve($deposit, null);

        $deposit->refresh();
        $this->assertSame('approved', $deposit->status);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'deposit_request',
            'referenceable_id' => $deposit->id,
        ]);
        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'wallet_recharge',
            'description' => 'Deposit of ₹500.00 credited',
            'referenceable_id' => $deposit->id,
        ]);
        $this->assertEquals(2, \App\Models\Transaction::where('user_id', $user->id)
            ->whereIn('type', ['deposit_request', 'deposit_status', 'wallet_recharge'])
            ->count());
        $this->assertEquals(2, \App\Models\Transaction::where('user_id', $user->id)
            ->visibleInWalletHistory()
            ->whereIn('type', ['deposit_request', 'deposit_status', 'wallet_recharge'])
            ->count());

        $wallet = app(WalletService::class)->getOrCreateWallet($user);
        $this->assertEquals('500.00000000', $wallet->recharged_balance);
    }

    public function test_deposit_cannot_be_approved_when_user_inactive(): void
    {
        $user = User::factory()->create([
            'status' => 'inactive',
            'kyc_status' => 'approved',
        ]);

        $deposit = DepositRequest::create([
            'user_id' => $user->id,
            'amount' => '500',
            'currency' => 'INR',
            'payment_method' => 'upi',
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\DepositRequestService::class)->approve($deposit);
    }

    public function test_deposit_cannot_be_approved_when_kyc_not_approved(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'pending',
        ]);

        $deposit = DepositRequest::create([
            'user_id' => $user->id,
            'amount' => '500',
            'currency' => 'INR',
            'payment_method' => 'upi',
            'status' => 'pending',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(\App\Services\DepositRequestService::class)->approve($deposit);
    }

    public function test_admin_approve_credits_wallet(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'kyc_status' => 'approved',
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/deposits', [
            'amount' => 300,
            'payment_method' => 'bank_transfer',
        ])->assertCreated();

        $deposit = DepositRequest::firstOrFail();

        app(WalletService::class)->adminRecharge(
            $user,
            (string) $deposit->amount,
            'Deposit approved',
        );

        $deposit->update(['status' => 'approved']);

        $wallet = app(WalletService::class)->getOrCreateWallet($user);
        $this->assertEquals('300.00000000', $wallet->recharged_balance);
    }
}
