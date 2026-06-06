<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DepositRequestResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WithdrawalRequestResource;
use App\Services\AdminDashboardService;
use App\Services\DepositRequestService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminDashboardStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected ?string $heading = 'Platform overview';

    protected ?string $description = 'Key metrics for app users, deposits, and withdrawal payouts';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $metrics = app(AdminDashboardService::class);
        $deposits = app(DepositRequestService::class);
        $total = max($metrics->totalUsers(), 1);

        return [
            Stat::make('Total Users', $metrics->totalUsers())
                ->description('Registered app users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary')
                ->chart($this->trend($metrics->totalUsers(), $total))
                ->url(UserResource::getUrl('index')),

            Stat::make('Pending Deposits', $deposits->pendingCount())
                ->description($metrics->formatInr($deposits->pendingAmount()).' awaiting verification')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('warning')
                ->chart($this->trend($deposits->pendingCount(), 5))
                ->url(DepositRequestResource::getUrl('index')),

            Stat::make('Payment Requests', $metrics->paymentRequestsCount())
                ->description($metrics->formatInr($metrics->paymentRequestsAmount()).' pending payout')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning')
                ->chart($this->trend($metrics->paymentRequestsCount(), 5))
                ->url(WithdrawalRequestResource::getUrl('index')),

            Stat::make('Payments Settled', $metrics->paymentsSettledCount())
                ->description($metrics->formatInr($metrics->paymentsSettledAmount()).' paid out')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->chart($this->trend($metrics->paymentsSettledCount(), 5))
                ->url(WithdrawalRequestResource::getUrl('index')),

            Stat::make('Active Users', $metrics->activeUsers())
                ->description('Can log in and trade')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart($this->trend($metrics->activeUsers(), $total))
                ->url(UserResource::getUrl('index')),

            Stat::make('Inactive Users', $metrics->inactiveUsers())
                ->description('Inactive, suspended, or banned')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger')
                ->chart($this->trend($metrics->inactiveUsers(), max($total / 4, 1)))
                ->url(UserResource::getUrl('index')),
        ];
    }

    /** @return array<int, int> */
    private function trend(int $value, int $scale): array
    {
        $scale = max($scale, 1);
        $v = max($value, 0);

        return [
            (int) max(1, round($v * 0.4)),
            (int) max(1, round($v * 0.55)),
            (int) max(1, round($v * 0.7)),
            (int) max(1, round($v * 0.85)),
            $v,
            min($scale, $v + 1),
            min($scale, max($v, 1)),
        ];
    }
}
