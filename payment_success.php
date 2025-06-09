<?php
session_start();
require_once "connect.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra order_id
if (!isset($_GET['order_id'])) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Không tìm thấy đơn hàng.</h3>";
    exit();
}

$order_id = $_GET['order_id'];
$user_id = $_SESSION['user_id'];

// Lấy thông tin đơn hàng
$sql = "SELECT orders.*, GROUP_CONCAT(order_items.product_name SEPARATOR '<br>') AS product_names 
        FROM orders 
        LEFT JOIN order_items ON orders.id = order_items.order_id 
        WHERE orders.id = ? AND orders.user_id = ? 
        GROUP BY orders.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Đơn hàng không tồn tại hoặc không thuộc về bạn.</h3>";
    exit();
}

// Đóng kết nối
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán Thành Công</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
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
        display: flex;
        justify-content: center;
        align-items: center;
    }
    .success-container {
        background: #fff;
        max-width: 600px;
        width: 100%;
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
        padding: 40px;
        text-align: center;
    }
    .success-container h2 {
        color: #2ecc71;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 16px;
    }
    .success-container p {
        font-size: 16px;
        color: #555;
        margin-bottom: 24px;
    }
    .order-info {
        background: #fff7f7;
        padding: 20px;
        border-radius: 16px;
        border: 1px solid #ffe4e1;
        margin-bottom: 24px;
        text-align: left;
    }
    .order-info p {
        font-size: 16px;
        color: #444;
        margin: 8px 0;
    }
    .home-btn {
        display: inline-block;
        padding: 14px 32px;
        background: linear-gradient(90deg, #ff6b81 0%, #ff8e53 100%);
        color: #fff;
        border: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .home-btn:hover {
        background: linear-gradient(90deg, #ff4d68 0%, #ff7036 100%);
        box-shadow: 0 8px 24px rgba(255, 107, 129, 0.5);
        transform: translateY(-3px);
    }
    @media (max-width: 600px) {
        .success-container {
            padding: 24px;
        }
        .success-container h2 {
            font-size: 24px;
        }
        .order-info p {
            font-size: 14px;
        }
        .home-btn {
            padding: 12px 24px;
            font-size: 14px;
        }
    }
</style>
<body>
    <div class="success-container">
        <h2>Thanh Toán Thành Công!</h2>
        <p>Cảm ơn bạn đã mua hàng tại Luna Shop. Đơn hàng của bạn đã được xác nhận.</p>
        <div class="order-info">
            <p><strong>Mã đơn hàng:</strong> #<?php echo htmlspecialchars($order_id); ?></p>
            <p><strong>Sản phẩm:</strong> <?php echo $order['product_names']; ?></p>
            <p><strong>Tổng tiền:</strong> <?php echo number_format($order['final_total']); ?>đ</p>
            <p><strong>Phương thức thanh toán:</strong> Thanh toán online</p>
            <p><strong>Địa chỉ giao hàng:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
        </div>
        <a href="home.php" class="home-btn"><i class="fas fa-home"></i> Về Trang Chủ</a>
    </div>
</body>
</html>