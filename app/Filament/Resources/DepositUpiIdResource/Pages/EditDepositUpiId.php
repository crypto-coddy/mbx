<?php

namespace App\Filament\Resources\DepositUpiIdResource\Pages;

use App\Filament\Resources\DepositUpiIdResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDepositUpiId extends EditRecord
{
    protected static string $resource = DepositUpiIdResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
