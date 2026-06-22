<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Role;

class UserProvisioningService
{
    public function __construct(
        protected WalletService $walletService,
        protected TradeSettingService $settings,
        protected ReferralService $referrals,
    ) {}

    /**
     * Profile, wallet, role, and optional signup reward after a user record exists.
     */
    public function provision(
        User $user,
        ?User $referrer = null,
        bool $grantReward = false,
        bool $markPhoneVerified = false,
        ?string $country = null,
        ?string $state = null,
        ?string $city = null,
        ?string $phoneCountryCode = null,
    ): void {
        $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'country' => $country ?? 'India',
                'state' => $state,
                'city' => $city,
                'phone_country_code' => $phoneCountryCode,
            ],
        );

        $this->walletService->getOrCreateWallet($user);

        if ($grantReward) {
            $amount = $this->settings->get('signup_referral_reward', '200');
            $this->walletService->grantSignupReward($user, $amount, $referrer);
        }

        if ($referrer) {
            $this->referrals->processOnSignup($user);
        }

        if ($markPhoneVerified && ! $user->phone_verified) {
            $user->update(['phone_verified' => true]);
        }

        if (! $user->hasRole('user') && Role::where('name', 'user')->exists()) {
            $user->assignRole('user');
        }
    }
}
