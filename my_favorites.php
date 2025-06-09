<?php
session_start();
require_once 'connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Xử lý xóa sản phẩm khỏi danh sách yêu thích
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_favorite'])) {
    $product_id = $_POST['product_id'];
    $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $stmt->close();
    header("Location: my_favorites.php");
    exit();
}

// Truy vấn danh sách sản phẩm yêu thích
$favorites = [];
$sql = "SELECT p.id, p.name, p.product_image, p.price, p.old_price 
        FROM favorites f 
        JOIN products p ON f.product_id = p.id 
        WHERE f.user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $favorites[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sản Phẩm Yêu Thích - Luna Beauty</title>
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
        .favorite-card {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s;
        }
        .favorite-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        .favorite-img img {
            width: 100%;
            height: 180px;
            object-fit: cover;
            border-radius: 0.5rem;
        }
        .favorite-img img[src=""], .favorite-img img:not([src]) {
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 0.875rem;
        }
        .favorite-title {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <div class="container mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-white shadow-2xl rounded-2xl p-6 sm:p-8">
            <h1 class="text-3xl font-bold text-pink-600 text-center mb-8">Sản Phẩm Yêu Thích</h1>
            <?php if (empty($favorites)): ?>
                <p class="text-gray-600 text-center text-lg py-4">Bạn chưa có sản phẩm yêu thích nào.</p>
            <?php else: ?>
                <div class="favorite-list grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    <?php foreach ($favorites as $product): ?>
                        <div class="favorite-card bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">
                            <div class="favorite-img">
                                <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" onerror="this.src=''; this.alt='Không có ảnh';">
                            </div>
                            <h3 class="favorite-title text-base font-medium text-gray-800 mt-3"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price mt-2">
                                <?php if ($product['old_price'] > 0): ?>
                                    <span class="line-through text-gray-500 text-sm"><?php echo number_format($product['old_price'], 0, ',', '.'); ?>đ</span>
                                <?php endif; ?>
                                <span class="text-pink-600 font-bold text-base"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="favorite-actions flex justify-center gap-2 mt-3">
                                <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="inline-flex items-center px-2 py-1 bg-pink-500 text-white rounded-lg hover:bg-pink-600 transition-colors text-sm">
                                    <i class="fas fa-eye mr-1"></i> Xem chi tiết
                                </a>
                                <form method="POST" class="inline-flex">
                                    <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($product['id']); ?>">
                                    <button type="submit" name="remove_favorite" class="inline-flex items-center px-2 py-1 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition-colors text-sm">
                                        <i class="fas fa-trash mr-1"></i> Xóa
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
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