<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Models\Asset;
use App\Models\UserProfileAssetChart;
use App\Services\ChartDataModeService;
use App\Services\MarketChartService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MarketChartsRelationManager extends RelationManager
{
    protected static string $relationship = 'profileMarketCharts';

    protected static ?string $title = 'Market charts';

    protected static ?string $icon = 'heroicon-o-chart-bar';

    public function table(Table $table): Table
    {
        return $table
            ->description(function (): string {
                $profile = $this->getOwnerRecord()->profile;
                if (app(ChartDataModeService::class)->isRealForProfile($profile)) {
                    return 'This user is on Real market data — UP/DOWN controls apply only after you set Chart data source to Custom above.';
                }

                return 'Control UP/DOWN chart direction for this user on the mobile Markets screen. Each market can be set independently.';
            })
            ->columns([
                Tables\Columns\TextColumn::make('asset.symbol')
                    ->label('Market')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('asset.display_name')
                    ->label('Name'),
                Tables\Columns\BadgeColumn::make('chart_trend')
                    ->label('Chart')
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->colors(['success' => 'up', 'danger' => 'down']),
                Tables\Columns\TextColumn::make('set_at')
                    ->label('Last changed')
                    ->dateTime()
                    ->placeholder('Default'),
            ])
            ->defaultSort('asset_id')
            ->headerActions([])
            ->actions([
                Tables\Actions\Action::make('chartUp')
                    ->label('Chart UP')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Show chart going UP for this user?')
                    ->modalDescription('This user will see an upward line and a buy signal for this market.')
                    ->action(function (UserProfileAssetChart $record) {
                        $record->load(['profile', 'asset']);
                        app(MarketChartService::class)->setTrendForProfile(
                            $record->profile,
                            $record->asset,
                            'up',
                            auth()->id()
                        );
                        Notification::make()->title('Chart set to UP for user')->success()->send();
                    }),
                Tables\Actions\Action::make('chartDown')
                    ->label('Chart DOWN')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Show chart going DOWN for this user?')
                    ->modalDescription('This user will see a downward line — discourage buying for this market.')
                    ->action(function (UserProfileAssetChart $record) {
                        $record->load(['profile', 'asset']);
                        app(MarketChartService::class)->setTrendForProfile(
                            $record->profile,
                            $record->asset,
                            'down',
                            auth()->id()
                        );
                        Notification::make()->title('Chart set to DOWN for user')->success()->send();
                    }),
            ])
            ->bulkActions([]);
    }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $owner = $this->getOwnerRecord();
        $profile = $owner->profile()->firstOrCreate(
            ['user_id' => $owner->id],
            ['country' => 'India']
        );

        app(MarketChartService::class)->ensureProfileChartRows($profile);

        return parent::getTableQuery()?->with(['asset', 'profile']);
    }
}
