<?php

use App\Models\AdminNotification;
use App\Services\AdminNotifier;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| حذف الإشعارات القديمة
|--------------------------------------------------------------------------
|
| تعمل يوميًا الساعة 12:10 بعد منتصف الليل.
| تحذف أي إشعار تجاوز مدة الاحتفاظ المحددة.
|
*/

Schedule::call(function (): void {
    AdminNotification::query()
        ->where(
            'created_at',
            '<',
            now()->subDays(
                (int) config(
                    'notifications.retention_days',
                    7
                )
            )
        )
        ->delete();
})
    ->dailyAt('00:10')
    ->name('prune-expired-admin-notifications');

/*
|--------------------------------------------------------------------------
| تذكير الفحص الشهري
|--------------------------------------------------------------------------
|
| يعمل أول يوم من كل شهر الساعة 9 صباحًا.
| يتحقق أولًا من عدم إنشاء التذكير خلال الشهر نفسه.
|
*/

Schedule::call(function (): void {
    $alreadyCreated = AdminNotification::query()
        ->where('type', 'monthly_check')
        ->whereBetween(
            'created_at',
            [
                now()->startOfMonth(),
                now()->endOfMonth(),
            ]
        )
        ->exists();

    if (! $alreadyCreated) {
        AdminNotifier::create(
            'Monthly website check',
            'It is time for the monthly review. '
                .'Check products, stock, prices, pending orders, '
                .'site details, and email delivery.',
            'monthly_check'
        );
    }
})
    ->monthlyOn(1, '09:00')
    ->name('monthly-admin-review-reminder');

Schedule::command('paypal:reconcile')
    ->everyMinute()
    ->withoutOverlapping();
