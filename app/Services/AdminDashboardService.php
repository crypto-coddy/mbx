<?php

namespace App\Services;

use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Builder;

class AdminDashboardService
{
    /** App users only (excludes admin / super_admin staff). */
    public function appUsersQuery(): Builder
    {
        return User::query()->whereDoesntHave('roles', function (Builder $query) {
            $query->whereIn('name', ['admin', 'super_admin']);
        });
    }

    public function totalUsers(): int
    {
        return $this->appUsersQuery()->count();
    }

    public function activeUsers(): int
    {
        return $this->appUsersQuery()->where('status', 'active')->count();
    }

    public function inactiveUsers(): int
    {
        return $this->appUsersQuery()->whereIn('status', ['inactive', 'suspended', 'banned'])->count();
    }

    public function paymentRequestsCount(): int
    {
        return WithdrawalRequest::whereIn('status', ['pending', 'processing', 'approved'])->count();
    }

    public function paymentRequestsAmount(): string
    {
        return (string) WithdrawalRequest::whereIn('status', ['pending', 'processing', 'approved'])->sum('amount');
    }

    public function paymentsSettledCount(): int
    {
        return WithdrawalRequest::where('status', 'paid')->count();
    }

    public function paymentsSettledAmount(): string
    {
        return (string) WithdrawalRequest::where('status', 'paid')->sum('amount');
    }

    public function formatInr(string $amount): string
    {
        return '₹'.number_format((float) $amount, 2);
    }
}
