<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\AdminDashboardService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class InactiveUsersTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Inactive users';

    protected static ?string $description = 'Accounts that cannot trade until reactivated';

    public function table(Table $table): Table
    {
        return $table
            ->striped()
            ->query(
                app(AdminDashboardService::class)
                    ->appUsersQuery()
                    ->with('wallet')
                    ->whereIn('status', ['inactive', 'suspended', 'banned'])
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('email')->toggleable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                        'gray' => 'banned',
                    ]),
                Tables\Columns\BadgeColumn::make('kyc_status')->label('KYC'),
                Tables\Columns\TextColumn::make('wallet.balance')
                    ->label('Balance')
                    ->money('INR'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Manage')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No inactive users')
            ->emptyStateDescription('All app users are currently active.')
            ->paginated([10, 25, 50]);
    }
}
