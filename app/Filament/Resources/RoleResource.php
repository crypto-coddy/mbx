<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = Role::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Roles & permissions';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return static::canAdmin('manage_roles');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_roles') && in_array($record->name, ['admin', 'super_admin'], true);
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Role')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->disabled()
                        ->dehydrated(false),
                    Forms\Components\CheckboxList::make('permissions')
                        ->relationship('permissions', 'name')
                        ->options(
                            Permission::query()
                                ->where('guard_name', 'web')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                        )
                        ->columns(2)
                        ->bulkToggleable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Role')->badge(),
                Tables\Columns\TextColumn::make('permissions_count')
                    ->counts('permissions')
                    ->label('Permissions'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('guard_name', 'web')
            ->whereIn('name', ['admin', 'super_admin']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
