<?php

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Api\ApiController;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Support\TicketNumberGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['sometimes', 'string']]);

        $query = SupportTicket::where('user_id', $request->user()->id)->latest();

        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return $this->success($query->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:trading,withdrawal,kyc,account,technical,other'],
            'message' => ['required', 'string'],
        ]);

        $ticket = SupportTicket::create([
            'user_id' => $request->user()->id,
            'ticket_number' => TicketNumberGenerator::generate(),
            'subject' => $data['subject'],
            'category' => $data['category'],
            'status' => 'open',
        ]);

        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
        ]);

        return $this->success($ticket->load('messages'), 'Ticket created.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)
            ->with(['messages.user:id,name'])
            ->findOrFail($id);

        return $this->success($ticket);
    }

    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string'],
            'attachments' => ['sometimes', 'array'],
        ]);

        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);

        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'attachments' => $data['attachments'] ?? null,
        ]);

        if ($ticket->status === 'waiting_user') {
            $ticket->update(['status' => 'in_progress']);
        }

        return $this->success($message, 'Reply sent.');
    }

    public function close(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::where('user_id', $request->user()->id)->findOrFail($id);
        $ticket->update(['status' => 'closed', 'resolved_at' => now()]);

        return $this->success($ticket->fresh(), 'Ticket closed.');
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $query = SupportTicket::with('user:id,name,phone')->latest();

        foreach (['status', 'category', 'priority'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }

        return $this->success($query->paginate(20));
    }

    public function adminShow(int $id): JsonResponse
    {
        $ticket = SupportTicket::with(['user:id,name,phone', 'messages.user:id,name'])->findOrFail($id);

        return $this->success($ticket);
    }

    public function adminReply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['message' => ['required', 'string']]);
        $ticket = SupportTicket::findOrFail($id);

        $message = SupportMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => $request->user()->id,
            'message' => $data['message'],
            'is_admin_reply' => true,
        ]);

        $ticket->update(['status' => 'waiting_user']);

        return $this->success($message, 'Admin reply sent.');
    }

    public function adminUpdate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:open,in_progress,waiting_user,resolved,closed'],
            'priority' => ['sometimes', 'in:low,medium,high,urgent'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ]);

        $ticket = SupportTicket::findOrFail($id);
        $ticket->update($data);

        return $this->success($ticket->fresh(), 'Ticket updated.');
    }
}
