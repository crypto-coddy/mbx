<?php

namespace App\Filament\Resources\DepositRequestResource\Pages;

use App\Filament\Resources\DepositRequestResource;
use App\Models\DepositRequest;
use App\Services\DepositRequestService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewDepositRequest extends ViewRecord
{
    protected static string $resource = DepositRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var DepositRequest $record */
        $record = $this->record;

        return [
            Actions\Action::make('approve')
                ->label('Approve & credit wallet')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Approve deposit')
                ->modalDescription(fn () => $record->user?->canApproveDeposit()
                    ? 'This will credit the customer wallet with the deposit amount.'
                    : implode(' ', $record->user?->depositApprovalBlockers() ?? ['Customer is not eligible for deposit approval.']))
                ->visible(fn () => $record->status === 'pending' && auth()->user()?->can('manage_deposits'))
                ->disabled(fn () => ! $record->user?->canApproveDeposit())
                ->action(function () use ($record) {
                    try {
                        app(DepositRequestService::class)->approve($record, auth()->id());
                        Notification::make()->title('Deposit approved & wallet credited')->success()->send();
                        $this->refreshFormData(['status', 'processed_at', 'processor.name']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $record->status === 'pending' && auth()->user()?->can('manage_deposits'))
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Rejection reason')
                        ->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        app(DepositRequestService::class)->reject($record, $data['reason'], auth()->id());
                        Notification::make()->title('Deposit rejected')->warning()->send();
                        $this->refreshFormData(['status', 'rejection_reason', 'processed_at', 'processor.name']);
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\EditAction::make(),
        ];
    }
}
