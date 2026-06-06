<?php

namespace Database\Seeders;

use App\Models\DepositBankAccount;
use App\Models\TradeSetting;
use Illuminate\Database\Seeder;

class DepositBankAccountSeeder extends Seeder
{
    public function run(): void
    {
        $accountNumber = TradeSetting::query()->where('key', 'deposit_account_number')->value('value') ?? '123456789012';
        $ifsc = TradeSetting::query()->where('key', 'deposit_ifsc')->value('value') ?? 'SBIN0001234';

        DepositBankAccount::firstOrCreate(
            [
                'account_number' => $accountNumber,
                'ifsc' => $ifsc,
            ],
            [
                'label' => 'Primary account',
                'bank_name' => TradeSetting::query()->where('key', 'deposit_bank_name')->value('value') ?? 'State Bank of India',
                'account_holder' => TradeSetting::query()->where('key', 'deposit_account_holder')->value('value') ?? 'QuantX Payments',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
    }
}
