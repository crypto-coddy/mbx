<?php

namespace App\Jobs;

use App\Services\ReferralService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReferralCommissionsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ReferralService $referralService): void
    {
        $referralService->creditPendingCommissions();
    }
}
