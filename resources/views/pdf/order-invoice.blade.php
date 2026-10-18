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
            padding: 25px;
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
            font-size: 25px;
        }

        .reference {
            margin-top: 8px;
            color: #e0e8df;
        }

        .section {
            margin-top: 24px;
        }

        .section-title {
            margin: 0 0 12px;
            font-size: 16px;
            color: #263b32;
        }

        .info-table,
        .items-table,
        .total-table {
            width: 100%;
            border-collapse: collapse;
        }

        .info-table td {
            padding: 7px 0;
            border-bottom: 1px solid #edf0eb;
            vertical-align: top;
        }

        .info-label {
            width: 32%;
            color: #778178;
        }

        .items-table th {
            padding: 11px 8px;
            background: #f5f7f2;
            text-align: left;
        }

        .items-table td {
            padding: 11px 8px;
            border-bottom: 1px solid #edf0eb;
        }

        .items-table .center {
            text-align: center;
        }

        .items-table .right {
            text-align: right;
        }

        .total-table {
            margin-top: 18px;
        }

        .total-table td {
            padding: 10px 8px;
            font-size: 16px;
            font-weight: bold;
        }

        .total-value {
            color: #a65f42;
            text-align: right;
        }

        .status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 5px;
            background: #f6eee7;
            color: #a65f42;
            font-weight: bold;
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
            margin-top: 35px;
            padding-top: 15px;
            border-top: 1px solid #edf0eb;
            color: #778178;
            text-align: center;
            font-size: 10px;
        }
    </style>
</head>

<body>
    <div class="header">
        <p class="brand">HEART MADE</p>

        <h1>Order invoice</h1>

        <div class="reference">
            {{ $reference }}
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">
            Customer information
        </h2>

        <table class="info-table">
            <tr>
                <td class="info-label">
                    Customer
                </td>

                <td>
                    {{ $order->customer_name }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Email
                </td>

                <td>
                    {{ $order->customer_email }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Phone
                </td>

                <td>
                    {{ $order->customer_phone }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Delivery address
                </td>

                <td>
                    {!! nl2br(e($deliveryAddress ?: 'Not provided')) !!}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Order date
                </td>

                <td>
                    {{ $order->created_at->format(
                        'd M Y, H:i'
                    ) }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Payment method
                </td>

                <td>
                    {{ $paymentMethod }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Payment status
                </td>

                <td>
                    {{ $paymentStatus }}
                </td>
            </tr>

            <tr>
                <td class="info-label">
                    Order status
                </td>

                <td>
                    <span class="status">
                        {{ $orderStatus }}
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">
            Order items
        </h2>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>

                    <th class="center">
                        Qty
                    </th>

                    <th class="right">
                        Unit price
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

        <table class="total-table">
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
    </div>

    <div class="footer">
        Heart Made - Good things, made well.
        <br>

        This invoice was generated electronically.
    </div>
</body>
</html>
