<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Lấy danh sách đơn hàng của người dùng
$sql = "SELECT o.id, o.total, o.discount, o.shipping_fee, o.final_total, o.address, o.status, o.created_at, o.payment_method, 
               oi.product_id, oi.product_name, oi.product_option, oi.price, oi.quantity, oi.product_image
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[$row['id']]['order_info'] = [
        'total' => $row['total'],
        'discount' => $row['discount'],
        'shipping_fee' => $row['shipping_fee'],
        'final_total' => $row['final_total'],
        'address' => $row['address'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'payment_method' => $row['payment_method']
    ];
    if ($row['product_name']) {
        $orders[$row['id']]['items'][] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'product_option' => $row['product_option'],
            'price' => $row['price'],
            'quantity' => $row['quantity'],
            'product_image' => $row['product_image']
        ];
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fff5f7 0%, #f8e9ec 100%);
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
            padding: 40px 20px;
        }

        .orders-container {
            max-width: 1200px;
            margin: 0 auto;
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
            padding: 40px;
        }

        h2 {
            text-align: center;
            color: #ff6b81;
            font-size: 32px;
            font-weight: 600;
            margin-bottom: 32px;
        }

        .order {
            border-bottom: 1px solid #f0f0f0;
            padding: 24px 0;
            margin-bottom: 24px;
        }

        .order:last-child {
            border-bottom: none;
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .order-header h3 {
            font-size: 20px;
            color: #333;
            font-weight: 600;
        }

        .order-status {
            padding: 8px 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .order-status.pending {
            background: #ffe4b5;
            color: #d2691e;
        }

        .order-status.confirmed {
            background: #d4edda;
            color: #155724;
        }

        .order-status.cancelled {
            background:rgb(198, 110, 8);
            color:white;
        }

        .order-status.rejected {
            background:rgb(237, 5, 5);
            color:white;
        }

        .order-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 20px;
            align-items: center;
            margin-bottom: 16px;
        }

        .order-item img {
            width: 100%;
            height: 100px;
            object-fit: cover;
            border-radius: 12px;
        }

        .order-item-details p {
            margin: 4px 0;
            font-size: 15px;
            color: #555;
        }

        .order-item-details strong {
            color: #333;
        }

        .order-summary {
            background: #fff7f7;
            padding: 20px;
            border-radius: 16px;
            margin-top: 20px;
        }

        .order-summary p {
            font-size: 16px;
            color: #444;
            margin: 8px 0;
        }

        .order-summary .total {
            font-size: 18px;
            font-weight: 700;
            color: #ff0000;
        }

        @media (max-width: 768px) {
            .orders-container {
                padding: 24px;
            }

            .order-item {
                grid-template-columns: 80px 1fr;
                gap: 16px;
            }

            .order-item img {
                height: 80px;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            .order-item {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .order-item img {
                max-width: 120px;
                margin: 0 auto;
            }

            .order-summary .total {
                font-size: 16px;
            }
        }

        .review-btn {
            padding: 8px 16px;
            background: #ff6b81;
            color: #fff;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.3s;
        }

        .review-btn:hover {
            background: #e55a6f;
        }
    </style>
</head>

<body>
    <div class="orders-container">
        <h2>Đơn Hàng Của Tôi</h2>
        <?php if (empty($orders)): ?>
            <p style="text-align: center; color: #555;">Bạn chưa có đơn hàng nào.</p>
        <?php else: ?>
            <?php foreach ($orders as $order_id => $order): ?>
                <div class="order">
                    <div class="order-header">
                        <h3>Đơn hàng #<?php echo $order_id; ?> - <?php echo date('d/m/Y H:i', strtotime($order['order_info']['created_at'])); ?></h3>
                        <span class="order-status <?php
                                                    echo $order['order_info']['status'] == 'Chờ xử lý' ? 'pending' : ($order['order_info']['status'] == 'Đã xác nhận' ? 'confirmed' : ($order['order_info']['status'] == 'Đã từ chối' ? 'rejected' : 'cancelled')); ?>">
                            <?php echo htmlspecialchars($order['order_info']['status']); ?>
                        </span>
                    </div>
                    <?php foreach ($order['items'] as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars($item['product_image']); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            <div class="order-item-details">
                                <p><strong><?php echo htmlspecialchars($item['product_name']); ?></strong></p>
                                <p>Phân loại: <?php echo htmlspecialchars($item['product_option']); ?></p>
                                <p>Giá: <?php echo number_format($item['price']); ?>đ</p>
                                <p>Số lượng: <?php echo $item['quantity']; ?></p>
                            </div>
                            <div class="order-item-actions">
                                <p>Thành tiền: <?php echo number_format($item['price'] * $item['quantity']); ?>đ</p>
                                <?php if ($order['order_info']['status'] == 'Đã thanh toán'): ?>
                                    <a href="product_detail.php?id=<?php echo htmlspecialchars($item['product_id']); ?>" class="review-btn">Đánh giá sản phẩm</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="order-summary">
                        <p>Địa chỉ giao hàng: <?php echo htmlspecialchars($order['order_info']['address']); ?></p>
                        <p>Phương thức thanh toán: <?php echo $order['order_info']['payment_method'] == 'COD' ? 'Thanh toán khi nhận hàng' : 'Thanh toán online'; ?></p>
                        <p>Tạm tính: <?php echo number_format($order['order_info']['total']); ?>đ</p>
                        <p>Giảm giá: <?php echo number_format($order['order_info']['discount']); ?>đ</p>
                        <p>Phí vận chuyển: <?php echo number_format($order['order_info']['shipping_fee']); ?>đ</p>
                        <p class="total">Tổng cộng: <?php echo number_format($order['order_info']['final_total']); ?>đ</p>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>

</html>