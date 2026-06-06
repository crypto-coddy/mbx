<?php

namespace App\Services;

use App\Jobs\ProcessReferralCommissionsJob;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TradeService
{
    public function __construct(
        protected WalletService $walletService,
        protected ReferralService $referralService,
        protected TradeSettingService $settings,
    ) {}

    public function buy(User $user, int $assetId, string $amount): array
    {
        if (! $this->settings->getBool('trading_enabled', true)) {
            throw new InvalidArgumentException('Trading is currently disabled.');
        }

        $asset = \App\Models\Asset::where('id', $assetId)->where('is_active', true)->where('trading_enabled', true)->firstOrFail();
        $price = $asset->effectivePrice();

        if (bccomp($amount, '0', 8) <= 0) {
            throw new InvalidArgumentException('Trade amount must be positive.');
        }

        if (bccomp($amount, (string) $asset->min_trade_amount, 8) < 0) {
            throw new InvalidArgumentException('Amount below minimum trade size.');
        }

        if (bccomp($amount, (string) $asset->max_trade_amount, 8) > 0) {
            throw new InvalidArgumentException('Amount exceeds maximum trade size.');
        }

        if (bccomp($price, '0', 8) <= 0) {
            throw new InvalidArgumentException('Asset price unavailable.');
        }

        $quantity = bcdiv($amount, $price, 8);

        return DB::transaction(function () use ($user, $asset, $amount, $price, $quantity) {
            $this->walletService->debitForTrade(
                $user,
                $amount,
                'trade_loss',
                "Buy {$asset->symbol} position",
            );

            $trade = Trade::create([
                'user_id' => $user->id,
                'asset_id' => $asset->id,
                'type' => 'buy',
                'amount' => $amount,
                'quantity' => $quantity,
                'price_at_trade' => $price,
                'price_source' => $asset->priceSource(),
                'status' => 'open',
            ]);

            $wallet = $this->walletService->getOrCreateWallet($user->fresh());

            return ['trade' => $trade->load('asset'), 'wallet' => $wallet];
        });
    }

    /**
     * User requests to close — admin must set profit/loss before wallet is credited.
     */
    public function requestSell(User $user, int $tradeId): array
    {
        $trade = Trade::where('user_id', $user->id)
            ->where('id', $tradeId)
            ->where('status', 'open')
            ->with('asset')
            ->firstOrFail();

        $trade->update([
            'status' => 'pending_settlement',
            'settlement_requested_at' => now(),
            'meta' => array_merge($trade->meta ?? [], [
                'sell_requested_at' => now()->toIso8601String(),
            ]),
        ]);

        return ['trade' => $trade->fresh()->load('asset')];
    }

    /**
     * Admin settles a pending trade with explicit profit or loss (INR/USD amount).
     */
    public function settleByAdmin(
        Trade $trade,
        string $profitLoss,
        ?User $admin = null,
        ?string $note = null,
    ): array {
        if ($trade->status !== 'pending_settlement') {
            throw new InvalidArgumentException('Trade is not awaiting admin settlement.');
        }

        $user = $trade->user;
        $asset = $trade->asset;

        return DB::transaction(function () use ($trade, $user, $asset, $profitLoss, $admin, $note) {
            $quantity = (string) ($trade->quantity ?? bcdiv((string) $trade->amount, (string) $trade->price_at_trade, 8));
            $entryPrice = (string) $trade->price_at_trade;
            $costBasis = bcmul($quantity, $entryPrice, 8);

            $percent = bccomp($costBasis, '0', 8) > 0
                ? bcmul(bcdiv($profitLoss, $costBasis, 8), '100', 4)
                : '0';

            $closingPrice = bccomp($quantity, '0', 8) > 0
                ? bcadd($entryPrice, bcdiv($profitLoss, $quantity, 8), 8)
                : $entryPrice;

            $trade->update([
                'closing_price' => $closingPrice,
                'profit_loss' => $profitLoss,
                'profit_loss_percent' => $percent,
                'status' => 'closed',
                'closed_at' => now(),
                'settled_by' => $admin?->id,
                'admin_settlement_note' => $note,
                'meta' => array_merge($trade->meta ?? [], [
                    'settled_by_admin' => true,
                    'settled_at' => now()->toIso8601String(),
                ]),
            ]);

            $this->creditWalletForClose($user, $trade->fresh(), $asset, $profitLoss);

            $this->referralService->processOnTradeClose($trade->fresh());
            ProcessReferralCommissionsJob::dispatch()->afterCommit();

            $wallet = $this->walletService->getOrCreateWallet($user->fresh());

            return [
                'trade' => $trade->fresh()->load(['asset', 'user']),
                'profit_loss' => $profitLoss,
                'wallet' => $wallet,
            ];
        });
    }

    /** @deprecated Use requestSell — kept for backwards compatibility in tests */
    public function sell(User $user, int $tradeId): array
    {
        return $this->requestSell($user, $tradeId);
    }

    protected function creditWalletForClose(User $user, Trade $trade, $asset, string $profitLoss): void
    {
        $principal = (string) $trade->amount;
        $payout = bcadd($principal, $profitLoss, 8);

        if (bccomp($profitLoss, '0', 8) >= 0) {
            $this->walletService->creditWithStats(
                $user,
                $payout,
                'trade_profit',
                bccomp($profitLoss, '0', 8) > 0 ? 'total_profit' : null,
                "Close {$asset->symbol} trade #{$trade->id} (admin settled)",
                $trade,
            );
        } else {
            $lossAmount = ltrim($profitLoss, '-');
            $returnAmount = bcsub($principal, $lossAmount, 8);

            if (bccomp($returnAmount, '0', 8) > 0) {
                $this->walletService->credit(
                    $user,
                    $returnAmount,
                    'trade_loss',
                    "Close {$asset->symbol} trade #{$trade->id} (partial return)",
                    $trade,
                );
            }

            $this->walletService->applyLoss($user, $lossAmount);
        }
    }
}
