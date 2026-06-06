<?php

namespace App\Jobs;

use App\Services\OtpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CleanExpiredOtpsJob implements ShouldQueue
{
    use Queueable;

    public function handle(OtpService $otpService): void
    {
        $otpService->cleanExpired();
    }
}
