<?php

namespace App\Services;

use App\Models\DepositUpiId;
use Illuminate\Support\Collection;

class DepositUpiService
{
    public function __construct(
        protected TradeSettingService $settings,
    ) {}

    /** @return Collection<int, DepositUpiId> */
    public function active(): Collection
    {
        return DepositUpiId::query()
            ->active()
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get(['id', 'label', 'upi_id']);
    }

    /** @return list<array{id: int, label: string|null, upi_id: string}> */
    public function activeForApi(): array
    {
        return $this->active()
            ->map(fn (DepositUpiId $row) => [
                'id' => $row->id,
                'label' => $row->label,
                'upi_id' => $row->upi_id,
            ])
            ->values()
            ->all();
    }

    public function primaryUpiId(): string
    {
        $first = $this->active()->first();

        if ($first !== null) {
            return $first->upi_id;
        }

        return $this->settings->get('deposit_upi_id', 'quantx@upi');
    }
}
