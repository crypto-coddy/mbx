<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Transaction;
use App\Services\WalletService;
use App\Support\TransactionLabels;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ViewUserWallet extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('view_customers') ?? false;
    }

    protected static string $resource = UserResource::class;

    protected static string $view = 'filament.resources.user-resource.pages.view-user-wallet';

    protected static ?string $navigationIcon = 'heroicon-o-wallet';

    protected static bool $shouldRegisterNavigation = false;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadMissing(['wallet', 'profile']);

        app(WalletService::class)->getOrCreateWallet($this->getRecord());
        $this->record->load('wallet');
    }

    public function getTitle(): string|Htmlable
    {
        return 'Wallet — '.$this->getRecord()->name;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $user = $this->getRecord();
        $parts = array_filter([$user->phone, $user->email]);

        return implode(' · ', $parts) ?: null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recharge')
                ->label('Recharge wallet')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Amount (INR)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('₹'),
                    Forms\Components\Textarea::make('description')
                        ->label('Note for customer history')
                        ->default('Wallet recharge by admin')
                        ->required()
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    app(WalletService::class)->adminRecharge(
                        $this->getRecord(),
                        number_format((float) $data['amount'], 8, '.', ''),
                        $data['description'],
                        auth()->id(),
                    );

                    $this->getRecord()->load('wallet');

                    Notification::make()
                        ->title('Wallet recharged')
                        ->body('₹'.$data['amount'].' added. Customer will see this in the mobile app.')
                        ->success()
                        ->send();
                }),
            Action::make('back')
                ->label('Back to users')
                ->url(UserResource::getUrl('index'))
                ->color('gray'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Transaction::query()->where('user_id', $this->getRecord()->id))
            ->heading('Transaction history')
            ->description('All wallet activity for this user — recharges, withdrawals, trades, and rewards.')
            ->emptyStateHeading('No transactions yet')
            ->emptyStateDescription('Recharge the wallet or wait for signup reward / trades to appear here.')
            ->emptyStateIcon('heroicon-o-document-text')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date & time')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => TransactionLabels::type($state))
                    ->badge()
                    ->color(fn (string $state): string => TransactionLabels::typeColor($state)),
                Tables\Columns\TextColumn::make('direction')
                    ->label('Dr/Cr')
                    ->formatStateUsing(fn (string $state): string => $state === 'credit' ? 'Credit' : 'Debit')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(function ($state, Transaction $record): string {
                        $prefix = $record->direction === 'credit' ? '+' : '-';

                        return $prefix.'₹'.number_format((float) $state, 2);
                    })
                    ->color(fn (Transaction $record): string => $record->direction === 'credit' ? 'success' : 'danger')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('balance_before')
                    ->label('Balance before')
                    ->formatStateUsing(fn ($state) => '₹'.number_format((float) $state, 2))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Balance after')
                    ->formatStateUsing(fn ($state) => '₹'.number_format((float) $state, 2)),
                Tables\Columns\TextColumn::make('meta.status')
                    ->label('Status')
                    ->badge()
                    ->placeholder('—')
                    ->color(fn (?string $state): string => match ($state) {
                        'paid', 'approved' => 'success',
                        'pending', 'processing' => 'warning',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Details')
                    ->wrap()
                    ->searchable()
                    ->limit(60)
                    ->tooltip(fn (Transaction $record): ?string => $record->description),
                Tables\Columns\TextColumn::make('meta.informational')
                    ->label('Note')
                    ->formatStateUsing(fn ($state): string => $state ? 'Ledger note' : '—')
                    ->badge()
                    ->color(fn ($state): string => $state ? 'warning' : 'gray')
                    ->tooltip('Informational entry — balance before/after unchanged'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Transaction type')
                    ->options([
                        'wallet_recharge' => 'Wallet recharge',
                        'signup_reward' => 'Signup reward',
                        'withdrawal_request' => 'Withdrawal request',
                        'withdrawal_status' => 'Withdrawal status',
                        'withdrawal_reversal' => 'Withdrawal reversal',
                        'withdrawal' => 'Withdrawal paid',
                        'trade_profit' => 'Trade profit',
                        'trade_loss' => 'Trade / buy',
                        'referral_commission' => 'Referral commission',
                        'admin_credit' => 'Admin credit',
                        'admin_debit' => 'Admin debit',
                    ]),
                Tables\Filters\SelectFilter::make('direction')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalHeading(fn (Transaction $record): string => 'Transaction #'.$record->id)
                    ->infolist(fn (Infolist $infolist): Infolist => $infolist->schema([
                        Infolists\Components\Section::make('Transaction')
                            ->schema([
                                Infolists\Components\TextEntry::make('id')->label('Transaction ID'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Date & time')
                                    ->dateTime('d M Y, H:i:s'),
                                Infolists\Components\TextEntry::make('type')
                                    ->label('Type')
                                    ->formatStateUsing(fn (string $state): string => TransactionLabels::type($state)),
                                Infolists\Components\TextEntry::make('direction')
                                    ->badge()
                                    ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger'),
                                Infolists\Components\TextEntry::make('amount')
                                    ->label('Amount')
                                    ->formatStateUsing(fn ($state, Transaction $record): string => ($record->direction === 'credit' ? '+' : '-').'₹'.number_format((float) $state, 2)),
                            ])
                            ->columns(2),
                        Infolists\Components\Section::make('Balances')
                            ->schema([
                                Infolists\Components\TextEntry::make('balance_before')
                                    ->label('Balance before')
                                    ->formatStateUsing(fn ($state) => '₹'.number_format((float) $state, 2)),
                                Infolists\Components\TextEntry::make('balance_after')
                                    ->label('Balance after')
                                    ->formatStateUsing(fn ($state) => '₹'.number_format((float) $state, 2)),
                                Infolists\Components\TextEntry::make('meta.informational')
                                    ->label('Informational only')
                                    ->formatStateUsing(fn ($state) => $state ? 'Yes (no balance change)' : 'No'),
                            ])
                            ->columns(3),
                        Infolists\Components\Section::make('Description & reference')
                            ->schema([
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Description')
                                    ->placeholder('—')
                                    ->columnSpanFull(),
                                Infolists\Components\TextEntry::make('meta.status')
                                    ->label('Withdrawal / status')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('meta.withdrawal_id')
                                    ->label('Withdrawal request ID')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('meta.admin_id')
                                    ->label('Admin user ID')
                                    ->placeholder('—'),
                                Infolists\Components\TextEntry::make('referenceable_type')
                                    ->label('Linked record')
                                    ->formatStateUsing(fn (?string $state, Transaction $record): string => $state
                                        ? class_basename($state).' #'.$record->referenceable_id
                                        : '—'),
                            ])
                            ->columns(2),
                    ]))
                    ->record(fn (Transaction $record) => $record),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }
}
