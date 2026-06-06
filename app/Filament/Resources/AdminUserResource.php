<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\AdminUserResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\User;
use App\Support\ReferralCodeGenerator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AdminUserResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Admin users';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'admin user';

    protected static ?string $pluralModelLabel = 'admin users';

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_admin_users');
    }

    public static function canCreate(): bool
    {
        return static::canAdmin('manage_admin_users');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_admin_users');
    }

    public static function canDelete($record): bool
    {
        return static::canAdmin('manage_admin_users') && ! $record->hasRole('super_admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Account')
                ->schema([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\TextInput::make('phone')
                        ->tel()
                        ->required()
                        ->unique(ignoreRecord: true),
                    Forms\Components\Select::make('admin_role')
                        ->label('Role')
                        ->options(fn (): array => static::assignableRoleOptions())
                        ->required()
                        ->dehydrated(false)
                        ->default('admin'),
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->label(fn (string $operation): string => $operation === 'create' ? 'Password' : 'New password')
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->rules(fn (string $operation, ?string $state): array => match (true) {
                            $operation === 'create' => ['required', Password::defaults()],
                            filled($state) => [Password::defaults()],
                            default => [],
                        }),
                    Forms\Components\Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                        ])
                        ->default('active')
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('phone')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'super_admin' ? 'warning' : 'info'),
                Tables\Columns\BadgeColumn::make('status'),
                ...AuditTableColumns::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (User $record): bool => ! $record->hasRole('super_admin')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['admin', 'super_admin']));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
            'create' => Pages\CreateAdminUser::route('/create'),
            'edit' => Pages\EditAdminUser::route('/{record}/edit'),
        ];
    }

    /** @return array<string, string> */
    public static function assignableRoleOptions(): array
    {
        $roles = ['admin' => 'Admin'];

        if (auth()->user()?->hasRole('super_admin')) {
            $roles['super_admin'] = 'Super Admin';
        }

        return $roles;
    }

    public static function syncAdminRole(User $user, string $roleName): void
    {
        $role = Role::where('name', $roleName)->where('guard_name', 'web')->firstOrFail();
        $user->syncRoles([$role]);
    }
}
