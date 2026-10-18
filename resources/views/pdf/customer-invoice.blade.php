@php
    $currency = 'JD';
    $paymentStatus = match (strtolower((string) data_get($order, 'payment_status', ''))) {
        'paid' => 'Paid',
        'unpaid', 'pending' => 'Awaiting payment',
        'refunded' => 'Refunded',
        'reversed' => 'Reversed',
        default => 'Not recorded',
    };
    $orderStatus = match (strtolower((string) data_get($order, 'status', ''))) {
        'pending' => 'Pending',
        'confirmed' => 'Confirmed',
        'shipped' => 'Shipped',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled',
        default => 'Not recorded',
    };
    $paymentMethod = match (strtolower(trim((string) data_get($order, 'payment_method', '')))) {
        'paypal' => 'PayPal',
        'cod', 'cash on delivery' => 'Cash on Delivery',
        default => 'Not recorded',
    };
    $deliveryAddress = str_replace(
        [', Building ', ', Apartment '],
        ["\nBuilding ", "\nApartment "],
        (string) data_get($order, 'shipping_address', '')
    );
@endphp
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">

    <title>
        Invoice {{ $reference }}
    </title>

    <style>
        @page {
            margin: 35px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #263b32;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 12px;
            line-height: 1.5;
        }

        .header {
            padding: 28px;
            background: #263b32;
            color: #ffffff;
        }

        .brand {
            margin: 0 0 8px;
            color: #dfc9aa;
            font-size: 13px;
            letter-spacing: 3px;
        }

        .header h1 {
            margin: 0;
            font-size: 26px;
        }

        .header p {
            margin: 10px 0 0;
            color: #e0e8df;
        }

        .content {
            padding: 25px 5px;
        }

        .welcome {
            margin-bottom: 24px;
            padding: 18px;
            background: #f8f6f1;
            border-radius: 8px;
        }

        .welcome h2 {
            margin: 0 0 8px;
            color: #263b32;
            font-size: 17px;
        }

        .welcome p {
            margin: 0;
        }

        .details {
            width: 100%;
            margin-bottom: 25px;
            border-collapse: collapse;
        }

        .details td {
            padding: 8px 0;
            border-bottom: 1px solid #edf0eb;
        }

        .label {
            width: 32%;
            color: #778178;
        }

        .items {
            width: 100%;
            border-collapse: collapse;
        }

        .items th {
            padding: 12px 8px;
            background: #f5f7f2;
            text-align: left;
        }

        .items td {
            padding: 12px 8px;
            border-bottom: 1px solid #edf0eb;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .total {
            width: 100%;
            margin-top: 18px;
            border-collapse: collapse;
        }

        .total td {
            padding: 12px 8px;
            font-size: 17px;
            font-weight: bold;
        }

        .total-value {
            color: #a65f42;
            text-align: right;
        }

        .thanks {
            margin-top: 30px;
            padding: 20px;
            background: #f6eee7;
            text-align: center;
        }

        .thanks h3 {
            margin: 0 0 7px;
            font-size: 17px;
        }

        .thanks p {
            margin: 0;
            color: #778178;
        }

        .details, .info-table, .items, .items-table {
            table-layout: fixed;
        }

        .details td, .info-table td, .items td, .items-table td {
            vertical-align: top;
            word-wrap: break-word;
        }

        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .footer {
            margin-top: 25px;
            color: #778178;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <p class="brand">
            HEART MADE
        </p>

        <h1>
            Customer invoice
        </h1>

        <p>
            {{ $reference }}
        </p>
    </div>

    <div class="content">
        <div class="welcome">
            <h2>
                Thank you,
                {{ $order->customer_name }}!
            </h2>

            <p>
                Please keep this invoice for your records.
                Your current order and payment status are listed below.
            </p>
        </div>

        <table class="details">
            <tr>
                <td class="label">
                    Invoice number
                </td>

                <td>
                    {{ $reference }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Order date
                </td>

                <td>
                    {{ $order->created_at->format(
                        'd M Y, H:i'
                    ) }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Customer
                </td>

                <td>
                    {{ $order->customer_name }}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Delivery address
                </td>

                <td>
                    {!! nl2br(e($deliveryAddress ?: 'Not provided')) !!}
                </td>
            </tr>

            <tr>
                <td class="label">
                    Payment method
                </td>

                <td>
                    {{ $paymentMethod }}
                </td>
            </tr>
            <tr>
                <td class="label">Payment status</td>
                <td>{{ $paymentStatus }}</td>
            </tr>
            <tr>
                <td class="label">Order status</td>
                <td>{{ $orderStatus }}</td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th>Product</th>

                    <th class="center">
                        Quantity
                    </th>

                    <th class="right">
                        Price
                    </th>

                    <th class="right">
                        Total
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($order->items as $item)
                    <tr>
                        <td>
                            {{ $item->name }}
                        </td>

                        <td class="center">
                            {{ $item->qty }}
                        </td>

                        <td class="right">
                            {{ number_format(
                                (float) $item->price,
                                2
                            ) }}
                            {{ $currency }}
                        </td>

                        <td class="right">
                            {{ number_format(
                                (float) $item->price
                                * (int) $item->qty,
                                2
                            ) }}
                            {{ $currency }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="total">
            <tr>
                <td>Order total</td>

                <td class="total-value">
                    {{ number_format(
                        (float) $order->total,
                        2
                    ) }}
                    {{ $currency }}
                </td>
            </tr>
        </table>

        <div class="thanks">
            <h3>
                Thank you for choosing Heart Made
            </h3>

            <p>
                Good things, made well.
            </p>
        </div>

        <div class="footer">
            This invoice was generated electronically.
        </div>
    </div>
</body>
</html>
