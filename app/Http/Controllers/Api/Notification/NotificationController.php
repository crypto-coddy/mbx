<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Api\ApiController;
use App\Models\PushNotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $notifications = PushNotificationLog::where('user_id', $request->user()->id)
            ->orWhere('is_broadcast', true)
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->success($notifications);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = PushNotificationLog::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)->orWhere('is_broadcast', true);
        })->findOrFail($id);

        $notification->update(['read_at' => now()]);

        return $this->success($notification->fresh());
    }

    public function markAllRead(Request $request): JsonResponse
    {
        PushNotificationLog::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return $this->success(null, 'All notifications marked as read.');
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = PushNotificationLog::where(function ($q) use ($request) {
            $q->where('user_id', $request->user()->id)->orWhere('is_broadcast', true);
        })->whereNull('read_at')->count();

        return $this->success(['count' => $count]);
    }
}
