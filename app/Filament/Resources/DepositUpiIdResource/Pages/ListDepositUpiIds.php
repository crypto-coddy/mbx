<?php

namespace App\Filament\Resources\DepositUpiIdResource\Pages;

use App\Filament\Resources\DepositUpiIdResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepositUpiIds extends ListRecords
{
    protected static string $resource = DepositUpiIdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add UPI ID'),
        ];
    }
}
