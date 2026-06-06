<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;

class PortfolioService
{
    public function __construct(protected WalletService $walletService) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $wallet = $this->walletService->getOrCreateWallet($user);
        $balance = (string) $wallet->balance;

        $startOfDay = now()->startOfDay();
        $firstTxToday = Transaction::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $startOfDay)
            ->orderBy('created_at')
            ->first();

        $openingBalance = $firstTxToday
            ? (string) $firstTxToday->balance_before
            : $balance;

        $dailyChange = bcsub($balance, $openingBalance, 8);
        $dailyChangePct = bccomp($openingBalance, '0', 8) > 0
            ? bcmul(bcdiv($dailyChange, $openingBalance, 8), '100', 4)
            : '0.0000';

        $sparkline = Transaction::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(24)
            ->get(['balance_after', 'created_at'])
            ->reverse()
            ->values()
            ->map(fn (Transaction $tx) => [
                'value' => (string) $tx->balance_after,
                'at' => $tx->created_at?->toIso8601String(),
            ]);

        if ($sparkline->count() < 2) {
            $sparkline = collect([
                ['value' => $openingBalance, 'at' => $startOfDay->toIso8601String()],
                ['value' => $balance, 'at' => now()->toIso8601String()],
            ]);
        }

        return [
            'balance' => $balance,
            'available_balance' => $wallet->availableBalance(),
            'currency' => $wallet->currency,
            'daily_change' => $dailyChange,
            'daily_change_pct' => $dailyChangePct,
            'display_balance' => $balance,
            'chart' => $sparkline,
        ];
    }
}
