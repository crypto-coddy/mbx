<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'trade_profit', 'trade_loss', 'referral_commission',
            'withdrawal', 'withdrawal_reversal', 'withdrawal_request', 'withdrawal_status',
            'signup_reward', 'wallet_recharge', 'admin_credit', 'admin_debit', 'deposit',
            'deposit_request', 'deposit_status'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM(
            'trade_profit', 'trade_loss', 'referral_commission',
            'withdrawal', 'withdrawal_reversal', 'withdrawal_request', 'withdrawal_status',
            'signup_reward', 'wallet_recharge', 'admin_credit', 'admin_debit', 'deposit'
        ) NOT NULL");
    }
};
