<?php

namespace App\Services;

use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketService
{
    public function openCount(): int
    {
        return SupportTicket::query()
            ->whereIn('status', ['open', 'in_progress', 'waiting_user'])
            ->count();
    }

    public function adminReply(SupportTicket $ticket, User $admin, string $message): SupportMessage
    {
        $reply = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $admin->id,
            'message' => $message,
            'is_admin_reply' => true,
        ]);

        $ticket->update(['status' => 'waiting_user']);

        return $reply;
    }

    public function resolve(SupportTicket $ticket): SupportTicket
    {
        $ticket->update([
            'status' => 'resolved',
            'resolved_at' => now(),
        ]);

        return $ticket->fresh();
    }

    public function close(SupportTicket $ticket): SupportTicket
    {
        $ticket->update([
            'status' => 'closed',
            'resolved_at' => $ticket->resolved_at ?? now(),
        ]);

        return $ticket->fresh();
    }
}
