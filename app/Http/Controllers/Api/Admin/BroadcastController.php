<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ApiController;
use App\Models\PushNotificationLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BroadcastController extends ApiController
{
    public function sendToUser(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'type' => ['required', 'string'],
            'data' => ['sometimes', 'array'],
        ]);

        $user = User::findOrFail($data['user_id']);

        $log = PushNotificationLog::create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => $data['type'],
            'data' => $data['data'] ?? null,
            'fcm_token' => $user->fcm_token,
            'status' => 'pending',
        ]);

        return $this->success($log, 'Notification queued.', 201);
    }

    public function broadcast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'body' => ['required', 'string'],
            'type' => ['required', 'string'],
        ]);

        $log = PushNotificationLog::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'type' => $data['type'],
            'is_broadcast' => true,
            'status' => 'pending',
        ]);

        return $this->success($log, 'Broadcast queued.', 201);
    }

    public function history(): JsonResponse
    {
        $logs = PushNotificationLog::latest()->paginate(20);

        return $this->success($logs);
    }
}
