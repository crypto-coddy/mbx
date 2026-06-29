<?php

namespace App\Events;

use App\Events\Concerns\BroadcastsWhenEnabled;
use App\Models\Asset;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PriceUpdated implements ShouldBroadcast
{
    use BroadcastsWhenEnabled, Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Asset $asset,
        public string $price,
        public string $source,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('prices'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PriceUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'asset_id' => $this->asset->id,
            'symbol' => $this->asset->symbol,
            'price' => $this->price,
            'source' => $this->source,
            'price_change_24h' => $this->asset->price_change_24h,
            'recorded_at' => now()->toIso8601String(),
        ];
    }
}
