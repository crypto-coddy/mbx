<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\WalletService;
use App\Support\ReferralCodeGenerator;
use Illuminate\Console\Command;

class CreateAdminCommand extends Command
{
    protected $signature = 'mbx:create-admin
                            {email? : Admin email}
                            {--phone= : Phone number}
                            {--name=Admin : Admin name}
                            {--password= : Password (prompted if omitted)}
                            {--role=admin : Role: admin or super_admin}';

    protected $description = 'Create an admin user with wallet and profile';

    public function handle(WalletService $walletService): int
    {
        $email = $this->argument('email') ?? $this->ask('Email');
        $phone = $this->option('phone') ?? $this->ask('Phone', '9000000001');
        $name = $this->option('name');
        $password = $this->option('password') ?? $this->secret('Password');
        $role = $this->option('role');

        if (! in_array($role, ['admin', 'super_admin'], true)) {
            $this->error('Role must be admin or super_admin.');

            return self::FAILURE;
        }

        if (User::where('phone', $phone)->orWhere('email', $email)->exists()) {
            $this->error('User with this phone or email already exists.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => $password,
            'referral_code' => ReferralCodeGenerator::generate(),
            'status' => 'active',
            'kyc_status' => 'approved',
            'phone_verified' => true,
        ]);

        $user->profile()->create(['country' => 'India']);
        $walletService->getOrCreateWallet($user);
        $user->assignRole($role);

        $this->info("Admin created: {$user->email} ({$user->phone}) with role {$role}.");

        return self::SUCCESS;
    }
}
