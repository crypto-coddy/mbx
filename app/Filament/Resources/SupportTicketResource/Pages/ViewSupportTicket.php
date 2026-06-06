<?php

namespace App\Filament\Resources\SupportTicketResource\Pages;

use App\Filament\Concerns\AuthorizesAdminPermission;
use App\Filament\Resources\SupportTicketResource;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    use AuthorizesAdminPermission;

    protected static string $resource = SupportTicketResource::class;

    protected static string $view = 'filament.resources.support-ticket.view-support-ticket';

    public function getTitle(): string
    {
        return $this->record->ticket_number.' — '.$this->record->subject;
    }

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->record->loadMissing(['user', 'messages.user']);
    }

    protected function getHeaderActions(): array
    {
        /** @var SupportTicket $record */
        $record = $this->record;

        return [
            Actions\Action::make('reply')
                ->label('Reply to customer')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->visible(fn (): bool => static::canAdmin('manage_support') && ! in_array($record->status, ['resolved', 'closed'], true))
                ->form([
                    Forms\Components\Textarea::make('message')
                        ->label('Your reply')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data) use ($record) {
                    app(SupportTicketService::class)->adminReply($record, auth()->user(), $data['message']);
                    Notification::make()->title('Reply sent')->success()->send();
                    $this->record->load(['user', 'messages.user']);
                }),
            Actions\Action::make('resolve')
                ->label('Mark resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canAdmin('manage_support') && ! in_array($record->status, ['resolved', 'closed'], true))
                ->action(function () use ($record) {
                    app(SupportTicketService::class)->resolve($record);
                    Notification::make()->title('Ticket resolved')->success()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
            Actions\Action::make('close')
                ->label('Close ticket')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (): bool => static::canAdmin('manage_support') && $record->status !== 'closed')
                ->action(function () use ($record) {
                    app(SupportTicketService::class)->close($record);
                    Notification::make()->title('Ticket closed')->warning()->send();
                    $this->refreshFormData(['status', 'resolved_at']);
                }),
            Actions\EditAction::make()
                ->visible(fn (): bool => static::canAdmin('manage_support')),
        ];
    }
}
