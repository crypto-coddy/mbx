<?php

namespace App\Filament\Pages;

use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class MobileChartSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = 'Mobile charts';

    protected static ?string $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.mobile-chart-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->can('manage_markets') ?? false;
    }

    public function mount(): void
    {
        $mode = app(ChartDataModeService::class)->mode();
        $version = app(ChartDataVersionService::class)->version();

        $this->form->fill([
            'mobile_chart_data_source' => $mode,
            'mobile_chart_data_version' => $version,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Trade charts on mobile')
                    ->description('Platform-wide default for new users and users without a per-user override. Set per-user sources on Customers → Users → edit user → Mobile chart data source.')
                    ->schema([
                        ToggleButtons::make('mobile_chart_data_source')
                            ->label('Chart data source')
                            ->options([
                                ChartDataModeService::MODE_REAL => 'Real market data',
                                ChartDataModeService::MODE_CUSTOM => 'Custom (admin controlled)',
                            ])
                            ->icons([
                                ChartDataModeService::MODE_REAL => 'heroicon-o-globe-alt',
                                ChartDataModeService::MODE_CUSTOM => 'heroicon-o-adjustments-horizontal',
                            ])
                            ->colors([
                                ChartDataModeService::MODE_REAL => 'success',
                                ChartDataModeService::MODE_CUSTOM => 'warning',
                            ])
                            ->inline()
                            ->required()
                            ->live()
                            ->helperText(fn ($state) => $state === ChartDataModeService::MODE_REAL
                                ? 'Live prices and intraday charts from external feeds. Choose v1 or v2 below when Real is selected.'
                                : 'You control chart direction, prices, and per-user overrides from Markets (v1) and Users. Recommended for demos and guided trading.'),
                    ]),
                Section::make('Real market feed version (v1 / v2)')
                    ->description('Applies when Chart data source is Real. v1 = admin Markets (Yahoo/Binance/metals). v2 = admin Markets Live (Twelve Data OHLC candles).')
                    ->schema([
                        ToggleButtons::make('mobile_chart_data_version')
                            ->label('Default real feed version')
                            ->options([
                                ChartDataVersionService::VERSION_V1 => 'v1 — Markets',
                                ChartDataVersionService::VERSION_V2 => 'v2 — Markets Live',
                            ])
                            ->icons([
                                ChartDataVersionService::VERSION_V1 => 'heroicon-o-chart-bar',
                                ChartDataVersionService::VERSION_V2 => 'heroicon-o-signal',
                            ])
                            ->colors([
                                ChartDataVersionService::VERSION_V1 => 'info',
                                ChartDataVersionService::VERSION_V2 => 'success',
                            ])
                            ->inline()
                            ->required()
                            ->visible(fn ($get) => ($get('mobile_chart_data_source') ?? ChartDataModeService::MODE_REAL) === ChartDataModeService::MODE_REAL)
                            ->helperText(fn ($state) => $state === ChartDataVersionService::VERSION_V2
                                ? 'Mobile uses Twelve Data OHLC candles (same as Markets Live admin page).'
                                : 'Mobile uses legacy real feeds from Markets (v1).'),
                    ])
                    ->visible(fn ($get) => ($get('mobile_chart_data_source') ?? ChartDataModeService::MODE_REAL) === ChartDataModeService::MODE_REAL),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $mode = $state['mobile_chart_data_source'] ?? ChartDataModeService::MODE_REAL;
        $version = $state['mobile_chart_data_version'] ?? ChartDataVersionService::VERSION_V1;

        app(ChartDataModeService::class)->setMode($mode);
        app(ChartDataVersionService::class)->setVersion($version);

        Notification::make()
            ->title('Mobile chart mode updated')
            ->body($mode === ChartDataModeService::MODE_REAL
                ? 'Mobile users on Real mode will use '.app(ChartDataVersionService::class)->label($version).'.'
                : 'Mobile users will see admin-controlled charts (Markets v1).')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Save')
                ->submit('save'),
        ];
    }
}
