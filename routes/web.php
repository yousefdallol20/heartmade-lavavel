<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\PayPalCheckoutController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// مسارات تسجيل دخول الإدارة
Route::prefix('admin')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    // طلب رابط استعادة كلمة المرور
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,1');
    // تعيين كلمة مرور جديدة
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
    // المسارات التي تتطلب تسجيل الدخول
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

Route::get('/products', [ProductController::class, 'index'])
    ->name('products.index');

Route::get(
    '/paypal/return/{payment}',
    [
        PayPalCheckoutController::class,
        'returned',
    ]
)->name('checkout.paypal.return');

Route::get(
    '/paypal/cancel/{payment}',
    [
        PayPalCheckoutController::class,
        'cancelled',
    ]
)->name('checkout.paypal.cancel');
