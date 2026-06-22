<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\DepositRequestResource\Pages;
use App\Models\DepositRequest;
use App\Services\DepositRequestService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use InvalidArgumentException;

class DepositRequestResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = DepositRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-tray';

    protected static ?string $navigationLabel = 'Deposits';

    protected static ?string $modelLabel = 'deposit request';

    protected static ?string $pluralModelLabel = 'Deposit requests';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?int $navigationSort = 0;

    public static function getNavigationBadge(): ?string
    {
        $count = app(DepositRequestService::class)->pendingCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_deposits');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_deposits');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Customer')
                ->schema([
                    Forms\Components\Placeholder::make('customer_name')
                        ->label('Name')
                        ->content(fn (?DepositRequest $record) => $record?->user?->name ?? '—'),
                    Forms\Components\Placeholder::make('customer_phone')
                        ->label('Phone')
                        ->content(fn (?DepositRequest $record) => $record?->user?->phone ?? '—'),
                    Forms\Components\Placeholder::make('customer_email')
                        ->label('Email')
                        ->content(fn (?DepositRequest $record) => $record?->user?->email ?? '—'),
                ])
                ->columns(3),
            Forms\Components\Section::make('Deposit details')
                ->schema([
                    Forms\Components\TextInput::make('amount')->label('Amount (INR)')->disabled(),
                    Forms\Components\Select::make('payment_method')
                        ->label('Payment method')
                        ->options([
                            'upi' => 'UPI',
                            'bank_transfer' => 'Bank transfer',
                        ])
                        ->disabled(),
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment reference / UTR')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\Textarea::make('note')
                        ->label('Customer note')
                        ->disabled()
                        ->columnSpanFull(),
                    Forms\Components\ViewField::make('payment_screenshot_path')
                        ->label('Payment screenshot')
                        ->view('filament.components.deposit-payment-screenshot')
                        ->visible(fn (?DepositRequest $record) => filled($record?->payment_screenshot_path))
                        ->columnSpanFull(),
                    Forms\Components\DateTimePicker::make('created_at')
                        ->label('Submitted at')
                        ->disabled(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Admin review')
                ->description('Use Approve or Reject buttons above to update payment status. Approving credits the customer wallet.')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                            'cancelled' => 'Cancelled',
                        ])
                        ->disabled(fn (?DepositRequest $record) => $record?->status !== 'pending'),
                    Forms\Components\Textarea::make('rejection_reason')
                        ->label('Rejection reason')
                        ->disabled(fn (?DepositRequest $record) => $record?->status !== 'pending'),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Customer')
                ->schema([
                    Infolists\Components\TextEntry::make('user.name')->label('Name'),
                    Infolists\Components\TextEntry::make('user.phone')->label('Phone'),
                    Infolists\Components\TextEntry::make('user.email')->label('Email'),
                    Infolists\Components\TextEntry::make('user.status')
                        ->label('Account status')
                        ->badge()
                        ->color(fn (?string $state) => $state === 'active' ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('user.kyc_status')
                        ->label('KYC status')
                        ->badge()
                        ->color(fn (?string $state) => $state === 'approved' ? 'success' : 'warning'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('Approval eligibility')
                ->description('Deposits can only be approved when the customer account is active and KYC is approved.')
                ->schema([
                    Infolists\Components\TextEntry::make('approval_ready')
                        ->label('Ready to approve')
                        ->state(fn (DepositRequest $record) => $record->user?->canApproveDeposit() ? 'Yes' : 'No')
                        ->badge()
                        ->color(fn (DepositRequest $record) => $record->user?->canApproveDeposit() ? 'success' : 'danger'),
                    Infolists\Components\TextEntry::make('approval_blockers')
                        ->label('Action required')
                        ->state(fn (DepositRequest $record) => $record->user?->canApproveDeposit()
                            ? 'None — you may approve after verifying payment.'
                            : implode(' ', $record->user?->depositApprovalBlockers() ?? []))
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (DepositRequest $record) => $record->status === 'pending'),
            Infolists\Components\Section::make('Payment submitted by customer')
                ->description('Verify the user payment against UTR/reference and amount before approving.')
                ->schema([
                    Infolists\Components\TextEntry::make('amount')
                        ->label('Amount')
                        ->money('INR')
                        ->weight('bold')
                        ->size('lg'),
                    Infolists\Components\TextEntry::make('payment_method')
                        ->label('Method')
                        ->formatStateUsing(fn (string $state) => $state === 'bank_transfer' ? 'Bank transfer' : 'UPI')
                        ->badge(),
                    Infolists\Components\TextEntry::make('payment_reference')
                        ->label('Payment reference / UTR')
                        ->placeholder('Not provided')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('note')
                        ->label('Customer note')
                        ->placeholder('—')
                        ->columnSpanFull(),
                    Infolists\Components\ImageEntry::make('payment_screenshot_path')
                        ->label('Payment screenshot')
                        ->disk('public')
                        ->height(320)
                        ->visible(fn (DepositRequest $record) => filled($record->payment_screenshot_path))
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('Submitted at')
                        ->dateTime(),
                ])
                ->columns(2),
            Infolists\Components\Section::make('Status')
                ->schema([
                    Infolists\Components\TextEntry::make('status')
                        ->badge()
                        ->color(fn (string $state) => match ($state) {
                            'pending' => 'warning',
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('Rejection reason')
                        ->placeholder('—')
                        ->visible(fn (DepositRequest $record) => filled($record->rejection_reason)),
                    Infolists\Components\TextEntry::make('processor.name')
                        ->label('Processed by')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('processed_at')
                        ->label('Processed at')
                        ->dateTime()
                        ->placeholder('—'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.phone')->label('Phone')->searchable(),
                Tables\Columns\TextColumn::make('user.status')
                    ->label('Account')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'active' ? 'success' : 'danger')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.kyc_status')
                    ->label('KYC')
                    ->badge()
                    ->color(fn (?string $state) => $state === 'approved' ? 'success' : 'warning')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('amount')->money('INR')->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Method')
                    ->formatStateUsing(fn (string $state) => $state === 'bank_transfer' ? 'Bank' : 'UPI')
                    ->badge(),
                Tables\Columns\TextColumn::make('payment_reference')
                    ->label('UTR / reference')
                    ->placeholder('—')
                    ->copyable()
                    ->searchable()
                    ->limit(24),
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->placeholder('—')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('pending'),
                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Method')
                    ->options([
                        'upi' => 'UPI',
                        'bank_transfer' => 'Bank transfer',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                static::approveTableAction(),
                static::rejectTableAction(),
            ])
            ->bulkActions([]);
    }

    public static function approveTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve deposit')
            ->modalDescription(fn (DepositRequest $record) => $record->user?->canApproveDeposit()
                ? 'This will credit the customer wallet with the deposit amount.'
                : implode(' ', $record->user?->depositApprovalBlockers() ?? ['Customer is not eligible for deposit approval.']))
            ->visible(fn (DepositRequest $record) => $record->status === 'pending' && static::canAdmin('manage_deposits'))
            ->disabled(fn (DepositRequest $record) => ! $record->user?->canApproveDeposit())
            ->action(function (DepositRequest $record) {
                try {
                    app(DepositRequestService::class)->approve($record, auth()->id());
                    Notification::make()->title('Deposit approved & wallet credited')->success()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function rejectTableAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-o-x-mark')
            ->color('danger')
            ->visible(fn (DepositRequest $record) => $record->status === 'pending' && static::canAdmin('manage_deposits'))
            ->form([
                Forms\Components\Textarea::make('reason')
                    ->label('Rejection reason')
                    ->required()
                    ->helperText('Shown in the customer wallet activity history'),
            ])
            ->action(function (DepositRequest $record, array $data) {
                try {
                    app(DepositRequestService::class)->reject($record, $data['reason'], auth()->id());
                    Notification::make()->title('Deposit rejected')->warning()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepositRequests::route('/'),
            'view' => Pages\ViewDepositRequest::route('/{record}'),
            'edit' => Pages\EditDepositRequest::route('/{record}/edit'),
        ];
    }
}
