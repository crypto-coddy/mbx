<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Api\ApiController;
use App\Models\WithdrawalRequest;
use App\Services\TradeSettingService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WithdrawalController extends ApiController
{
    public function __construct(
        protected WalletService $walletService,
        protected TradeSettingService $settings,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'use_saved_bank' => ['sometimes', 'boolean'],
            'bank_details' => ['required_without:use_saved_bank', 'array'],
            'bank_details.upi_id' => ['sometimes', 'nullable', 'string'],
            'bank_details.bank_name' => ['sometimes', 'nullable', 'string'],
            'bank_details.account_number' => ['sometimes', 'nullable', 'string'],
            'bank_details.account_holder' => ['sometimes', 'nullable', 'string'],
            'bank_details.ifsc' => ['sometimes', 'nullable', 'string'],
        ]);

        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);
        $amount = (string) $data['amount'];
        $minAvailable = $this->settings->get('min_available_to_withdraw', '300');
        $minAmount = $this->settings->get('min_withdrawal_amount', '300');
        $withdrawable = $wallet->withdrawableBalance();

        if (bccomp($withdrawable, $minAvailable, 8) <= 0) {
            return $this->error(
                "Withdrawal requires more than ₹{$minAvailable} in admin-recharged balance. Signup reward cannot be withdrawn.",
                null,
                422
            );
        }

        if (bccomp($amount, $minAmount, 8) < 0) {
            return $this->error("Minimum withdrawal amount is ₹{$minAmount}.", null, 422);
        }

        if (bccomp($amount, $withdrawable, 8) > 0) {
            return $this->error('Amount exceeds your withdrawable (recharged) balance.', null, 422);
        }

        $bankDetails = $data['bank_details'] ?? null;

        if ($request->boolean('use_saved_bank')) {
            $profile = $user->profile;
            if (! $profile?->bank_name && ! $profile?->upi_id) {
                return $this->error('No saved bank or UPI details found. Add them in your profile or provide details.', null, 422);
            }
            $bankDetails = [
                'bank_name' => $profile->bank_name,
                'account_number' => $profile->account_number,
                'account_holder' => $profile->account_holder_name,
                'ifsc' => $profile->ifsc_code,
                'upi_id' => $profile->upi_id,
            ];
        }

        if (empty($bankDetails['upi_id']) && empty($bankDetails['account_number'])) {
            return $this->error('Provide UPI ID or bank account details for payout.', null, 422);
        }

        try {
            $this->walletService->lockForWithdrawal($user, $amount);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $withdrawal = WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $wallet->currency,
            'bank_details' => $bankDetails,
            'status' => 'pending',
        ]);

        $this->walletService->recordWithdrawalEvent(
            $user,
            $withdrawal,
            'pending',
            "Withdrawal request ₹{$amount} — pending admin approval",
            'withdrawal_request',
        );

        return $this->success($withdrawal, 'Withdrawal request submitted. Admin will process via UPI or bank transfer.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string']]);

        $query = WithdrawalRequest::where('user_id', $request->user()->id)->latest();

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $this->success($query->paginate(20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success($withdrawal);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $withdrawal = WithdrawalRequest::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        try {
            $this->walletService->unlockForWithdrawal($request->user(), (string) $withdrawal->amount);
        } catch (InvalidArgumentException $e) {
            return $this->error($e->getMessage(), null, 422);
        }

        $withdrawal->update(['status' => 'rejected', 'rejection_reason' => 'Cancelled by user']);

        $this->walletService->recordWithdrawalEvent(
            $request->user(),
            $withdrawal->fresh(),
            'cancelled',
            "Withdrawal request ₹{$withdrawal->amount} cancelled — funds released",
            'withdrawal_reversal',
        );

        return $this->success($withdrawal->fresh(), 'Withdrawal cancelled.');
    }
}
