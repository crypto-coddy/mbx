<?php

namespace App\Services;

use App\Models\ReferralCommission;
use App\Models\Trade;
use App\Models\User;

class ReferralService
{
    public function __construct(
        protected TradeSettingService $settings,
        protected WalletService $walletService,
    ) {}

    public function processOnTradeClose(Trade $trade): void
    {
        if ($trade->status !== 'closed') {
            return;
        }

        $sourceUser = $trade->user;
        $referrer = $sourceUser->referrer;
        $level = 1;

        while ($referrer && $level <= 3) {
            $rate = $this->rateForLevel($level);

            if ($rate > 0) {
                $commissionAmount = bcmul(
                    (string) $trade->amount,
                    bcdiv((string) $rate, '100', 8),
                    8
                );

                if (bccomp($commissionAmount, '0', 8) > 0) {
                    ReferralCommission::create([
                        'beneficiary_user_id' => $referrer->id,
                        'source_user_id' => $sourceUser->id,
                        'trade_id' => $trade->id,
                        'referral_level' => $level,
                        'trade_amount' => $trade->amount,
                        'commission_rate' => $rate,
                        'commission_amount' => $commissionAmount,
                        'status' => 'pending',
                    ]);
                }
            }

            $referrer = $referrer->referrer;
            $level++;
        }
    }

    public function creditPendingCommissions(): int
    {
        $count = 0;

        ReferralCommission::where('status', 'pending')
            ->with(['beneficiary', 'trade'])
            ->orderBy('id')
            ->chunkById(100, function ($commissions) use (&$count) {
                foreach ($commissions as $commission) {
                    $beneficiary = $commission->beneficiary;

                    if (! $beneficiary) {
                        $commission->update(['status' => 'cancelled']);

                        continue;
                    }

                    $this->walletService->creditWithStats(
                        $beneficiary,
                        (string) $commission->commission_amount,
                        'referral_commission',
                        'total_commission',
                        "Level {$commission->referral_level} referral commission from trade #{$commission->trade_id}",
                        $commission,
                    );

                    $commission->update([
                        'status' => 'credited',
                        'credited_at' => now(),
                    ]);

                    $count++;
                }
            });

        return $count;
    }

    protected function rateForLevel(int $level): float
    {
        return match ($level) {
            1 => $this->settings->getFloat('referral_commission_l1', 5),
            2 => $this->settings->getFloat('referral_commission_l2', 2),
            3 => $this->settings->getFloat('referral_commission_l3', 1),
            default => 0,
        };
    }
}
