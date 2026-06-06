<x-filament-panels::page>
    @php
        /** @var \App\Models\SupportTicket $ticket */
        $ticket = $this->record;
        $user = $ticket->user;
        $statusColors = [
            'open' => 'danger',
            'in_progress' => 'warning',
            'waiting_user' => 'info',
            'resolved' => 'success',
            'closed' => 'gray',
        ];
        $priorityColors = [
            'low' => 'gray',
            'medium' => 'primary',
            'high' => 'warning',
            'urgent' => 'danger',
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-filament::section heading="Customer">
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $user?->name ?? '—' }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Phone</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $user?->phone ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">User ID</p>
                        <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $user?->public_user_id ?? '—' }}</p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $user?->email ?? '—' }}</p>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="Complaint details">
            <div class="space-y-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Ticket #</p>
                    <p class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">{{ $ticket->ticket_number }}</p>
                </div>
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Subject</p>
                    <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $ticket->subject }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge>{{ ucfirst($ticket->category) }}</x-filament::badge>
                    <x-filament::badge :color="$statusColors[$ticket->status] ?? 'gray'">
                        {{ str_replace('_', ' ', ucfirst($ticket->status)) }}
                    </x-filament::badge>
                    <x-filament::badge :color="$priorityColors[$ticket->priority] ?? 'gray'">
                        {{ ucfirst($ticket->priority) }} priority
                    </x-filament::badge>
                </div>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Submitted</p>
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $ticket->created_at?->format('d M Y, H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Resolved</p>
                        <p class="mt-1 font-medium text-gray-950 dark:text-white">{{ $ticket->resolved_at?->format('d M Y, H:i') ?? '—' }}</p>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Conversation" description="Messages between the customer and support team.">
        @if ($ticket->messages->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No messages yet.</p>
        @else
            <div class="space-y-4">
                @foreach ($ticket->messages as $message)
                    @php
                        $isAdmin = (bool) $message->is_admin_reply;
                        $sender = $isAdmin ? 'Support team' : ($message->user?->name ?? 'Customer');
                    @endphp
                    <div @class([
                        'flex',
                        'justify-end' => ! $isAdmin,
                        'justify-start' => $isAdmin,
                    ])>
                        <div @class([
                            'max-w-[85%] rounded-2xl px-4 py-3 shadow-sm ring-1',
                            'bg-primary-600 text-white ring-primary-500/30' => $isAdmin,
                            'bg-white text-gray-950 ring-gray-200 dark:bg-gray-900 dark:text-white dark:ring-gray-700' => ! $isAdmin,
                        ])>
                            <div @class([
                                'mb-1 flex items-center gap-2 text-xs font-semibold',
                                'text-primary-100' => $isAdmin,
                                'text-gray-500 dark:text-gray-400' => ! $isAdmin,
                            ])>
                                <span>{{ $sender }}</span>
                                @if ($isAdmin)
                                    <span class="rounded-full bg-white/20 px-2 py-0.5 text-[10px] uppercase tracking-wide">Admin</span>
                                @endif
                            </div>
                            <p @class([
                                'whitespace-pre-wrap text-sm leading-6',
                                'text-white' => $isAdmin,
                                'text-gray-900 dark:text-gray-100' => ! $isAdmin,
                            ])>{{ $message->message }}</p>
                            <p @class([
                                'mt-2 text-[11px]',
                                'text-primary-100/90' => $isAdmin,
                                'text-gray-400' => ! $isAdmin,
                            ])>{{ $message->created_at?->format('d M Y, H:i') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
