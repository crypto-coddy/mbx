<?php

namespace App\Filament\Resources\DepositBankAccountResource\Pages;

use App\Filament\Resources\DepositBankAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepositBankAccount extends EditRecord
{
    protected static string $resource = DepositBankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
