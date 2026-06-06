<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\SupportTicketResource\Pages;
use App\Filament\Support\AuditTableColumns;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SupportTicketResource extends Resource
{
    use AuthorizesAdminPermission;

    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-lifebuoy';

    protected static ?string $navigationLabel = 'Complaints';

    protected static ?string $modelLabel = 'complaint';

    protected static ?string $pluralModelLabel = 'Complaints';

    protected static ?string $navigationGroup = 'Customers';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        $count = app(SupportTicketService::class)->openCount();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canViewAny(): bool
    {
        return static::canAdmin('view_support');
    }

    public static function canView($record): bool
    {
        return static::canAdmin('view_support');
    }

    public static function canEdit($record): bool
    {
        return static::canAdmin('manage_support');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Ticket')
                ->schema([
                    Forms\Components\TextInput::make('ticket_number')->disabled(),
                    Forms\Components\TextInput::make('subject')->disabled(),
                    Forms\Components\TextInput::make('category')->disabled(),
                    Forms\Components\Select::make('status')
                        ->options([
                            'open' => 'Open',
                            'in_progress' => 'In progress',
                            'waiting_user' => 'Waiting for user',
                            'resolved' => 'Resolved',
                            'closed' => 'Closed',
                        ])
                        ->required(),
                    Forms\Components\Select::make('priority')
                        ->options([
                            'low' => 'Low',
                            'medium' => 'Medium',
                            'high' => 'High',
                            'urgent' => 'Urgent',
                        ])
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('ticket_number')->label('Ticket #')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user.name')->label('Customer')->searchable(),
                Tables\Columns\TextColumn::make('user.phone')->label('Phone')->toggleable(),
                Tables\Columns\TextColumn::make('subject')->limit(40)->searchable(),
                Tables\Columns\BadgeColumn::make('category'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'danger' => 'open',
                        'warning' => 'in_progress',
                        'info' => 'waiting_user',
                        'success' => 'resolved',
                        'gray' => 'closed',
                    ]),
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'gray' => 'low',
                        'primary' => 'medium',
                        'warning' => 'high',
                        'danger' => 'urgent',
                    ]),
                Tables\Columns\TextColumn::make('created_at')->label('Submitted')->dateTime('d M Y, H:i')->sortable(),
                ...AuditTableColumns::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'in_progress' => 'In progress',
                    'waiting_user' => 'Waiting for user',
                    'resolved' => 'Resolved',
                    'closed' => 'Closed',
                ]),
                Tables\Filters\SelectFilter::make('category')->options([
                    'trading' => 'Trading',
                    'withdrawal' => 'Withdrawal',
                    'kyc' => 'KYC',
                    'account' => 'Account',
                    'technical' => 'Technical',
                    'other' => 'Other',
                ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSupportTickets::route('/'),
            'view' => Pages\ViewSupportTicket::route('/{record}'),
            'edit' => Pages\EditSupportTicket::route('/{record}/edit'),
        ];
    }
}
