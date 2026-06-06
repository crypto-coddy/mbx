<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\AssetResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\Asset;
use App\Services\MarketCatalogService;
use App\Services\MarketChartService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class AssetResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = Asset::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Markets';

    protected static ?string $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_markets');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_markets');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_markets');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Asset')
                ->schema([
                    Forms\Components\TextInput::make('name')->required(),
                    Forms\Components\TextInput::make('symbol')->required(),
                    Forms\Components\Select::make('category')
                        ->options(MarketCatalogService::CATEGORIES)
                        ->default('commodities')
                        ->required(),
                    Forms\Components\TextInput::make('display_name')->required(),
                    Forms\Components\TextInput::make('icon_url')->label('Icon URL')->url(),
                    Forms\Components\TextInput::make('live_price')->numeric()->label('Live price'),
                    Forms\Components\Toggle::make('is_active')->default(true),
                    Forms\Components\Toggle::make('trading_enabled')->default(true),
                    Forms\Components\TextInput::make('min_trade_amount')->numeric(),
                    Forms\Components\TextInput::make('max_trade_amount')->numeric(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Chart direction (global default)')
                ->description('Default for all users without a per-user override. To control charts for one user, open Users → edit user → Market charts tab.')
                ->schema([
                    Forms\Components\Select::make('chart_trend')
                        ->label('Chart trend')
                        ->options([
                            'up' => 'UP — encourage buying',
                            'down' => 'DOWN — discourage buying',
                        ])
                        ->required()
                        ->default('up')
                        ->live(),
                    Forms\Components\Placeholder::make('signal_preview')
                        ->label('User sees')
                        ->content(fn (?Asset $record) => ($record?->chart_trend ?? 'up') === 'up'
                            ? '🟢 Market moving up — good time to buy'
                            : '🔴 Market moving down — avoid buying now'),
                    Forms\Components\TextInput::make('price_change_24h')
                        ->numeric()
                        ->label('24h change % (display)'),
                    Forms\Components\TextInput::make('admin_price')->numeric()->label('Override price'),
                    Forms\Components\Toggle::make('admin_override_active')->label('Use override price'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('symbol')->weight('bold'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\BadgeColumn::make('category')
                    ->colors([
                        'warning' => 'commodities',
                        'info' => 'crypto',
                        'success' => 'forex',
                        'gray' => 'indices',
                    ]),
                Tables\Columns\TextColumn::make('live_price')->money('USD'),
                Tables\Columns\BadgeColumn::make('chart_trend')
                    ->label('Chart')
                    ->formatStateUsing(fn (string $state) => strtoupper($state))
                    ->colors(['success' => 'up', 'danger' => 'down']),
                Tables\Columns\TextColumn::make('price_change_24h')
                    ->label('24h %')
                    ->formatStateUsing(fn ($state) => ($state >= 0 ? '+' : '').number_format((float) $state, 2).'%')
                    ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\IconColumn::make('trading_enabled')->boolean(),
                ...AuditTableColumns::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('chartUp')
                    ->label('Chart UP')
                    ->icon('heroicon-o-arrow-trending-up')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Show chart going UP?')
                    ->modalDescription('Users will see an upward chart and a buy signal.')
                    ->action(function (Asset $record) {
                        app(MarketChartService::class)->setTrend($record, 'up', auth()->id());
                        Notification::make()->title('Chart set to UP')->success()->send();
                    }),
                Tables\Actions\Action::make('chartDown')
                    ->label('Chart DOWN')
                    ->icon('heroicon-o-arrow-trending-down')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Show chart going DOWN?')
                    ->modalDescription('Users will see a downward chart — don\'t buy now.')
                    ->action(function (Asset $record) {
                        app(MarketChartService::class)->setTrend($record, 'down', auth()->id());
                        Notification::make()->title('Chart set to DOWN')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssets::route('/'),
            'create' => Pages\CreateAsset::route('/create'),
            'edit' => Pages\EditAsset::route('/{record}/edit'),
        ];
    }
}
