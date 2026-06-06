<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AdminDashboardStats;
use App\Filament\Widgets\InactiveUsersTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_dashboard') ?? false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getHeading(): string
    {
        return 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        return 'QuantX overview — users, payouts, and platform health';
    }

    public function getWidgets(): array
    {
        return [
            AdminDashboardStats::class,
            InactiveUsersTable::class,
        ];
    }
}
