<?php

namespace App\Services;

class ChartDataModeService
{
    public const SETTING_KEY = 'mobile_chart_data_source';

    public const MODE_REAL = 'real';

    public const MODE_CUSTOM = 'custom';

    /** Form value — stored as null on profile (use platform default). */
    public const MODE_PLATFORM_DEFAULT = 'default';

    public function __construct(protected TradeSettingService $settings) {}

    public function mode(): string
    {
        $value = $this->settings->get(self::SETTING_KEY, self::MODE_REAL);

        return $value === self::MODE_REAL ? self::MODE_REAL : self::MODE_CUSTOM;
    }

    public function modeForProfile(?\App\Models\UserProfile $profile): string
    {
        $override = $profile?->mobile_chart_data_source;

        if ($override === self::MODE_REAL) {
            return self::MODE_REAL;
        }

        if ($override === self::MODE_CUSTOM) {
            return self::MODE_CUSTOM;
        }

        return $this->mode();
    }

    public function isReal(): bool
    {
        return $this->mode() === self::MODE_REAL;
    }

    public function isRealForProfile(?\App\Models\UserProfile $profile): bool
    {
        return $this->modeForProfile($profile) === self::MODE_REAL;
    }

    public function isCustom(): bool
    {
        return ! $this->isReal();
    }

    public function isCustomForProfile(?\App\Models\UserProfile $profile): bool
    {
        return ! $this->isRealForProfile($profile);
    }

    public function setMode(string $mode): void
    {
        $this->settings->set(
            self::SETTING_KEY,
            $mode === self::MODE_REAL ? self::MODE_REAL : self::MODE_CUSTOM,
            'Mobile trade charts: real = live market feeds; custom = admin-controlled trends and prices',
        );
    }
}
