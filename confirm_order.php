<?php
session_start();
require_once "connect.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra order_temp
if (!isset($_SESSION['order_temp']) || empty($_SESSION['order_temp']['cart_items'])) {
    echo "<h3 style='color:red;text-align:center;'>Không có thông tin sản phẩm để đặt hàng. Vui lòng chọn lại!</h3>";
    exit();
}

// Lấy thông tin từ order_temp
$order_temp = $_SESSION['order_temp'];
$order_id = $order_temp['order_id'];
$cart_items = $order_temp['cart_items'];
$subtotal = $order_temp['subtotal'];
$discount = $order_temp['discount'];
$shipping_fee = $order_temp['shipping_fee'];
$grand_total = $order_temp['final_total'];
$address_value = $order_temp['address'];
$voucher_id = $order_temp['voucher_id'] ?? null;

// Lấy user_id
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Lấy phương thức thanh toán từ bảng orders
$sql = "SELECT payment_method FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$payment_method = $order['payment_method'] ?? 'COD';

// Xử lý voucher
$voucher_code = '';
$voucher_description = '';
$voucher_message = '';
if ($voucher_id) {
    $sql = "SELECT code, discount, discount_type, min_order_value 
            FROM vouchers 
            WHERE id = ? AND is_active = 1 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $voucher_id);
    $stmt->execute();
    $voucher = $stmt->get_result()->fetch_assoc();

    if ($voucher) {
        if ($subtotal >= $voucher['min_order_value']) {
            $voucher_code = $voucher['code'];
            if ($voucher['discount_type'] === 'percentage') {
                $voucher_description = "Giảm " . ($voucher['discount'] * 100) . "%";
            } else {
                $voucher_description = "Giảm " . number_format($voucher['discount']) . "đ";
            }
        } else {
            $voucher_message = "Voucher không áp dụng được: Đơn hàng chưa đạt giá trị tối thiểu (" . number_format($voucher['min_order_value']) . "đ)";
            $voucher_id = null;
        }
    } else {
        $voucher_message = "Voucher không hợp lệ hoặc đã hết hạn";
        $voucher_id = null;
    }
} else {
    $voucher_message = "Không sử dụng voucher";
}

// Xử lý địa chỉ giao hàng
$address_parts = explode(" - ", $address_value);
$recipient_name = $address_parts[0] ?? '';
$recipient_phone = $address_parts[1] ?? '';
$address_details = $address_parts[2] ?? $address_value;

// Trong phần xử lý khi nhấn xác nhận COD
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_order'])) {
    // Bắt đầu giao dịch
    $conn->begin_transaction();
    
    try {
        // Cập nhật trạng thái đơn hàng thành "Chờ xử lý"
        $sql = "UPDATE orders SET status = 'Chờ xử lý' WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();

        // Lấy ID của admin
        $sql = "SELECT id FROM users WHERE username LIKE '%admin%' LIMIT 1";
        $stmt_admin = $conn->prepare($sql);
        $stmt_admin->execute();
        $admin_result = $stmt_admin->get_result();
        if ($admin_result->num_rows > 0) {
            $admin_id = $admin_result->fetch_assoc()['id'];
        } else {
            throw new Exception("Không tìm thấy tài khoản admin!");
        }
        $stmt_admin->close();

        // Tạo thông báo cho admin
        $message = "Đơn hàng mới #{$order_id} từ người dùng {$username} cần được xác nhận.";
        $sql = "INSERT INTO notifications (user_id, order_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iis", $admin_id, $order_id, $message);
        $stmt->execute();

        // Cập nhật sold và stock cho từng sản phẩm trong đơn hàng
        foreach ($cart_items as $item) {
            $product_id = $item['product_id'];
            $quantity = $item['quantity'];

            // Cập nhật cột sold (tăng) và stock (giảm)
            $sql = "UPDATE products SET sold = sold + ?, stock = stock - ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iii", $quantity, $quantity, $product_id);
            $stmt->execute();

            // Kiểm tra nếu stock < 0, báo lỗi và rollback
            $sql = "SELECT stock FROM products WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $product = $result->fetch_assoc();

            if ($product['stock'] < 0) {
                throw new Exception("Sản phẩm " . htmlspecialchars($item['product_name']) . " đã hết hàng!");
            }
        }

        // Xóa các mục trong cart_items dựa trên session_id
        $session_id = session_id();
        $sql = "DELETE FROM cart_items WHERE session_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $session_id);
        $stmt->execute();

        // Commit giao dịch
        $conn->commit();

        // Xóa session sau khi xác nhận thành công
        unset($_SESSION['checkout_items']);
        unset($_SESSION['order_temp']);

        // Chuyển hướng đến trang thành công với order_id
        header("Location: order_success.php?order_id=$order_id");
        exit();
    } catch (Exception $e) {
        // Rollback giao dịch nếu có lỗi
        $conn->rollback();
        echo "<h3 style='color:red;text-align:center;'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</h3>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác Nhận Đơn Hàng</title>
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
    }

    .confirm-container {
        background: #fff;
        max-width: 900px;
        margin: 0 auto;
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
        padding: 40px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .confirm-container:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
    }

    h2 {
        text-align: center;
        color: #ff6b81;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 32px;
    }

    .product-header {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 32px;
        border-bottom: 2px solid #f0f0f0;
        padding-bottom: 24px;
        margin-bottom: 32px;
    }

    .product-header img {
        width: 100%;
        height: 200px;
        object-fit: cover;
        border-radius: 16px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
        transition: transform 0.3s ease;
    }

    .product-header img:hover {
        transform: scale(1.05);
    }

    .product-details h1 {
        font-size: 20px;
        color: #333;
        font-weight: 600;
        margin-bottom: 12px;
    }

    .product-details p {
        font-size: 16px;
        color: #555;
        margin: 8px 0;
    }

    .order-summary {
        background: #fff7f7;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 32px;
        border: 1px solid #ffe4e1;
    }

    .order-summary h3 {
        font-size: 20px;
        color: #ff6b81;
        font-weight: 500;
        margin-bottom: 16px;
    }

    .order-summary p {
        font-size: 16px;
        color: #444;
        margin: 8px 0;
    }

    .order-summary .voucher-info {
        color: #2ecc71;
        font-weight: 500;
    }

    .order-summary .total {
        font-size: 20px;
        font-weight: 700;
        color: #ff0000;
        margin-top: 16px;
    }

    .address-details, .payment-details {
        background: #fff7f7;
        padding: 24px;
        border-radius: 16px;
        margin-bottom: 32px;
        border: 1px solid #ffe4e1;
    }

    .address-details h3, .payment-details h3 {
        font-size: 20px;
        color: #ff6b81;
        font-weight: 500;
        margin-bottom: 16px;
    }

    .address-row, .payment-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
    }

    .address-info p, .payment-info p {
        font-size: 16px;
        color: #444;
        margin: 6px 0;
    }

    .change-address-btn, .change-payment-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 16px;
        background: #fff;
        border: 2px solid #ff6b81;
        color: #ff6b81;
        font-size: 15px;
        font-weight: 500;
        border-radius: 12px;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .change-address-btn:hover, .change-payment-btn:hover {
        background: #ff6b81;
        color: #fff;
        box-shadow: 0 6px 16px rgba(255, 107, 129, 0.4);
    }

    .confirm-btn {
        width: 100%;
        padding: 16px;
        font-size: 16px;
        font-weight: 500;
        background: linear-gradient(90deg, #ff6b81 0%, #ff8e53 100%);
        color: #fff;
        border: none;
        border-radius: 50px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .confirm-btn:hover {
        background: linear-gradient(90deg, #ff4d68 0%, #ff7036 100%);
        box-shadow: 0 8px 24px rgba(255, 107, 129, 0.5);
        transform: translateY(-3px);
    }

    @media (max-width: 768px) {
        .confirm-container {
            padding: 24px;
        }

        .product-header {
            grid-template-columns: 150px 1fr;
            gap: 24px;
        }

        .product-header img {
            height: 150px;
        }

        .product-details h1 {
            font-size: 24px;
        }

        .order-summary .total {
            font-size: 18px;
        }

        .address-details, .payment-details {
            padding: 20px;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 20px 12px;
        }

        .product-header {
            grid-template-columns: 1fr;
            gap: 16px;
            text-align: center;
        }

        .product-header img {
            max-width: 200px;
            margin: 0 auto;
        }

        .product-details h1 {
            font-size: 20px;
        }

        .confirm-btn {
            padding: 14px;
            font-size: 15px;
        }
    }
</style>

<body>
    <div class="confirm-container">
        <h2>Xác Nhận Đơn Hàng</h2>

        <!-- Hiển thị danh sách sản phẩm -->
        <?php foreach ($cart_items as $item): ?>
            <div class="product-header">
                <img src="<?= htmlspecialchars($item['product_img']) ?>" alt="<?= htmlspecialchars($item['product_name']) ?>">
                <div class="product-details">
                    <h1><?= htmlspecialchars($item['product_name']) ?></h1>
                    <p>Giá: <span><?= number_format($item['product_price']) ?></span>đ</p>
                    <p><strong>Phân loại:</strong> <?= htmlspecialchars($item['product_option']) ?></p>
                    <p><strong>Số lượng:</strong> <?= $item['quantity'] ?></p>
                    <p>Thành tiền: <?= number_format($item['product_price'] * $item['quantity']) ?>đ</p>
                </div>
            </div>
        <?php endforeach; ?>

        <!-- Hiển thị thông tin đơn hàng -->
        <div class="order-summary">
            <h3>Tóm tắt đơn hàng</h3>
            <p>Tạm tính: <?= number_format($subtotal) ?>đ</p>
            <?php if ($discount > 0): ?>
                <p class="voucher-info">Voucher (<?= htmlspecialchars($voucher_code) ?> - <?= htmlspecialchars($voucher_description) ?>): -<?= number_format($discount) ?>đ</p>
            <?php else: ?>
                <p class="voucher-info"><?= htmlspecialchars($voucher_message) ?></p>
            <?php endif; ?>
            <p>Phí vận chuyển: <?= number_format($shipping_fee) ?>đ</p>
            <p class="total">Tổng cộng: <span id="total"><?= number_format($grand_total) ?></span>đ</p>
        </div>

        <!-- Hiển thị địa chỉ giao hàng -->
        <div class="address-details">
            <h3>Địa chỉ giao hàng</h3>
            <div class="address-row">
                <div class="address-info">
                    <p><strong><?= htmlspecialchars($recipient_name) ?></strong></p>
                    <p>SĐT: <?= htmlspecialchars($recipient_phone) ?></p>
                    <p>Địa chỉ: <?= htmlspecialchars($address_details) ?></p>
                </div>
                <a href="change_address.php" class="change-address-btn" title="Thay đổi địa chỉ">
                    <i class="fas fa-pen"></i> Thay đổi
                </a>
            </div>
        </div>

        <!-- Hiển thị phương thức thanh toán -->
        <div class="payment-details">
            <h3>Phương thức thanh toán</h3>
            <div class="payment-row">
                <div class="payment-info">
                    <p><strong><?= $payment_method === 'COD' ? 'Thanh toán khi nhận hàng (COD)' : 'Thanh toán online' ?></strong></p>
                </div>
                <a href="delivery.php" class="change-payment-btn" title="Thay đổi phương thức thanh toán">
                    <i class="fas fa-pen"></i> Thay đổi
                </a>
            </div>
        </div>

        <!-- Form xác nhận đơn hàng -->
        <form method="POST" action="">
            <input type="hidden" name="confirm_order" value="1">
            <button type="submit" class="confirm-btn">Xác Nhận Đặt Hàng (COD)</button>
        </form>
    </div>
</body>

</html>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>