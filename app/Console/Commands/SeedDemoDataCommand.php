<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class SeedDemoDataCommand extends Command
{
    protected $signature = 'mbx:seed-demo
                            {--platform : Run platform DatabaseSeeder first (does not wipe the database)}
                            {--fresh : Alias for --platform}
                            {--force : Re-create demo wallet activity even if demo users were already seeded}';

    protected $description = 'Seed 10 demo users with admin recharges, buy/sell trades, and wallet history';

    public function handle(): int
    {
        if ($this->option('platform') || $this->option('fresh')) {
            $this->components->info('Running platform seeders (run migrate separately; this does not truncate data).');
            $this->call('db:seed');
        } else {
            $this->call('mbx:sync-markets', ['--charts' => true]);
        }

        config(['mbx.demo_seed_force' => (bool) $this->option('force')]);
        $this->call('db:seed', ['--class' => DemoDataSeeder::class]);
        config(['mbx.demo_seed_force' => false]);

        return self::SUCCESS;
    }
}
