<?php

namespace App\Services;

use App\Models\DepositUpiId;
use Illuminate\Support\Collection;

class DepositUpiService
{
    public function __construct(
        protected TradeSettingService $settings,
        protected UpiQrService $upiQr,
    ) {}

    /** @return Collection<int, DepositUpiId> */
    public function active(): Collection
    {
        return DepositUpiId::query()
            ->active()
            ->orderByDesc('sort_order')
            ->orderBy('id')
            ->get(['id', 'label', 'upi_id', 'payee_name', 'show_qr_code']);
    }

    /** @return list<array{id: int, label: string|null, upi_id: string, payee_name: string|null, show_qr_code: bool, upi_uri: string|null}> */
    public function activeForApi(): array
    {
        return $this->active()
            ->map(fn (DepositUpiId $row) => $this->formatForApi($row))
            ->values()
            ->all();
    }

    /** @return array{id: int, label: string|null, upi_id: string, payee_name: string|null, show_qr_code: bool, upi_uri: string|null} */
    public function formatForApi(DepositUpiId $row): array
    {
        $showQr = (bool) $row->show_qr_code;

        return [
            'id' => $row->id,
            'label' => $row->label,
            'upi_id' => $row->upi_id,
            'payee_name' => $row->payee_name,
            'show_qr_code' => $showQr,
            'upi_uri' => $showQr
                ? $this->upiQr->paymentUri($row->upi_id, $row->payee_name)
                : null,
        ];
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
