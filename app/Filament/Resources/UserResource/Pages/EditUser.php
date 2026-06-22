<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->profile()->firstOrCreate(
            ['user_id' => $this->record->id],
            ['country' => 'India'],
        );
        $this->record->load('profile');

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->refresh()->load('profile');
    }
}
