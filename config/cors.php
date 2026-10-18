<?php

return [
    // المسارات التي تستقبل طلبات واجهة Vue
    'paths' => [
        'api/*',
        'admin/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        'http://localhost:5173',
        env('FRONTEND_URL'),
    ])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // السماح بإرسال ملفات تعريف الارتباط الخاصة بالجلسة
    'supports_credentials' => true,
];
