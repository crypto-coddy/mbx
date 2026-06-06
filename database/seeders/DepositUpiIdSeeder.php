<?php

namespace Database\Seeders;

use App\Models\DepositUpiId;
use App\Models\TradeSetting;
use Illuminate\Database\Seeder;

class DepositUpiIdSeeder extends Seeder
{
    public function run(): void
    {
        $fallback = TradeSetting::query()->where('key', 'deposit_upi_id')->value('value') ?? 'quantx@upi';

        DepositUpiId::firstOrCreate(
            ['upi_id' => $fallback],
            [
                'label' => 'Primary UPI',
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
    }
}
