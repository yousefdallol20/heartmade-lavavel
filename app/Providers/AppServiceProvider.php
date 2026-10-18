<?php

namespace App\Providers;

use App\Models\Inquiry;
use App\Models\Order;
use App\Observers\SubmissionObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(SubmissionObserver::class);
        Inquiry::observe(SubmissionObserver::class);
    }
}
