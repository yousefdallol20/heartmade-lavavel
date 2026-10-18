<?php

return [
    'admin_email' => env('ADMIN_NOTIFICATION_EMAIL'),

    'admin_url' => rtrim(
        env('FRONTEND_URL', 'http://localhost:5173'),
        '/'
    ).'/#/admin',

    'currency' => 'JD',
];
