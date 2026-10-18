@php
    $frontendUrl = rtrim((string) config('checkout.frontend_url', config('app.url')), '/');
@endphp
@php
    $isPaid = data_get($order ?? [], 'payment_status') === 'paid';
    $paymentReference = data_get($order ?? [], 'reference')
        ?? data_get($order ?? [], 'id');
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment status | Heart Made</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 48px 16px; background: #f7f3ee; color: #263b32; font-family: Arial, Helvetica, sans-serif; line-height: 1.7; }
        .card { max-width: 640px; margin: 40px auto; padding: clamp(24px, 5vw, 40px); border: 1px solid #e8dfd7; border-radius: 24px; background: #fff; text-align: center; }
        .brand { color: #a65f42; font-size: 12px; letter-spacing: 3px; }
        h1 { margin: 16px 0; font-size: clamp(25px, 5vw, 34px); line-height: 1.3; }
        p { color: #786e68; overflow-wrap: anywhere; }
        .button { display: inline-block; margin-top: 16px; padding: 12px 24px; border-radius: 999px; background: #263b32; color: #fff; text-decoration: none; }
        .button:focus-visible { outline: 3px solid #a65f42; outline-offset: 4px; }
    </style>
</head>
<body>
    <main class="card">
        <span class="brand">HEART MADE</span>
        <h1>{{ $isPaid ? 'Payment confirmed' : 'Check your payment status' }}</h1>
        @if ($isPaid)
            <p>Your payment has been confirmed. Thank you for supporting Heart Made.</p>
        @else
            <p>Opening this page does not confirm that a payment was completed.</p>
            <p>Return to checkout to verify the payment before placing another order.</p>
        @endif
        @if (filled($paymentReference))
            <p><strong>Reference:</strong> {{ $paymentReference }}</p>
        @endif
        <a class="button" href="{{ $frontendUrl }}/#/checkout">Return to checkout</a>
    </main>
</body>
</html>
