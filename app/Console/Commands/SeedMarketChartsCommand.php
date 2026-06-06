<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Services\MarketChartService;
use Illuminate\Console\Command;

class SeedMarketChartsCommand extends Command
{
    protected $signature = 'mbx:seed-market-charts';

    protected $description = 'Seed dummy chart data for XAU, XAG, USDT and ensure assets are active';

    public function handle(MarketChartService $charts): int
    {
        $assets = Asset::all();

        if ($assets->isEmpty()) {
            $this->error('No assets found. Run php artisan db:seed first.');

            return self::FAILURE;
        }

        foreach ($assets as $asset) {
            $asset->update([
                'is_active' => true,
                'trading_enabled' => true,
                'chart_trend' => $asset->chart_trend ?? 'up',
            ]);
            $charts->setTrend($asset->fresh(), $asset->chart_trend ?? 'up');
            $this->info("Chart seeded: {$asset->symbol} ({$asset->chart_trend})");
        }

        return self::SUCCESS;
    }
}
