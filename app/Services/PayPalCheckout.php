<?php

namespace App\Services;

use App\Http\Controllers\CheckoutController;
use App\Jobs\SendSubmissionEmail;
use App\Models\CheckoutPayment;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PayPalCheckout
{
    public function __construct(
        public PayPalGateway $gateway,
        public CheckoutQuote $quotes
    ) {}

    public function available(): bool
    {
        try {
            $this->quotes->rate();

            return (bool) config('checkout.paypal_enabled')
                && $this->gateway->ready()
                && (
                    $this->gateway->mode() === 'sandbox'
                    || (
                        trim((string) config('checkout.webhook_id')) !== ''
                        && parse_url(
                            (string) config('app.url'),
                            PHP_URL_SCHEME
                        ) === 'https'
                        && parse_url(
                            (string) config('checkout.frontend_url'),
                            PHP_URL_SCHEME
                        ) === 'https'
                    )
                );
        } catch (\Throwable) {
            return false;
        }
    }

    public function start(
        array $data,
        string $quoteToken,
        string $checkoutToken
    ): CheckoutPayment {
        abort_unless(
            $this->available(),
            503,
            'PayPal is temporarily unavailable.'
        );

        $tokenHash = hash('sha256', $checkoutToken);

        $payloadHash = hash(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR)
        );

        $existing = CheckoutPayment::where(
            'checkout_token_hash',
            $tokenHash
        )->first();

        if ($existing) {
            abort_unless(
                hash_equals($existing->payload_hash, $payloadHash),
                409,
                'This checkout was already submitted with different details.'
            );

            return $this->refresh($existing, false);
        }

        $quote = $this->quotes->verify(
            $quoteToken,
            $data['items'],
            (string) $data['expected_total']
        );

        try {
            $payment = DB::transaction(function () use (
                $data,
                $quote,
                $tokenHash,
                $payloadHash
            ) {
                $order = app(CheckoutController::class)
                    ->reserve($data, true);

                return CheckoutPayment::create([
                    'order_id' => $order->id,
                    'reference' => (string) Str::uuid(),
                    'checkout_token_hash' => $tokenHash,
                    'payload_hash' => $payloadHash,
                    'environment' => $this->gateway->mode(),
                    'status' => 'creating',
                    'order_amount' => $order->total,
                    'payment_amount' => $quote['payment_amount'],
                    'usd_to_jod' => $quote['usd_to_jod'],
                    'create_request_id' => (string) Str::uuid(),
                    'capture_request_id' => (string) Str::uuid(),

                    'expires_at' => now()->addMinutes(
                        (int) config('checkout.reservation_minutes')
                    ),
                ]);
            });
        } catch (QueryException $exception) {
            // الطلب المتزامن يستعيد السجل نفسه دون حجز المخزون مرتين.
            $payment = CheckoutPayment::where(
                'checkout_token_hash',
                $tokenHash
            )->first();

            if (
                ! $payment
                || ! hash_equals($payment->payload_hash, $payloadHash)
            ) {
                throw $exception;
            }
        }

        return $this->refresh($payment, false);
    }

    private function createPayload(
        CheckoutPayment $payment
    ): array {
        return [
            'intent' => 'CAPTURE',

            'purchase_units' => [[
                'reference_id' => $payment->reference,
                'custom_id' => $payment->reference,
                'invoice_id' => 'HM-'.$payment->reference,
                'description' => 'Heart Made order #'.$payment->order_id,

                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $payment->payment_amount,
                ],
            ]],

            'payment_source' => [
                'paypal' => [
                    'experience_context' => [
                        'brand_name' => 'Heart Made',
                        'user_action' => 'PAY_NOW',
                        'shipping_preference' => 'NO_SHIPPING',

                        'return_url' => URL::signedRoute(
                            'checkout.paypal.return',
                            ['payment' => $payment->reference]
                        ),

                        'cancel_url' => URL::signedRoute(
                            'checkout.paypal.cancel',
                            ['payment' => $payment->reference]
                        ),
                    ],
                ],
            ],
        ];
    }

    private function validateRemote(
        CheckoutPayment $payment,
        array $remote
    ): array {
        $units = $remote['purchase_units'] ?? [];
        $unit = $units[0] ?? [];
        $amount = $unit['amount'] ?? [];

        if (
            count($units) !== 1
            || ($remote['id'] ?? '') !== $payment->paypal_order_id
            || ($remote['intent'] ?? '') !== 'CAPTURE'
            || ($unit['custom_id'] ?? '') !== $payment->reference
            || ($unit['invoice_id'] ?? '')
                !== 'HM-'.$payment->reference
            || ($amount['currency_code'] ?? '') !== 'USD'
            || $this->quotes->cents(
                (string) ($amount['value'] ?? 'invalid')
            ) !== $this->quotes->cents($payment->payment_amount)
        ) {
            throw new \RuntimeException(
                'PayPal order does not match the saved payment.'
            );
        }

        return $unit;
    }

    public function refresh(
        CheckoutPayment $source,
        bool $capture = false,
        bool $cancel = false
    ): CheckoutPayment {
        return Cache::lock(
            'heartmade-paypal-'.$source->reference,
            300
        )->block(5, function () use ($source, $capture, $cancel) {
            $shouldCapture = false;

            $payment = $this->inspect(
                $source,
                $capture,
                $cancel,
                $shouldCapture
            );

            if ($shouldCapture) {
                try {
                    // المحاولة تُحفظ قبل الاتصال الخارجي لحماية نتيجة الدفع.
                    $this->gateway->capture(
                        $payment->paypal_order_id,
                        $payment->capture_request_id
                    );
                } catch (\Throwable $exception) {
                    Log::warning(
                        'PayPal capture result is uncertain.',
                        [
                            'payment_reference' => $payment->reference,
                            'exception_class' => get_class($exception),
                        ]
                    );
                }

                $unused = false;

                return $this->inspect(
                    $payment,
                    false,
                    false,
                    $unused
                );
            }

            return $payment;
        });
    }

    private function inspect(
        CheckoutPayment $source,
        bool $capture,
        bool $cancel,
        bool &$shouldCapture
    ): CheckoutPayment {
        return DB::transaction(function () use (
            $source,
            $capture,
            $cancel,
            &$shouldCapture
        ) {
            $order = Order::whereKey($source->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            $payment = CheckoutPayment::whereKey($source->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($payment->environment !== $this->gateway->mode()) {
                return $payment;
            }

            if (
                $payment->stock_released_at
                || in_array(
                    $payment->status,
                    ['refunded', 'reversed'],
                    true
                )
            ) {
                return $payment;
            }

            try {
                if (! $payment->paypal_order_id) {
                    if ($payment->expires_at->isPast() || $cancel) {
                        $this->release(
                            $payment,
                            $order,
                            $cancel ? 'cancelled' : 'expired'
                        );

                        return $payment;
                    }

                    $remote = $this->gateway->create(
                        $this->createPayload($payment),
                        $payment->create_request_id
                    );

                    if (
                        ! is_string($remote['id'] ?? null)
                        || $remote['id'] === ''
                    ) {
                        throw new \RuntimeException(
                            'PayPal did not return an order identifier.'
                        );
                    }

                    $payment->paypal_order_id = $remote['id'];

                    foreach ($remote['links'] ?? [] as $link) {
                        if (
                            in_array(
                                $link['rel'] ?? '',
                                ['approve', 'payer-action'],
                                true
                            )
                        ) {
                            $url = (string) ($link['href'] ?? '');

                            $host = parse_url($url, PHP_URL_HOST);

                            $expected = $payment->environment === 'sandbox'
                                ? 'www.sandbox.paypal.com'
                                : 'www.paypal.com';

                            if (
                                parse_url($url, PHP_URL_SCHEME) !== 'https'
                                || $host !== $expected
                            ) {
                                throw new \RuntimeException(
                                    'Invalid PayPal approval URL.'
                                );
                            }

                            $payment->approval_url = $url;
                        }
                    }

                    $payment->status = 'awaiting_approval';
                    $payment->save();
                }

                $remote = $this->gateway->show(
                    $payment->paypal_order_id
                );

                $unit = $this->validateRemote($payment, $remote);

                $captures = $unit['payments']['captures'] ?? [];

                if (
                    $capture
                    && ! $cancel
                    && ! $payment->expires_at->isPast()
                    && ($remote['status'] ?? '') === 'APPROVED'
                    && $captures === []
                ) {
                    $payment->capture_attempted_at ??= now();
                    $payment->status = 'capturing';
                    $payment->last_checked_at = now();
                    $payment->save();

                    $shouldCapture = true;

                    return $payment;
                }

                if (count($captures) === 1) {
                    $result = $captures[0];

                    if (
                        ($result['amount']['currency_code'] ?? '') !== 'USD'
                        || $this->quotes->cents(
                            (string) ($result['amount']['value'] ?? 'invalid')
                        ) !== $this->quotes->cents($payment->payment_amount)
                        || ! is_string($result['id'] ?? null)
                    ) {
                        throw new \RuntimeException(
                            'Captured amount does not match the saved payment.'
                        );
                    }

                    $payment->paypal_capture_id = $result['id'];

                    $state = $result['status'] ?? '';

                    if ($state === 'COMPLETED') {
                        $this->complete($payment, $order);
                    } elseif ($state === 'PENDING') {
                        $payment->status = 'pending';
                    } elseif (
                        in_array(
                            $state,
                            ['REFUNDED', 'PARTIALLY_REFUNDED'],
                            true
                        )
                    ) {
                        $this->paymentIssue(
                            $payment,
                            $order,
                            'refunded'
                        );
                    } elseif (
                        in_array(
                            $state,
                            ['DECLINED', 'FAILED'],
                            true
                        )
                    ) {
                        if ($payment->paid_at) {
                            $this->paymentIssue(
                                $payment,
                                $order,
                                'reversed'
                            );
                        } else {
                            $this->release(
                                $payment,
                                $order,
                                'failed'
                            );
                        }
                    } else {
                        $payment->status = 'review_required';
                    }
                } elseif (count($captures) > 1) {
                    throw new \RuntimeException(
                        'Multiple captures require manual review.'
                    );
                } elseif ($payment->capture_attempted_at) {
                    // نتيجة التحصيل غير المؤكدة لا تسمح بإعادة المخزون.
                    $payment->status = 'review_required';
                } elseif (
                    $cancel
                    || $payment->expires_at->isPast()
                    || ($remote['status'] ?? '') === 'VOIDED'
                ) {
                    $this->release(
                        $payment,
                        $order,
                        $cancel ? 'cancelled' : 'expired'
                    );
                } else {
                    $payment->status = 'awaiting_approval';
                }

                $payment->last_error_code = null;
            } catch (\Throwable $exception) {
                $payment->refresh();
                $order->refresh();

                $payment->status = $payment->paid_at
                    ? 'completed'
                    : (
                        $payment->capture_attempted_at
                            ? 'review_required'
                            : 'retry_required'
                    );

                $payment->last_error_code = 'PAYPAL_CHECK_FAILED';

                Log::warning(
                    'PayPal payment check failed.',
                    [
                        'payment_reference' => $payment->reference,
                        'exception_class' => get_class($exception),
                    ]
                );
            }

            $payment->last_checked_at = now();
            $payment->save();

            return $payment;
        });
    }

    private function complete(
        CheckoutPayment $payment,
        Order $order
    ): void {
        DB::transaction(function () use ($payment, $order) {
            if (
                $payment->stock_released_at
                || $order->status === 'cancelled'
            ) {
                throw new \RuntimeException(
                    'Payment requires manual stock review.'
                );
            }

            $payment->status = 'completed';
            $payment->paid_at ??= now();

            $order->payment_status = 'paid';
            $order->save();

            if (! $payment->notification_queued_at) {
                AdminNotifier::create(
                    'Paid PayPal order received',
                    "Order #{$order->id} from {$order->customer_name} "
                        .'was paid through PayPal. Capture: '
                        .$payment->paypal_capture_id.'.',
                    'order',
                    $order->id
                );

                AdminNotifier::largeOrder($order->load('items'));

                SendSubmissionEmail::dispatch('order', $order->id)
                    ->onConnection('database')
                    ->onQueue('default')
                    ->beforeCommit();

                $payment->notification_queued_at = now();
            }

            $payment->save();
        });
    }

    private function paymentIssue(
        CheckoutPayment $payment,
        Order $order,
        string $status
    ): void {
        if ($payment->status !== $status) {
            AdminNotifier::create(
                'PayPal payment requires review',
                "Order #{$order->id}: payment {$status}. "
                    .'Review this order in PayPal before fulfilment.',
                'payment_review',
                $order->id
            );
        }

        $payment->status = $status;
        $order->payment_status = $status;
        $order->save();
    }

    private function release(
        CheckoutPayment $payment,
        Order $order,
        string $status
    ): void {
        if ($payment->stock_released_at || $payment->paid_at) {
            return;
        }

        $items = $order->items()
            ->get()
            ->groupBy('product_id');

        $products = Product::whereIn(
            'id',
            $items->keys()->filter()
        )->orderBy('id')->lockForUpdate()->get();

        foreach ($products as $product) {
            $product->stock +=
                (int) $items[$product->id]->sum('qty');

            $product->save();
        }

        $order->status = 'cancelled';
        $order->save();

        $payment->status = $status;
        $payment->stock_released_at = now();
        $payment->save();
    }

    public function summary(CheckoutPayment $payment): array
    {
        $payment->refresh();

        return [
            'reference' => $payment->reference,
            'status' => $payment->status,
            'environment' => $payment->environment,
            'order_amount' => $payment->order_amount,
            'payment_amount' => $payment->payment_amount,
            'payment_currency' => 'USD',
            'usd_to_jod' => $payment->usd_to_jod,
            'expires_at' => $payment->expires_at->toIso8601String(),

            'approval_url' => ! $payment->stock_released_at
                && ! $payment->paid_at
                && ! $payment->expires_at->isPast()
                    ? $payment->approval_url
                    : null,

            'receipt' => $payment->status === 'completed'
                ? app(CheckoutController::class)->receipt(
                    $payment->order()->firstOrFail()
                )
                : null,
        ];
    }
}
