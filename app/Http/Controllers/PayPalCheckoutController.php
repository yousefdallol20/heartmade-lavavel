<?php

namespace App\Http\Controllers;

use App\Models\CheckoutPayment;
use App\Services\CheckoutQuote;
use App\Services\PayPalCheckout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayPalCheckoutController extends Controller
{
    public function __construct(
        private PayPalCheckout $checkout
    ) {}

    public function configuration(): JsonResponse
    {
        $available = $this->checkout->available();

        return response()->json([
            'available' => $available,

            'environment' => in_array(
                config('paypal.mode'),
                ['sandbox', 'live'],
                true
            ) ? config('paypal.mode') : null,

            'payment_currency' => 'USD',
            'order_currency' => 'JOD',

            'usd_to_jod' => $available
                ? $this->checkout->quotes->rate()
                : null,
        ]);
    }

    public function quote(
        Request $request,
        CheckoutQuote $quotes
    ): JsonResponse {
        abort_unless(
            $this->checkout->available(),
            503,
            'PayPal is temporarily unavailable.'
        );

        $data = $request->validate([
            'items' => [
                'required', 'array', 'min:1', 'max:100',
            ],

            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                'exists:products,id',
            ],

            'items.*.qty' => [
                'required', 'integer', 'min:1', 'max:100',
            ],
        ]);

        return response()->json([
            'data' => $quotes->create($data['items']),
        ]);
    }

    public function store(
        Request $request,
        CheckoutController $orders
    ): JsonResponse {
        $data = $orders->validatedData($request, true);

        $extra = $request->validate([
            'quote_token' => [
                'required', 'string', 'max:20000',
            ],

            'checkout_token' => [
                'required', 'uuid',
            ],
        ]);

        $payment = $this->checkout->start(
            $data,
            $extra['quote_token'],
            $extra['checkout_token']
        );

        return response()->json([
            'success' => true,
            'data' => $this->checkout->summary($payment),
        ], 201);
    }

    private function authorizePayment(
        Request $request,
        string $reference
    ): CheckoutPayment {
        $data = $request->validate([
            'checkout_token' => ['required', 'uuid'],
        ]);

        $payment = CheckoutPayment::where(
            'reference',
            $reference
        )->firstOrFail();

        abort_unless(
            hash_equals(
                $payment->checkout_token_hash,
                hash('sha256', $data['checkout_token'])
            ),
            403,
            'Invalid checkout token.'
        );

        return $payment;
    }

    public function status(
        Request $request,
        string $reference
    ): JsonResponse {
        $payment = $this->authorizePayment(
            $request,
            $reference
        );

        return response()->json([
            'data' => $this->checkout->summary(
                $this->checkout->refresh($payment)
            ),
        ]);
    }

    public function capture(
        Request $request,
        string $reference
    ): JsonResponse {
        $payment = $this->authorizePayment(
            $request,
            $reference
        );

        return response()->json([
            'data' => $this->checkout->summary(
                $this->checkout->refresh($payment, true)
            ),
        ]);
    }

    public function cancel(
        Request $request,
        string $reference
    ): JsonResponse {
        $payment = $this->authorizePayment(
            $request,
            $reference
        );

        return response()->json([
            'data' => $this->checkout->summary(
                $this->checkout->refresh($payment, false, true)
            ),
        ]);
    }

    public function returned(
        Request $request,
        string $payment
    ) {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring([
                'token',
                'PayerID',
            ]),
            403
        );

        $record = CheckoutPayment::where(
            'reference',
            $payment
        )->firstOrFail();

        abort_unless(
            hash_equals(
                (string) $record->paypal_order_id,
                (string) $request->query('token')
            ),
            403
        );

        return $this->redirect('returned');
    }

    public function cancelled(
        Request $request,
        string $payment
    ) {
        abort_unless(
            $request->hasValidSignatureWhileIgnoring([
                'token',
                'PayerID',
            ]),
            403
        );

        CheckoutPayment::where(
            'reference',
            $payment
        )->firstOrFail();

        return $this->redirect('cancelled');
    }

    private function redirect(string $result)
    {
        $url = rtrim(
            (string) config('checkout.frontend_url'),
            '/'
        );

        abort_unless(
            in_array(
                parse_url($url, PHP_URL_SCHEME),
                ['http', 'https'],
                true
            ),
            500
        );

        return redirect()->away(
            $url.'/#/checkout?paypal='.$result
        );
    }
}
