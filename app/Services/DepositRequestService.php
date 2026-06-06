<?php

namespace App\Services;

use App\Models\DepositRequest;
use App\Models\User;
use InvalidArgumentException;

class DepositRequestService
{
    public function __construct(
        protected WalletService $walletService,
    ) {}

    public function approve(DepositRequest $deposit, ?int $adminUserId = null): DepositRequest
    {
        if ($deposit->status !== 'pending') {
            throw new InvalidArgumentException('Only pending deposit requests can be approved.');
        }

        $deposit->loadMissing('user');
        $this->assertUserEligibleForDepositApproval($deposit->user);

        $amountLabel = $this->walletService->formatDisplayAmount((string) $deposit->amount);

        $this->walletService->adminRecharge(
            $deposit->user,
            (string) $deposit->amount,
            "Deposit of {$amountLabel} credited",
            $adminUserId,
            $deposit,
        );

        $deposit->update([
            'status' => 'approved',
            'processed_by' => $adminUserId,
            'processed_at' => now(),
        ]);

        return $deposit->fresh();
    }

    public function reject(DepositRequest $deposit, string $reason, ?int $adminUserId = null): DepositRequest
    {
        if ($deposit->status !== 'pending') {
            throw new InvalidArgumentException('Only pending deposit requests can be rejected.');
        }

        $deposit->loadMissing('user');

        $deposit->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'processed_by' => $adminUserId,
            'processed_at' => now(),
        ]);

        $amountLabel = $this->walletService->formatDisplayAmount((string) $deposit->amount);
        $this->walletService->recordDepositEvent(
            $deposit->user,
            $deposit->fresh(),
            'rejected',
            "Deposit request of {$amountLabel} rejected",
        );

        return $deposit->fresh();
    }

    public function pendingCount(): int
    {
        return DepositRequest::query()->where('status', 'pending')->count();
    }

    public function pendingAmount(): string
    {
        return (string) DepositRequest::query()->where('status', 'pending')->sum('amount');
    }

    private function assertUserEligibleForDepositApproval(User $user): void
    {
        $blockers = $user->depositApprovalBlockers();

        if ($blockers !== []) {
            throw new InvalidArgumentException(implode(' ', $blockers));
        }
    }
}
