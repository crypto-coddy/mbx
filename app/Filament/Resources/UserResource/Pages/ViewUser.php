<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\UserResource;
use App\Models\User;
use App\Services\WalletService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    use AuthorizesAdminPermission;

    protected static string $resource = UserResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $this->record->loadMissing(['wallet', 'profile', 'referrer', 'creator', 'updater']);
        app(WalletService::class)->getOrCreateWallet($this->getRecord());
        $this->record->load('wallet');
    }

    protected function getHeaderActions(): array
    {
        /** @var User $record */
        $record = $this->record;

        return [
            Actions\Action::make('wallet')
                ->label('Wallet')
                ->icon('heroicon-o-wallet')
                ->color('info')
                ->url(UserResource::getUrl('wallet', ['record' => $record])),
            Actions\Action::make('recharge')
                ->label('Recharge')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => static::canAdmin('recharge_wallet'))
                ->form([
                    \Filament\Forms\Components\TextInput::make('amount')
                        ->label('Amount (INR)')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->prefix('₹'),
                    \Filament\Forms\Components\Textarea::make('description')
                        ->label('Note')
                        ->default('Wallet recharge by admin')
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    app(WalletService::class)->adminRecharge(
                        $record,
                        number_format((float) $data['amount'], 8, '.', ''),
                        $data['description'],
                        auth()->id(),
                    );

                    $this->record->load('wallet');

                    Notification::make()
                        ->title('Wallet recharged')
                        ->body('₹'.$data['amount'].' — visible in customer app.')
                        ->success()
                        ->send();
                }),
            Actions\EditAction::make()->label('Update'),
        ];
    }
}
