<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $cutoff = now()->subDays(
            (int) config('notifications.retention_days', 7)
        );

        $validated = $request->validate([
            'per_page' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],

            'unread_only' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $notifications = AdminNotification::query()
            ->where('created_at', '>=', $cutoff)
            ->with([
                'inquiry',
                'order.items',
            ])
            ->when(
                $request->boolean('unread_only'),
                fn ($query) => $query->whereNull('read_at')
            )
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'data' => $notifications->items(),

            'unread_count' => AdminNotification::query()
                ->where('created_at', '>=', $cutoff)
                ->whereNull('read_at')
                ->count(),

            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    public function markRead(
        Request $request,
        AdminNotification $notification
    ): JsonResponse {
        $this->authorizeAdmin($request);

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',

            'data' => $notification->fresh()->load([
                'inquiry',
                'order.items',
            ]),

            'unread_count' => AdminNotification::query()
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $updated = AdminNotification::query()
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'updated_count' => $updated,

            'unread_count' => AdminNotification::query()
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            (bool) $request->user()?->is_admin,
            403,
            'Admin access is required.'
        );
    }
}
