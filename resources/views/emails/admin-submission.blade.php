@php
    $currency = 'JD';
    $isOrder = $submissionType === 'order';
    $paymentStatus = match (strtolower((string) ($submission['payment_status'] ?? ''))) {
        'paid' => 'Paid',
        'unpaid', 'pending' => 'Awaiting payment',
        'refunded' => 'Refunded',
        'reversed' => 'Reversed',
        default => 'Not recorded',
    };

    $title = $isOrder ? 'A new order has arrived.' : 'A new corporate inquiry has arrived.';

    $reference = ($isOrder ? 'ORDER-' : 'INQUIRY-') . str_pad((string) $submission['id'], 6, '0', STR_PAD_LEFT);

    $fields = $isOrder
        ? [
            'Customer name' => $submission['customer_name'] ?? null,
            'Email address' => $submission['customer_email'] ?? null,
            'Phone number' => $submission['customer_phone'] ?? null,
            'Delivery address' => $submission['shipping_address'] ?? null,
            'Payment method' => $submission['payment_method'] ?? null,
            'Payment status' => $paymentStatus,
            'Order status' => ucfirst($submission['status'] ?? 'pending'),
        ]
        : [
            'Company name' => $submission['company'] ?? null,
            'Contact person' => $submission['contact'] ?? null,
            'Email address' => $submission['email'] ?? null,
            'Phone number' => $submission['phone'] ?? null,
            'Estimated quantity' => $submission['quantity'] ?? null,
        ];

    if ($isOrder && strtolower(trim((string) ($submission['payment_method'] ?? ''))) === 'paypal'
        && filled($submission['checkout_payment']['paypal_capture_id'] ?? null)) {
        $fields['PayPal capture ID'] = $submission['checkout_payment']['paypal_capture_id'];
    }

    $receivedAt = \Illuminate\Support\Carbon::parse($submission['created_at'])->format('M d, Y · H:i');

    $money = fn($value) => number_format((float) $value, 2, '.', ',') . ' ' . $currency;
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $reference }} | Heart Made</title>
</head>

<body style="margin:0;padding:0;background:#f3efe8;font-family:Arial,Helvetica,sans-serif;color:#263b32;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">
        {{ $title }} {{ $reference }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
        <tr>
            <td align="center" style="padding:32px 12px;">
                <table role="presentation" width="640" cellspacing="0" cellpadding="0"
                    style="width:100%;max-width:640px;background:#ffffff;border-radius:20px;overflow:hidden;">
                    <tr>
                        <td style="padding:32px;background:#263b32;color:#ffffff;">
                            <p style="margin:0 0 20px;font-size:13px;letter-spacing:3px;color:#dfc9aa;">
                                HEART MADE
                            </p>

                            <h1 style="margin:0;font-size:28px;line-height:1.3;font-weight:600;">
                                {{ $title }}
                            </h1>

                            <p style="margin:16px 0 0;font-size:14px;line-height:1.7;color:#e0e8df;">
                                Everything you need to review this submission
                                is included below.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px 32px 12px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td>
                                        <span
                                            style="display:inline-block;padding:9px 12px;background:#f6eee7;color:#a65f42;border-radius:8px;font-size:12px;font-weight:bold;">
                                            {{ $reference }}
                                        </span>
                                    </td>

                                    <td align="right" style="font-size:12px;color:#778178;">
                                        {{ $receivedAt }}
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:20px 32px;">
                            <h2 style="margin:0 0 18px;font-size:18px;">
                                {{ $isOrder ? 'Customer details' : 'Company details' }}
                            </h2>

                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                style="border-collapse:collapse;">
                                @foreach ($fields as $label => $value)
                                    <tr>
                                        <td width="38%" valign="top"
                                            style="padding:13px 8px 13px 0;border-bottom:1px solid #edf0eb;font-size:13px;color:#778178;">
                                            {{ $label }}
                                        </td>

                                        <td valign="top"
                                            style="padding:13px 0;border-bottom:1px solid #edf0eb;font-size:14px;line-height:1.6;word-break:break-word;">
                                            @if ($isOrder && $label === 'Delivery address')
                                                {!! nl2br(e(str_replace(
                                                    [', Building ', ', Apartment '],
                                                    ["\nBuilding ", "\nApartment "],
                                                    (string) ($value ?: 'Not provided')
                                                ))) !!}
                                            @else
                                                {{ filled($value) ? $value : 'Not provided' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>

                    @if ($isOrder)
                        <tr>
                            <td style="padding:12px 32px 24px;">
                                <h2 style="margin:0 0 18px;font-size:18px;">
                                    Order items
                                </h2>

                                <table width="100%" cellspacing="0" cellpadding="0"
                                    style="border-collapse:collapse;font-size:13px;">
                                    <thead>
                                        <tr style="background:#f5f7f2;">
                                            <th align="left" style="padding:12px 8px;">
                                                Product
                                            </th>

                                            <th align="center" style="padding:12px 6px;">
                                                Qty
                                            </th>

                                            <th align="right" style="padding:12px 6px;">
                                                Price
                                            </th>

                                            <th align="right" style="padding:12px 8px;">
                                                Total
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($submission['items'] ?? [] as $item)
                                            <tr>
                                                <td style="padding:14px 8px;border-bottom:1px solid #edf0eb;">
                                                    {{ $item['name'] }}
                                                </td>

                                                <td align="center"
                                                    style="padding:14px 6px;border-bottom:1px solid #edf0eb;">
                                                    {{ $item['qty'] }}
                                                </td>

                                                <td align="right"
                                                    style="padding:14px 6px;border-bottom:1px solid #edf0eb;white-space:nowrap;">
                                                    {{ $money($item['price']) }}
                                                </td>

                                                <td align="right"
                                                    style="padding:14px 8px;border-bottom:1px solid #edf0eb;white-space:nowrap;">
                                                    {{ $money((float) $item['price'] * (int) $item['qty']) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                <table role="presentation" width="100%" cellspacing="0" cellpadding="0"
                                    style="margin-top:18px;">
                                    <tr>
                                        <td style="padding:12px 0;font-size:18px;font-weight:bold;">
                                            Order total
                                        </td>

                                        <td align="right"
                                            style="padding:12px 0;font-size:20px;font-weight:bold;color:#a65f42;">
                                            {{ $money($submission['total']) }}
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    @else
                        <tr>
                            <td style="padding:12px 32px 24px;">
                                <h2 style="margin:0 0 14px;font-size:18px;">
                                    Request details
                                </h2>

                                <div
                                    style="padding:20px;background:#f8f6f1;border-radius:12px;font-size:14px;line-height:1.8;white-space:pre-wrap;word-break:break-word;">
                                    {{ filled($submission['details'] ?? null) ? $submission['details'] : 'No additional details provided.' }}
                                </div>
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td align="center" style="padding:12px 32px 32px;">
                            @if ($isOrder && !empty($invoiceUrl))
                                <a href="{{ $invoiceUrl }}"
                                    style="
            display: inline-block;
            margin: 0 8px 12px;
            padding: 15px 28px;
            background: #263b32;
            color: #ffffff;
            text-decoration: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: bold;
        ">
                                    Download invoice PDF
                                </a>
                            @endif

                            <a href="{{ $adminUrl }}"
                                style="display:inline-block;padding:15px 28px;background:#a65f42;color:#ffffff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:bold;">
                                Open admin dashboard
                            </a>

                            <p style="margin:18px 0 0;font-size:12px;line-height:1.7;color:#778178;">
                                Reply to this email to contact the sender,
                                when an email address is available.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td align="center"
                            style="padding:20px;background:#f8f6f1;font-size:12px;line-height:1.7;color:#778178;">
                            Heart Made · Good things, made well.
                            <br>
                            This email was generated by your website.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>
