<?php
session_start();
require_once 'connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Kiểm tra order_id
if (!isset($_GET['id'])) {
    header("Location: my_orders.php");
    exit();
}

$order_id = $_GET['id'];

// Lấy thông tin đơn hàng
$sql = "SELECT id, created_at, total, status, address, payment_method FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "<h3 style='color:red;text-align:center;'>Đơn hàng không tồn tại hoặc không thuộc về bạn.</h3>";
    exit();
}

// Lấy danh sách sản phẩm trong đơn hàng
$order_items = [];
$sql = "SELECT product_name as name, product_image, quantity, price, product_option
        FROM order_items 
        WHERE order_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    error_log("No products found for order_id: $order_id");
}
while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi Tiết Đơn Hàng - Luna Beauty</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #fef2f2 0%, #f9e2e6 100%);
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
        }
        .order-item img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            border-radius: 0.75rem;
        }
        .order-item img[src=""], .order-item img:not([src]) {
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.875rem;
        }
        .order-item {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s;
        }
        .order-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .product-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a202c;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-2xl rounded-2xl p-6 sm:p-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-pink-600 text-center mb-8">Chi Tiết Đơn Hàng #<?php echo htmlspecialchars($order['id']); ?></h1>
            
            <div class="order-info bg-pink-50 border border-pink-200 rounded-xl p-6 mb-8">
                <h3 class="text-xl font-semibold text-pink-600 mb-4">Thông Tin Đơn Hàng</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <p><strong>Ngày đặt:</strong> <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></p>
                    <p><strong>Tổng tiền:</strong> <?php echo number_format($order['total'], 0, ',', '.'); ?>đ</p>
                    <p><strong>Trạng thái:</strong> <?php echo htmlspecialchars($order['status']); ?></p>
                    <p><strong>Địa chỉ giao hàng:</strong> <?php echo htmlspecialchars($order['address']); ?></p>
                    <p class="sm:col-span-2"><strong>Phương thức thanh toán:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                </div>
            </div>

            <div class="order-items">
                <h3 class="text-2xl font-semibold text-pink-600 mb-6">Sản Phẩm Trong Đơn Hàng</h3>
                <?php if (empty($order_items)): ?>
                    <p class="text-gray-600 text-center py-4">Không tìm thấy sản phẩm nào trong đơn hàng này. Vui lòng kiểm tra lại dữ liệu.</p>
                <?php else: ?>
                    <div class="space-y-6">
                        <?php foreach ($order_items as $item): ?>
                            <div class="order-item flex flex-col sm:flex-row items-start sm:items-center bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <img src="<?php echo htmlspecialchars($item['product_image'] ?: ''); ?>" alt="<?php echo htmlspecialchars($item['name'] ?: 'Không có tên'); ?>" class="mr-6" onerror="this.src=''; this.alt='Không có ảnh';">
                                <div class="order-item-details flex-1">
                                    <h4 class="product-name"><?php echo htmlspecialchars($item['name'] ?: 'Sản phẩm không xác định'); ?></h4>
                                    <p class="text-sm text-gray-600 mt-2"><strong>Phân loại:</strong> <?php echo htmlspecialchars($item['product_option'] ?: 'Không có'); ?></p>
                                    <p class="text-sm text-gray-600"><strong>Số lượng:</strong> <?php echo $item['quantity'] ?: 0; ?></p>
                                    <p class="text-sm text-gray-600"><strong>Giá:</strong> <?php echo number_format($item['price'] ?: 0, 0, ',', '.'); ?>đ</p>
                                    <p class="text-sm text-gray-600"><strong>Tổng:</strong> <?php echo number_format(($item['price'] ?: 0) * ($item['quantity'] ?: 0), 0, ',', '.'); ?>đ</p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex flex-col sm:flex-row justify-center gap-4 mt-8">
                <a href="my_orders.php" class="inline-flex items-center px-6 py-3 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition-colors text-lg">
                    <i class="fas fa-arrow-left mr-2"></i> Quay lại
                </a>
                <a href="home.php" class="inline-flex items-center px-6 py-3 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition-colors text-lg">
                    <i class="fas fa-home mr-2"></i> Trở về trang chủ
                </a>
            </div>
        </div>
    </div>
</body>
</html>