<?php

namespace App\Jobs;

use App\Mail\CustomerOrderConfirmedMail;
use App\Models\Order;
use App\Services\AdminNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendOrderConfirmedEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(
        public int $orderId
    ) {}

    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $order = Order::with('items')->find(
            $this->orderId
        );

        if (! $order) {
            return;
        }

        if (
            ! filter_var(
                $order->customer_email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'The order does not contain a valid customer email.'
            );
        }

        Mail::to($order->customer_email)->send(
            new CustomerOrderConfirmedMail(
                $order->toArray()
            )
        );
    }

    public function failed(?Throwable $exception): void
    {
        if (
            ! Order::whereKey($this->orderId)->exists()
        ) {
            return;
        }

        AdminNotifier::create(
            'Customer confirmation email failed',
            "The confirmation email for order #{$this->orderId} "
                .'could not be sent after all attempts.',
            'email_failed',
            $this->orderId
        );
    }
}
