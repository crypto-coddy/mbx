<?php

namespace App\Filament\Resources\AdminUserResource\Pages;

use App\Filament\Resources\AdminUserResource;
use App\Models\User;
use App\Support\ReferralCodeGenerator;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminUser extends CreateRecord
{
    protected static string $resource = AdminUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['referral_code'] = ReferralCodeGenerator::generate();
        $data['kyc_status'] = 'approved';
        $data['phone_verified'] = true;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record;
        $role = $this->data['admin_role'] ?? 'admin';

        AdminUserResource::syncAdminRole($user, $role);
    }
}
