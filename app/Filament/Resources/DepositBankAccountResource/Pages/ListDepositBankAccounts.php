<?php

namespace App\Filament\Resources\DepositBankAccountResource\Pages;

use App\Filament\Resources\DepositBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDepositBankAccounts extends ListRecords
{
    protected static string $resource = DepositBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add bank account'),
        ];
    }
}
