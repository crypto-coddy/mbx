<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SeedAssetsCommand extends Command
{
    protected $signature = 'mbx:seed-assets';

    protected $description = 'Sync all market assets (commodities, crypto, forex, indices) and chart history';

    public function handle(): int
    {
        return $this->call('mbx:sync-markets', ['--charts' => true]);
    }
}
