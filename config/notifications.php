<?php

return [
    /*
     * عدد الأيام التي يبقى فيها الإشعار ظاهرًا
     * داخل لوحة الإدارة.
     */
    'retention_days' => max(
        1,
        (int) env(
            'ADMIN_NOTIFICATION_RETENTION_DAYS',
            7
        )
    ),

    /*
     * إذا وصلت كمية منتج واحد في الطلب إلى هذا العدد،
     * يتم إنشاء تنبيه كمية كبيرة.
     */
    'large_quantity_threshold' => max(
        1,
        (int) env(
            'LARGE_ORDER_QUANTITY_THRESHOLD',
            10
        )
    ),
];
