<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\TradeResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\Trade;
use App\Services\TradeService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

class TradeResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = Trade::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Trading';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_trades');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_trades');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->relationship('user', 'name')->disabled(),
            Forms\Components\Select::make('asset_id')->relationship('asset', 'symbol')->disabled(),
            Forms\Components\Select::make('type')->options(['buy' => 'Buy', 'sell' => 'Sell'])->disabled(),
            Forms\Components\TextInput::make('amount')
                ->label('Amount (INR)')
                ->prefix('₹')
                ->disabled(),
            Forms\Components\TextInput::make('price_at_trade')->label('Entry price')->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'open' => 'Open',
                    'pending_settlement' => 'Pending settlement',
                    'closed' => 'Closed',
                    'cancelled' => 'Cancelled',
                ])
                ->disabled(),
            Forms\Components\TextInput::make('profit_loss')
                ->label('Profit / loss (INR)')
                ->prefix('₹')
                ->helperText('Positive = profit credited to wallet. Negative = loss (e.g. -25.50).')
                ->visible(fn (?Trade $record) => $record?->status === 'pending_settlement'),
            Forms\Components\Textarea::make('admin_settlement_note')
                ->label('Settlement note')
                ->rows(2)
                ->visible(fn (?Trade $record) => $record?->status === 'pending_settlement'),
            Forms\Components\TextInput::make('closing_price')->disabled(),
            Forms\Components\TextInput::make('profit_loss_percent')->disabled(),
            Forms\Components\DateTimePicker::make('settlement_requested_at')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('settlement_requested_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('asset.symbol'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('profit_loss')
                    ->label('P/L')
                    ->money('INR')
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending_settlement',
                        'success' => 'closed',
                        'primary' => 'open',
                    ]),
                Tables\Columns\TextColumn::make('settlement_requested_at')
                    ->label('Sell requested')
                    ->dateTime()
                    ->placeholder('—'),
                ...AuditTableColumns::make(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'pending_settlement' => 'Pending settlement',
                    'closed' => 'Closed',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('settle')
                    ->label('Settle P/L')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Trade $record) => $record->status === 'pending_settlement')
                    ->form([
                        Forms\Components\TextInput::make('profit_loss')
                            ->label('Profit / loss (INR)')
                            ->prefix('₹')
                            ->required()
                            ->helperText('Use negative values for loss, e.g. -50'),
                        Forms\Components\Textarea::make('admin_settlement_note')->label('Note')->rows(2),
                    ])
                    ->action(function (Trade $record, array $data) {
                        try {
                            app(TradeService::class)->settleByAdmin(
                                $record,
                                (string) $data['profit_loss'],
                                auth()->user(),
                                $data['admin_settlement_note'] ?? null,
                            );
                            Notification::make()->title('Trade settled')->success()->send();
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrades::route('/'),
            'edit' => Pages\EditTrade::route('/{record}/edit'),
        ];
    }
}
