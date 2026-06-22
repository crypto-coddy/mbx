<?php

namespace App\Services;

use App\Models\UserProfile;

class ChartDataVersionService
{
    public const SETTING_KEY = 'mobile_chart_data_version';

    /** Admin Markets — Yahoo, Binance, metals API (legacy real feed). */
    public const VERSION_V1 = 'v1';

    /** Admin Markets Live — Twelve Data OHLC candles. */
    public const VERSION_V2 = 'v2';

    public const VERSION_PLATFORM_DEFAULT = 'default';

    public function __construct(protected TradeSettingService $settings) {}

    public function version(): string
    {
        $value = $this->settings->get(self::SETTING_KEY, self::VERSION_V1);

        return $value === self::VERSION_V2 ? self::VERSION_V2 : self::VERSION_V1;
    }

    public function versionForProfile(?UserProfile $profile): string
    {
        $override = $profile?->mobile_chart_data_version;

        if ($override === self::VERSION_V2) {
            return self::VERSION_V2;
        }

        if ($override === self::VERSION_V1) {
            return self::VERSION_V1;
        }

        return $this->version();
    }

    public function isV2ForProfile(?UserProfile $profile): bool
    {
        return $this->versionForProfile($profile) === self::VERSION_V2;
    }

    /** Whether mobile chart config comes from this profile or platform defaults. */
    public function configScopeForProfile(?UserProfile $profile): string
    {
        if (! $profile) {
            return 'platform';
        }

        if (filled($profile->mobile_chart_data_source) || filled($profile->mobile_chart_data_version)) {
            return 'user';
        }

        return 'platform';
    }

    /**
     * @return array{mode: string, version: string, scope: string, source_override: ?string, version_override: ?string}
     */
    public function mobileMetaForProfile(?UserProfile $profile): array
    {
        $modeService = app(ChartDataModeService::class);

        return [
            'mode' => $modeService->modeForProfile($profile),
            'version' => $this->versionForProfile($profile),
            'scope' => $this->configScopeForProfile($profile),
            'source_override' => $profile?->mobile_chart_data_source,
            'version_override' => $profile?->mobile_chart_data_version,
        ];
    }

    public function setVersion(string $version): void
    {
        $this->settings->set(
            self::SETTING_KEY,
            $version === self::VERSION_V2 ? self::VERSION_V2 : self::VERSION_V1,
            'Mobile real-market chart feed: v1 = Markets (Yahoo/Binance/metals); v2 = Markets Live (Twelve Data OHLC)',
        );
    }

    public function label(string $version): string
    {
        return match ($version) {
            self::VERSION_V2 => 'v2 — Markets Live (Twelve Data)',
            self::VERSION_V1 => 'v1 — Markets (Yahoo/Binance/metals)',
            default => 'Platform default',
        };
    }

    public function effectiveDescriptionForProfile(?UserProfile $profile): string
    {
        $mode = app(ChartDataModeService::class)->modeForProfile($profile);

        if ($mode === ChartDataModeService::MODE_CUSTOM) {
            return 'Custom (admin controlled) — Markets v1';
        }

        return 'Real market data — '.$this->label($this->versionForProfile($profile));
    }

    public function effectiveDescriptionForFormState(
        ?UserProfile $profile,
        ?string $sourceOverride,
        ?string $versionOverride,
    ): string {
        $modeService = app(ChartDataModeService::class);
        $source = $sourceOverride ?? $profile?->mobile_chart_data_source;

        $mode = match ($source) {
            ChartDataModeService::MODE_REAL => ChartDataModeService::MODE_REAL,
            ChartDataModeService::MODE_CUSTOM => ChartDataModeService::MODE_CUSTOM,
            default => $modeService->mode(),
        };

        if ($mode === ChartDataModeService::MODE_CUSTOM) {
            return 'Custom (admin controlled) — Markets v1';
        }

        $version = $versionOverride ?? $profile?->mobile_chart_data_version;

        if ($version === self::VERSION_V2) {
            return 'Real market data — '.$this->label(self::VERSION_V2);
        }

        if ($version === self::VERSION_V1) {
            return 'Real market data — '.$this->label(self::VERSION_V1);
        }

        return 'Real market data — '.$this->label($this->version());
    }
}
