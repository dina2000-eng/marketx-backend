<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request
            ->user()
            ->notifications()
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $notifications->items(),

            'meta' => [
                'current_page' =>
                    $notifications->currentPage(),

                'last_page' =>
                    $notifications->lastPage(),

                'total' =>
                    $notifications->total(),

                'unread_count' =>
                    $request
                        ->user()
                        ->unreadNotifications()
                        ->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'unread_count' =>
                $request
                    ->user()
                    ->unreadNotifications()
                    ->count(),
        ]);
    }

    public function markAsRead(
        Request $request,
        string $id
    ) {
        $notification =
            $request
                ->user()
                ->notifications()
                ->where('id', $id)
                ->firstOrFail();

        if ($notification->unread()) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' =>
                'Notification marked as read',
        ]);
    }

    public function markAllAsRead(
        Request $request
    ) {
        $request
            ->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'message' =>
                'All notifications marked as read',
        ]);
    }
}