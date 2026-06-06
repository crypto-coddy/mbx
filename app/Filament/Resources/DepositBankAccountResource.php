<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\DepositBankAccountResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\DepositBankAccount;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class DepositBankAccountResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = DepositBankAccount::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationGroup = 'Mobile Configuration';

    protected static ?string $navigationLabel = 'Deposit bank accounts';

    protected static ?string $modelLabel = 'deposit bank account';

    protected static ?string $pluralModelLabel = 'Deposit bank accounts';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_mobile_config');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function canDelete($record): bool
    {
        return static::canAdmin('manage_mobile_config');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Bank account')
                ->description('Only active accounts are shown to users for bank transfer deposits on mobile.')
                ->schema([
                    Forms\Components\TextInput::make('label')
                        ->label('Display label')
                        ->maxLength(120)
                        ->placeholder('Primary account')
                        ->helperText('Optional name shown above account details on mobile'),
                    Forms\Components\TextInput::make('account_holder')
                        ->label('Account holder name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('bank_name')
                        ->label('Bank name')
                        ->required()
                        ->maxLength(120),
                    Forms\Components\TextInput::make('account_number')
                        ->label('Account number')
                        ->required()
                        ->maxLength(30),
                    Forms\Components\TextInput::make('ifsc')
                        ->label('IFSC code')
                        ->required()
                        ->maxLength(20)
                        ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state))),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Active')
                        ->helperText('Inactive accounts are hidden from users')
                        ->default(true)
                        ->live(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Sort order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Higher numbers appear first when multiple accounts are active'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('account_holder')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bank_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label('Account no.')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('ifsc')
                    ->copyable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                ...AuditTableColumns::make(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDepositBankAccounts::route('/'),
            'create' => Pages\CreateDepositBankAccount::route('/create'),
            'edit' => Pages\EditDepositBankAccount::route('/{record}/edit'),
        ];
    }
}
