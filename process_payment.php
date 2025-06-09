<?php
session_start();
require_once "connect.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra order_id
if (!isset($_POST['order_id'])) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Không tìm thấy đơn hàng.</h3>";
    exit();
}

$order_id = $_POST['order_id'];
$bank = $_POST['bank'] ?? ''; // Ngân hàng được chọn (nếu có)
$user_id = $_SESSION['user_id'];

// Kiểm tra đơn hàng
$sql = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Đơn hàng không tồn tại hoặc không thuộc về bạn.</h3>";
    exit();
}

// Kiểm tra cart_items từ session order_temp
if (!isset($_SESSION['order_temp']) || empty($_SESSION['order_temp']['cart_items'])) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Không có thông tin sản phẩm trong đơn hàng.</h3>";
    exit();
}
$cart_items = $_SESSION['order_temp']['cart_items'];

// Kiểm tra voucher_id trong đơn hàng
if ($order['voucher_id']) {
    $sql = "SELECT id FROM vouchers WHERE id = ? AND is_active = 1 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order['voucher_id']);
    $stmt->execute();
    $voucher = $stmt->get_result()->fetch_assoc();
    if (!$voucher) {
        // Nếu voucher không hợp lệ, đặt voucher_id thành NULL
        $sql = "UPDATE orders SET voucher_id = NULL WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
    }
}

// Lấy username của người dùng
$sql = "SELECT username FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$username = $user['username'];

// Giả lập logic xử lý thanh toán (thay bằng API thanh toán thực tế)
$payment_success = true; // Thay bằng kiểm tra thực tế từ cổng thanh toán

if ($payment_success) {
    // Bắt đầu giao dịch
    $conn->begin_transaction();

    try {
        // Cập nhật trạng thái đơn hàng
        $sql = "UPDATE orders SET status = 'Đã thanh toán', payment_method = 'Online' WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $order_id, $user_id);
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

        // Tạo thông báo cho admin
        $message = "Đơn hàng mới #$order_id từ người dùng $username đã thanh toán " . number_format($order['final_total']) . " VNĐ.";
        $sql = "INSERT INTO notifications (order_id, message, user_id) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isi", $order_id, $message, $user_id);
        $stmt->execute();

        // Commit giao dịch
        $conn->commit();

        // Xóa session sau khi thanh toán thành công
        unset($_SESSION['checkout_items']);
        unset($_SESSION['order_temp']);

        // Chuyển hướng đến trang xác nhận thanh toán thành công
        header("Location: payment_success.php?order_id=$order_id");
        exit();
    } catch (Exception $e) {
        // Rollback giao dịch nếu có lỗi
        $conn->rollback();
        echo "<h3 style='color:red;text-align:center;'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</h3>";
        exit();
    }
} else {
    // Chuyển hướng đến trang lỗi thanh toán
    header("Location: payment_failed.php?order_id=$order_id");
    exit();
}

$stmt->close();
$conn->close();
?>