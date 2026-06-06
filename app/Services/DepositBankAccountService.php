<?php

namespace App\Services;

use App\Models\DepositBankAccount;
use Illuminate\Support\Collection;

class DepositBankAccountService
{
    public function __construct(
        protected TradeSettingService $settings,
    ) {}

    /** @return Collection<int, DepositBankAccount> */
    public function active(): Collection
    {
        return DepositBankAccount::query()
            ->active()
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get(['id', 'label', 'bank_name', 'account_number', 'ifsc', 'account_holder']);
    }

    /** @return list<array{id: int, label: string|null, bank_name: string, account_number: string, ifsc: string, account_holder: string}> */
    public function activeForApi(): array
    {
        return $this->active()
            ->map(fn (DepositBankAccount $row) => [
                'id' => $row->id,
                'label' => $row->label,
                'bank_name' => $row->bank_name,
                'account_number' => $row->account_number,
                'ifsc' => $row->ifsc,
                'account_holder' => $row->account_holder,
            ])
            ->values()
            ->all();
    }

    /** @return array{bank_name: string, account_number: string, ifsc: string, account_holder: string} */
    public function primaryAccount(): array
    {
        $first = $this->active()->first();

        if ($first !== null) {
            return [
                'bank_name' => $first->bank_name,
                'account_number' => $first->account_number,
                'ifsc' => $first->ifsc,
                'account_holder' => $first->account_holder,
            ];
        }

        return [
            'bank_name' => $this->settings->get('deposit_bank_name', 'State Bank of India'),
            'account_number' => $this->settings->get('deposit_account_number', '123456789012'),
            'ifsc' => $this->settings->get('deposit_ifsc', 'SBIN0001234'),
            'account_holder' => $this->settings->get('deposit_account_holder', 'QuantX Payments'),
        ];
    }
}
