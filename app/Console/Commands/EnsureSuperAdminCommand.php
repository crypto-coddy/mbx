<?php

namespace App\Console\Commands;

use App\Services\SuperAdminService;
use Illuminate\Console\Command;

class EnsureSuperAdminCommand extends Command
{
    protected $signature = 'mbx:ensure-super-admin
                            {--email=admin@mbxzone.com : Admin email for Filament login}
                            {--phone=9999999999 : Admin phone}
                            {--password=password : Admin password}';

    protected $description = 'Create or reset the super admin account (fixes Filament login)';

    public function handle(SuperAdminService $superAdmin): int
    {
        $email = $this->option('email');
        $phone = $this->option('phone');
        $password = $this->option('password');

        $superAdmin->ensure($email, $phone, $password);

        $this->info('Super admin is ready for Filament login.');

        $this->newLine();
        $this->table(['Field', 'Value'], [
            ['Admin URL', url('/admin')],
            ['Email', $email],
            ['Password', $password],
            ['Phone', $phone],
        ]);

        return self::SUCCESS;
    }
}
