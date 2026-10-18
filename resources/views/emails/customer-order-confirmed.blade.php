@php
    $currency = 'JD';
    $reference = 'ORDER-' . str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT);

    $paymentStatus = match (strtolower((string) ($order['payment_status'] ?? ''))) {
        'paid' => 'Paid',
        'unpaid', 'pending' => 'Awaiting payment',
        'refunded' => 'Refunded',
        'reversed' => 'Reversed',
        default => 'Not recorded',
    };
    $deliveryAddress = str_replace(
        [', Building ', ', Apartment '],
        ["\nBuilding ", "\nApartment "],
        (string) ($order['shipping_address'] ?? '')
    );

    $money = fn($value) => number_format((float) $value, 2, '.', ',') . ' ' . $currency;
@endphp

<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $reference }} confirmed</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background: #f3efe8;
        font-family: Arial, Helvetica, sans-serif;
        color: #263b32;
    ">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="padding: 32px 12px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0"
                    style="
                        width: 100%;
                        max-width: 640px;
                        background: #ffffff;
                        border-radius: 20px;
                        overflow: hidden;
                    ">
                    <tr>
                        <td
                            style="
                                padding: 32px;
                                background: #263b32;
                                color: #ffffff;
                            ">
                            <p
                                style="
                                    margin: 0 0 18px;
                                    color: #dfc9aa;
                                    letter-spacing: 3px;
                                    font-size: 13px;
                                ">
                                HEART MADE
                            </p>

                            <h1
                                style="
                                    margin: 0;
                                    font-size: 28px;
                                ">
                                Your order is confirmed
                            </h1>

                            <p
                                style="
                                    margin: 14px 0 0;
                                    line-height: 1.7;
                                    color: #e0e8df;
                                ">
                                Hello {{ $order['customer_name'] }},
                                we have confirmed {{ $reference }}
                                and will prepare it for delivery.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 28px 32px;">
                            <h2
                                style="
                                    margin: 0 0 16px;
                                    font-size: 18px;
                                ">
                                Order summary
                            </h2>

                            <table width="100%" cellspacing="0" cellpadding="0" style="border-collapse: collapse;">
                                @foreach ($order['items'] ?? [] as $item)
                                    <tr>
                                        <td
                                            style="
                                                padding: 12px 0;
                                                border-bottom: 1px solid #edf0eb;
                                            ">
                                            {{ $item['name'] }}
                                            ×
                                            {{ $item['qty'] }}
                                        </td>

                                        <td align="right"
                                            style="
                                                padding: 12px 0;
                                                border-bottom: 1px solid #edf0eb;
                                            ">
                                            {{ $money((float) $item['price'] * (int) $item['qty']) }}
                                        </td>
                                    </tr>
                                @endforeach

                                <tr>
                                    <td
                                        style="
                                            padding: 18px 0;
                                            font-weight: bold;
                                        ">
                                        Total
                                    </td>

                                    <td align="right"
                                        style="
                                            padding: 18px 0;
                                            font-weight: bold;
                                            color: #a65f42;
                                        ">
                                        {{ $money($order['total']) }}
                                    </td>
                                </tr>
                            </table>

                            <p
                                style="
                                    margin: 10px 0 0;
                                    line-height: 1.7;
                                ">
                                <strong>Delivery address:</strong>
                                <br>

                                {!! nl2br(e($deliveryAddress ?: 'Not provided')) !!}
                            </p>

                            <p style="margin:16px 0;line-height:1.7;">
                                <strong>Payment status:</strong> {{ $paymentStatus }}
                            </p>
                            <p style="margin:10px 0;color:#648272;">Free delivery across Jordan.</p>

                            @if (!empty($invoiceUrl))
                            <div style="
        margin-top: 25px;
        text-align: center;
    ">
                                <a href="{{ $invoiceUrl }}"
                                    style="
            display: inline-block;
            padding: 14px 26px;
            border-radius: 10px;
            background: #a65f42;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        ">
                                    Download invoice PDF
                                </a>
                            </div>

                            <p
                                style="
        margin: 12px 0 0;
        color: #778178;
        text-align: center;
        font-size: 11px;
    ">
                                The invoice link is available for 7 days.
                            </p>
                            @endif

                            <p
                                style="
                                    margin: 18px 0 0;
                                    line-height: 1.7;
                                    color: #778178;
                                ">
                                We will contact you if we need any
                                additional delivery details.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
