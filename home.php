<?php
session_start();
require_once 'connect.php';

// Truy vấn marquee
$marquee_content = '';
$resultMarquee = $conn->query("SELECT content FROM marquees WHERE is_active = 1 LIMIT 1");
if ($resultMarquee && $resultMarquee->num_rows > 0) {
    $marquee = $resultMarquee->fetch_assoc();
    $marquee_content = htmlspecialchars($marquee['content']);
}

// Truy vấn sliders
$sliders = [];
$resultSliders = $conn->query("SELECT image, link FROM sliders ORDER BY `order` ASC");
if ($resultSliders) {
    while ($row = $resultSliders->fetch_assoc()) {
        $sliders[] = $row;
    }
} else {
    error_log("SQL Error (sliders): " . $conn->error, 3, "errors.log");
}

// Truy vấn sản phẩm khuyến mãi (lấy 8 sản phẩm có price < 1000000)
$promotion_products = [];
$resultPromotionProducts = $conn->query("SELECT id, name, product_image, price, old_price FROM products WHERE price < 1000000 LIMIT 8");
if ($resultPromotionProducts) {
    while ($row = $resultPromotionProducts->fetch_assoc()) {
        $promotion_products[] = $row;
    }
} else {
    error_log("SQL Error (promotion_products): " . $conn->error, 3, "errors.log");
}

// Truy vấn sản phẩm nổi bật
$featured_products = [];
$resultFeaturedProducts = $conn->query("SELECT id, name, product_image, price, old_price, rating, sold, location FROM products ORDER BY sold DESC LIMIT 11");
if ($resultFeaturedProducts) {
    while ($row = $resultFeaturedProducts->fetch_assoc()) {
        $featured_products[] = $row;
    }
} else {
    error_log("SQL Error (featured_products): " . $conn->error, 3, "errors.log");
}

// Kiểm tra sản phẩm yêu thích của người dùng (nếu đã đăng nhập)
$favorite_products = [];
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $favorite_products[] = $row['product_id'];
    }
    $stmt->close();
}

// Truy vấn sản phẩm bán chạy nhất (1 sản phẩm có sold cao nhất)
$best_selling_product = null;
$resultBestSelling = $conn->query("SELECT id, name, product_image, price, old_price, rating, sold, location FROM products ORDER BY sold DESC LIMIT 1");
if ($resultBestSelling) {
    if ($resultBestSelling->num_rows > 0) {
        $best_selling_product = $resultBestSelling->fetch_assoc();
    }
} else {
    error_log("SQL Error (best_selling_product): " . $conn->error, 3, "errors.log");
}

// Truy vấn footer từ cơ sở dữ liệu
$footer_data = [
    'care_links' => [],
    'about_links' => [],
    'social_links' => [],
    'payment_methods' => [],
    'bottom_text' => ''
];
$resultFooter = $conn->query("SELECT section, content FROM footer_settings WHERE is_active = 1");
if ($resultFooter) {
    while ($row = $resultFooter->fetch_assoc()) {
        $section = $row['section'];
        $content = $section === 'bottom_text' ? $row['content'] : json_decode($row['content'], true);
        $footer_data[$section] = $content;
    }
} else {
    error_log("SQL Error (footer_settings): " . $conn->error, 3, "errors.log");
}

// Khởi tạo giỏ hàng
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$session_id = session_id();
$cart_count = 0;

// Truy vấn tổng số lượng sản phẩm trong giỏ hàng
$sql = "SELECT SUM(quantity) AS total_quantity FROM cart_items WHERE session_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $session_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $cart_count = $row['total_quantity'] ?? 0;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Trang chủ - Luna Beauty</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="./assets/fonts/fontawesome-free-6.4.0-web/fontawesome-free-6.4.0-web/css/all.min.css">
</head>
<style>
    /* Header styles */
    header {
        display: block;
        position: relative;
        z-index: 1000;
    }

    /* Category promotion */
    .category-promotion {
        margin-top: 10px;
        text-align: center;
    }

    .category-promotion__link {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        background: #e84a70;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-weight: bold;
        font-size: 16px;
        transition: background 0.3s;
    }

    .category-promotion__link i {
        margin-right: 8px;
    }

    .category-promotion__link:hover {
        background: #c73a5f;
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0, 0, 0, 0.5);
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 20px;
        border-radius: 8px;
        width: 80%;
        max-width: 1000px;
        position: relative;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    .close {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 24px;
        cursor: pointer;
        color: #333;
    }

    .close:hover {
        color: #e84a70;
    }

    .modal-title {
        font-family: 'Poppins', sans-serif;
        text-align: center;
        color: #e84a70;
        font-weight: bold;
        margin-bottom: 20px;
        font-size: 24px;
    }

    .modal-product-list {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        justify-content: center;
    }

    .modal-product-card {
        background: #fff;
        border: 1px solid #eee;
        border-radius: 8px;
        width: 180px;
        padding: 10px;
        text-align: center;
        transition: transform 0.2s;
    }

    .modal-product-img {
        position: relative;
    }

    .modal-product-img img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        border-radius: 4px;
    }

    .modal-product-title {
        font-size: 14px;
        margin: 10px 0;
        height: 40px;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .price .old-price {
        text-decoration: line-through;
        color: #999;
        font-size: 12px;
    }

    .price .new-price {
        color: #e84a70;
        font-weight: bold;
        font-size: 14px;
    }

    .modal-product-actions .view-detail {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 12px;
        background: #e84a70;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
    }

    .modal-product-actions .view-detail:hover {
        background: #c73a5f;
    }

    /* Favorite button */
    .product-actions {
        display: flex;
        gap: 10px;
        justify-content: center;
    }

    .favorite-btn {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        background: #f0f0f0;
        color: #333;
        text-decoration: none;
        border-radius: 4px;
        font-size: 12px;
        transition: background 0.3s, color 0.3s;
    }

    .favorite-btn.favorited {
        background: #e84a70;
        color: white;
    }

    .favorite-btn:hover {
        background: #d0d0d0;
    }

    .favorite-btn.favorited:hover {
        background: #c73a5f;
    }

    /* News modal */
    #tinTucBox {
        max-height: 80vh;
        overflow-y: auto;
        scrollbar-width: thin;
        scrollbar-color: #e84a70 transparent;
        background: #fff;
        border-radius: 12px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        margin-top: 16px;
        position: relative;
    }

    #tinTucBox::-webkit-scrollbar {
        width: 8px;
    }

    #tinTucBox::-webkit-scrollbar-track {
        background: transparent;
    }

    #tinTucBox::-webkit-scrollbar-thumb {
        background: #e84a70;
        border-radius: 4px;
    }

    #tinTucBox::-webkit-scrollbar-thumb:hover {
        background: #c73a5f;
    }

    #tinTucBox h2 {
        color: #e84a70;
        font-size: 28px;
        font-weight: 700;
        text-align: center;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }

    .news-list {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    .news-item {
        background: #fff;
        border-radius: 10px;
        overflow: hidden;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
    }

    .news-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .news-item img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        border-bottom: 2px solid #e84a70;
    }

    .news-content {
        padding: 15px;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .news-content h3 {
        font-size: 18px;
        color: #333;
        margin: 0 0 10px;
        font-weight: 600;
        line-height: 1.4;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .news-content p {
        font-size: 14px;
        color: #666;
        margin: 0 0 10px;
        line-height: 1.5;
    }

    .news-content a {
        display: inline-block;
        padding: 8px 16px;
        background: #e84a70;
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        text-align: center;
        transition: background 0.3s ease;
        align-self: flex-start;
    }

    .news-content a:hover {
        background: #c73a5f;
    }

    /* Marquee styles */
    .marquee-container {
        padding: 10px 0;
        overflow: hidden;
        white-space: nowrap;
        position: relative;
        clear: both;
        width: 100%;
        margin-top: 10px;
    }

    .marquee-text {
        display: inline-block;
        font-size: 24px;
        color: #e84a70 !important;
        font-weight: bold;
        animation: marquee 20s linear infinite;
        line-height: 24px;
        min-width: 100%;
    }

    @keyframes marquee {
        0% {
            transform: translateX(100%);
        }

        100% {
            transform: translateX(-100%);
        }
    }

    .marquee-text:hover {
        animation-play-state: paused;
    }

    /* Best-sell styles */
.best-sell {
    flex: 0 0 180px;
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    margin-top: 20px;
}

.best-sell .category__heading {
    font-size: 18px;
    color: #e84a70;
    margin-bottom: 10px;
    text-align: center;
    font-weight: bold;
}

.best-sell .product-card {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    width: 100%;
}

.category__best-sell{
    width: 180px;
    margin-top: 70px;
}

.best-sell .product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

.best-sell .product-img {
    position: relative;
    width: 100%;
}

.best-sell .product-img img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    border-bottom: 1px solid #eee;
}

.best-sell .badge.discount {
    position: absolute;
    top: 10px;
    background: #ff9800;
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 12px;
}

.best-sell .product-info {
    padding: 10px;
    text-align: center;
}

.best-sell .product-title {
    font-size: 14px;
    margin: 5px 0;
    height: 40px;
    overflow: hidden;
    text-overflow: ellipsis;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    color: #333;
}

.best-sell .price {
    margin: 5px 0;
}

.best-sell .old-price {
    text-decoration: line-through;
    color: #999;
    font-size: 12px;
    margin-right: 5px;
}

.best-sell .new-price {
    color: #e84a70;
    font-weight: bold;
    font-size: 14px;
}

.best-sell .extra-info {
    font-size: 12px;
    color: #666;
    margin: 10px 0;
}

.best-sell .extra-info .rating {
    display: block;
}

.best-sell .location {
    display: block;
    margin-top: 5px;
}

.best-sell .product-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.best-sell .view-detail {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    background: #e84a70;
    color: white;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
    transition: background 0.3s;
}

.best-sell .view-detail:hover {
    background: #c73a5f;
}

.best-sell .favorite-btn {
    display: inline-flex;
    align-items: center;
    padding: 8px 12px;
    background: #f0f0f0;
    color: #333;
    border-radius: 4px;
    font-size: 12px;
    text-decoration: none;
    transition: background 0.3s, color 0.3s;
}

.best-sell .favorite-btn.favorited {
    background: #e84a70;
    color: white;
}

.best-sell .favorite-btn:hover {
    background: #d0d0d0;
}

.best-sell .favorite-btn.favorited:hover {
    background: #c73a5f;
}


/* Responsive */
@media (max-width: 768px) {
    .main-content {
        flex-direction: column;
    }
    .best-sell {
        flex: 0 0 auto;
        width: 100%;
    }
    .best-sell .product-img img {
        height: 150px;
    }
}

</style>

<body>
    <!-- Header -->
    <header>
        <div class="top-info">
            <div class="left"></div>
            <div class="right">
                <?php
                if (isset($_SESSION['username'])) {
                    echo "<span>Xin chào <strong>" . htmlspecialchars($_SESSION['username']) . "</strong></span>";
                } else {
                    echo '<a href="login.php">Bạn chưa đăng nhập</a>';
                }
                ?>
            </div>
        </div>

        <div class="topbar">
            <a href="home.php" class="logo">
                <img src="assets/images/logo1.png" alt="Luna Beauty" style="height: 140px;">
            </a>
            <form class="search-box" method="GET" action="search.php">
                <input type="text" name="query" placeholder="Tìm kiếm sản phẩm..." required>
                <button type="submit"><i class="fas fa-search"></i></button>
            </form>
            <div class="icon-container">
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="cart-count"><?php echo $cart_count; ?></span>
                </a>
                <a href="javascript:void(0)" class="setting-icon" onclick="toggleSettings()">
                    <i class="fa-solid fa-gear"></i>
                </a>
            </div>
            <div class="settings-page">
                <div class="settings-header">
                    <i class="fa-solid fa-arrow-left" onclick="closeSettings()"></i>
                    <h2>Thiết lập tài khoản</h2>
                </div>
                <div class="settings-section">
                    <div class="settings-title">Tài khoản của tôi</div>
                    <a href="account.php" class="settings-item">Tài khoản & Bảo mật</a>
                    <a href="change_address.php" class="settings-item">Địa Chỉ</a>
                    <a href="bank.php" class="settings-item">Tài khoản / Thẻ ngân hàng</a>
                </div>
                <div class="settings-section">
                    <div class="settings-title">Đơn hàng của tôi</div>
                    <a href="my_orders.php" class="settings-item">Đơn hàng</a>
                    <a href="my_favorites.php" class="settings-item">Yêu thích</a>
                </div>
                <div class="settings-section">
                    <div class="settings-title">Quản lý</div>
                    <?php
                    $username = isset($_SESSION['username']) ? $_SESSION['username'] : '';
                    $isAdmin = stripos($username, 'admin') !== false;
                    ?>
                    <a href="<?php echo $isAdmin ? 'admin.php' : '#'; ?>" class="settings-item" <?php echo !$isAdmin ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>Quản lý trang</a>
                    <div class="settings-item">Ngôn ngữ / Language<div class="subtext">Tiếng Việt</div>
                    </div>
                </div>
                <div class="settings-logout">
                    <a href="logout.php"><button>Đăng xuất</button></a>
                </div>
            </div>
        </div>

        <nav class="navbar">
            <a href="home.php"><i class="fa-solid fa-house"></i></a>
            <a href="#" onclick="openGioiThieu()">Giới thiệu</a>
            <a href="#" onclick="openTinTuc()">Tin tức</a>
            <a href="vouchers.php">Voucher</a>
            <a href="contact.php">Liên hệ</a>
        </nav>

        <div id="gioiThieuBox" style="display: none; background: rgb(255, 240, 245); padding: 20px; color: black; border-radius: 4px; position: relative; margin-top: 16px">
            <span onclick="closeGioiThieu()" style="position: absolute; top: 10px; right: 20px; font-size: 24px; cursor: pointer;">×</span>
            <h2>🌸 Giới thiệu về <strong>Luna Beauty</strong></h2>
            <p>Chào bạn đến với <strong>Luna Beauty</strong> – thế giới mỹ phẩm nơi vẻ đẹp tự nhiên được tôn vinh mỗi ngày!</p>
            <p><strong>Luna Beauty</strong> được thành lập với mong muốn mang đến cho bạn những sản phẩm chăm sóc da chính hãng, an toàn và hiệu quả...</p>
            <ul>
                <li>Sản phẩm 100% chính hãng, có đầy đủ hóa đơn – nguồn gốc rõ ràng.</li>
                <li>Tư vấn chăm sóc da chuyên sâu, phù hợp với từng loại da.</li>
                <li>Chính sách đổi trả minh bạch.</li>
                <li>Giao hàng toàn quốc.</li>
            </ul>
            <p><strong>Sứ mệnh:</strong> Chúng tôi tin rằng đẹp là khi bạn tự tin là chính mình.</p>
        </div>

        <div id="tinTucBox" style="display: none;">
            <span onclick="closeTinTuc()" style="position: absolute; top: 10px; right: 20px; font-size: 24px; cursor: pointer;">×</span>
            <h2><i class="fas fa-newspaper"></i> Tin tức mới nhất từ Luna Beauty</h2>
            <div class="news-list">
                <?php if (empty($promotion_products)): ?>
                    <p>Không có tin tức nào hiện tại.</p>
                <?php else: ?>
                    <?php foreach ($promotion_products as $product): ?>
                        <div class="news-item">
                            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="news-content">
                                <h3><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p>Giá: <span style="color: #e84a70;"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</span>
                                    <?php if ($product['old_price'] > 0): ?>
                                        <span style="text-decoration: line-through; color: #999;">(<?php echo number_format($product['old_price'], 0, ',', '.'); ?>đ)</span>
                                    <?php endif; ?>
                                </p>
                                <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>">Xem chi tiết</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Marquee -->
    <div class="marquee-container">
        <div class="marquee-text">
            <?php echo $marquee_content ?: '🌟 Chào mừng bạn đến với Luna Beauty! 🌟'; ?>
        </div>
    </div>

    <div class="main-content">
        <nav class="category">
            <h3 class="category__heading">
                <i class="category__heading_icon fa-solid fa-list"></i>
                DANH MỤC
            </h3>
            <ul class="category-list">
                <li class="category-item"><a href="skincare.php" class="category-item__link">Skincare</a></li>
                <li class="category-item"><a href="makeup.php" class="category-item__link">Makeup</a></li>
                <li class="category-item"><a href="haircare.php" class="category-item__link">Haircare</a></li>
                <li class="category-item"><a href="bodycare.php" class="category-item__link">Bodycare</a></li>
                <li class="category-item"><a href="perfume.php" class="category-item__link">Perfume</a></li>
            </ul>
            <div class="category-promotion">
                <a href="javascript:void(0)" class="category-promotion__link" onclick="openPromotionModal()">
                    <i class="fas fa-gift"></i> Khuyến mãi tháng 6
                </a>
            </div>
        </nav>

        <div id="promotionModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closePromotionModal()">×</span>
                <h2 class="modal-title">Khuyến Mãi Tháng 6</h2>
                <h3><i>*Chào hè tháng 6 LunaShop chúng tôi giảm giá 20% cho một số sản phẩm, xin cảm ơn quý khách hàng đã luôn ủng hộ cửa hàng của chúng tôi</i></h3>
                <div class="modal-product-list">
                    <?php if (empty($promotion_products)): ?>
                        <p>Không có sản phẩm khuyến mãi nào trong tháng 6.</p>
                    <?php else: ?>
                        <?php foreach ($promotion_products as $product): ?>
                            <div class="modal-product-card">
                                <div class="modal-product-img">
                                    <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <span class="badge discount">-20%</span>
                                </div>
                                <div class="modal-product-info">
                                    <h3 class="modal-product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <div class="price">
                                        <span class="old-price"><?php echo number_format($product['old_price'], 0, ',', '.'); ?>đ</span>
                                        <span class="new-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</span>
                                    </div>
                                    <div class="modal-product-actions">
                                        <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="view-detail">
                                            <i class="fas fa-eye"></i> Xem chi tiết
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="product-list">
            <div class="slider-container">
                <div class="slider">
                    <?php if (empty($sliders)): ?>
                        <p>Không có slider nào.</p>
                    <?php else: ?>
                        <?php $first = true; ?>
                        <?php foreach ($sliders as $slider): ?>
                            <a href="<?php echo htmlspecialchars($slider['link']); ?>" class="slide <?php echo $first ? 'active' : ''; ?>">
                                <img src="assets/<?php echo htmlspecialchars($slider['image']); ?>" alt="Slider">
                            </a>
                            <?php $first = false; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="slider-buttons">
                    <button class="prev">❮</button>
                    <button class="next">❯</button>
                </div>
            </div>
            <div class="featured-title">Sản Phẩm Nổi Bật</div>

            <?php if (empty($featured_products)): ?>
                <p>Không có sản phẩm nổi bật nào.</p>
            <?php else: ?>
                <?php foreach ($featured_products as $product): ?>
                    <div class="product-card" data-id="<?php echo htmlspecialchars($product['id']); ?>">
                        <div class="product-img">
                            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <?php
                            $discount = $product['old_price'] > 0 ? round((($product['old_price'] - $product['price']) / $product['old_price']) * 100) : 0;
                            if ($discount > 0):
                            ?>
                                <span class="badge discount">-<?php echo $discount; ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="product-info">
                            <h3 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h3>
                            <div class="price">
                                <?php if ($product['old_price'] > 0): ?>
                                    <span class="old-price"><?php echo number_format($product['old_price'], 0, ',', '.'); ?>đ</span>
                                <?php endif; ?>
                                <span class="new-price"><?php echo number_format($product['price'], 0, ',', '.'); ?>đ</span>
                            </div>
                            <div class="extra-info">
                                <span class="rating">★ <?php echo htmlspecialchars($product['rating']); ?> | Đã bán <?php echo number_format($product['sold'], 0, ',', '.'); ?></span>
                                <span class="location"><?php echo htmlspecialchars($product['location']); ?></span>
                            </div>
                            <div class="product-actions">
                                <a href="product_detail.php?id=<?php echo htmlspecialchars($product['id']); ?>" class="view-detail">
                                    <i class="fas fa-eye"></i> Xem chi tiết
                                </a>
                                <a href="javascript:void(0)" class="favorite-btn <?php echo in_array($product['id'], $favorite_products) ? 'favorited' : ''; ?>" data-product-id="<?php echo htmlspecialchars($product['id']); ?>">
                                    <i class="fas fa-heart"></i> Yêu thích
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="best-sell">
    <nav class="category__best-sell">
        <h4 class="category__heading">SẢN PHẨM BÁN CHẠY</h4>
        <?php if ($best_selling_product): ?>
            <div class="product-card" data-id="<?php echo htmlspecialchars($best_selling_product['id']); ?>">
                <div class="product-img">
                    <img src="<?php echo htmlspecialchars($best_selling_product['product_image']); ?>" alt="<?php echo htmlspecialchars($best_selling_product['name']); ?>">
                    <?php
                    $discount = $best_selling_product['old_price'] > 0 ? round((($best_selling_product['old_price'] - $best_selling_product['price']) / $best_selling_product['old_price']) * 100) : 0;
                    if ($discount > 0):
                    ?>
                        <span class="badge discount">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                </div>
                <div class="product-info">
                    <h3 class="product-title"><?php echo htmlspecialchars($best_selling_product['name']); ?></h3>
                    <div class="price">
                        <?php if ($best_selling_product['old_price'] > 0): ?>
                            <span class="old-price"><?php echo number_format($best_selling_product['old_price'], 0, ',', '.'); ?>đ</span>
                        <?php endif; ?>
                        <span class="new-price"><?php echo number_format($best_selling_product['price'], 0, ',', '.'); ?>đ</span>
                    </div>
                    <div class="extra-info">
                        <span class="rating">★ <?php echo htmlspecialchars($best_selling_product['rating']); ?> | Đã bán <?php echo number_format($best_selling_product['sold'], 0, ',', '.'); ?></span>
                        <span class="location"><?php echo htmlspecialchars($best_selling_product['location']); ?></span>
                    </div>
                    <div class="product-actions">
                        <a href="product_detail.php?id=<?php echo htmlspecialchars($best_selling_product['id']); ?>" class="view-detail">
                            <i class="fas fa-eye"></i> Xem chi tiết
                        </a>
                        <a href="javascript:void(0)" class="favorite-btn <?php echo in_array($best_selling_product['id'], $favorite_products) ? 'favorited' : ''; ?>" data-product-id="<?php echo htmlspecialchars($best_selling_product['id']); ?>">
                            <i class="fas fa-heart"></i> Yêu thích
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <p>Không có sản phẩm bán chạy nào.</p>
        <?php endif; ?>
    </nav>
</div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-column">
                <h4>CHĂM SÓC KHÁCH HÀNG</h4>
                <ul>
                    <?php if (!empty($footer_data['care_links'])): ?>
                        <?php foreach ($footer_data['care_links'] as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#">Trung tâm trợ giúp</a></li>
                        <li><a href="#">Hướng dẫn mua hàng</a></li>
                        <li><a href="#">Chính sách đổi trả</a></li>
                        <li><a href="#">Hướng dẫn thanh toán</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-column">
                <h4>VỀ CHÚNG TÔI</h4>
                <ul>
                    <?php if (!empty($footer_data['about_links'])): ?>
                        <?php foreach ($footer_data['about_links'] as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><?php echo htmlspecialchars($link['text']); ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#">Giới thiệu</a></li>
                        <li><a href="#">Tuyển dụng</a></li>
                        <li><a href="#">Điều khoản</a></li>
                        <li><a href="#">Bảo mật</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-column">
                <h4>THEO DÕI CHÚNG TÔI</h4>
                <ul>
                    <?php if (!empty($footer_data['social_links'])): ?>
                        <?php foreach ($footer_data['social_links'] as $link): ?>
                            <li><a href="<?php echo htmlspecialchars($link['url']); ?>"><i class="<?php echo htmlspecialchars($link['icon']); ?>"></i> <?php echo htmlspecialchars($link['text']); ?></a></li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li><a href="#"><i class="fab fa-facebook"></i> Facebook</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-youtube"></i> YouTube</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-column">
                <h4>PHƯƠNG THỨC THANH TOÁN</h4>
                <div class="payment-icons">
                    <?php if (!empty($footer_data['payment_methods'])): ?>
                        <?php foreach ($footer_data['payment_methods'] as $method): ?>
                            <img src="<?php echo htmlspecialchars($method['image']); ?>" alt="<?php echo htmlspecialchars($method['alt']); ?>">
                        <?php endforeach; ?>
                    <?php else: ?>
                        <img src="assets/images/payment/visa.png" alt="Visa">
                        <img src="assets/images/payment/mastercard.png" alt="MasterCard">
                        <img src="assets/images/payment/cod.png" alt="COD">
                        <img src="assets/images/payment/momo.png" alt="MoMo">
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p><?php echo htmlspecialchars($footer_data['bottom_text'] ?? '© 2025 Mỹ Phẩm 563. Địa chỉ: 123 Trần Duy Hưng, Hà Nội. ĐKKD: 0123456789.'); ?></p>
        </div>
    </footer>

    <script src="script.js"></script>
    <script>
        function toggleSettings() {
            document.querySelector(".settings-page").classList.toggle("open");
        }

        function closeSettings() {
            document.querySelector(".settings-page").classList.remove("open");
        }

        function openGioiThieu() {
            document.getElementById("gioiThieuBox").style.display = "block";
            document.body.classList.add("modal-open");
        }

        function closeGioiThieu() {
            document.getElementById("gioiThieuBox").style.display = "none";
            document.body.classList.remove("modal-open");
        }

        function openTinTuc() {
            document.getElementById("tinTucBox").style.display = "block";
            document.body.classList.add("modal-open");
        }

        function closeTinTuc() {
            document.getElementById("tinTucBox").style.display = "none";
            document.body.classList.remove("modal-open");
        }

        function openPromotionModal() {
            document.getElementById("promotionModal").style.display = "block";
            document.body.classList.add("modal-open");
        }

        function closePromotionModal() {
            document.getElementById("promotionModal").style.display = "none";
            document.body.classList.remove("modal-open");
        }

        window.onclick = function(event) {
            const modal = document.getElementById("promotionModal");
            if (event.target == modal) {
                modal.style.display = "none";
                document.body.classList.remove("modal-open");
            }
            const settingsPage = document.querySelector(".settings-page");
            const settingsIcon = document.querySelector(".setting-icon");
            if (!settingsPage.contains(event.target) && !settingsIcon.contains(event.target)) {
                settingsPage.classList.remove("open");
            }
        }

        document.querySelectorAll('.favorite-btn').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                fetch('add_to_favorites.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `product_id=${productId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.action === 'added') {
                                this.classList.add('favorited');
                                this.innerHTML = '<i class="fas fa-heart"></i> Yêu thích';
                            } else {
                                this.classList.remove('favorited');
                                this.innerHTML = '<i class="fas fa-heart"></i> Yêu thích';
                            }
                            alert(data.message);
                        } else {
                            alert(data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Đã xảy ra lỗi. Vui lòng thử lại.');
                    });
            });
        });
    </script>
</body>

</html>
