<?php

namespace App\Http\Controllers;

use App\Models\CheckoutPayment;
use App\Services\PayPalCheckout;
use Illuminate\Http\Request;

class PayPalWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        PayPalCheckout $checkout
    ) {
        $event = $request->json()->all();

        $headers = [];

        foreach ([
            'paypal-transmission-id',
            'paypal-transmission-time',
            'paypal-cert-url',
            'paypal-auth-algo',
            'paypal-transmission-sig',
        ] as $key) {
            $headers[$key] = (string) $request->header($key);
        }

        // التحقق من التوقيع يسبق التعامل مع حالة الدفع.
        abort_unless(
            $checkout->gateway->verifyWebhook($headers, $event),
            403,
            'Invalid PayPal webhook signature.'
        );

        $type = (string) ($event['event_type'] ?? '');

        $id = str_starts_with($type, 'CHECKOUT.ORDER.')
            ? ($event['resource']['id'] ?? null)
            : (
                $event['resource']['supplementary_data']['related_ids']['order_id'] ?? null
            );

        $payment = $id
            ? CheckoutPayment::where(
                'paypal_order_id',
                $id
            )->first()
            : null;

        if (! $payment && isset($event['resource']['id'])) {
            $captureId =
                $event['resource']['supplementary_data']['related_ids']['capture_id']
                ?? $event['resource']['id'];

            $payment = CheckoutPayment::where(
                'paypal_capture_id',
                $captureId
            )->first();
        }

        if (
            ! $payment
            && str_starts_with($type, 'PAYMENT.CAPTURE.')
        ) {
            foreach ($event['resource']['links'] ?? [] as $link) {
                if (($link['rel'] ?? '') === 'up') {
                    $path = (string) parse_url(
                        (string) ($link['href'] ?? ''),
                        PHP_URL_PATH
                    );

                    if (
                        preg_match(
                            '~^/v2/payments/captures/([A-Za-z0-9]+)$~',
                            $path,
                            $matches
                        )
                    ) {
                        $payment = CheckoutPayment::where(
                            'paypal_capture_id',
                            $matches[1]
                        )->first();
                    }
                }
            }
        }

        if ($payment) {
            // الحالة النهائية تُجلب من API وليس من جسم الإشعار وحده.
            $checked = $checkout->refresh(
                $payment,
                $type === 'CHECKOUT.ORDER.APPROVED'
            );

            abort_if(
                $checked->last_error_code !== null,
                503,
                'Payment verification is temporarily unavailable.'
            );
        }

        return response()->json([
            'received' => true,
        ]);
    }
}
