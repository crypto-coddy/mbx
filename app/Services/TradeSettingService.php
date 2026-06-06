<?php

namespace App\Services;

use App\Models\TradeSetting;
use Illuminate\Support\Facades\Cache;

class TradeSettingService
{
    public function get(string $key, ?string $default = null): ?string
    {
        $settings = $this->all();

        return $settings[$key] ?? $default;
    }

    public function set(string $key, string $value, ?string $description = null): TradeSetting
    {
        $setting = TradeSetting::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $value,
                'description' => $description,
            ])
        );

        Cache::forget('trade_settings');

        return $setting;
    }

    public function all(): array
    {
        return Cache::remember('trade_settings', 300, function () {
            return TradeSetting::pluck('value', 'key')->toArray();
        });
    }

    public function getFloat(string $key, float $default = 0): float
    {
        return (float) ($this->get($key, (string) $default) ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
