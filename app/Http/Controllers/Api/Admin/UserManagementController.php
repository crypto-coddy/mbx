<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\Trade;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UserManagementController extends ApiController
{
    public function __construct(
        protected WalletService $walletService,
        protected AdminActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profile', 'wallet', 'roles'])->latest();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        foreach (['status', 'kyc_status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return $this->success($query->paginate($request->integer('per_page', 20)));
    }

    public function show(int $id): JsonResponse
    {
        $user = User::with(['profile', 'wallet', 'roles', 'kycDocuments'])->findOrFail($id);

        return $this->success($user);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended,banned'],
            'reason' => ['nullable', 'string'],
        ]);

        $user = User::findOrFail($id);
        $before = $user->only(['status']);
        $user->update(['status' => $data['status']]);

        $this->activityLogger->log(
            $request->user()->id,
            'user.status_updated',
            "Updated user #{$id} status to {$data['status']}",
            $before,
            $user->only(['status']),
            $user,
            $request
        );

        return $this->success($user->fresh(), 'User status updated.');
    }

    public function walletAdjust(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:credit,debit'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string'],
        ]);

        $user = User::findOrFail($id);
        $amount = (string) $data['amount'];

        try {
            $transaction = $data['type'] === 'credit'
                ? $this->walletService->adminRecharge($user, $amount, $data['description'], $request->user()->id)
                : $this->walletService->debit($user, $amount, 'admin_debit', $data['description']);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $this->activityLogger->log(
            $request->user()->id,
            'wallet.adjust',
            "Wallet {$data['type']} of {$amount} for user #{$id}",
            null,
            ['amount' => $amount, 'type' => $data['type']],
            $user,
            $request
        );

        return $this->success([
            'transaction' => $transaction,
            'wallet' => $user->fresh()->wallet,
        ], 'Wallet adjusted.');
    }

    public function trades(int $id): JsonResponse
    {
        $trades = Trade::where('user_id', $id)->with('asset')->latest()->paginate(20);

        return $this->success($trades);
    }

    public function referralTree(int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return $this->success($this->buildTree($user, 1, 3));
    }

    protected function buildTree(User $user, int $level, int $maxLevel): array
    {
        if ($level > $maxLevel) {
            return [];
        }

        return $user->referrals()->get()->map(fn (User $ref) => [
            'id' => $ref->id,
            'name' => $ref->name,
            'phone' => $ref->phone,
            'level' => $level,
            'children' => $this->buildTree($ref, $level + 1, $maxLevel),
        ])->toArray();
    }
}
