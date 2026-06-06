<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\MarketChartService;
use App\Support\MarketAssetCatalog;
use Illuminate\Console\Command;

class SyncMarketCatalogCommand extends Command
{
    protected $signature = 'mbx:sync-markets {--charts : Seed admin chart history for new assets}';

    protected $description = 'Sync default markets for all categories (commodities, crypto, forex, indices)';

    public function handle(MarketChartService $charts): int
    {
        foreach (MarketAssetCatalog::definitions() as $data) {
            $asset = Asset::updateOrCreate(
                ['symbol' => $data['symbol']],
                array_merge($data, [
                    'is_active' => true,
                    'trading_enabled' => true,
                    'chart_trend' => $data['chart_trend'] ?? 'up',
                    'price_updated_at' => now(),
                ])
            );

            $this->line("  • {$asset->symbol} ({$asset->category}) — {$asset->display_name}");
        }

        if ($this->option('charts')) {
            $charts->seedAllActiveAssets();
            $this->info('Chart history seeded.');
        }

        $this->newLine();
        $this->info('Market catalog synced. Manage each asset in Admin → Trading → Markets.');

        return self::SUCCESS;
    }
}
