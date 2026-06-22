<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers\MarketChartsRelationManager;
use App\Filament\Support\AuditTableColumns;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\ChartDataModeService;
use App\Services\ChartDataVersionService;
use App\Services\WalletService;
use Filament\Notifications\Notification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 1;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_customers');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_customers');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_customers');
    }

    public static function canView($record): bool
    {
        return static::canAdmin('view_customers');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->schema([
                    Forms\Components\TextInput::make('public_user_id')
                        ->label('User ID')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->maxLength(255)
                        ->nullable()
                        ->unique(table: User::class, column: 'email', ignoreRecord: true)
                        ->helperText('Optional. Must be unique — do not reuse the super admin email.'),
                    Forms\Components\TextInput::make('password')
                        ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New password')
                        ->password()
                        ->revealable()
                        ->live(onBlur: true)
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->helperText(fn (string $operation): ?string => $operation === 'edit'
                            ? 'Leave blank to keep the current password.'
                            : null)
                        ->rules(fn (string $operation, ?string $state): array => match (true) {
                            $operation === 'create' => ['required', 'confirmed', Password::defaults()],
                            filled($state) => ['confirmed', Password::defaults()],
                            default => [],
                        }),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->label(fn (string $operation): string => $operation === 'create' ? 'Confirm password' : 'Confirm new password')
                        ->password()
                        ->revealable()
                        ->dehydrated(false)
                        ->required(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || filled($get('password')))
                        ->visible(fn (string $operation, Forms\Get $get): bool => $operation === 'create' || filled($get('password'))),
                    Forms\Components\Select::make('referred_by')
                        ->label('Referrer (optional)')
                        ->relationship(
                            name: 'referrer',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn (Builder $query) => $query
                                ->whereDoesntHave('roles', fn (Builder $q) => $q->whereIn('name', ['admin', 'super_admin'])),
                        )
                        ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->name} ({$record->phone})")
                        ->searchable(['name', 'phone', 'email'])
                        ->preload()
                        ->visibleOn('create'),
                    Forms\Components\TextInput::make('referral_code')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                ])
                ->columns(2),
            Forms\Components\Section::make('Location')
                ->description('Collected at mobile registration.')
                ->schema([
                    Forms\Components\Group::make()
                        ->relationship('profile')
                        ->schema([
                            Forms\Components\TextInput::make('country')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('state')
                                ->label('State / region')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('city')
                                ->maxLength(100),
                            Forms\Components\TextInput::make('phone_country_code')
                                ->label('Phone country code')
                                ->prefix('+')
                                ->maxLength(5)
                                ->helperText('Dial code without + (e.g. 91 for India).'),
                        ]),
                ])
                ->columns(2)
                ->visibleOn('edit'),
            Forms\Components\Section::make('Status')
                ->schema([
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                            'banned' => 'Banned',
                        ])
                        ->default('active')
                        ->required(),
                    Forms\Components\Select::make('kyc_status')
                        ->options([
                            'not_submitted' => 'Not submitted',
                            'pending' => 'Pending',
                            'approved' => 'Approved',
                            'rejected' => 'Rejected',
                        ])
                        ->default('not_submitted')
                        ->required(),
                ])
                ->columns(2),
            Forms\Components\Section::make('Payout details')
                ->description('Saved by the customer in the mobile app (Profile tab). Used for withdrawal requests.')
                ->schema(static::payoutDetailsSchema())
                ->visibleOn('edit')
                ->columns(2)
                ->collapsible(),
            Forms\Components\Section::make('Mobile chart data source')
                ->description('Choose what this user sees on the mobile Markets screen. Overrides the platform default from Trading → Mobile charts.')
                ->schema([
                    Forms\Components\Group::make()
                        ->relationship('profile')
                        ->schema(static::mobileChartDataSourceSchema()),
                ])
                ->visibleOn('edit'),
        ]);
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function mobileChartDataSourceSchema(): array
    {
        return [
            Forms\Components\ToggleButtons::make('mobile_chart_data_source')
                ->label('Chart data source')
                ->options([
                    ChartDataModeService::MODE_PLATFORM_DEFAULT => 'Platform default',
                    ChartDataModeService::MODE_REAL => 'Real market data',
                    ChartDataModeService::MODE_CUSTOM => 'Custom (admin controlled)',
                ])
                ->icons([
                    ChartDataModeService::MODE_PLATFORM_DEFAULT => 'heroicon-o-adjustments-horizontal',
                    ChartDataModeService::MODE_REAL => 'heroicon-o-globe-alt',
                    ChartDataModeService::MODE_CUSTOM => 'heroicon-o-pencil-square',
                ])
                ->colors([
                    ChartDataModeService::MODE_PLATFORM_DEFAULT => 'gray',
                    ChartDataModeService::MODE_REAL => 'success',
                    ChartDataModeService::MODE_CUSTOM => 'warning',
                ])
                ->inline()
                ->default(ChartDataModeService::MODE_PLATFORM_DEFAULT)
                ->formatStateUsing(fn (?string $state) => filled($state) ? $state : ChartDataModeService::MODE_PLATFORM_DEFAULT)
                ->dehydrateStateUsing(fn (?string $state) => $state === ChartDataModeService::MODE_PLATFORM_DEFAULT ? null : $state)
                ->live()
                ->helperText(function (?string $state) {
                    $global = app(ChartDataModeService::class)->mode();
                    $globalLabel = $global === ChartDataModeService::MODE_REAL ? 'Real market data' : 'Custom (admin controlled)';

                    return match ($state) {
                        ChartDataModeService::MODE_REAL => 'This user always sees live prices and charts from external feeds. Choose v1 or v2 below. Per-market UP/DOWN are ignored in Real mode.',
                        ChartDataModeService::MODE_CUSTOM => 'This user always sees admin-controlled charts from Markets (v1). Use the Market charts section below to set UP/DOWN per asset.',
                        default => "Uses the platform default (currently: {$globalLabel}). Set Trading → Mobile charts to change the default for all users without an override.",
                    };
                }),
            Forms\Components\ToggleButtons::make('mobile_chart_data_version')
                ->label('Real market feed version')
                ->options([
                    ChartDataVersionService::VERSION_PLATFORM_DEFAULT => 'Platform default',
                    ChartDataVersionService::VERSION_V1 => 'v1 — Markets',
                    ChartDataVersionService::VERSION_V2 => 'v2 — Markets Live',
                ])
                ->icons([
                    ChartDataVersionService::VERSION_PLATFORM_DEFAULT => 'heroicon-o-adjustments-horizontal',
                    ChartDataVersionService::VERSION_V1 => 'heroicon-o-chart-bar',
                    ChartDataVersionService::VERSION_V2 => 'heroicon-o-signal',
                ])
                ->colors([
                    ChartDataVersionService::VERSION_PLATFORM_DEFAULT => 'gray',
                    ChartDataVersionService::VERSION_V1 => 'info',
                    ChartDataVersionService::VERSION_V2 => 'success',
                ])
                ->inline()
                ->default(ChartDataVersionService::VERSION_PLATFORM_DEFAULT)
                ->formatStateUsing(fn (?string $state) => filled($state) ? $state : ChartDataVersionService::VERSION_PLATFORM_DEFAULT)
                ->dehydrateStateUsing(fn (?string $state) => $state === ChartDataVersionService::VERSION_PLATFORM_DEFAULT ? null : $state)
                ->live()
                ->visible(function (Forms\Get $get): bool {
                    $source = $get('mobile_chart_data_source') ?? ChartDataModeService::MODE_PLATFORM_DEFAULT;

                    if ($source === ChartDataModeService::MODE_CUSTOM) {
                        return false;
                    }

                    if ($source === ChartDataModeService::MODE_REAL) {
                        return true;
                    }

                    return app(ChartDataModeService::class)->mode() === ChartDataModeService::MODE_REAL;
                })
                ->helperText(function (Forms\Get $get): string {
                    $state = $get('mobile_chart_data_version') ?? ChartDataVersionService::VERSION_PLATFORM_DEFAULT;
                    $global = app(ChartDataVersionService::class)->version();

                    return match ($state) {
                        ChartDataVersionService::VERSION_V2 => 'Twelve Data OHLC candles on mobile (Markets Live).',
                        ChartDataVersionService::VERSION_V1 => 'Legacy real feed: Yahoo, Binance, metals (Markets v1).',
                        default => 'Uses platform default (currently: '.app(ChartDataVersionService::class)->label($global).').',
                    };
                }),
            Forms\Components\Placeholder::make('effective_chart_config')
                ->label('Effective for this user')
                ->content(function (?UserProfile $record, Forms\Get $get): string {
                    $source = $get('mobile_chart_data_source') ?? ChartDataModeService::MODE_PLATFORM_DEFAULT;
                    $version = $get('mobile_chart_data_version') ?? ChartDataVersionService::VERSION_PLATFORM_DEFAULT;

                    return app(ChartDataVersionService::class)->effectiveDescriptionForFormState(
                        $record,
                        $source === ChartDataModeService::MODE_PLATFORM_DEFAULT ? null : $source,
                        $version === ChartDataVersionService::VERSION_PLATFORM_DEFAULT ? null : $version,
                    );
                })
                ->helperText('What this user actually sees on mobile after overrides above are applied.'),
            Forms\Components\Placeholder::make('platform_chart_default')
                ->label('Platform default (all users without overrides)')
                ->content(function (): string {
                    $mode = app(ChartDataModeService::class)->mode();
                    $version = app(ChartDataVersionService::class)->version();

                    if ($mode === ChartDataModeService::MODE_CUSTOM) {
                        return 'Custom (admin controlled) — Markets v1';
                    }

                    return 'Real market data — '.app(ChartDataVersionService::class)->label($version);
                }),
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function payoutDetailsSchema(): array
    {
        return [
            Forms\Components\Placeholder::make('payout_upi')
                ->label('UPI ID')
                ->content(fn (?User $record): string => filled($record?->profile?->upi_id)
                    ? $record->profile->upi_id
                    : 'Not set'),
            Forms\Components\Placeholder::make('payout_bank_name')
                ->label('Bank name')
                ->content(fn (?User $record): string => $record?->profile?->bank_name ?: '—'),
            Forms\Components\Placeholder::make('payout_account_holder')
                ->label('Account holder')
                ->content(fn (?User $record): string => $record?->profile?->account_holder_name ?: '—'),
            Forms\Components\Placeholder::make('payout_account_number')
                ->label('Account number')
                ->content(fn (?User $record): string => filled($record?->profile?->account_number)
                    ? $record->profile->account_number
                    : '—'),
            Forms\Components\Placeholder::make('payout_ifsc')
                ->label('IFSC code')
                ->content(fn (?User $record): string => $record?->profile?->ifsc_code ?: '—'),
            Forms\Components\Placeholder::make('payout_account_type')
                ->label('Account type')
                ->content(fn (?User $record): string => $record?->profile?->account_type
                    ? ucfirst($record->profile->account_type)
                    : '—'),
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Account')
                ->schema([
                    Infolists\Components\TextEntry::make('public_user_id')->label('User ID')->copyable(),
                    Infolists\Components\TextEntry::make('name'),
                    Infolists\Components\TextEntry::make('phone'),
                    Infolists\Components\TextEntry::make('email')->placeholder('—'),
                    Infolists\Components\TextEntry::make('referral_code')->label('Referral code')->copyable(),
                    Infolists\Components\TextEntry::make('referrer.name')->label('Referred by')->placeholder('—'),
                    Infolists\Components\IconEntry::make('phone_verified')->label('Phone verified')->boolean(),
                    Infolists\Components\IconEntry::make('email_verified_flag')->label('Email verified')->boolean(),
                ])
                ->columns(3),
            Infolists\Components\Section::make('Location')
                ->schema([
                    Infolists\Components\TextEntry::make('profile.country')->label('Country')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.state')->label('State / region')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.city')->label('City')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.phone_country_code')
                        ->label('Phone country code')
                        ->formatStateUsing(fn (?string $state) => filled($state) ? "+{$state}" : '—'),
                ])
                ->columns(4),
            Infolists\Components\Section::make('Status')
                ->schema([
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('kyc_status')->label('KYC')->badge(),
                    Infolists\Components\TextEntry::make('kyc_rejection_reason')
                        ->label('KYC rejection reason')
                        ->placeholder('—')
                        ->visible(fn (User $record) => $record->kyc_status === 'rejected'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('Wallet')
                ->schema([
                    Infolists\Components\TextEntry::make('wallet.balance')
                        ->label('Balance')
                        ->money('INR'),
                    Infolists\Components\TextEntry::make('wallet.reward_balance')
                        ->label('Reward balance')
                        ->money('INR'),
                    Infolists\Components\TextEntry::make('wallet.recharged_balance')
                        ->label('Recharged balance')
                        ->money('INR'),
                    Infolists\Components\TextEntry::make('wallet.locked_balance')
                        ->label('Locked balance')
                        ->money('INR'),
                    Infolists\Components\TextEntry::make('wallet.currency')->label('Currency'),
                ])
                ->columns(3),
            Infolists\Components\Section::make('Payout details')
                ->description('Saved by the customer in the mobile app.')
                ->schema([
                    Infolists\Components\TextEntry::make('profile.upi_id')->label('UPI ID')->placeholder('Not set'),
                    Infolists\Components\TextEntry::make('profile.bank_name')->label('Bank name')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.account_holder_name')->label('Account holder')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.account_number')->label('Account number')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.ifsc_code')->label('IFSC code')->placeholder('—'),
                    Infolists\Components\TextEntry::make('profile.account_type')
                        ->label('Account type')
                        ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
                ])
                ->columns(3)
                ->collapsible(),
            Infolists\Components\Section::make('Mobile chart data source')
                ->schema([
                    Infolists\Components\TextEntry::make('profile.mobile_chart_data_source')
                        ->label('Chart data source')
                        ->formatStateUsing(function (?string $state) {
                            return match ($state) {
                                ChartDataModeService::MODE_REAL => 'Real market data',
                                ChartDataModeService::MODE_CUSTOM => 'Custom (admin controlled)',
                                default => 'Platform default',
                            };
                        }),
                    Infolists\Components\TextEntry::make('profile.mobile_chart_data_version')
                        ->label('Real feed version override')
                        ->formatStateUsing(function (?string $state) {
                            return match ($state) {
                                ChartDataVersionService::VERSION_V2 => 'v2 — Markets Live (Twelve Data)',
                                ChartDataVersionService::VERSION_V1 => 'v1 — Markets',
                                default => 'Platform default',
                            };
                        }),
                    Infolists\Components\TextEntry::make('effective_chart_config')
                        ->label('Effective on mobile')
                        ->state(function (User $record): string {
                            return app(ChartDataVersionService::class)->effectiveDescriptionForProfile($record->profile);
                        }),
                ])
                ->collapsible(),
            Infolists\Components\Section::make('Audit')
                ->schema([
                    Infolists\Components\TextEntry::make('created_at')->dateTime('d M Y, H:i'),
                    Infolists\Components\TextEntry::make('creator.name')->label('Created by')->placeholder('—'),
                    Infolists\Components\TextEntry::make('updated_at')->dateTime('d M Y, H:i'),
                    Infolists\Components\TextEntry::make('updater.name')->label('Updated by')->placeholder('—'),
                ])
                ->columns(4)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('serial')
                    ->label('Sl. No.')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('public_user_id')
                    ->label('User ID')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('wallet.balance')
                    ->label('Balance')
                    ->money('INR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('wallet.reward_balance')
                    ->label('Reward')
                    ->money('INR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('wallet.recharged_balance')
                    ->label('Recharged')
                    ->money('INR')
                    ->toggleable(),
                Tables\Columns\BadgeColumn::make('status'),
                Tables\Columns\BadgeColumn::make('kyc_status')->label('KYC'),
                Tables\Columns\TextColumn::make('referral_code')->toggleable(isToggledHiddenByDefault: true),
                ...AuditTableColumns::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('wallet')
                    ->label('Wallet')
                    ->icon('heroicon-o-wallet')
                    ->color('info')
                    ->url(fn (User $record) => UserResource::getUrl('wallet', ['record' => $record])),
                Tables\Actions\Action::make('recharge')
                    ->label('Recharge')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (): bool => static::canAdmin('recharge_wallet'))
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount (INR)')
                            ->numeric()
                            ->required()
                            ->minValue(1)
                            ->prefix('₹'),
                        Forms\Components\Textarea::make('description')
                            ->label('Note')
                            ->default('Wallet recharge by admin')
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        app(WalletService::class)->getOrCreateWallet($record);
                        app(WalletService::class)->adminRecharge(
                            $record,
                            number_format((float) $data['amount'], 8, '.', ''),
                            $data['description'],
                            auth()->id(),
                        );
                        Notification::make()
                            ->title('Wallet recharged')
                            ->body('₹'.$data['amount'].' — visible in customer app.')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\EditAction::make()->label('Update'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['wallet', 'profile', 'creator', 'updater'])
            ->whereDoesntHave('roles', fn (Builder $q) => $q->whereIn('name', ['admin', 'super_admin']));
    }

    public static function getRelations(): array
    {
        return [
            MarketChartsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
            'wallet' => Pages\ViewUserWallet::route('/{record}/wallet'),
        ];
    }
}
