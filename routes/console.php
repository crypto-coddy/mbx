<?php

use App\Jobs\CleanExpiredOtpsJob;
use App\Jobs\FetchLivePricesJob;
use App\Jobs\ProcessReferralCommissionsJob;
use App\Jobs\TickCustomChartsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new FetchLivePricesJob)->everyThirtySeconds();
Schedule::job(new TickCustomChartsJob)->everyMinute();
Schedule::job(new ProcessReferralCommissionsJob)->everyMinute();
Schedule::job(new CleanExpiredOtpsJob)->hourly();
