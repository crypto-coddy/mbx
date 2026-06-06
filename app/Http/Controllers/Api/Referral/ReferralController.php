<?php

namespace App\Http\Controllers\Api\Referral;

use App\Http\Controllers\Api\ApiController;
use App\Models\ReferralCommission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends ApiController
{
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'total_referrals' => $user->referrals()->count(),
            'active_referrals' => $user->referrals()->where('status', 'active')->count(),
            'total_commission_earned' => $user->referralCommissionsEarned()
                ->where('status', 'credited')
                ->sum('commission_amount'),
            'commission_this_month' => $user->referralCommissionsEarned()
                ->where('status', 'credited')
                ->whereMonth('credited_at', now()->month)
                ->sum('commission_amount'),
            'referral_code' => $user->referral_code,
        ]);
    }

    public function team(Request $request): JsonResponse
    {
        $referrals = $request->user()->referrals()
            ->withCount('trades')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        $referrals->getCollection()->transform(function (User $ref) use ($request) {
            $commission = ReferralCommission::where('beneficiary_user_id', $request->user()->id)
                ->where('source_user_id', $ref->id)
                ->where('status', 'credited')
                ->sum('commission_amount');

            return [
                'user' => $ref->only(['id', 'name', 'phone', 'status', 'created_at']),
                'join_date' => $ref->created_at,
                'total_trades' => $ref->trades_count,
                'commission_earned' => $commission,
            ];
        });

        return $this->success($referrals);
    }

    public function tree(Request $request): JsonResponse
    {
        return $this->success($this->buildTree($request->user(), 1, 3));
    }

    public function commissions(Request $request): JsonResponse
    {
        $commissions = ReferralCommission::where('beneficiary_user_id', $request->user()->id)
            ->with(['sourceUser:id,name,phone', 'trade.asset'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->success($commissions);
    }

    protected function buildTree(User $user, int $level, int $maxLevel): array
    {
        if ($level > $maxLevel) {
            return [];
        }

        return $user->referrals()->get()->map(function (User $ref) use ($level, $maxLevel) {
            return [
                'id' => $ref->id,
                'name' => $ref->name,
                'phone' => $ref->phone,
                'status' => $ref->status,
                'level' => $level,
                'children' => $this->buildTree($ref, $level + 1, $maxLevel),
            ];
        })->toArray();
    }
}
