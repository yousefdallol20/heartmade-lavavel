<?php

namespace App\Services;

use App\Models\AdminNotification;
use App\Models\Order;

class AdminNotifier
{
    public static function create(
        string $title,
        string $message,
        string $type = 'general',
        ?int $orderId = null,
        ?int $inquiryId = null
    ): AdminNotification {
        return AdminNotification::create([
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'order_id' => $orderId,
            'inquiry_id' => $inquiryId,
            'read_at' => null,
        ]);
    }

    public static function actor(?object $user): string
    {
        $email = $user === null
            ? ''
            : trim((string) ($user->email ?? ''));

        return $email !== ''
            ? $email
            : 'an administrator';
    }

    public static function largeOrder(Order $order): void
    {
        $threshold = max(
            1,
            (int) config(
                'notifications.large_quantity_threshold',
                10
            )
        );

        $largeItems = $order->items
            ->filter(
                fn ($item) => (int) $item->qty >= $threshold
            )
            ->map(
                fn ($item) => "{$item->name} ({$item->qty})"
            )
            ->values();

        if ($largeItems->isEmpty()) {
            return;
        }

        /*
         * منع إنشاء نفس تنبيه الكمية الكبيرة
         * أكثر من مرة لنفس الطلب.
         */
        if (
            AdminNotification::query()
                ->where('type', 'large_order')
                ->where('order_id', $order->id)
                ->exists()
        ) {
            return;
        }

        self::create(
            'Large quantity order detected',
            "Order #{$order->id} contains large quantities: "
                .$largeItems->join(', ')
                .'. Please review stock and production capacity.',
            'large_order',
            $order->id
        );
    }
}
