<?php

namespace App\Http\Controllers\Api\Wallet;

use App\Http\Controllers\Api\ApiController;
use App\Models\DepositRequest;
use App\Services\DepositBankAccountService;
use App\Services\DepositUpiService;
use App\Services\TradeSettingService;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DepositController extends ApiController
{
    public function __construct(
        protected WalletService $walletService,
        protected TradeSettingService $settings,
        protected DepositUpiService $depositUpi,
        protected DepositBankAccountService $depositBankAccounts,
    ) {}

    public function instructions(Request $request): JsonResponse
    {
        $upiIds = $this->depositUpi->activeForApi();
        $bankAccounts = $this->depositBankAccounts->activeForApi();
        $primaryBank = $this->depositBankAccounts->primaryAccount();

        return $this->success([
            'min_deposit_amount' => $this->settings->get('min_deposit_amount', '300'),
            'upi_id' => $this->depositUpi->primaryUpiId(),
            'upi_ids' => $upiIds,
            'bank_name' => $primaryBank['bank_name'],
            'account_number' => $primaryBank['account_number'],
            'ifsc' => $primaryBank['ifsc'],
            'account_holder' => $primaryBank['account_holder'],
            'bank_accounts' => $bankAccounts,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:upi,bank_transfer'],
            'payment_reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
            'payment_screenshot' => ['nullable', 'image', 'max:5120'],
        ]);

        $user = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($user);
        $amount = (string) $data['amount'];
        $minDeposit = $this->settings->get('min_deposit_amount', '300');

        if (bccomp($amount, $minDeposit, 8) < 0) {
            return $this->error("Minimum deposit amount is ₹{$minDeposit}.", null, 422);
        }

        $amountLabel = Money::formatInr($amount);

        $screenshotPath = null;
        $screenshotUrl = null;

        if ($request->hasFile('payment_screenshot')) {
            $stored = $request->file('payment_screenshot')->store('deposits/'.$user->id, 'public');
            $screenshotPath = $stored;
            $screenshotUrl = Storage::disk('public')->url($stored);
        }

        $deposit = DepositRequest::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'currency' => $wallet->currency,
            'payment_method' => $data['payment_method'],
            'payment_reference' => $data['payment_reference'] ?? null,
            'note' => $data['note'] ?? null,
            'payment_screenshot_path' => $screenshotPath,
            'payment_screenshot_url' => $screenshotUrl,
            'status' => 'pending',
        ]);

        $this->walletService->recordDepositEvent(
            $user,
            $deposit,
            'pending',
            "Deposit request {$amountLabel} — pending admin verification",
            'deposit_request',
        );

        return $this->success($deposit, 'Deposit request submitted. Admin will verify your payment and credit your wallet.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string']]);

        $query = DepositRequest::where('user_id', $request->user()->id)->latest();

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $this->success($query->paginate(20));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $deposit = DepositRequest::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->success($deposit);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $deposit = DepositRequest::where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $deposit->update(['status' => 'cancelled']);

        $amountLabel = Money::formatInr((string) $deposit->amount);
        $this->walletService->recordDepositEvent(
            $request->user(),
            $deposit->fresh(),
            'cancelled',
            "Deposit request {$amountLabel} cancelled",
        );

        return $this->success($deposit->fresh(), 'Deposit request cancelled.');
    }
}
