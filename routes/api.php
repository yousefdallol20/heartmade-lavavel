<?php

use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\PayPalCheckoutController;
use App\Http\Controllers\PayPalWebhookController;
use App\Http\Controllers\ProductController as StorefrontProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Vue connected to Laravel successfully.',
    ]);
});

Route::get('/home', [
    HomeController::class,
    'index',
]);

Route::get('/products', [
    StorefrontProductController::class,
    'index',
]);

Route::get('/products/{product}', [
    StorefrontProductController::class,
    'show',
]);

Route::post('/inquiries', [
    InquiryController::class,
    'store',
])->middleware('throttle:3,1');

Route::post('/orders', [
    CheckoutController::class,
    'store',
])->middleware('throttle:5,1');

Route::prefix('admin')
    ->middleware('auth:sanctum')
    ->group(function () {
        Route::apiResource(
            'products',
            AdminProductController::class
        );

        Route::apiResource(
            'orders',
            OrderController::class
        );

        Route::apiResource(
            'partners',
            PartnerController::class
        );

        Route::get('/settings', [
            SettingController::class,
            'show',
        ]);

        Route::patch('/settings', [
            SettingController::class,
            'update',
        ]);

        Route::put('/settings/password', [
            SettingController::class,
            'updatePassword',
        ])->middleware('throttle:5,1');

        Route::get('/inquiries', [
            InquiryController::class,
            'index',
        ]);

        Route::get('/inquiries/{inquiry}', [
            InquiryController::class,
            'show',
        ]);

        Route::get('/notifications', [
            NotificationController::class,
            'index',
        ]);

        Route::patch('/notifications/read-all', [
            NotificationController::class,
            'markAllRead',
        ]);

        Route::patch('/notifications/{notification}/read', [
            NotificationController::class,
            'markRead',
        ]);
    });

Route::get(
    '/orders/{order}/invoice/admin',
    [
        InvoiceController::class,
        'downloadAdmin',
    ]
)
    ->middleware('signed')
    ->name('orders.invoice.admin');

Route::get(
    '/orders/{order}/invoice/customer',
    [
        InvoiceController::class,
        'downloadCustomer',
    ]
)
    ->middleware('signed')
    ->name('orders.invoice.customer');

Route::prefix('paypal')->group(function () {
    Route::get(
        '/configuration',
        [PayPalCheckoutController::class, 'configuration']
    );

    Route::post(
        '/quote',
        [PayPalCheckoutController::class, 'quote']
    )->middleware('throttle:30,1');

    Route::post(
        '/orders',
        [PayPalCheckoutController::class, 'store']
    )->middleware('throttle:10,1');

    Route::post(
        '/payments/{reference}/status',
        [PayPalCheckoutController::class, 'status']
    )->middleware('throttle:30,1');

    Route::post(
        '/payments/{reference}/capture',
        [PayPalCheckoutController::class, 'capture']
    )->middleware('throttle:20,1');

    Route::post(
        '/payments/{reference}/cancel',
        [PayPalCheckoutController::class, 'cancel']
    )->middleware('throttle:10,1');

    Route::post(
        '/webhook',
        PayPalWebhookController::class
    )->middleware('throttle:120,1');
});
