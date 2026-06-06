<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\ReferralCommission;
use App\Models\User;
use App\Services\TradeSettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralManagementController extends ApiController
{
    public function __construct(protected TradeSettingService $settings) {}

    public function tree(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);

        return $this->success($this->buildTree($user, 1, 5));
    }

    public function commissions(int $userId): JsonResponse
    {
        $commissions = ReferralCommission::where('beneficiary_user_id', $userId)
            ->with(['sourceUser:id,name', 'trade'])
            ->latest()
            ->paginate(20);

        return $this->success($commissions);
    }

    public function settings(): JsonResponse
    {
        return $this->success([
            'l1_rate' => $this->settings->getFloat('referral_commission_l1', 5),
            'l2_rate' => $this->settings->getFloat('referral_commission_l2', 2),
            'l3_rate' => $this->settings->getFloat('referral_commission_l3', 1),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'l1_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'l2_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'l3_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $this->settings->set('referral_commission_l1', (string) $data['l1_rate']);
        $this->settings->set('referral_commission_l2', (string) $data['l2_rate']);
        $this->settings->set('referral_commission_l3', (string) $data['l3_rate']);

        return $this->success([
            'l1_rate' => $data['l1_rate'],
            'l2_rate' => $data['l2_rate'],
            'l3_rate' => $data['l3_rate'],
        ], 'Referral settings updated.');
    }

    protected function buildTree(User $user, int $level, int $max): array
    {
        if ($level > $max) {
            return [];
        }

        return $user->referrals()->get()->map(fn (User $ref) => [
            'id' => $ref->id,
            'name' => $ref->name,
            'level' => $level,
            'children' => $this->buildTree($ref, $level + 1, $max),
        ])->toArray();
    }
}
