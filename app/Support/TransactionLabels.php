<?php

namespace App\Support;

class TransactionLabels
{
    public static function type(string $type): string
    {
        return match ($type) {
            'wallet_recharge' => 'Wallet recharge',
            'signup_reward' => 'Signup reward',
            'withdrawal' => 'Withdrawal paid',
            'withdrawal_request' => 'Withdrawal request',
            'withdrawal_status' => 'Withdrawal status',
            'withdrawal_reversal' => 'Withdrawal reversal',
            'trade_profit' => 'Trade profit',
            'trade_loss' => 'Trade / buy',
            'referral_commission' => 'Referral commission',
            'admin_credit' => 'Admin credit',
            'admin_debit' => 'Admin debit',
            'deposit' => 'Deposit',
            'deposit_request' => 'Deposit request',
            'deposit_status' => 'Deposit update',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public static function typeColor(string $type): string
    {
        return match ($type) {
            'wallet_recharge', 'signup_reward', 'trade_profit', 'referral_commission', 'admin_credit', 'deposit' => 'success',
            'deposit_request' => 'warning',
            'deposit_status' => 'warning',
            'withdrawal', 'withdrawal_request', 'trade_loss', 'admin_debit' => 'danger',
            'withdrawal_reversal' => 'info',
            'withdrawal_status' => 'warning',
            default => 'gray',
        };
    }
}
