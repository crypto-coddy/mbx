<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Trade;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends ApiController
{
    public function overview(): JsonResponse
    {
        return $this->success([
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'kyc_pending' => User::where('kyc_status', 'pending')->count(),
            'trades_today' => Trade::whereDate('created_at', today())->count(),
            'volume_today' => Trade::whereDate('created_at', today())->sum('amount'),
            'withdrawals_pending' => WithdrawalRequest::where('status', 'pending')->count(),
            'total_revenue' => Trade::where('status', 'closed')->sum('amount'),
        ]);
    }

    public function charts(Request $request): JsonResponse
    {
        $days = match ($request->input('period', '7d')) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $trades = Trade::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(amount) as volume')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $users = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success(['trades' => $trades, 'users' => $users]);
    }

    public function tradeStats(Request $request): JsonResponse
    {
        $days = match ($request->input('period', '7d')) {
            '30d' => 30,
            '90d' => 90,
            default => 7,
        };

        $stats = Trade::with('asset:id,name,symbol')
            ->where('created_at', '>=', now()->subDays($days))
            ->select('asset_id', DB::raw('COUNT(*) as trade_count'), DB::raw('SUM(amount) as volume'))
            ->groupBy('asset_id')
            ->get();

        return $this->success($stats);
    }

    public function userStats(Request $request): JsonResponse
    {
        $days = match ($request->input('period', '30d')) {
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $stats = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as registrations')
        )
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return $this->success($stats);
    }
}
