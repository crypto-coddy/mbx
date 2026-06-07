<?php

namespace App\Jobs;

use App\Services\MarketChartService;
use Illuminate\Foundation\Queue\Queueable;

class TickCustomChartsJob
{
    use Queueable;

    public function handle(MarketChartService $charts): void
    {
        $charts->tickAllCustomCharts();
    }
}
