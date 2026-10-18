@php
    $frontendUrl = rtrim((string) config('checkout.frontend_url', config('app.url')), '/');
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout interrupted | Heart Made</title>
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
        <h1>Checkout interrupted</h1>
        <p>You left the PayPal checkout before this page could confirm the payment.</p>
        <p>If you approved a payment, check its status before starting another payment. Contact us if you need help.</p>
        <a class="button" href="{{ $frontendUrl }}/#/checkout">Return to checkout</a>
    </main>
</body>
</html>
