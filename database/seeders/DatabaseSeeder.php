<?php

namespace Database\Seeders;

use App\Models\TradeSetting;
use App\Services\SuperAdminService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $settings = [
            'trading_enabled' => ['true', 'Enable or disable trading globally'],
            'referral_commission_l1' => ['5', 'Level 1 referral commission percent'],
            'referral_commission_l2' => ['2', 'Level 2 referral commission percent'],
            'referral_commission_l3' => ['1', 'Level 3 referral commission percent'],
            'referral_signup_bonus' => ['50', 'Flat INR bonus credited to L1/L2/L3 referrers when someone joins with a code (max 3 levels)'],
            'signup_referral_reward' => ['200', 'INR reward credited to new user when registered with a referral code (locked, tradeable only)'],
            'min_available_to_withdraw' => ['300', 'Minimum withdrawable balance (INR) required before user can submit a withdrawal request'],
            'min_withdrawal_amount' => ['300', 'Minimum per withdrawal request amount in INR'],
            'max_withdrawal_per_day' => ['50000', 'Maximum withdrawal per day'],
            'withdrawal_processing_days' => ['3', 'Expected withdrawal processing days'],
            'kyc_required_to_trade' => ['true', 'Require KYC approval before trading'],
            'price_fetch_interval_sec' => ['30', 'Price fetch interval in seconds'],
            'mobile_chart_data_source' => ['real', 'Mobile trade charts: real = live market feeds; custom = admin-controlled'],
            'mobile_chart_data_version' => ['v1', 'Real-market feed version: v1 = Markets; v2 = Markets Live (Twelve Data)'],
            'min_deposit_amount' => ['300', 'Minimum deposit request amount in INR'],
            'deposit_upi_id' => ['quantx@upi', 'UPI ID shown to users for wallet deposits'],
            'deposit_bank_name' => ['State Bank of India', 'Bank name for user deposits'],
            'deposit_account_number' => ['123456789012', 'Bank account number for user deposits'],
            'deposit_ifsc' => ['SBIN0001234', 'IFSC code for user deposits'],
            'deposit_account_holder' => ['QuantX Payments', 'Account holder name for user deposits'],
        ];

        foreach ($settings as $key => [$value, $description]) {
            TradeSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value, 'description' => $description]
            );
        }

        Artisan::call('mbx:sync-markets', ['--charts' => true]);

        app(SuperAdminService::class)->ensure(
            env('SUPER_ADMIN_EMAIL', 'admin@mbxzone.com'),
            env('SUPER_ADMIN_PHONE', '9999999999'),
            env('SUPER_ADMIN_PASSWORD', 'password'),
        );

        $this->call(BlogPostSeeder::class);
        $this->call(DepositUpiIdSeeder::class);
        $this->call(DepositBankAccountSeeder::class);
    }
}
