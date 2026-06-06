<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\UserProvisioningService;
use App\Support\ReferralCodeGenerator;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['referral_code'] = ReferralCodeGenerator::generate();
        $data['phone_verified'] = true;

        if (empty($data['status'])) {
            $data['status'] = 'active';
        }

        $data['email'] = filled($data['email'] ?? null) ? trim($data['email']) : null;
        $data['phone'] = (string) ($data['phone'] ?? '');

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var User $user */
        $user = $this->record->fresh();
        $referrer = $user->referred_by
            ? User::find($user->referred_by)
            : null;

        try {
            app(UserProvisioningService::class)->provision(
                $user,
                $referrer,
                grantReward: true,
                markPhoneVerified: true,
            );
        } catch (\Throwable $e) {
            Notification::make()
                ->title('User saved but wallet setup failed')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $reward = app(\App\Services\TradeSettingService::class)->get('signup_referral_reward', '200');

        Notification::make()
            ->title('User created')
            ->body("₹{$reward} signup reward credited to wallet (tradeable, not withdrawable).")
            ->success()
            ->send();
    }
}
