<?php

namespace App\Filament\Resources\DepositRequestResource\Pages;

use App\Filament\Resources\DepositRequestResource;
use App\Models\DepositRequest;
use App\Services\DepositRequestService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use InvalidArgumentException;

class EditDepositRequest extends EditRecord
{
    protected static string $resource = DepositRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var DepositRequest $record */
        $record = $this->record;

        return [
            Actions\ViewAction::make(),
            Actions\Action::make('approve')
                ->label('Approve & credit wallet')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription(fn () => $record->user?->canApproveDeposit()
                    ? 'This will credit the customer wallet with the deposit amount.'
                    : implode(' ', $record->user?->depositApprovalBlockers() ?? ['Customer is not eligible for deposit approval.']))
                ->visible(fn () => $record->status === 'pending')
                ->disabled(fn () => ! $record->user?->canApproveDeposit())
                ->action(function () use ($record) {
                    try {
                        app(DepositRequestService::class)->approve($record, auth()->id());
                        Notification::make()->title('Deposit approved & wallet credited')->success()->send();
                        $this->redirect(DepositRequestResource::getUrl('view', ['record' => $record]));
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn () => $record->status === 'pending')
                ->form([
                    Forms\Components\Textarea::make('reason')->label('Rejection reason')->required(),
                ])
                ->action(function (array $data) use ($record) {
                    try {
                        app(DepositRequestService::class)->reject($record, $data['reason'], auth()->id());
                        Notification::make()->title('Deposit rejected')->warning()->send();
                        $this->redirect(DepositRequestResource::getUrl('view', ['record' => $record]));
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var DepositRequest $record */
        $record = $this->record;

        if ($record->status === 'pending' && ($data['status'] ?? null) === 'approved') {
            throw new InvalidArgumentException('Use "Approve & credit wallet" to approve a deposit.');
        }

        if ($record->status === 'pending' && ($data['status'] ?? null) === 'rejected' && empty($data['rejection_reason'])) {
            throw new InvalidArgumentException('Provide a rejection reason or use the Reject action.');
        }

        return $data;
    }
}
