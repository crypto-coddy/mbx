<?php

namespace App\Jobs;

use App\Services\MarketChartService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TickCustomChartsJob implements ShouldQueue
{
    use Queueable;

    public function handle(MarketChartService $charts): void
    {
        $charts->tickAllCustomCharts();
    }
}
