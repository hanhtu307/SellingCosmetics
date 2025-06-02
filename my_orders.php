<?php
session_start();
require_once 'connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Truy vấn danh sách đơn hàng
$orders = [];
$sql = "SELECT id, created_at, total, status FROM orders WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đơn Hàng Của Tôi - Luna Beauty</title>
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
        .order-table tbody tr {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s;
        }
        .order-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .status {
            font-weight: 600;
        }
        .status.pending {
            color: #d69e2e;
        }
        .status.cancelled {
            color: #e53e3e;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-2xl rounded-2xl p-6 sm:p-8">
            <h1 class="text-3xl font-bold text-pink-600 text-center mb-8">Đơn Hàng Của Tôi</h1>
            <?php if (empty($orders)): ?>
                <p class="text-gray-600 text-center text-lg py-4">Bạn chưa có đơn hàng nào.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="order-table w-full border-collapse">
                        <thead>
                            <tr class="bg-pink-50 text-pink-600">
                                <th class="p-4 text-left">Mã Đơn Hàng</th>
                                <th class="p-4 text-left">Ngày Đặt</th>
                                <th class="p-4 text-center">Tổng Tiền</th>
                                <th class="p-4 text-center">Trạng Thái</th>
                                <th class="p-4 text-center">Chi Tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr class="border-b border-gray-200">
                                    <td class="p-4">#<?php echo htmlspecialchars($order['id']); ?></td>
                                    <td class="p-4"><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                    <td class="p-4 text-center"><?php echo number_format($order['total'], 0, ',', '.'); ?>đ</td>
                                    <td class="p-4 text-center status <?php echo strtolower(str_replace(' ', '-', $order['status'])); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <a href="order_detail.php?id=<?php echo htmlspecialchars($order['id']); ?>" class="inline-flex items-center px-4 py-2 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition-colors">
                                            <i class="fas fa-eye mr-2"></i> Xem chi tiết
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <div class="flex justify-center mt-8">
                <a href="home.php" class="inline-flex items-center px-6 py-3 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition-colors text-lg">
                    <i class="fas fa-home mr-2"></i> Trở về trang chủ
                </a>
            </div>
        </div>
    </div>
</body>
</html>
