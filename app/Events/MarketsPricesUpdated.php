<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsWhenEnabled;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Broadcast full markets snapshot — one message for all connected clients. */
class MarketsPricesUpdated implements ShouldBroadcast
{
    use BroadcastsWhenEnabled, Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  list<array{asset_id: int, symbol: string, live_price: string, price_change_24h: string, source?: string, recorded_at?: string}>  $quotes
     */
    public function __construct(public array $quotes) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('prices'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MarketsPricesUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'quotes' => $this->quotes,
            'synced_at' => now()->toIso8601String(),
        ];
    }
}
