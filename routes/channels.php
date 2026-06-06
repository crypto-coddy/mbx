<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('prices', function () {
    return true;
});

Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('admin', function ($user) {
    return $user->hasRole(['admin', 'super_admin']);
});
