<?php

namespace App\Filament\Pages;

use App\Services\ChartDataModeService;
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

        $this->form->fill([
            'mobile_chart_data_source' => $mode,
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
                                ? 'Live prices and intraday charts from metals API, Binance, and Yahoo Finance (similar to investing.com). Admin chart overrides are ignored on mobile.'
                                : 'You control chart direction, prices, and per-user overrides from Markets and Users. Recommended for demos and guided trading.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $mode = $state['mobile_chart_data_source'] ?? ChartDataModeService::MODE_REAL;

        app(ChartDataModeService::class)->setMode($mode);

        Notification::make()
            ->title('Mobile chart mode updated')
            ->body($mode === ChartDataModeService::MODE_REAL
                ? 'Mobile users will see live market charts.'
                : 'Mobile users will see admin-controlled charts.')
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
