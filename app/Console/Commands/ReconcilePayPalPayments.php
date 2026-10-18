<?php

namespace App\Console\Commands;

use App\Models\CheckoutPayment;
use App\Services\PayPalCheckout;
use Illuminate\Console\Command;

class ReconcilePayPalPayments extends Command
{
    protected $signature = 'paypal:reconcile';

    protected $description =
        'Verify outstanding PayPal payments and safely release expired stock reservations.';

    public function handle(PayPalCheckout $checkout): int
    {
        $count = 0;

        CheckoutPayment::whereNotIn('status', [
            'completed',
            'refunded',
            'reversed',
            'cancelled',
            'expired',
            'failed',
        ])
            ->where(
                'environment',
                $checkout->gateway->mode()
            )
            ->orderBy('id')
            ->chunkById(
                50,
                function ($payments) use ($checkout, &$count) {
                    foreach ($payments as $payment) {
                        $checkout->refresh($payment, true);
                        $count++;
                    }
                }
            );

        $this->info(
            "Checked {$count} PayPal payment(s)."
        );

        return self::SUCCESS;
    }
}
