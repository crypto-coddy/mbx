<?php

namespace App\Events\Concerns;

/** Skip queued broadcast jobs when WebSockets are not running (e.g. shared hosting). */
trait BroadcastsWhenEnabled
{
    public function broadcastWhen(): bool
    {
        return (bool) config('broadcasting.enabled', false);
    }
}
