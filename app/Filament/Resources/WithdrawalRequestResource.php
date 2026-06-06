<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\WithdrawalRequestResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\WithdrawalRequest;
use App\Services\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

class WithdrawalRequestResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = WithdrawalRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Withdrawals';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_withdrawals');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_withdrawals');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('user_id')->relationship('user', 'name')->disabled(),
            Forms\Components\TextInput::make('amount')->numeric()->disabled(),
            Forms\Components\KeyValue::make('bank_details')->disabled(),
            Forms\Components\Select::make('status')
                ->options([
                    'pending' => 'Pending',
                    'processing' => 'Processing',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                    'paid' => 'Paid',
                ]),
            Forms\Components\Textarea::make('rejection_reason'),
            Forms\Components\TextInput::make('transaction_reference')
                ->label('Payment reference (UPI / bank txn ID)'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->searchable(),
                Tables\Columns\TextColumn::make('user.phone'),
                Tables\Columns\TextColumn::make('amount')->money('INR'),
                Tables\Columns\BadgeColumn::make('status')->colors([
                    'warning' => 'pending',
                    'info' => 'processing',
                    'primary' => 'approved',
                    'danger' => 'rejected',
                    'success' => 'paid',
                ]),
                ...AuditTableColumns::make(false),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\Action::make('approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (WithdrawalRequest $record) => $record->status === 'pending')
                    ->action(function (WithdrawalRequest $record) {
                        $record->update([
                            'status' => 'approved',
                            'processed_by' => auth()->id(),
                            'processed_at' => now(),
                        ]);
                        app(WalletService::class)->recordWithdrawalEvent(
                            $record->user,
                            $record->fresh(),
                            'approved',
                            "Withdrawal request ₹{$record->amount} approved — payout pending",
                        );
                        Notification::make()->title('Withdrawal approved')->success()->send();
                    }),
                Tables\Actions\Action::make('reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (WithdrawalRequest $record) => in_array($record->status, ['pending', 'processing', 'approved'], true))
                    ->form([
                        Forms\Components\Textarea::make('reason')->required(),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data) {
                        try {
                            app(WalletService::class)->unlock($record->user, (string) $record->amount);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        $record->update([
                            'status' => 'rejected',
                            'rejection_reason' => $data['reason'],
                            'processed_by' => auth()->id(),
                            'processed_at' => now(),
                        ]);
                        app(WalletService::class)->recordWithdrawalEvent(
                            $record->user,
                            $record->fresh(),
                            'rejected',
                            "Withdrawal request ₹{$record->amount} rejected — {$data['reason']}",
                            'withdrawal_reversal',
                        );
                        Notification::make()->title('Withdrawal rejected')->success()->send();
                    }),
                Tables\Actions\Action::make('markPaid')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn (WithdrawalRequest $record) => in_array($record->status, ['approved', 'processing'], true))
                    ->form([
                        Forms\Components\TextInput::make('transaction_reference')->required(),
                        Forms\Components\Select::make('payment_method')
                            ->options(['upi' => 'UPI', 'bank_transfer' => 'Bank transfer', 'other' => 'Other'])
                            ->default('upi'),
                    ])
                    ->action(function (WithdrawalRequest $record, array $data) {
                        $walletService = app(WalletService::class);
                        try {
                            $walletService->completeWithdrawal($record->user, (string) $record->amount);
                        } catch (InvalidArgumentException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        $record->update([
                            'status' => 'paid',
                            'transaction_reference' => $data['transaction_reference'],
                            'paid_at' => now(),
                            'processed_by' => auth()->id(),
                            'processed_at' => now(),
                        ]);
                        $walletService->recordWithdrawalEvent(
                            $record->user,
                            $record->fresh(),
                            'paid',
                            "Withdrawal ₹{$record->amount} paid — ref {$data['transaction_reference']}",
                        );
                        Notification::make()->title('Marked as paid')->success()->send();
                    }),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWithdrawalRequests::route('/'),
            'edit' => Pages\EditWithdrawalRequest::route('/{record}/edit'),
        ];
    }
}
