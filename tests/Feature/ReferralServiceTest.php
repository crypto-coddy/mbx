<?php

namespace Tests\Feature;

use App\Models\Trade;
use App\Models\TradeSetting;
use App\Models\User;
use App\Models\Wallet;
use App\Services\ReferralService;
use Tests\TestCase;

class ReferralServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureRole('user');
        foreach (['referral_commission_l1' => '5', 'referral_commission_l2' => '2', 'referral_commission_l3' => '1'] as $key => $value) {
            TradeSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    public function test_creates_commission_for_referrer_on_trade(): void
    {
        $referrer = User::factory()->create();
        $referrer->assignRole('user');
        Wallet::create(['user_id' => $referrer->id]);

        $trader = User::factory()->create(['referred_by' => $referrer->id]);
        $trader->assignRole('user');

        $asset = $this->testAsset([
            'name' => 'Gold',
            'symbol' => 'XAU',
            'display_name' => 'Gold',
            'live_price' => 1000,
        ]);

        $trade = Trade::create([
            'user_id' => $trader->id,
            'asset_id' => $asset->id,
            'type' => 'buy',
            'amount' => 100,
            'price_at_trade' => 1000,
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        app(ReferralService::class)->processOnTradeClose($trade);

        $this->assertDatabaseHas('referral_commissions', [
            'beneficiary_user_id' => $referrer->id,
            'source_user_id' => $trader->id,
        ]);
    }
}
