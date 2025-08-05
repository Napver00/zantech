<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - Order Confirmation</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f8f8;
            padding: 20px;
            margin: 0;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        .container {
            max-width: 600px;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            margin: auto;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }
        .header img {
            max-width: 120px;
            margin-bottom: 10px;
        }
        .header h2 {
            margin: 0;
            color: #2c3e50;
            font-size: 28px;
        }
        .invoice-info {
            text-align: right;
            margin-bottom: 30px;
            color: #6c757d;
        }
        .invoice-info p {
            margin: 4px 0;
        }
        .billed-to {
            margin-bottom: 30px;
        }
        .billed-to strong {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
        }
        .billed-to span {
            display: block;
            color: #6c757d;
            line-height: 1.6;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table th, .summary-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
        }
        .summary-table td {
            color: #495057;
        }
        .summary-table .align-right {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            padding-top: 10px;
        }
        .totals table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 8px 0;
        }
        .totals .label {
            text-align: right;
            color: #6c757d;
        }
        .totals .value {
            text-align: right;
            font-weight: bold;
            color: #2c3e50;
        }
        .totals .grand-total .value {
            font-size: 20px;
            color: #0d6efd;
        }
        .footer {
            text-align: center;
            font-size: 12px;
            color: #777;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
        }
        .footer a {
            color: #0d6efd;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="https://raw.githubusercontent.com/Nakib00/zan-tech-invoice/refs/heads/main/ZAN%20Tech%20Logo.png" alt="ZAN Tech Logo">
            <h2>Invoice</h2>
        </div>
        <div class="invoice-info">
            <p><strong>Invoice Code:</strong> {{ $orderDetails->order->invoice_code }}</p>
            <p><strong>Date:</strong> {{ date('F d, Y') }}</p>
        </div>
        <div class="billed-to">
            <strong>Billed To:</strong>
            <span>{{ $orderDetails->user->name }}</span>
            <span>{{ $orderDetails->user->email }}</span>
            <span>{{ $orderDetails->shipping_address->address }}</span>
        </div>
        <h3>Order Summary</h3>
        <table class="summary-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="align-right">Quantity</th>
                    <th class="align-right">Price</th>
                    <th class="align-right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orderDetails->order_items as $item)
                    <tr>
                        <td>{{ $item->name }}</td>
                        <td class="align-right">{{ $item->quantity }}</td>
                        <td class="align-right">Tk {{ number_format($item->price, 2) }}</td>
                        <td class="align-right">Tk {{ number_format($item->quantity * $item->price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td class="label">Subtotal of Items:</td>
                    <td class="value">Tk {{ number_format($orderDetails->order->item_subtotal, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Shipping:</td>
                    <td class="value">Tk {{ number_format($orderDetails->order->shipping_charge, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Discount:</td>
                    <td class="value">Tk {{ number_format($orderDetails->order->discount, 2) }}</td>
                </tr>
                @if (isset($orderDetails->coupon))
                    <tr>
                        <td class="label">Coupon: {{ $orderDetails->coupon->code }}</td>
                        <td class="value">Tk {{ number_format($orderDetails->coupon->amount, 2) }}</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td class="label"><strong>Total:</strong></td>
                    <td class="value"><strong>Tk {{ number_format($orderDetails->order->total_amount, 2) }}</strong></td>
                </tr>
            </table>
        </div>

        <p style="text-align: center; margin-top: 30px;">Thank you for your purchase!</p>

        <div class="footer">
            <p>&copy; {{ date('Y') }} ZAN Tech. All rights reserved.</p>
            <p>For support, contact us at <a href="mailto:support@zantechbd.com">support@zantechbd.com</a></p>
        </div>
    </div>
</body>
</html>
