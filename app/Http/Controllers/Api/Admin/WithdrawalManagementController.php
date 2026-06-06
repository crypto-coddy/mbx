<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\WithdrawalRequest;
use App\Services\AdminActivityLogger;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WithdrawalManagementController extends ApiController
{
    public function __construct(
        protected WalletService $walletService,
        protected AdminActivityLogger $activityLogger,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = WithdrawalRequest::with('user:id,name,phone')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $this->success($query->paginate(20));
    }

    public function show(int $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::with('user.profile', 'processor:id,name')->findOrFail($id);

        return $this->success($withdrawal);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::where('status', 'pending')->findOrFail($id);

        $withdrawal->update([
            'status' => 'approved',
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $this->walletService->recordWithdrawalEvent(
            $withdrawal->user,
            $withdrawal->fresh(),
            'approved',
            "Withdrawal request ₹{$withdrawal->amount} approved — payout pending",
        );

        $this->log($request, $withdrawal, 'withdrawal.approved');

        return $this->success($withdrawal->fresh(), 'Withdrawal approved.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string']]);
        $withdrawal = WithdrawalRequest::whereIn('status', ['pending', 'processing', 'approved'])->findOrFail($id);

        try {
            $this->walletService->unlock($withdrawal->user, (string) $withdrawal->amount);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $withdrawal->update([
            'status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $this->walletService->recordWithdrawalEvent(
            $withdrawal->user,
            $withdrawal->fresh(),
            'rejected',
            "Withdrawal request ₹{$withdrawal->amount} rejected — {$data['reason']}",
            'withdrawal_reversal',
        );

        $this->log($request, $withdrawal, 'withdrawal.rejected');

        return $this->success($withdrawal->fresh(), 'Withdrawal rejected and funds returned.');
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'transaction_reference' => ['required', 'string'],
            'payment_method' => ['sometimes', 'string', 'in:upi,bank_transfer,other'],
        ]);
        $withdrawal = WithdrawalRequest::whereIn('status', ['approved', 'processing'])->findOrFail($id);

        try {
            $this->walletService->completeWithdrawal($withdrawal->user, (string) $withdrawal->amount);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $withdrawal->update([
            'status' => 'paid',
            'transaction_reference' => $data['transaction_reference'],
            'paid_at' => now(),
            'processed_by' => $request->user()->id,
            'processed_at' => now(),
        ]);

        $this->walletService->recordWithdrawalEvent(
            $withdrawal->user,
            $withdrawal->fresh(),
            'paid',
            "Withdrawal ₹{$withdrawal->amount} paid — ref {$data['transaction_reference']}",
        );

        $this->log($request, $withdrawal, 'withdrawal.paid');

        return $this->success($withdrawal->fresh(), 'Withdrawal marked as paid.');
    }

    public function bulkApprove(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']]);
        $count = 0;

        foreach ($data['ids'] as $id) {
            $withdrawal = WithdrawalRequest::where('status', 'pending')->find($id);
            if ($withdrawal) {
                $withdrawal->update([
                    'status' => 'approved',
                    'processed_by' => $request->user()->id,
                    'processed_at' => now(),
                ]);
                $this->walletService->recordWithdrawalEvent(
                    $withdrawal->user,
                    $withdrawal->fresh(),
                    'approved',
                    "Withdrawal request ₹{$withdrawal->amount} approved — payout pending",
                );
                $count++;
            }
        }

        return $this->success(['approved_count' => $count], "{$count} withdrawals approved.");
    }

    protected function log(Request $request, WithdrawalRequest $withdrawal, string $action): void
    {
        $this->activityLogger->log(
            $request->user()->id,
            $action,
            "{$action} withdrawal #{$withdrawal->id}",
            null,
            ['status' => $withdrawal->status],
            $withdrawal,
            $request
        );
    }
}
