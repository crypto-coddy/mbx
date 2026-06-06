<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;

class EditAdminUser extends EditRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $record */
        $record = $this->record;
        $data['admin_role'] = $record->roles->first()?->name ?? 'admin';

        return $data;
    }

    protected function afterSave(): void
    {
        if (isset($this->data['admin_role'])) {
            AdminUserResource::syncAdminRole($this->record, $this->data['admin_role']);
        }
    }
}
