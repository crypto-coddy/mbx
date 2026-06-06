<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Api\ApiController;
use App\Models\Trade;
use App\Services\PortfolioService;
use App\Services\TradeSettingService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends ApiController
{
    public function __construct(
        protected WalletService $walletService,
        protected TradeSettingService $settings,
        protected PortfolioService $portfolio,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->walletService->getOrCreateWallet($request->user());

        $available = $wallet->availableBalance();
        $withdrawable = $wallet->withdrawableBalance();
        $minAvailable = $this->settings->get('min_available_to_withdraw', '300');

        return $this->success([
            'balance' => $wallet->balance,
            'locked_balance' => $wallet->locked_balance,
            'available_balance' => $available,
            'reward_balance' => $wallet->reward_balance,
            'recharged_balance' => $wallet->recharged_balance,
            'withdrawable_balance' => $withdrawable,
            'withdrawal_locked' => $wallet->withdrawal_locked,
            'can_request_withdrawal' => bccomp($withdrawable, $minAvailable, 8) > 0,
            'min_available_to_withdraw' => $minAvailable,
            'min_withdrawal_amount' => $this->settings->get('min_withdrawal_amount', '300'),
            'total_income' => $wallet->total_income,
            'total_withdrawn' => $wallet->total_withdrawn,
            'total_commission' => $wallet->total_commission,
            'total_profit' => $wallet->total_profit,
            'total_loss' => $wallet->total_loss,
            'currency' => $wallet->currency,
            'portfolio' => $this->portfolio->summary($request->user()),
        ]);
    }

    public function incomeBreakdown(Request $request): JsonResponse
    {
        $trades = Trade::where('user_id', $request->user()->id)
            ->where('status', 'closed')
            ->with('asset')
            ->get()
            ->groupBy('asset_id');

        $breakdown = $trades->map(function ($group) {
            $asset = $group->first()->asset;
            $profit = $group->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) > 0)->sum('profit_loss');
            $loss = $group->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) < 0)->sum(fn ($t) => abs((float) $t->profit_loss));

            return [
                'asset_name' => $asset->name,
                'symbol' => $asset->symbol,
                'total_profit' => $profit,
                'total_loss' => $loss,
                'net' => $group->sum('profit_loss'),
                'trade_count' => $group->count(),
            ];
        })->values();

        return $this->success($breakdown);
    }
}
