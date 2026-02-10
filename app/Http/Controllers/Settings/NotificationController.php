<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * Show account notifications.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(static function ($notification): array {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => (string) ($data['title'] ?? class_basename($notification->type)),
                    'message' => (string) ($data['message'] ?? ''),
                    'actionUrl' => is_string($data['action_url'] ?? null) ? $data['action_url'] : null,
                    'createdAt' => $notification->created_at?->toIso8601String(),
                    'readAt' => $notification->read_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('settings/notifications', [
            'status' => $request->session()->get('status'),
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification as read.
     */
    public function read(Request $request, string $notification): RedirectResponse
    {
        $userNotification = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->first();

        abort_if($userNotification === null, 404);

        if ($userNotification->read_at === null) {
            $userNotification->markAsRead();
        }

        return back()->with('status', __('Notification marked as read.'));
    }

    /**
     * Mark all notifications as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('status', __('All notifications marked as read.'));
    }
}
