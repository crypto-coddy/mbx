<?php

namespace App\Services;

use App\Models\User;
use App\Support\ReferralCodeGenerator;
use Database\Seeders\RoleSeeder;

class SuperAdminService
{
    public function ensure(
        string $email = 'admin@mbxzone.com',
        string $phone = '9999999999',
        string $password = 'password',
    ): User {
        (new RoleSeeder)->run();

        $admin = User::withTrashed()
            ->where(function ($query) use ($phone, $email) {
                $query->where('phone', $phone)->orWhere('email', $email);
            })
            ->first();

        if ($admin?->trashed()) {
            $admin->restore();
        }

        $attributes = [
            'name' => 'Super Admin',
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'status' => 'active',
            'kyc_status' => 'approved',
            'phone_verified' => true,
        ];

        if ($admin) {
            $admin->fill($attributes);
            $admin->save();
        } else {
            $admin = User::create(array_merge($attributes, [
                'referral_code' => ReferralCodeGenerator::generate(),
            ]));
        }

        $admin->profile()->firstOrCreate(['user_id' => $admin->id], ['country' => 'India']);
        app(WalletService::class)->getOrCreateWallet($admin);

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        return $admin->fresh();
    }
}
