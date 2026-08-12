<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận đơn hàng</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #f1b3c6;
            color: #fff;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px;
        }
        .content p {
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .order-details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .order-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .order-details th, .order-details td {
            text-align: left;
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .order-details th {
            color: #555;
        }
        .total-row td {
            font-weight: bold;
            font-size: 16px;
            color: #d84570;
        }
        .footer {
            background-color: #eee;
            color: #777;
            text-align: center;
            padding: 15px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Cảm ơn bạn đã đặt hàng!</h1>
        </div>
        <div class="content">
            <p>Chào <strong>{{ $orderData['customer_name'] }}</strong>,</p>
            <p>Đơn hàng của bạn đã được nhận và đang trong quá trình xử lý. Dưới đây là chi tiết đơn hàng của bạn:</p>
            
            <div class="order-details">
                <p><strong>Mã đơn hàng:</strong> {{ $orderData['mahd'] }}</p>
                <p><strong>Ngày đặt:</strong> {{ $orderData['date'] }}</p>
                <p><strong>Hình thức thanh toán:</strong> {{ $orderData['payment_method'] }}</p>
                <p><strong>Địa chỉ giao hàng:</strong> {{ $orderData['address'] }}</p>

                <h3 style="margin-top: 20px; border-bottom: 2px solid #f1b3c6; padding-bottom: 5px;">Sản phẩm</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Tên SP</th>
                            <th style="text-align: center;">SL</th>
                            <th style="text-align: right;">Giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($orderData['items'] as $item)
                        <tr>
                            <td>{{ $item['name'] }}</td>
                            <td style="text-align: center;">{{ $item['quantity'] }}</td>
                            <td style="text-align: right;">{{ number_format($item['price'], 0, ',', '.') }}đ</td>
                        </tr>
                        @endforeach
                        
                        @if($orderData['discount'] > 0)
                        <tr>
                            <td colspan="2" style="text-align: right; color: #28a745;">Giảm giá Voucher:</td>
                            <td style="text-align: right; color: #28a745;">-{{ number_format($orderData['discount'], 0, ',', '.') }}đ</td>
                        </tr>
                        @endif

                        <tr class="total-row">
                            <td colspan="2" style="text-align: right;">Tổng cộng:</td>
                            <td style="text-align: right;">{{ number_format($orderData['total'], 0, ',', '.') }}đ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p>Chúng tôi sẽ liên hệ với bạn trong thời gian sớm nhất để xác nhận đơn hàng và tiến hành giao hàng. Xin cảm ơn bạn đã lựa chọn Dola Bakery!</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} Dola Bakery. Tất cả các quyền được bảo lưu.
        </div>
    </div>
</body>
</html>
