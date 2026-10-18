<?php

return [
    'paypal_enabled' => env('PAYPAL_CHECKOUT_ENABLED', true),

    'usd_to_jod' => env(
        'PAYPAL_USD_TO_JOD_RATE',
        env('PAYPAL_MODE', 'sandbox') === 'sandbox'
            ? '0.70900000'
            : ''
    ),

    'reservation_minutes' => 15,

    'quote_minutes' => 5,

    'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),

    'frontend_url' => env(
        'FRONTEND_URL',
        'http://localhost:5173'
    ),
];
