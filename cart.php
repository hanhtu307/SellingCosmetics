<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require_once 'connect.php';

// Kiểm tra xem có thông báo Toast hay không
$toast_message = $_SESSION['toast_message'] ?? '';
unset($_SESSION['toast_message']);

// Lấy session_id hiện tại
$session_id = session_id();

// Xử lý khi người dùng cập nhật số lượng (nếu có)
// (Có thể thêm logic cập nhật số lượng nếu cần)

$cart_items = [];
$sql = "SELECT * FROM cart_items WHERE session_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $session_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $cart_items[] = $row;
}

$total_price = 0;
foreach ($cart_items as $item) {
    $total_price += $item['price'] * $item['quantity'];
}

?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giỏ hàng - Luna Beauty</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<style>
    /* Global Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #fef6f8 0%, #fff9f2 100%);
        color: #2d2d2d;
        min-height: 100vh;
        padding: 40px 20px;
    }

    /* Wrap Container */
    .wrap {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
    }

    /* Cart Container */
    .cart-container {
        background: #ffffff;
        max-width: 1200px;
        margin: 0 auto;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        width: 100%;
    }

    h2 {
        text-align: center;
        color: #d6336c;
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
    }
    h2 i {
        color: #d6336c;
    }

    /* Toast Notification */
    .toast-message {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #34c759;
        color: #fff;
        padding: 15px 25px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 500;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        animation: slideFadeIn 0.5s ease;
    }

    /* Cart Table */
    .cart-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0 12px;
        margin-bottom: 30px;
    }

    .cart-table th,
    .cart-table td {
        padding: 15px 12px;
        text-align: center;
        font-size: 16px;
        background: #fff;
        border-bottom: none;
    }

    .cart-table th {
        background: #fff1f4;
        color: #d6336c;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 14px;
        padding: 12px 10px;
    }

    /* Phân bổ độ rộng cột */
    .cart-table th:nth-child(1), .cart-table td:nth-child(1) { /* Checkbox */
        width: 5%;
    }
    .cart-table th:nth-child(2), .cart-table td:nth-child(2) { /* Ảnh */
        width: 10%;
    }
    .cart-table th:nth-child(3), .cart-table td:nth-child(3) { /* Tên sản phẩm */
        width: 30%;
        text-align: left;
    }
    .cart-table th:nth-child(4), .cart-table td:nth-child(4) { /* Phân loại */
        width: 15%;
    }
    .cart-table th:nth-child(5), .cart-table td:nth-child(5) { /* Giá */
        width: 15%;
    }
    .cart-table th:nth-child(6), .cart-table td:nth-child(6) { /* Số lượng */
        width: 10%;
    }
    .cart-table th:nth-child(7), .cart-table td:nth-child(7) { /* Tổng tiền */
        width: 15%;
    }

    .cart-table tr {
        background: #fff;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        transition: transform 0.2s ease;
    }

    .cart-table tr:hover {
        transform: translateY(-2px);
        background: #fef6f8;
    }

    .cart-table img {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid #eee;
        display: block;
        margin: 0 auto;
    }

    .cart-table input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #d6336c;
        cursor: pointer;
        margin: 0 auto;
    }

    /* Buttons */
    .home-btn,
    .checkout-btn,
    .remove-btn {
        padding: 12px 25px;
        border: none;
        border-radius: 25px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        min-width: 160px;
        justify-content: center;
    }

    .home-btn {
        background: #fff;
        color: #d6336c;
        border: 2px solid #d6336c;
    }

    .home-btn:hover {
        background: #d6336c;
        color: #fff;
        box-shadow: 0 4px 12px rgba(214, 51, 108, 0.3);
    }

    .checkout-btn {
        background: linear-gradient(90deg, #d6336c 0%, #f76707 100%);
        color: #fff;
    }

    .checkout-btn:hover {
        background: linear-gradient(90deg, #c2255c 0%, #e8590c 100%);
        box-shadow: 0 6px 20px rgba(214, 51, 108, 0.4);
        transform: translateY(-2px);
    }

    .remove-btn {
        background: #fff;
        color: #e03131;
        border: 2px solid #e03131;
    }

    .remove-btn:hover {
        background: #e03131;
        color: #fff;
        box-shadow: 0 4px 12px rgba(224, 49, 49, 0.3);
    }

    /* Cart Footer */
    .cart-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding: 20px;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        flex-wrap: nowrap;
        width: 100%;
    }

    .cart-footer p {
        font-size: 20px;
        font-weight: 700;
        color: #d6336c;
        margin: 0;
    }

    .cart-footer .buttons {
        display: flex;
        gap: 15px;
        align-items: center;
        flex-shrink: 0;
        justify-content: flex-end; /* Align buttons to the right */
    }

    /* Form chứa nút Thanh toán */
    #checkout-form {
        display: inline-flex;
        align-items: center;
    }

    /* Ensure the outer checkout form aligns with the buttons row */
    form#checkout-form-outer {
        display: inline-flex;
        align-items: center;
        margin-left: 0;
    }

    /* Adjust wrap to ensure proper alignment */
    .wrap {
        position: relative;
    }

    /* Animations */
    @keyframes slideFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .cart-container {
            max-width: 100%;
            padding: 25px;
        }
        .cart-table th, .cart-table td {
            padding: 10px 8px;
            font-size: 14px;
        }
        .cart-table img {
            width: 50px;
            height: 50px;
        }
        .home-btn, .checkout-btn, .remove-btn {
            padding: 10px 20px;
            font-size: 14px;
            min-width: 140px;
        }
    }

    @media (max-width: 768px) {
        .cart-container {
            padding: 20px;
        }
        h2 {
            font-size: 24px;
        }
        .cart-table {
            display: block;
            overflow-x: auto;
            white-space: nowrap;
        }
        .cart-table th, .cart-table td {
            font-size: 12px;
            padding: 8px 6px;
        }
        .cart-footer {
            flex-direction: column;
            align-items: center;
            gap: 15px;
        }
        .cart-footer .buttons {
            width: 100%;
            justify-content: center; /* Center buttons on mobile */
        }
        .home-btn, .checkout-btn, .remove-btn {
            width: 100%;
            min-width: unset;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 15px 10px;
        }
        .cart-container {
            padding: 15px;
        }
        h2 {
            font-size: 20px;
        }
        .cart-table th, .cart-table td {
            font-size: 11px;
            padding: 6px 4px;
        }
        .cart-table img {
            width: 40px;
            height: 40px;
        }
        .home-btn, .checkout-btn, .remove-btn {
            padding: 8px 15px;
            font-size: 12px;
        }
        .cart-footer p {
            font-size: 16px;
        }
        .toast-message {
            top: 15px;
            right: 15px;
            padding: 10px 15px;
            font-size: 14px;
        }
    }
</style>

<body>
    <div class="wrap">
        <form method="POST" action="remove_from_cart.php">
            <div class="cart-container">
                <h2><i class="fas fa-shopping-cart"></i> Giỏ hàng của bạn</h2>

                <?php if ($toast_message): ?>
                    <div class="toast-message"><?= htmlspecialchars($toast_message); ?></div>
                <?php endif; ?>

                <table class="cart-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onclick="toggleSelectAll()"> Chọn tất cả</th>
                            <th>Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Phân loại</th>
                            <th>Giá</th>
                            <th>Số lượng</th>
                            <th>Tổng tiền</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($cart_items) > 0): ?>
                            <?php foreach ($cart_items as $item):
                                $subtotal = $item['price'] * $item['quantity'];
                            ?>
                                <tr>
                                    <td><input type="checkbox" name="selected_items[]" value="<?= $item['id']; ?>" onchange="updateCheckoutForm()"></td>
                                    <td><img src="assets/<?= htmlspecialchars($item['product_image']); ?>" alt="<?= htmlspecialchars($item['product_name']); ?>"></td>
                                    <td><?= htmlspecialchars($item['product_name']); ?></td>
                                    <td><?= htmlspecialchars($item['product_option']); ?></td>
                                    <td><?= number_format($item['price'], 0, '.', '.'); ?></td>
                                    <td><?= $item['quantity']; ?></td>
                                    <td><?= number_format($subtotal, 0, '.', '.'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7">Giỏ hàng của bạn hiện tại trống!</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if (count($cart_items) > 0): ?>
                    <div class="cart-footer">
                        <p>Tổng cộng: <?= number_format($total_price, 0, '.', '.'); ?></p>
                        <div class="buttons">
                            <button type="button" class="home-btn" onclick="window.location.href='home.php';">
                                <i class="fas fa-home"></i> Quay về trang chủ
                            </button>
                            <button type="submit" class="remove-btn">
                                <i class="fas fa-trash"></i> Xóa đã chọn
                            </button>
                            <form id="checkout-form" method="POST" action="checkout.php" style="display: inline;">
                                <?php foreach ($cart_items as $item): ?>
                                    <input type="hidden" name="items[<?= $item['id']; ?>][product_name]" value="<?= htmlspecialchars($item['product_name']); ?>">
                                    <input type="hidden" name="items[<?= $item['id']; ?>][product_price]" value="<?= $item['price']; ?>">
                                    <input type="hidden" name="items[<?= $item['id']; ?>][product_option]" value="<?= htmlspecialchars($item['product_option']); ?>">
                                    <input type="hidden" name="items[<?= $item['id']; ?>][product_img]" value="<?= htmlspecialchars($item['product_image']); ?>">
                                    <input type="hidden" name="items[<?= $item['id']; ?>][quantity]" value="<?= $item['quantity']; ?>">
                                <?php endforeach; ?>
                            </form>
                            <form method="POST" action="checkout.php" id="checkout-form-outer">
                                <!-- Các input hidden được JS thêm động -->
                                <button type="submit" id="checkout-btn" class="checkout-btn" disabled>
                                    <i class="fas fa-credit-card"></i> Thanh toán
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <button type="button" class="home-btn" onclick="window.location.href='home.php';">
                        <i class="fas fa-home"></i> Quay về trang chủ
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <script>
        function toggleSelectAll() {
            var selectAll = document.getElementById('select-all');
            var checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            updateCheckoutForm();
        }

        function updateCheckoutForm() {
            var checkboxes = document.querySelectorAll('input[name="selected_items[]"]:checked');
            var checkoutBtn = document.getElementById('checkout-btn');
            var checkoutForm = document.getElementById('checkout-form-outer');
            checkoutBtn.disabled = checkboxes.length === 0;

            // Xóa các input cũ
            checkoutForm.innerHTML = '';
            checkboxes.forEach(checkbox => {
                var id = checkbox.value;
                var item = <?php echo json_encode($cart_items); ?>.find(i => i.id == id);
                checkoutForm.innerHTML += `
                    <input type="hidden" name="items[${id}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${id}][product_name]" value="${item.product_name}">
                    <input type="hidden" name="items[${id}][product_price]" value="${item.price}">
                    <input type="hidden" name="items[${id}][product_option]" value="${item.product_option || ''}">
                    <input type="hidden" name="items[${id}][product_img]" value="${item.product_image}">
                    <input type="hidden" name="items[${id}][quantity]" value="${item.quantity}">
                `;
            });
            checkoutForm.innerHTML += `
                <button type="submit" id="checkout-btn" class="checkout-btn" ${checkboxes.length === 0 ? 'disabled' : ''}>
                    <i class="fas fa-credit-card"></i> Thanh toán
                </button>
            `;
        }

        // Gọi hàm khi tải trang để kiểm tra trạng thái ban đầu
        window.onload = function() {
            updateCheckoutForm();
        };
    </script>
</body>

</html>
<?php
$conn->close();
?>