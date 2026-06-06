<?php

namespace App\Http\Controllers\Api\Trading;

use App\Http\Controllers\Api\ApiController;
use App\Models\Trade;
use App\Services\TradeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class TradeController extends ApiController
{
    public function __construct(protected TradeService $tradeService) {}

    public function buy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'amount' => ['required', 'numeric', 'min:0.00000001'],
        ]);

        try {
            $result = $this->tradeService->buy($request->user(), $data['asset_id'], (string) $data['amount']);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success([
            'trade' => $result['trade'],
            'wallet_balance_after' => $result['wallet']->balance,
        ], 'Buy order placed.', 201);
    }

    public function sell(Request $request): JsonResponse
    {
        $data = $request->validate(['trade_id' => ['required', 'integer', 'exists:trades,id']]);

        try {
            $result = $this->tradeService->requestSell($request->user(), $data['trade_id']);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        return $this->success([
            'trade' => $result['trade'],
        ], 'Close request submitted. Admin will settle profit or loss.');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:open,pending_settlement,closed,cancelled,all'],
            'asset_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Trade::where('user_id', $request->user()->id)
            ->with('asset')
            ->latest();

        if (($data['status'] ?? 'all') !== 'all') {
            $query->where('status', $data['status']);
        }

        if (! empty($data['asset_id'])) {
            $query->where('asset_id', $data['asset_id']);
        }

        return $this->success($query->paginate($data['per_page'] ?? 20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $trade = Trade::where('user_id', $request->user()->id)
            ->with('asset')
            ->findOrFail($id);

        return $this->success($trade);
    }

    public function pnlSummary(Request $request): JsonResponse
    {
        $data = $request->validate(['period' => ['sometimes', 'in:7d,30d,all']]);
        $userId = $request->user()->id;

        $query = Trade::where('user_id', $userId)->where('status', 'closed');

        $query = match ($data['period'] ?? 'all') {
            '7d' => $query->where('closed_at', '>=', now()->subDays(7)),
            '30d' => $query->where('closed_at', '>=', now()->subDays(30)),
            default => $query,
        };

        $trades = $query->with('asset')->get();
        $totalProfit = $trades->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) > 0)
            ->sum(fn ($t) => (float) $t->profit_loss);
        $totalLoss = $trades->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) < 0)
            ->sum(fn ($t) => abs((float) $t->profit_loss));

        $byAsset = $trades->groupBy('asset_id')->map(function ($group) {
            $asset = $group->first()->asset;

            return [
                'asset_id' => $asset->id,
                'asset_name' => $asset->name,
                'symbol' => $asset->symbol,
                'trade_count' => $group->count(),
                'total_profit' => $group->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) > 0)->sum('profit_loss'),
                'total_loss' => $group->filter(fn ($t) => bccomp((string) $t->profit_loss, '0', 8) < 0)->sum(fn ($t) => abs((float) $t->profit_loss)),
                'net' => $group->sum('profit_loss'),
            ];
        })->values();

        return $this->success([
            'total_trades' => $trades->count(),
            'total_profit' => $totalProfit,
            'total_loss' => $totalLoss,
            'net_pnl' => $totalProfit - $totalLoss,
            'by_asset' => $byAsset,
        ]);
    }
}
