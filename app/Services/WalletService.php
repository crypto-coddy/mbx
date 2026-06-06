<?php

namespace App\Services;

use App\Models\DepositRequest;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['currency' => 'INR']
        );
    }

    public function grantSignupReward(User $user, string $amount, ?User $referrer = null): ?Transaction
    {
        if (bccomp($amount, '0', 8) <= 0) {
            return null;
        }

        return DB::transaction(function () use ($user, $amount, $referrer) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (string) $wallet->balance;

            $wallet->balance = bcadd($balanceBefore, $amount, 8);
            $wallet->locked_balance = bcadd((string) $wallet->locked_balance, $amount, 8);
            $wallet->reward_balance = bcadd((string) $wallet->reward_balance, $amount, 8);
            $wallet->total_income = bcadd((string) $wallet->total_income, $amount, 8);
            $wallet->save();

            $description = $referrer
                ? "Referral signup reward (non-withdrawable) via {$referrer->name}"
                : 'Signup reward (non-withdrawable)';

            return $this->recordTransaction(
                $user,
                $wallet,
                $amount,
                'credit',
                'signup_reward',
                $balanceBefore,
                $description,
                $referrer,
                ['locked' => true, 'currency' => $wallet->currency],
            );
        });
    }

    public function credit(
        User $user,
        string $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (string) $wallet->balance;
            $wallet->balance = bcadd($balanceBefore, $amount, 8);
            $wallet->total_income = bcadd((string) $wallet->total_income, $amount, 8);
            $wallet->save();

            return $this->recordTransaction($user, $wallet, $amount, 'credit', $type, $balanceBefore, $description, $reference, $meta);
        });
    }

    public function debit(
        User $user,
        string $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($type !== 'admin_debit' && bccomp($wallet->withdrawableBalance(), $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient withdrawable balance.');
            }

            if (bccomp((string) $wallet->balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $balanceBefore = (string) $wallet->balance;
            $wallet->balance = bcsub($balanceBefore, $amount, 8);
            $rechargeDebit = bccomp((string) $wallet->recharged_balance, $amount, 8) >= 0
                ? $amount
                : (string) $wallet->recharged_balance;
            if (bccomp($rechargeDebit, '0', 8) > 0) {
                $wallet->recharged_balance = bcsub((string) $wallet->recharged_balance, $rechargeDebit, 8);
            }
            $wallet->save();

            return $this->recordTransaction($user, $wallet, $amount, 'debit', $type, $balanceBefore, $description, $reference, $meta);
        });
    }

    /**
     * Debit for trading — can spend reward (locked) balance; reduces locked first.
     */
    public function debitForTrade(
        User $user,
        string $amount,
        string $type,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->balance, $amount, 8) < 0) {
                throw new InvalidArgumentException('Insufficient balance.');
            }

            $rewardSpend = bccomp((string) $wallet->reward_balance, $amount, 8) >= 0
                ? $amount
                : (string) $wallet->reward_balance;

            if (bccomp($rewardSpend, '0', 8) > 0) {
                $wallet->reward_balance = bcsub((string) $wallet->reward_balance, $rewardSpend, 8);
                $wallet->locked_balance = bcsub((string) $wallet->locked_balance, $rewardSpend, 8);
            }

            $rechargeSpend = bcsub($amount, $rewardSpend, 8);
            if (bccomp($rechargeSpend, '0', 8) > 0) {
                $wallet->recharged_balance = bcsub((string) $wallet->recharged_balance, $rechargeSpend, 8);
            }

            $balanceBefore = (string) $wallet->balance;
            $wallet->balance = bcsub($balanceBefore, $amount, 8);
            $wallet->save();

            return $this->recordTransaction($user, $wallet, $amount, 'debit', $type, $balanceBefore, $description, $reference, $meta);
        });
    }

    /** Lock recharged balance for a pending withdrawal request. */
    public function lockForWithdrawal(User $user, string $amount): Wallet
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (bccomp($wallet->withdrawableBalance(), $amount, 8) < 0) {
                throw new InvalidArgumentException(
                    'Insufficient withdrawable balance. Only admin-recharged funds (not signup reward) can be withdrawn.'
                );
            }

            $wallet->recharged_balance = bcsub((string) $wallet->recharged_balance, $amount, 8);
            $wallet->withdrawal_locked = bcadd((string) $wallet->withdrawal_locked, $amount, 8);
            $wallet->save();

            return $wallet->fresh();
        });
    }

    public function unlockForWithdrawal(User $user, string $amount): Wallet
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->withdrawal_locked, $amount, 8) < 0) {
                throw new InvalidArgumentException('Cannot unlock more than withdrawal-locked balance.');
            }

            $wallet->recharged_balance = bcadd((string) $wallet->recharged_balance, $amount, 8);
            $wallet->withdrawal_locked = bcsub((string) $wallet->withdrawal_locked, $amount, 8);
            $wallet->save();

            return $wallet->fresh();
        });
    }

    public function completeWithdrawal(User $user, string $amount): Wallet
    {
        return DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if (bccomp((string) $wallet->withdrawal_locked, $amount, 8) < 0) {
                throw new InvalidArgumentException('Withdrawal lock mismatch.');
            }

            $wallet->balance = bcsub((string) $wallet->balance, $amount, 8);
            $wallet->withdrawal_locked = bcsub((string) $wallet->withdrawal_locked, $amount, 8);
            $wallet->total_withdrawn = bcadd((string) $wallet->total_withdrawn, $amount, 8);
            $wallet->save();

            return $wallet->fresh();
        });
    }

    /**
     * Admin adds withdrawable funds (customer recharge). Shows in app wallet + history.
     */
    public function adminRecharge(
        User $user,
        string $amount,
        string $description,
        ?int $adminUserId = null,
        ?DepositRequest $deposit = null,
    ): Transaction {
        if (bccomp($amount, '0', 8) <= 0) {
            throw new InvalidArgumentException('Recharge amount must be positive.');
        }

        return DB::transaction(function () use ($user, $amount, $description, $adminUserId, $deposit) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (string) $wallet->balance;

            $wallet->balance = bcadd($balanceBefore, $amount, 8);
            $wallet->recharged_balance = bcadd((string) $wallet->recharged_balance, $amount, 8);
            $wallet->total_deposited = bcadd((string) $wallet->total_deposited, $amount, 8);
            $wallet->total_income = bcadd((string) $wallet->total_income, $amount, 8);
            $wallet->save();

            return $this->recordTransaction(
                $user,
                $wallet,
                $amount,
                'credit',
                'wallet_recharge',
                $balanceBefore,
                $description,
                $deposit,
                [
                    'admin_id' => $adminUserId,
                    'withdrawable' => true,
                    'currency' => $wallet->currency,
                    'deposit_id' => $deposit?->id,
                ],
            );
        });
    }

    public function lock(User $user, string $amount): Wallet
    {
        return $this->lockForWithdrawal($user, $amount);
    }

    public function unlock(User $user, string $amount): Wallet
    {
        return $this->unlockForWithdrawal($user, $amount);
    }

    public function recordWithdrawalEvent(
        User $user,
        WithdrawalRequest $withdrawal,
        string $status,
        string $description,
        ?string $transactionType = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $withdrawal, $status, $description, $transactionType) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $type = $transactionType ?? 'withdrawal_status';
            $direction = in_array($status, ['rejected', 'cancelled'], true) ? 'credit' : 'debit';

            return $this->recordTransaction(
                $user,
                $wallet,
                (string) $withdrawal->amount,
                $direction,
                $type,
                (string) $wallet->balance,
                $description,
                $withdrawal,
                [
                    'withdrawal_id' => $withdrawal->id,
                    'status' => $status,
                    'informational' => true,
                ],
            );
        });
    }

    public function recordDepositEvent(
        User $user,
        DepositRequest $deposit,
        string $status,
        string $description,
        ?string $transactionType = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $deposit, $status, $description, $transactionType) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $type = $transactionType ?? 'deposit_status';

            return $this->recordTransaction(
                $user,
                $wallet,
                (string) $deposit->amount,
                'credit',
                $type,
                (string) $wallet->balance,
                $description,
                $deposit,
                [
                    'deposit_id' => $deposit->id,
                    'status' => $status,
                    'informational' => true,
                ],
            );
        });
    }

    public function findDepositRequestTransaction(DepositRequest $deposit): ?Transaction
    {
        return Transaction::query()
            ->where('referenceable_type', $deposit->getMorphClass())
            ->where('referenceable_id', $deposit->id)
            ->where('type', 'deposit_request')
            ->latest('id')
            ->first();
    }

    public function removeDepositRequestTransactions(DepositRequest $deposit): void
    {
        Transaction::query()
            ->where('referenceable_type', $deposit->getMorphClass())
            ->where('referenceable_id', $deposit->id)
            ->whereIn('type', ['deposit_request', 'deposit_status'])
            ->delete();
    }

    public function updateDepositRequestTransaction(
        DepositRequest $deposit,
        string $status,
        string $description,
    ): ?Transaction {
        $transaction = $this->findDepositRequestTransaction($deposit);

        if ($transaction === null) {
            return null;
        }

        $meta = is_array($transaction->meta) ? $transaction->meta : [];
        $meta['status'] = $status;

        $transaction->update([
            'description' => $description,
            'meta' => $meta,
        ]);

        return $transaction->fresh();
    }

    public function formatDisplayAmount(string|float|int $amount): string
    {
        return Money::formatInr($amount);
    }

    public function creditWithStats(
        User $user,
        string $amount,
        string $type,
        ?string $statField = null,
        ?string $description = null,
        ?Model $reference = null,
        ?array $meta = null,
    ): Transaction {
        return DB::transaction(function () use ($user, $amount, $type, $statField, $description, $reference, $meta) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $balanceBefore = (string) $wallet->balance;
            $wallet->balance = bcadd($balanceBefore, $amount, 8);
            $wallet->total_income = bcadd((string) $wallet->total_income, $amount, 8);

            if ($statField === 'total_profit') {
                $wallet->total_profit = bcadd((string) $wallet->total_profit, $amount, 8);
            } elseif ($statField === 'total_commission') {
                $wallet->total_commission = bcadd((string) $wallet->total_commission, $amount, 8);
            }

            $wallet->save();

            return $this->recordTransaction($user, $wallet, $amount, 'credit', $type, $balanceBefore, $description, $reference, $meta);
        });
    }

    public function applyLoss(User $user, string $amount): void
    {
        DB::transaction(function () use ($user, $amount) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->total_loss = bcadd((string) $wallet->total_loss, $amount, 8);
            $wallet->save();
        });
    }

    protected function recordTransaction(
        User $user,
        Wallet $wallet,
        string $amount,
        string $direction,
        string $type,
        string $balanceBefore,
        ?string $description,
        ?Model $reference,
        ?array $meta,
    ): Transaction {
        $informational = (bool) ($meta['informational'] ?? false);

        return Transaction::create([
            'user_id' => $user->id,
            'type' => $type,
            'amount' => $amount,
            'direction' => $direction,
            'balance_before' => $balanceBefore,
            'balance_after' => $informational ? $balanceBefore : (string) $wallet->balance,
            'referenceable_type' => $reference ? $reference->getMorphClass() : null,
            'referenceable_id' => $reference?->getKey(),
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
