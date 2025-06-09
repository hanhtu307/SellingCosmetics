<?php
session_start();
require_once "connect.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Lấy danh sách sản phẩm từ session
$cart_items = $_SESSION['checkout_items'] ?? [];
$voucher_id = $_SESSION['selected_voucher_id'] ?? null; // Đặt mặc định là null

if (empty($cart_items)) {
    echo "<h3 style='color:red;text-align:center;'>Không có sản phẩm nào để giao. Vui lòng quay lại giỏ hàng!</h3>";
    exit();
}

// Tính tổng tiền
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['product_price'] * $item['quantity'];
}

// Xử lý voucher
$discount = 0;
$valid_voucher_id = null; // Biến để lưu voucher_id hợp lệ
if ($voucher_id) {
    $sql = "SELECT discount, discount_type, min_order_value FROM vouchers WHERE id = ? AND is_active = 1 AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $voucher_id);
    $stmt->execute();
    $voucher = $stmt->get_result()->fetch_assoc();

    if ($voucher && $subtotal >= $voucher['min_order_value']) {
        $discount = ($voucher['discount_type'] === 'percentage') ? $subtotal * $voucher['discount'] : $voucher['discount'];
        $valid_voucher_id = $voucher_id; // Chỉ gán nếu voucher hợp lệ
    }
}
$total = $subtotal - $discount;

// Xử lý đơn hàng khi submit
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_delivery'])) {
    $user_id = $_SESSION['user_id'];
    $name = $_POST['name'];
    $phone = $_POST['phone'];
    $province = $_POST['province'];
    $address = $_POST['address'];
    $shipping_provider = $_POST['shipping_provider'];
    $payment_method = $_POST['payment_method'];
    $full_address = "$name - $phone - $address, $province";
    $status = "Chờ xử lý";
    $created_at = date("Y-m-d H:i:s");
    $updated_at = $created_at;
    $shipping_fee = ($province === 'Hà Nội' || $province === 'Hồ Chí Minh') ? 20000 : 30000;
    $final_total = $total + $shipping_fee;

    // Bắt đầu transaction để đảm bảo dữ liệu đồng bộ
    $conn->begin_transaction();

    try {
        // Insert vào bảng orders (header)
        $sql_order = "INSERT INTO orders (user_id, total, discount, shipping_fee, final_total, address, status, created_at, updated_at, voucher_id, payment_method, shipping_provider) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_order = $conn->prepare($sql_order);
        // Nếu voucher_id không hợp lệ, gán null
        $stmt_order->bind_param("iddddssssiss", $user_id, $subtotal, $discount, $shipping_fee, $final_total, $full_address, $status, $created_at, $updated_at, $valid_voucher_id, $payment_method, $shipping_provider);
        $stmt_order->execute();
        $order_id = $conn->insert_id;

        // Insert vào bảng order_items (line items)
        $sql_item = "INSERT INTO order_items (order_id, product_name, product_option, price, quantity, product_image) 
                     VALUES (?, ?, ?, ?, ?, ?)";
        $stmt_item = $conn->prepare($sql_item);

        foreach ($cart_items as $item) {
            $stmt_item->bind_param("issdis", $order_id, $item['product_name'], $item['product_option'], $item['product_price'], $item['quantity'], $item['product_img']);
            $stmt_item->execute();
        }

        // Commit transaction
        $conn->commit();

        // Lưu thông tin đơn hàng vào session tạm thời
        $_SESSION['order_temp'] = [
            'order_id' => $order_id,
            'cart_items' => $cart_items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping_fee' => $shipping_fee,
            'final_total' => $final_total,
            'address' => $full_address,
            'voucher_id' => $valid_voucher_id // Lưu voucher_id hợp lệ
        ];

        // Xóa session không liên quan, giữ checkout_items
        unset($_SESSION['selected_voucher_id']);

        // Redirect based on payment method
        if ($payment_method === "COD") {
            header("Location: confirm_order.php");
        } else if ($payment_method === "Online") {
            header("Location: online_payment.php?order_id=$order_id");
        }
        exit();
    } catch (Exception $e) {
        // Rollback nếu có lỗi
        $conn->rollback();
        echo "<h3 style='color:red;text-align:center;'>Đặt hàng thất bại: " . htmlspecialchars($e->getMessage()) . ". Vui lòng thử lại!</h3>";
    }
}

// Danh sách tỉnh thành Việt Nam (giữ nguyên)
$provinces = [
    "Hà Nội", "Hồ Chí Minh", "Hải Phòng", "Đà Nẵng", "Cần Thơ", "An Giang", "Bà Rịa - Vũng Tàu", "Bắc Giang", "Bắc Kạn",
    "Bạc Liêu", "Bắc Ninh", "Bến Tre", "Bình Định", "Bình Dương", "Bình Phước", "Bình Thuận", "Cà Mau", "Cao Bằng",
    "Đắk Lắk", "Đắk Nông", "Điện Biên", "Đồng Nai", "Đồng Tháp", "Gia Lai", "Hà Giang", "Hà Nam", "Hà Tĩnh", "Hải Dương",
    "Hậu Giang", "Hòa Bình", "Hưng Yên", "Khánh Hòa", "Kiên Giang", "Kon Tum", "Lai Châu", "Lâm Đồng", "Lạng Sơn", "Lào Cai",
    "Long An", "Nam Định", "Nghệ An", "Ninh Bình", "Ninh Thuận", "Phú Thọ", "Phú Yên", "Quảng Bình", "Quảng Nam", "Quảng Ngãi",
    "Quảng Ninh", "Quảng Trị", "Sóc Trăng", "Sơn La", "Tây Ninh", "Thái Bình", "Thái Nguyên", "Thanh Hóa", "Thừa Thiên Huế",
    "Tiền Giang", "Trà Vinh", "Tuyên Quang", "Vĩnh Long", "Vĩnh Phúc", "Yên Bái", "Phú Quốc"
];
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông Tin Giao Hàng</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<style>
    /* Giữ nguyên CSS từ mã gốc */
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
    .delivery-container {
        background: #fff;
        max-width: 900px;
        margin: 0 auto;
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
        padding: 40px;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .delivery-container:hover {
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
    .delivery-form-group {
        margin-bottom: 24px;
    }
    .delivery-form-group label {
        display: block;
        font-size: 16px;
        color: #444;
        margin-bottom: 8px;
    }
    .delivery-form-group input,
    .delivery-form-group select {
        width: 100%;
        padding: 12px;
        font-size: 16px;
        border: 1px solid #f0f0f0;
        border-radius: 12px;
        box-sizing: border-box;
    }
    .delivery-form-group select {
        appearance: none;
        background: #fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%23ff6b81" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>') no-repeat right 12px center;
        background-size: 12px;
        cursor: pointer;
    }
    .delivery-form-group input:focus,
    .delivery-form-group select:focus {
        border-color: #ff6b81;
        outline: none;
    }
    .delivery-form-group.payment-options {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .payment-options {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .payment-option {
        display: flex;
        align-items: center;
    }
    .payment-option input[type="radio"] {
        margin: 0;
        margin-right: 6px;
        width: 35px;
    }
    #online {
        width: 48px;
    }
    .payment-option label {
        margin: 0;
        font-size: 16px;
        color: #444;
        vertical-align: middle;
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
    .order-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    .order-item:last-child {
        border-bottom: none;
    }
    .order-item p {
        font-size: 16px;
        color: #444;
        margin: 0;
    }
    .order-summary .total {
        font-size: 20px;
        font-weight: 700;
        color: #ff0000;
        margin-top: 16px;
        text-align: right;
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
        .delivery-container {
            padding: 24px;
        }
        h2 {
            font-size: 24px;
        }
        .delivery-form-group input,
        .delivery-form-group select {
            padding: 10px;
            font-size: 15px;
        }
        .payment-option label {
            font-size: 15px;
        }
    }
    @media (max-width: 600px) {
        body {
            padding: 20px 12px;
        }
        .delivery-container {
            padding: 16px;
        }
        .order-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 8px;
        }
        .confirm-btn {
            padding: 14px;
            font-size: 15px;
        }
        .payment-option label {
            font-size: 14px;
        }
    }
</style>

<body>
    <div class="delivery-container">
        <h2>Thông Tin Giao Hàng</h2>

        <form method="POST" action="">
            <div class="delivery-form-group">
                <label for="name">Họ và tên:</label>
                <input type="text" id="name" name="name" required>
            </div>
            <div class="delivery-form-group">
                <label for="phone">Số điện thoại:</label>
                <input type="tel" id="phone" name="phone" pattern="[0-9]{10}" required>
            </div>
            <div class="delivery-form-group">
                <label for="province">Tỉnh/Thành phố:</label>
                <select id="province" name="province" required>
                    <option value="">Chọn tỉnh/thành phố</option>
                    <?php foreach ($provinces as $province): ?>
                        <option value="<?= htmlspecialchars($province) ?>"><?= htmlspecialchars($province) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="delivery-form-group">
                <label for="address">Địa chỉ cụ thể:</label>
                <input type="text" id="address" name="address" placeholder="Số nhà, đường, phường..." required>
            </div>
            <div class="delivery-form-group">
                <label for="shipping_provider">Đơn vị vận chuyển:</label>
                <select id="shipping_provider" name="shipping_provider" required>
                    <option value="">Chọn đơn vị vận chuyển</option>
                    <option value="GHTK">Giao Hàng Tiết Kiệm (GHTK)</option>
                    <option value="GHN">Giao Hàng Nhanh (GHN)</option>
                    <option value="Viettel Post">Viettel Post</option>
                </select>
            </div>
            <div class="delivery-form-group">
                <label>Phương thức thanh toán:</label>
                <div class="payment-options">
                    <div class="payment-option">
                        <label for="cod">Thanh toán khi nhận hàng (COD)</label>
                        <input type="radio" id="cod" name="payment_method" value="COD" required>
                    </div>
                    <div class="payment-option">
                        <label for="online">Thanh toán online (Thẻ/Túi tiền)</label>
                        <input type="radio" id="online" name="payment_method" value="Online">
                    </div>
                </div>
            </div>

            <div class="order-summary">
                <h3>Tóm Tắt Đơn Hàng</h3>
                <?php foreach ($cart_items as $item): ?>
                    <div class="order-item">
                        <p><?php echo htmlspecialchars($item['product_name']); ?> (<?php echo htmlspecialchars($item['product_option']); ?> x <?php echo $item['quantity']; ?>)</p>
                        <p><?php echo number_format($item['product_price'] * $item['quantity']); ?>đ</p>
                    </div>
                <?php endforeach; ?>
                <p>Phí vận chuyển: <span id="shipping-fee">Tính khi chọn tỉnh</span></p>
                <?php if ($discount > 0): ?>
                    <p>Giảm giá (Voucher): -<?php echo number_format($discount); ?>đ</p>
                <?php endif; ?>
                <p class="total">Tổng cộng: <?php echo number_format($total); ?>đ (chưa bao gồm phí vận chuyển)</p>
            </div>

            <input type="hidden" name="confirm_delivery" value="1">
            <button type="submit" class="confirm-btn"><i class="fas fa-check-circle"></i> Xác Nhận Đơn Hàng</button>
        </form>
    </div>

    <script>
        document.getElementById('province').addEventListener('change', function() {
            const province = this.value;
            let shippingFee = 30000; // Default fee
            if (province === 'Hà Nội' || province === 'Hồ Chí Minh') {
                shippingFee = 20000;
            }
            document.getElementById('shipping-fee').textContent = number_format(shippingFee) + 'đ';
            updateTotal();
        });

        function number_format(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function updateTotal() {
            let subtotal = <?php echo $total; ?>;
            let shippingFeeElement = document.getElementById('shipping-fee').textContent.replace('đ', '').replace(/\./g, '');
            let shippingFee = shippingFeeElement ? parseInt(shippingFeeElement) || 0 : 0;
            let grandTotal = subtotal + shippingFee;
            document.querySelector('.total').textContent = `Tổng cộng: ${number_format(grandTotal)}đ`;
        }

        window.onload = () => {
            updateTotal();
        };
    </script>
</body>
</html>

<?php
if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>