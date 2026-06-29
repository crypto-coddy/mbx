<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/** Tracks Twelve Data API credit usage against the active plan bucket. */
class TwelveDataCreditBudget
{
    public function plan(): string
    {
        $plan = strtolower((string) config('twelve_data.plan', 'grow'));

        return array_key_exists($plan, config('twelve_data.plans', [])) ? $plan : 'grow';
    }

    /**
     * @return array{credits_per_minute: int, daily_credit_cap: ?int, quote_chunk_size: int, chunk_delay_seconds: int}
     */
    public function planConfig(): array
    {
        return config('twelve_data.plans.'.$this->plan(), config('twelve_data.plans.grow'));
    }

    public function creditsPerMinute(): int
    {
        return (int) ($this->planConfig()['credits_per_minute'] ?? 377);
    }

    public function reservePerMinute(): int
    {
        return (int) config('twelve_data.credit_reserve_per_minute', 40);
    }

    public function availableCreditsThisMinute(string $scope = TwelveDataService::SCOPE_MOBILE): int
    {
        $limit = max(1, $this->creditsPerMinute() - $this->reservePerMinute());
        $used = (int) Cache::get($this->minuteKey($scope), 0);

        return max(0, $limit - $used);
    }

    public function canSpend(int $cost, string $scope = TwelveDataService::SCOPE_MOBILE): bool
    {
        if ($cost <= 0) {
            return true;
        }

        $dailyCap = $this->planConfig()['daily_credit_cap'] ?? null;
        if ($dailyCap !== null && (int) Cache::get($this->dailyKey(), 0) >= $dailyCap) {
            return false;
        }

        return $this->availableCreditsThisMinute($scope) >= $cost;
    }

    public function spend(int $cost, string $scope = TwelveDataService::SCOPE_MOBILE): bool
    {
        if ($cost <= 0) {
            return true;
        }

        if (! $this->canSpend($cost, $scope)) {
            return false;
        }

        $minuteKey = $this->minuteKey($scope);
        $used = (int) Cache::get($minuteKey, 0);
        Cache::put($minuteKey, $used + $cost, 120);

        $dailyKey = $this->dailyKey();
        $dailyUsed = (int) Cache::get($dailyKey, 0);
        Cache::put($dailyKey, $dailyUsed + $cost, 86_400);

        return true;
    }

    /**
     * @return array{plan: string, minute_used: int, minute_limit: int, minute_available: int, daily_used: int, daily_cap: ?int}
     */
    public function snapshot(string $scope = TwelveDataService::SCOPE_MOBILE): array
    {
        $limit = max(1, $this->creditsPerMinute() - $this->reservePerMinute());
        $used = (int) Cache::get($this->minuteKey($scope), 0);

        return [
            'plan' => $this->plan(),
            'minute_used' => $used,
            'minute_limit' => $limit,
            'minute_available' => max(0, $limit - $used),
            'daily_used' => (int) Cache::get($this->dailyKey(), 0),
            'daily_cap' => $this->planConfig()['daily_credit_cap'] ?? null,
        ];
    }

    protected function minuteKey(string $scope): string
    {
        return 'twelve_data:credits:minute:'.date('YmdHi').':'.$scope;
    }

    protected function dailyKey(): string
    {
        return 'twelve_data:credits:day:'.date('Ymd');
    }
}
