<?php
session_start();
require_once "connect.php";

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

// Kiểm tra user_id và quyền admin
$username = '';
$isAdmin = false;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $username = $user['username'];
        $isAdmin = stripos($username, 'admin') !== false;
    }
    $stmt->close();
}

// Nếu không phải admin, hiển thị thông báo lỗi
if (!$isAdmin) {
?>
    Không có quyền truy cập
    Bạn cần tài khoản admin để truy cập trang này.
    Quay lại trang chủ
<?php
    $conn->close();
    exit();
}

// Xác định trang hiện tại từ tham số URL
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Lấy từ khóa tìm kiếm nếu có và chuẩn hóa
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search = strtolower($search);

// Lấy dữ liệu thống kê
$totalStock = 0;
$itemProduct = 0;
$itemsDelivering = 0;
$itemsCustomer = 0;
$monthlyComments = [];
$resultComments = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total_comments
    FROM product_reviews
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultComments) {
    while ($row = $resultComments->fetch_assoc()) {
        $monthlyComments[] = [
            'month' => $row['month'],
            'total_comments' => (int)$row['total_comments']
        ];
    }
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

// Lấy số lượng sản phẩm bán được theo tháng
$monthlyProductsSold = [];
$resultProductsSold = $conn->query("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS month,
        SUM(oi.quantity) AS total_sold
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE YEAR(o.created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultProductsSold) {
    while ($row = $resultProductsSold->fetch_assoc()) {
        $monthlyProductsSold[] = [
            'month' => $row['month'],
            'total_sold' => (int)($row['total_sold'] ?? 0)
        ];
    }
}

// Lấy dữ liệu doanh thu theo tháng
$monthlyRevenue = [];
$resultRevenue = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(final_total) AS revenue
    FROM orders
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultRevenue) {
    while ($row = $resultRevenue->fetch_assoc()) {
        $monthlyRevenue[] = [
            'month' => $row['month'],
            'revenue' => (float)$row['revenue']
        ];
    }
}

$resultOrders = $conn->query("SELECT SUM(stock) AS total_stock FROM products");
if ($resultOrders) {
    $row = $resultOrders->fetch_assoc();
    $totalStock = $row['total_stock'];
}


$resultProducts = $conn->query("SELECT count(*) id from products");
if ($resultProducts) $itemProduct = $resultProducts->fetch_assoc()['id'] ?? 0;

$resultDelivering = $conn->query("SELECT COUNT(*) as delivering FROM orders WHERE status = 'Chờ xử lý'");
if ($resultDelivering) $itemsDelivering = $resultDelivering->fetch_assoc()['delivering'];

$resultReviews = $conn->query("SELECT COUNT(*) AS total_users FROM users WHERE username NOT LIKE '%admin%'");
if ($resultReviews) $itemsCustomer = $resultReviews->fetch_assoc()['total_users'];

// Lấy dữ liệu doanh thu theo tháng
$monthlyRevenue = [];
$resultRevenue = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(final_total) AS revenue
    FROM orders
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultRevenue) {
    while ($row = $resultRevenue->fetch_assoc()) {
        $monthlyRevenue[] = [
            'month' => $row['month'],
            'revenue' => (float)$row['revenue']
        ];
    }
}

// Lấy dữ liệu thông báo
// Trong phần lấy dữ liệu thông báo
$notifications = [];
if ($page == 'notification' && $search) {
    $stmt = $conn->prepare("SELECT n.id, n.order_id, n.message, n.is_read, n.created_at, u.username, n.user_id, o.status, o.payment_time 
                           FROM notifications n 
                           LEFT JOIN users u ON n.user_id = u.id 
                           LEFT JOIN orders o ON n.order_id = o.id 
                           WHERE LOWER(n.message) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $resultNotifications = $stmt->get_result();
    while ($row = $resultNotifications->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
} else {
    $stmt = $conn->prepare("SELECT n.id, n.order_id, n.message, n.is_read, n.created_at, u.username, n.user_id, o.status, o.payment_time 
                           FROM notifications n 
                           LEFT JOIN users u ON n.user_id = u.id 
                           LEFT JOIN orders o ON n.order_id = o.id 
                           ORDER BY n.created_at DESC");
    $stmt->execute();
    $resultNotifications = $stmt->get_result();
    if ($resultNotifications) {
        while ($row = $resultNotifications->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
    $stmt->close();
}

/// Xử lý xác nhận đơn hàng từ thông báo
if (isset($_POST['confirm_notification'])) {
    $notification_id = $_POST['notification_id'];
    $order_id = $_POST['order_id'];
    $action = $_POST['action'];

    $conn->begin_transaction();
    try {
        // Cập nhật trạng thái đơn hàng
        $status = ($action == 'accept') ? 'Đã xác nhận' : 'Đã từ chối';
        $sql = "UPDATE orders SET status = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $status, $order_id);
        $stmt->execute();

        // Đánh dấu thông báo là đã đọc
        $sql = "UPDATE notifications SET is_read = 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $notification_id);
        $stmt->execute();

        $conn->commit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<h3 style='color:red;text-align:center;'>Lỗi: " . htmlspecialchars($e->getMessage()) . "</h3>";
    }
}

// Xử lý xóa thông báo
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_notification']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?page=notification");
    exit();
}

// Lấy dữ liệu sản phẩm
$products = [];
if ($page == 'product' && $search) {
    $stmt = $conn->prepare("SELECT id, name, category, price, stock, product_image FROM products WHERE LOWER(name) LIKE ? OR LOWER(category) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
    $stmt->execute();
    $resultProducts = $stmt->get_result();
    while ($row = $resultProducts->fetch_assoc()) {
        $products[] = $row;
    }
    $stmt->close();
} else {
    $resultProducts = $conn->query("SELECT id, name, category, price, stock, product_image FROM products");
    if ($resultProducts) {
        while ($row = $resultProducts->fetch_assoc()) {
            $products[] = $row;
        }
    }
}

// Lấy dữ liệu đơn hàng
$orders = [];
if ($page == 'order' && $search) {
    $stmt = $conn->prepare("
        SELECT 
            o.id AS stt, 
            o.user_id, 
            o.total, 
            o.discount, 
            o.shipping_fee, 
            o.final_total, 
            o.address, 
            o.status, 
            o.created_at, 
            o.payment_method, 
            oi.product_name, 
            oi.product_option, 
            oi.price, 
            oi.quantity, 
            oi.product_image
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        LEFT JOIN users u ON o.user_id = u.id
        WHERE LOWER(u.username) LIKE ? OR LOWER(o.status) LIKE ? OR LOWER(o.created_at) LIKE ?
    ");
    $searchParam = "%$search%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $resultOrders = $stmt->get_result();
    while ($row = $resultOrders->fetch_assoc()) {
        $orders[] = $row;
    }
    $stmt->close();
} else {
    $resultOrders = $conn->query("
        SELECT 
            o.id AS stt, 
            o.user_id, 
            o.total, 
            o.discount, 
            o.shipping_fee, 
            o.final_total, 
            o.address, 
            o.status, 
            o.created_at, 
            o.payment_method, 
            oi.product_name, 
            oi.product_option, 
            oi.price, 
            oi.quantity, 
            oi.product_image
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
    ");
    if ($resultOrders) {
        while ($row = $resultOrders->fetch_assoc()) {
            $orders[] = $row;
        }
    }
}

// Lấy dữ liệu slider
$sliders = [];
if ($page == 'slider' && $search) {
    $stmt = $conn->prepare("SELECT id, name, image, link, `order` FROM sliders WHERE LOWER(name) LIKE ? OR LOWER(link) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
    $stmt->execute();
    $resultSliders = $stmt->get_result();
    while ($row = $resultSliders->fetch_assoc()) {
        $sliders[] = $row;
    }
    $stmt->close();
} else {
    $resultSliders = $conn->query("SELECT id, name, image, link, `order` FROM sliders");
    if ($resultSliders) {
        while ($row = $resultSliders->fetch_assoc()) {
            $sliders[] = $row;
        }
    }
}

// Lấy dữ liệu khách hàng
$customers = [];
if ($page == 'customer' && $search) {
    $stmt = $conn->prepare("SELECT id, username as name, phone, email FROM users WHERE (LOWER(username) LIKE ? OR LOWER(email) LIKE ? OR LOWER(phone) LIKE ?) AND username NOT LIKE '%admin%'");
    $searchParam = "%$search%";
    $stmt->bind_param("sss", $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $resultCustomers = $stmt->get_result();
    while ($row = $resultCustomers->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();
} else {
    $resultCustomers = $conn->query("SELECT id, username as name, phone, email FROM users WHERE username NOT LIKE '%admin%'");
    if ($resultCustomers) {
        while ($row = $resultCustomers->fetch_assoc()) {
            $customers[] = $row;
        }
    }
}

// Lấy dữ liệu danh mục
$categories = [];
if ($page == 'category' && $search) {
    $stmt = $conn->prepare("SELECT id, name, description FROM categories WHERE LOWER(name) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $resultCategories = $stmt->get_result();
    while ($row = $resultCategories->fetch_assoc()) {
        $categories[] = $row;
    }
    $stmt->close();
} else {
    $resultCategories = $conn->query("SELECT id, name, description FROM categories");
    if ($resultCategories) {
        while ($row = $resultCategories->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Lấy dữ liệu voucher
$vouchers = [];
if ($page == 'voucher' && $search) {
    $stmt = $conn->prepare("SELECT id, code, discount, discount_type, min_order_value, expires_at, is_active FROM vouchers WHERE LOWER(code) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $resultVouchers = $stmt->get_result();
    while ($row = $resultVouchers->fetch_assoc()) {
        $vouchers[] = $row;
    }
    $stmt->close();
} else {
    $resultVouchers = $conn->query("SELECT id, code, discount, discount_type, min_order_value, expires_at, is_active FROM vouchers");
    if ($resultVouchers) {
        while ($row = $resultVouchers->fetch_assoc()) {
            $vouchers[] = $row;
        }
    }
}

// Lấy dữ liệu liên hệ
$contacts = [];
if ($page == 'contact' && $search) {
    $stmt = $conn->prepare("SELECT id, name, email, phone, message, created_at FROM contacts WHERE LOWER(name) LIKE ? OR LOWER(email) LIKE ? OR LOWER(phone) LIKE ? OR LOWER(message) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
    $stmt->execute();
    $resultContacts = $stmt->get_result();
    while ($row = $resultContacts->fetch_assoc()) {
        $contacts[] = $row;
    }
    $stmt->close();
} else {
    $resultContacts = $conn->query("SELECT id, name, email, message, created_at FROM contact ORDER BY created_at DESC");
    if ($resultContacts) {
        while ($row = $resultContacts->fetch_assoc()) {
            $contacts[] = $row;
        }
    }
}

// Xử lý xóa liên hệ
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_contact']) && isset($_POST['id'])) {
    $id = $_POST['id'];
    $stmt = $conn->prepare("DELETE FROM contact WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
    header("Location: ?page=contact");
    exit();
}

// Lấy dữ liệu marquee
$marquees = [];
if ($page == 'marquee' && $search) {
    $stmt = $conn->prepare("SELECT id, content, is_active, updated_at FROM marquees WHERE LOWER(content) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $resultMarquees = $stmt->get_result();
    while ($row = $resultMarquees->fetch_assoc()) {
        $marquees[] = $row;
    }
    $stmt->close();
} else {
    $resultMarquees = $conn->query("SELECT id, content, is_active, updated_at FROM marquees ORDER BY updated_at DESC");
    if ($resultMarquees) {
        while ($row = $resultMarquees->fetch_assoc()) {
            $marquees[] = $row;
        }
    }
}

// Xử lý CRUD cho marquee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_marquee'])) {
        $content = $_POST['content'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO marquees (content, is_active) VALUES (?, ?)");
        $stmt->bind_param("si", $content, $is_active);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=marquee");
        exit();
    }
    if (isset($_POST['edit_marquee'])) {
        $id = $_POST['id'];
        $content = $_POST['content'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE marquees SET content = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sii", $content, $is_active, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=marquee");
        exit();
    }
    if (isset($_POST['delete_marquee']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM marquees WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=marquee");
        exit();
    }
}

// Lấy dữ liệu footer
$footer_sections = [];
if ($page == 'footer') {
    $resultFooter = $conn->query("SELECT id, section, content, is_active FROM footer_settings");
    if ($resultFooter) {
        while ($row = $resultFooter->fetch_assoc()) {
            $row['content'] = $row['section'] === 'bottom_text' ? $row['content'] : json_decode($row['content'], true);
            $footer_sections[$row['section']] = $row;
        }
    } else {
        error_log("SQL Error (footer_settings): " . $conn->error, 3, "errors.log");
    }
}

// Lấy dữ liệu navbar
$navbars = [];
if ($page == 'navbar' && $search) {
    $stmt = $conn->prepare("SELECT id, text, url, icon, is_active, order_num FROM navbar WHERE LOWER(text) LIKE ? OR LOWER(url) LIKE ?");
    $searchParam = "%$search%";
    $stmt->bind_param("ss", $searchParam, $searchParam);
    $stmt->execute();
    $resultNavbars = $stmt->get_result();
    while ($row = $resultNavbars->fetch_assoc()) {
        $navbars[] = $row;
    }
    $stmt->close();
} else {
    $resultNavbars = $conn->query("SELECT id, text, url, icon, is_active, order_num FROM navbar ORDER BY order_num ASC");
    if ($resultNavbars) {
        while ($row = $resultNavbars->fetch_assoc()) {
            $navbars[] = $row;
        }
    }
}

// Xử lý CRUD cho navbar
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $page == 'navbar') {
    if (isset($_POST['add_navbar'])) {
        $text = $_POST['text'];
        $url = $_POST['url'];
        $icon = $_POST['icon'] ?? '';
        $order_num = (int)$_POST['order_num'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO navbar (text, url, icon, order_num, is_active) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssii", $text, $url, $icon, $order_num, $is_active);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=navbar");
        exit();
    }
    if (isset($_POST['edit_navbar'])) {
        $id = $_POST['id'];
        $text = $_POST['text'];
        $url = $_POST['url'];
        $icon = $_POST['icon'] ?? '';
        $order_num = (int)$_POST['order_num'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE navbar SET text = ?, url = ?, icon = ?, order_num = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sssiii", $text, $url, $icon, $order_num, $is_active, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=navbar");
        exit();
    }
    if (isset($_POST['delete_navbar']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM navbar WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=navbar");
        exit();
    }
}

// Xử lý CRUD cho footer
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $page == 'footer') {
    if (isset($_POST['edit_footer'])) {
        $section = $_POST['section'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        if ($section === 'bottom_text') {
            $content = trim($_POST['bottom_text']);
        } else {
            $items = [];
            if ($section === 'payment_methods') {
                foreach ($_POST['image'] as $index => $image) {
                    if (!empty($image) && !empty($_POST['alt'][$index])) {
                        $items[] = ['image' => trim($image), 'alt' => trim($_POST['alt'][$index])];
                    }
                }
            } else {
                foreach ($_POST['text'] as $index => $text) {
                    if (!empty($text) && !empty($_POST['url'][$index])) {
                        $item = ['text' => trim($text), 'url' => trim($_POST['url'][$index])];
                        if ($section === 'social_links' && !empty($_POST['icon'][$index])) {
                            $item['icon'] = trim($_POST['icon'][$index]);
                        }
                        $items[] = $item;
                    }
                }
            }
            $content = json_encode($items, JSON_UNESCAPED_UNICODE);
        }

        $stmt = $conn->prepare("UPDATE footer_settings SET content = ?, is_active = ? WHERE section = ?");
        $stmt->bind_param("sis", $content, $is_active, $section);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=footer");
        exit();
    }
}


// Xử lý CRUD
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $name = $_POST['name'];
        $description = $_POST['description'];
        $stmt = $conn->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $description);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=category");
        exit();
    }
    if (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $stmt = $conn->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
        $stmt->bind_param("ssi", $name, $description, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=category");
        exit();
    }
    if (isset($_POST['delete_category']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=category");
        exit();
    }
    if (isset($_POST['add_product'])) {
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $product_image = '';
        if (isset($_POST['product_image']) && !empty($_POST['product_image'])) {
            $product_image = $_POST['product_image'];
        }
        $stmt = $conn->prepare("INSERT INTO product (name, category, price, stock, product_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssdss", $name, $category, $price, $stock, $product_image);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=product");
        exit();
    }
    if (isset($_POST['edit_product'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $category = $_POST['category'];
        $price = $_POST['price'];
        $stock = $_POST['stock'];
        $stmt = $conn->prepare("UPDATE product SET name = ?, category = ?, price = ?, stock = ? WHERE id = ?");
        $stmt->bind_param("ssdii", $name, $category, $price, $stock, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=product");
        exit();
    }
    if (isset($_POST['delete_product']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM product WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=product");
        exit();
    }
    if (isset($_POST['add_customer'])) {
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, phone, email, password) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $phone, $email, $password);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=customer");
        exit();
    }
    if (isset($_POST['edit_customer'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $stmt = $conn->prepare("UPDATE users SET username = ?, phone = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $name, $phone, $email, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=customer");
        exit();
    }
    if (isset($_POST['delete_customer']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=customer");
        exit();
    }
    if (isset($_POST['add_slider'])) {
        $name = $_POST['name'];
        $image = $_POST['image'];
        $link = $_POST['link'];
        $order = $_POST['order'];
        $stmt = $conn->prepare("INSERT INTO sliders (name, image, link, `order`) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $name, $image, $link, $order);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=slider");
        exit();
    }
    if (isset($_POST['edit_slider'])) {
        $id = $_POST['id'];
        $name = $_POST['name'];
        $image = $_POST['image'];
        $link = $_POST['link'];
        $order = $_POST['order'];
        $stmt = $conn->prepare("UPDATE sliders SET name = ?, image = ?, link = ?, `order` = ? WHERE id = ?");
        $stmt->bind_param("sssii", $name, $image, $link, $order, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=slider");
        exit();
    }
    if (isset($_POST['delete_slider']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM sliders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=slider");
        exit();
    }
    if (isset($_POST['edit_order'])) {
        $id = $_POST['id'];
        $status = $_POST['status'];
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=order");
        exit();
    }
    if (isset($_POST['delete_order']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM orders WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=order");
        exit();
    }
    if (isset($_POST['add_voucher'])) {
        $code = $_POST['code'];
        $discount = $_POST['discount'];
        $discount_type = $_POST['discount_type'];
        $min_order_value = $_POST['min_order_value'];
        $expires_at = $_POST['expires_at'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO vouchers (code, discount, discount_type, min_order_value, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdsdsi", $code, $discount, $discount_type, $min_order_value, $expires_at, $is_active);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=voucher");
        exit();
    }
    if (isset($_POST['edit_voucher'])) {
        $id = $_POST['id'];
        $code = $_POST['code'];
        $discount = $_POST['discount'];
        $discount_type = $_POST['discount_type'];
        $min_order_value = $_POST['min_order_value'];
        $expires_at = $_POST['expires_at'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $stmt = $conn->prepare("UPDATE vouchers SET code = ?, discount = ?, discount_type = ?, min_order_value = ?, expires_at = ?, is_active = ? WHERE id = ?");
        $stmt->bind_param("sdsdsii", $code, $discount, $discount_type, $min_order_value, $expires_at, $is_active, $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=voucher");
        exit();
    }
    if (isset($_POST['delete_voucher']) && isset($_POST['id'])) {
        $id = $_POST['id'];
        $stmt = $conn->prepare("DELETE FROM vouchers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        header("Location: ?page=voucher");
        exit();
    }
}

// Đăng xuất
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luna Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body>
    <div class="sidebar hidden" id="sidebar">
        <div class="sidebar-header">
            <h2>Luna Admin</h2>
            <button id="toggle-sidebar" class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <a href="?page=home" class="<?php echo $page == 'home' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Trang chủ</a>
        <a href="?page=category" class="<?php echo $page == 'category' ? 'active' : ''; ?>"><i class="fas fa-list"></i> Danh mục</a>
        <a href="?page=product" class="<?php echo $page == 'product' ? 'active' : ''; ?>"><i class="fas fa-box"></i> Sản phẩm</a>
        <a href="?page=order" class="<?php echo $page == 'order' ? 'active' : ''; ?>"><i class="fas fa-shopping-cart"></i> Đơn hàng</a>
        <a href="?page=slider" class="<?php echo $page == 'slider' ? 'active' : ''; ?>"><i class="fas fa-images"></i> Slider</a>
        <a href="?page=customer" class="<?php echo $page == 'customer' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Khách hàng</a>
        <a href="?page=voucher" class="<?php echo $page == 'voucher' ? 'active' : ''; ?>"><i class="fas fa-ticket-alt"></i> Voucher</a>
        <a href="?page=contact" class="<?php echo $page == 'contact' ? 'active' : ''; ?>"><i class="fa-solid fa-envelope"></i> Liên hệ</a>
        <a href="?page=marquee" class="<?php echo $page == 'marquee' ? 'active' : ''; ?>"><i class="fas fa-bullhorn"></i> Chữ chạy</a>
        <a href="?page=navbar" class="<?php echo $page == 'navbar' ? 'active' : ''; ?>"><i class="fas fa-bars"></i> Navbar</a>
        <a href="?page=notification" class="<?php echo $page == 'notification' ? 'active' : ''; ?>"><i class="fas fa-bell"></i> Thông báo</a>
        <a href="?page=footer" class="<?php echo $page == 'footer' ? 'active' : ''; ?>"><i class="fas fa-columns"></i> Footer</a>
        <a href="?logout=true"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a>
    </div>
    <div class="content">
        <button id="mobile-toggle-sidebar" class="toggle-btn mobile-toggle"><i class="fas fa-bars"></i></button>
        <div class="header">
            <h1><?php
                if ($page == 'home') echo 'TRANG CHỦ';
                elseif ($page == 'category') echo 'QUẢN LÝ DANH MỤC';
                elseif ($page == 'product') echo 'QUẢN LÝ SẢN PHẨM';
                elseif ($page == 'order') echo 'QUẢN LÝ ĐƠN HÀNG';
                elseif ($page == 'slider') echo 'QUẢN LÝ SLIDER';
                elseif ($page == 'customer') echo 'QUẢN LÝ KHÁCH HÀNG';
                elseif ($page == 'voucher') echo 'QUẢN LÝ VOUCHER';
                elseif ($page == 'contact') echo 'QUẢN LÝ LIÊN HỆ';
                elseif ($page == 'marquee') echo 'QUẢN LÝ CHỮ CHẠY';
                elseif ($page == 'notification') echo 'THÔNG BÁO';
                elseif ($page == 'footer') echo 'QUẢN LÝ FOOTER';

                ?></h1>
            <div>
                <span>Xin chào <?php echo htmlspecialchars($username); ?></span>
            </div>
        </div>
        <?php if ($page == 'home'): ?>
            <div class="stats">
                <div class="stat-box">
                    <h3><?php echo $totalStock; ?></h3>
                    <p>Tổng số hàng trong kho</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $itemProduct; ?></h3>
                    <p>Tổng số hàng</p>
                </div>
                <div class="stat-box">
                    <h3><?php echo $itemsCustomer; ?></h3>
                    <p>Tổng số khách hàng</p>
                </div>
            </div>
            <?php if (empty($monthlyRevenue) && empty($monthlyProductsSold) && empty($monthlyComments)): ?>
                <div style="text-align: center; color: #6B7280; padding: 20px;">
                    Chưa có dữ liệu để hiển thị.
                </div>
            <?php else: ?>
                <div style="width: 100%; max-width: 1000px; margin: 30px auto; padding: 20px; background: #FFFFFF; border-radius: 12px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); display: flex; gap: 20px;">
                    <div style="flex: 1; min-width: 300px;">
                        <canvas id="statsDoughnut"></canvas>
                    </div>
                    <div style="flex: 2;">
                        <canvas id="statsLine"></canvas>
                    </div>
                </div>
            <?php endif; ?>
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                <?php if (!empty($monthlyRevenue) || !empty($monthlyProductsSold) || !empty($monthlyComments)): ?>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Dữ liệu ban đầu
                        const initialData = <?php
                                            // Hợp nhất các tháng từ cả ba nguồn dữ liệu
                                            $allMonths = [];
                                            foreach ($monthlyRevenue as $item) {
                                                $allMonths[$item['month']] = true;
                                            }
                                            foreach ($monthlyProductsSold as $item) {
                                                $allMonths[$item['month']] = true;
                                            }
                                            foreach ($monthlyComments as $item) {
                                                $allMonths[$item['month']] = true;
                                            }
                                            ksort($allMonths);
                                            $labels = [];
                                            $revenues = [];
                                            $productsSold = [];
                                            $comments = [];
                                            $totalRevenue = 0;
                                            $totalProductsSold = 0;
                                            $totalComments = 0;

                                            foreach (array_keys($allMonths) as $month) {
                                                $date = DateTime::createFromFormat('Y-m', $month);
                                                $labels[] = $date->format('m/Y');
                                                // Doanh thu
                                                $revenue = 0;
                                                foreach ($monthlyRevenue as $item) {
                                                    if ($item['month'] === $month) {
                                                        $revenue = (float)$item['revenue'];
                                                        break;
                                                    }
                                                }
                                                $revenues[] = $revenue;
                                                $totalRevenue += $revenue;
                                                // Sản phẩm đã bán
                                                $sold = 0;
                                                foreach ($monthlyProductsSold as $item) {
                                                    if ($item['month'] === $month) {
                                                        $sold = (int)$item['total_sold'];
                                                        break;
                                                    }
                                                }
                                                $productsSold[] = $sold;
                                                $totalProductsSold += $sold;
                                                // Bình luận
                                                $comment = 0;
                                                foreach ($monthlyComments as $item) {
                                                    if ($item['month'] === $month) {
                                                        $comment = (int)$item['total_comments'];
                                                        break;
                                                    }
                                                }
                                                $comments[] = $comment;
                                                $totalComments += $comment;
                                            }

                                            echo json_encode([
                                                'labels' => $labels,
                                                'revenues' => $revenues,
                                                'productsSold' => $productsSold,
                                                'comments' => $comments,
                                                'totalRevenue' => $totalRevenue,
                                                'totalProductsSold' => $totalProductsSold,
                                                'totalComments' => $totalComments
                                            ]);
                                            ?>;

                        // Biểu đồ Doughnut
                        const ctxDoughnut = document.getElementById('statsDoughnut').getContext('2d');
                        const doughnutChart = new Chart(ctxDoughnut, {
                            type: 'doughnut',
                            data: {
                                labels: ['Doanh thu', 'Sản phẩm bán', 'Bình luận'],
                                datasets: [{
                                    data: [
                                        initialData.totalRevenue,
                                        initialData.totalProductsSold,
                                        initialData.totalComments
                                    ],
                                    backgroundColor: ['#EC4899', '#10B981', '#3B82F6'],
                                    borderColor: ['#DB2777', '#059669', '#2563EB'],
                                    borderWidth: 2,
                                    hoverOffset: 10
                                }]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                plugins: {
                                    legend: {
                                        position: 'top',
                                        labels: {
                                            font: {
                                                size: 14
                                            },
                                            color: '#4B5563'
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Tỷ lệ tổng quan (Năm)',
                                        font: {
                                            size: 18,
                                            weight: 'bold'
                                        },
                                        color: '#EC4899',
                                        padding: {
                                            top: 10,
                                            bottom: 20
                                        }
                                    },
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let label = context.label || '';
                                                let value = context.parsed;
                                                if (label === 'Doanh thu') {
                                                    value = value.toLocaleString('vi-VN', {
                                                        style: 'currency',
                                                        currency: 'VND'
                                                    });
                                                } else {
                                                    value = value.toLocaleString('vi-VN');
                                                }
                                                return `${label}: ${value}`;
                                            }
                                        }
                                    }
                                },
                                animation: {
                                    animateRotate: true,
                                    animateScale: true
                                }
                            }
                        });

                        // Biểu đồ Line
                        const ctxLine = document.getElementById('statsLine').getContext('2d');
                        const lineChart = new Chart(ctxLine, {
                            type: 'line',
                            data: {
                                labels: initialData.labels,
                                datasets: [{
                                        label: 'Doanh thu',
                                        data: initialData.revenues,
                                        borderColor: '#EC4899',
                                        backgroundColor: 'rgba(236, 72, 153, 0.2)',
                                        borderWidth: 2,
                                        tension: 0.3,
                                        fill: true,
                                        yAxisID: 'y'
                                    },
                                    {
                                        label: 'Sản phẩm đã bán',
                                        data: initialData.productsSold,
                                        borderColor: '#10B981',
                                        backgroundColor: 'rgba(16, 185, 129, 0.2)',
                                        borderWidth: 2,
                                        tension: 0.3,
                                        fill: true,
                                        yAxisID: 'y1'
                                    },
                                    {
                                        label: 'Số lượng bình luận',
                                        data: initialData.comments,
                                        borderColor: '#3B82F6',
                                        backgroundColor: 'rgba(59, 130, 246, 0.2)',
                                        borderWidth: 2,
                                        tension: 0.3,
                                        fill: true,
                                        yAxisID: 'y1'
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                scales: {
                                    y: {
                                        beginAtZero: true,
                                        position: 'left',
                                        ticks: {
                                            callback: function(value) {
                                                return value.toLocaleString('vi-VN', {
                                                    style: 'currency',
                                                    currency: 'VND'
                                                });
                                            },
                                            font: {
                                                size: 12
                                            },
                                            color: '#6B7280'
                                        },
                                        title: {
                                            display: true,
                                            text: 'Doanh thu (VNĐ)',
                                            font: {
                                                size: 14
                                            },
                                            color: '#4B5563'
                                        },
                                        grid: {
                                            color: '#E5E7EB',
                                            borderDash: [5, 5]
                                        }
                                    },
                                    y1: {
                                        beginAtZero: true,
                                        position: 'right',
                                        ticks: {
                                            callback: function(value) {
                                                return value.toLocaleString('vi-VN');
                                            },
                                            font: {
                                                size: 12
                                            },
                                            color: '#6B7280'
                                        },
                                        title: {
                                            display: true,
                                            text: 'Số lượng',
                                            font: {
                                                size: 14
                                            },
                                            color: '#4B5563'
                                        },
                                        grid: {
                                            drawOnChartArea: false
                                        }
                                    },
                                    x: {
                                        title: {
                                            display: true,
                                            text: 'Tháng',
                                            font: {
                                                size: 14
                                            },
                                            color: '#4B5563'
                                        },
                                        ticks: {
                                            font: {
                                                size: 12
                                            },
                                            color: '#6B7280'
                                        },
                                        grid: {
                                            display: false
                                        }
                                    }
                                },
                                plugins: {
                                    legend: {
                                        display: true,
                                        position: 'top',
                                        labels: {
                                            font: {
                                                size: 14
                                            },
                                            color: '#4B5563'
                                        }
                                    },
                                    title: {
                                        display: true,
                                        text: 'Xu hướng theo tháng',
                                        font: {
                                            size: 18,
                                            weight: 'bold'
                                        },
                                        color: '#EC4899',
                                        padding: {
                                            top: 10,
                                            bottom: 20
                                        }
                                    },
                                    tooltip: {
                                        mode: 'index',
                                        intersect: false,
                                        backgroundColor: '#FFFFFF',
                                        titleColor: '#333',
                                        bodyColor: '#333',
                                        borderColor: '#E5E7EB',
                                        borderWidth: 1,
                                        caretPadding: 10,
                                        callbacks: {
                                            label: function(context) {
                                                const datasetLabel = context.dataset.label || '';
                                                let value = context.parsed.y;
                                                if (context.datasetIndex === 0) {
                                                    value = value.toLocaleString('vi-VN', {
                                                        style: 'currency',
                                                        currency: 'VND'
                                                    });
                                                } else {
                                                    value = value.toLocaleString('vi-VN');
                                                }
                                                return `${datasetLabel}: ${value}`;
                                            }
                                        }
                                    }
                                },
                                animation: {
                                    duration: 1000,
                                    easing: 'easeInOutQuad'
                                },
                                hover: {
                                    mode: 'nearest',
                                    intersect: true
                                }
                            }
                        });

                        // Cập nhật biểu đồ
                        function updateChart() {
                            fetch('get_stats.php')
                                .then(response => {
                                    if (!response.ok) {
                                        throw new Error('Network response was not ok');
                                    }
                                    return response.json();
                                })
                                .then(data => {
                                    const allMonths = {};
                                    (data.monthlyRevenue || []).forEach(item => allMonths[item.month] = true);
                                    (data.monthlyProductsSold || []).forEach(item => allMonths[item.month] = true);
                                    (data.monthlyComments || []).forEach(item => allMonths[item.month] = true);
                                    const sortedMonths = Object.keys(allMonths).sort();
                                    const labels = sortedMonths.map(month => {
                                        const date = new Date(month + '-01');
                                        return date.toLocaleString('vi-VN', {
                                            month: 'numeric',
                                            year: 'numeric'
                                        });
                                    });
                                    const revenues = sortedMonths.map(month => {
                                        const item = (data.monthlyRevenue || []).find(i => i.month === month);
                                        return item ? item.revenue : 0;
                                    });
                                    const productsSold = sortedMonths.map(month => {
                                        const item = (data.monthlyProductsSold || []).find(i => i.month === month);
                                        return item ? item.total_sold : 0;
                                    });
                                    const comments = sortedMonths.map(month => {
                                        const item = (data.monthlyComments || []).find(i => i.month === month);
                                        return item ? item.total_comments : 0;
                                    });
                                    const totalRevenue = revenues.reduce((a, b) => a + b, 0);
                                    const totalProductsSold = productsSold.reduce((a, b) => a + b, 0);
                                    const totalComments = comments.reduce((a, b) => a + b, 0);

                                    doughnutChart.data.labels = ['Doanh thu', 'Sản phẩm bán', 'Bình luận'];
                                    doughnutChart.data.datasets[0].data = [totalRevenue, totalProductsSold, totalComments];
                                    doughnutChart.update();

                                    lineChart.data.labels = labels;
                                    lineChart.data.datasets[0].data = revenues;
                                    lineChart.data.datasets[1].data = productsSold;
                                    lineChart.data.datasets[2].data = comments;
                                    lineChart.update();
                                })
                                .catch(error => {
                                    console.error('Error fetching data:', error);
                                    alert('Không thể cập nhật biểu đồ. Vui lòng thử lại.');
                                });
                        }

                        setInterval(updateChart, 10000);
                        updateChart();
                    });
                <?php endif; ?>
            </script>
        <?php elseif ($page == 'category'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-category-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm danh mục</button>
                <div class="inner-search">
                    <form id="category-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="category">
                        <input type="text" name="search" placeholder="Tìm kiếm danh mục..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="category-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="category-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên danh mục</th>
                            <th>Mô tả</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($categories)): ?>
                            <tr ON (td) colspan="4" style="text-align: center;">Không tìm thấy danh mục nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($category['id']); ?></td>
                                    <td><?php echo htmlspecialchars($category['name']); ?></td>
                                    <td><?php echo htmlspecialchars($category['description']); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editCategory(<?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>', '<?php echo htmlspecialchars($category['description']); ?>')"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" name="delete_category" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-category-form" class="form-container">
                <h3>Thêm danh mục</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Tên danh mục</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="description">Mô tả</label>
                        <textarea id="description" name="description" required></textarea>
                    </div>
                    <button type="submit" name="add_category" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="document.getElementById('add-category-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-category-form" class="form-container">
                <h3>Sửa danh mục</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-name">Tên danh mục</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-description">Mô tả</label>
                        <textarea id="edit-description" name="description" required></textarea>
                    </div>
                    <button type="submit" name="edit_category" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-category-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'product'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-product-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm sản phẩm</button>
                <div class="inner-search">
                    <form id="product-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="product">
                        <input type="text" name="search" placeholder="Tìm kiếm sản phẩm hoặc danh mục..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="product-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="product-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Ảnh</th>
                            <th>Tên sản phẩm</th>
                            <th>Danh mục</th>
                            <th>Tồn kho</th>
                            <th>Giá</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Không tìm thấy sản phẩm nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td>
                                        <?php if (!empty($product['product_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($product['product_image']); ?>" alt="Ảnh sản phẩm" style="max-width: 80px; height: auto;">
                                        <?php else: ?>
                                            Không có ảnh
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo htmlspecialchars($product['category']); ?></td>
                                    <td><?php echo htmlspecialchars($product['stock']); ?></td>
                                    <td><?php echo number_format($product['price'], 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', '<?php echo addslashes($product['category']); ?>', <?php echo $product['price']; ?>, <?php echo $product['stock']; ?>)"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                                            <button type="submit" name="delete_product" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-product-form" class="form-container">
                <h3>Thêm sản phẩm</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Tên sản phẩm</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Danh mục</label>
                        <input type="text" id="category" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="price">Giá</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="stock">Tồn kho</label>
                        <input type="number" id="stock" name="stock" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="product_image">Ảnh</label>
                        <input type="text" id="product_image" name="product_image">
                    </div>
                    <button type="submit" name="add_product" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-product-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-product-form" class="form-container">
                <h3>Sửa sản phẩm</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-name">Tên sản phẩm</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-category">Danh mục</label>
                        <input type="text" id="edit-category" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-price">Giá</label>
                        <input type="number" id="edit-price" name="price" step="0.01" min="0" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-stock">Tồn kho</label>
                        <input type="number" id="edit-stock" name="stock" min="0" required>
                    </div>
                    <button type="submit" name="edit_product" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-product-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'order'): ?>
            <div class="search-container">
                <div class="inner-search">
                    <form id="order-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="order">
                        <input type="text" name="search" placeholder="Tìm kiếm khách hàng, trạng thái, ngày đặt..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="order-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Tên khách hàng</th>
                            <th>Ngày đặt</th>
                            <th>Số ĐT</th>
                            <th>Giá trị đơn hàng</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $resultUsers = $conn->query("SELECT id, username, phone FROM users");
                        $users = [];
                        if ($resultUsers) {
                            while ($row = $resultUsers->fetch_assoc()) {
                                $users[$row['id']] = $row;
                            }
                        }
                        $groupedOrders = [];
                        foreach ($orders as $order) {
                            $orderId = $order['stt'];
                            if (!isset($groupedOrders[$orderId])) {
                                $groupedOrders[$orderId] = [
                                    'user_id' => $order['user_id'],
                                    'total' => $order['final_total'],
                                    'address' => $order['address'],
                                    'status' => $order['status'],
                                    'created_at' => $order['created_at'],
                                    'payment_method' => $order['payment_method'],
                                    'items' => []
                                ];
                            }
                            if ($order['product_name']) {
                                $groupedOrders[$orderId]['items'][] = [
                                    'product_name' => $order['product_name'],
                                    'product_option' => $order['product_option'],
                                    'price' => $order['price'],
                                    'quantity' => $order['quantity'],
                                    'product_image' => $order['product_image']
                                ];
                            }
                        }
                        if (empty($groupedOrders)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Không tìm thấy đơn hàng nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($groupedOrders as $orderId => $order):
                                $customerName = isset($users[$order['user_id']]) ? $users[$order['user_id']]['username'] : 'Không xác định';
                                $customerPhone = isset($users[$order['user_id']]) ? $users[$order['user_id']]['phone'] : '';
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($orderId); ?></td>
                                    <td><?php echo htmlspecialchars($customerName); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($order['created_at']))); ?></td>
                                    <td><?php echo htmlspecialchars($customerPhone); ?></td>
                                    <td><?php echo number_format($order['total'], 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo htmlspecialchars($order['status']); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editOrder(<?php echo $orderId; ?>, '<?php echo addslashes($order['status']); ?>')"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $orderId; ?>">
                                            <button type="submit" name="delete_order" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="edit-order-form" class="form-container">
                <h3>Sửa đơn hàng</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-status">Trạng thái</label>
                        <select id="edit-status" name="status" required>
                            <option value="Chờ xử lý">Chờ xử lý</option>
                            <option value="Đã xác nhận">Đã xác nhận</option>
                            <option value="Đang giao">Đang giao</option>
                            <option value="Đã giao">Đã giao</option>
                            <option value="Đã hủy">Đã hủy</option>
                        </select>
                    </div>
                    <button type="submit" name="edit_order" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-order-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'slider'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-slider-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm slider</button>
                <div class="inner-search">
                    <form id="slider-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="slider">
                        <input type="text" name="search" placeholder="Tìm kiếm slider hoặc liên kết..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="slider-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="slider-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên slider</th>
                            <th>Hình ảnh</th>
                            <th>Liên kết</th>
                            <th>Thứ tự</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sliders)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Không tìm thấy slider nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sliders as $slider): ?>
                                <tr>
                                    <td><?php echo $slider['id']; ?></td>
                                    <td><?php echo htmlspecialchars($slider['name']); ?></td>
                                    <td><?php echo htmlspecialchars($slider['image']); ?></td>
                                    <td><?php echo htmlspecialchars($slider['link']); ?></td>
                                    <td><?php echo $slider['order']; ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editSlider(<?php echo $slider['id']; ?>, '<?php echo htmlspecialchars($slider['name']); ?>', '<?php echo htmlspecialchars($slider['image']); ?>', '<?php echo htmlspecialchars($slider['link']); ?>', <?php echo $slider['order']; ?>)"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $slider['id']; ?>">
                                            <button type="submit" name="delete_slider" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-slider-form" class="form-container">
                <h3>Thêm slider</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Tên slider</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="image">Hình ảnh</label>
                        <input type="text" id="image" name="image" required>
                    </div>
                    <div class="form-group">
                        <label for="link">Liên kết</label>
                        <input type="text" id="link" name="link" required>
                    </div>
                    <div class="form-group">
                        <label for="order">Thứ tự</label>
                        <input type="number" id="order" name="order" required>
                    </div>
                    <button type="submit" name="add_slider" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-slider-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-slider-form" class="form-container">
                <h3>Sửa slider</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-name">Tên slider</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-image">Hình ảnh</label>
                        <input type="text" id="edit-image" name="image" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-link">Liên kết</label>
                        <input type="text" id="edit-link" name="link" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-order">Thứ tự</label>
                        <input type="number" id="edit-order" name="order" required>
                    </div>
                    <button type="submit" name="edit_slider" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-slider-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'customer'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-customer-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm khách hàng</button>
                <div class="inner-search">
                    <form id="customer-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="customer">
                        <input type="text" name="search" placeholder="Tìm kiếm khách hàng, email " value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="customer-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="customer-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên khách hàng</th>
                            <th>Email</th>
                            <th>Số điện thoại</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Không tìm thấy khách hàng nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td><?php echo $customer['id']; ?></td>
                                    <td><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editCustomer(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['name']); ?>', '<?php echo addslashes($customer['email']); ?>', '<?php echo addslashes($customer['phone']); ?>')"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                                            <button type="submit" name="delete_customer" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-customer-form" class="form-container">
                <h3>Thêm khách hàng</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="name">Tên khách hàng</label>
                        <input type="text" id="name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Số điện thoại</label>
                        <input type="text" id="phone" name="phone" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Mật khẩu</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="add_customer" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-customer-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-customer-form" class="form-container">
                <h3>Sửa khách hàng</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-name">Tên khách hàng</label>
                        <input type="text" id="edit-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-email">Email</label>
                        <input type="email" id="edit-email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-phone">Số điện thoại</label>
                        <input type="text" id="edit-phone" name="phone" required>
                    </div>
                    <button type="submit" name="edit_customer" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-customer-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'voucher'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-voucher-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm voucher</button>
                <div class="inner-search">
                    <form id="voucher-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="voucher">
                        <input type="text" name="search" placeholder="Tìm kiếm mã voucher..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="voucher-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="voucher-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã Voucher</th>
                            <th>Giảm giá</th>
                            <th>Loại giảm giá</th>
                            <th>Giá trị đơn hàng tối thiểu</th>
                            <th>Ngày hết hạn</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($vouchers)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">Không tìm thấy voucher nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($vouchers as $voucher): ?>
                                <tr>
                                    <td><?php echo $voucher['id']; ?></td>
                                    <td><?php echo htmlspecialchars($voucher['code']); ?></td>
                                    <td><?php echo number_format($voucher['discount'], 0, ',', '.') . ($voucher['discount_type'] == 'percent' ? '%' : ' VNĐ'); ?></td>
                                    <td><?php echo $voucher['discount_type'] == 'percent' ? 'Phần trăm' : 'Cố định'; ?></td>
                                    <td><?php echo number_format($voucher['min_order_value'], 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($voucher['expires_at']))); ?></td>
                                    <td><?php echo $voucher['is_active'] ? 'Hoạt động' : 'Không hoạt động'; ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editVoucher(<?php echo $voucher['id']; ?>, '<?php echo htmlspecialchars($voucher['code']); ?>', <?php echo $voucher['discount']; ?>, '<?php echo $voucher['discount_type']; ?>', <?php echo $voucher['min_order_value']; ?>, '<?php echo $voucher['expires_at']; ?>', <?php echo $voucher['is_active']; ?>)"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $voucher['id']; ?>">
                                            <button type="submit" name="delete_voucher" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-voucher-form" class="form-container">
                <h3>Thêm voucher</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="code">Mã Voucher</label>
                        <input type="text" id="code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="discount">Giá trị</label>
                        <input type="number" id="discount" name="discount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="discount_type">Loại giảm giá</label>
                        <select id="discount_type" name="discount_type" required>
                            <option value="percent">Phần trăm</option>
                            <option value="fixed">Cố định</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="min_order_value">Giá trị đơn hàng tối thiểu</label>
                        <input type="number" id="min_order_value" name="min_order_value" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Ngày hết hạn</label>
                        <input type="datetime-local" id="expires_at" name="expires_at" required>
                    </div>
                    <div class="form-group">
                        <label for="is_active">Hoạt động</label>
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    </div>
                    <button type="submit" name="add_voucher" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-voucher-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-voucher-form" class="form-container">
                <h3>Sửa voucher</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-code">Mã Voucher</label>
                        <input type="text" id="edit-code" name="code" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-discount">Giá trị</label>
                        <input type="number" id="edit-discount" name="discount" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-discount_type">Loại giảm giá</label>
                        <select id="edit-discount_type" name="discount_type" required>
                            <option value="percent">Phần trăm</option>
                            <option value="fixed">Cố định</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-min_order_value">Giá trị đơn hàng tối thiểu</label>
                        <input type="number" id="edit-min_order_value" name="min_order_value" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-expires_at">Ngày hết hạn</label>
                        <input type="datetime-local" id="edit-expires_at" name="expires_at" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-is_active">Hoạt động</label>
                        <input type="checkbox" id="edit-is_active" name="is_active" value="1">
                    </div>
                    <button type="submit" name="edit_voucher" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-voucher-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php endif; ?>
        <?php if ($page == 'contact'): ?>
            <div class="search-container">
                <div class="inner-search">
                    <form id="contact-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="contact">
                        <input type="text" name="search" placeholder="Tìm kiếm tên, email, số điện thoại, nội dung..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="contact-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="contact-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>Email</th>
                            <th>Nội dung</th>
                            <th>Ngày gửi</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contacts)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Không tìm thấy liên hệ nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($contact['id']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['name']); ?></td>
                                    <td><?php echo htmlspecialchars($contact['email']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($contact['message'], 0, 50)) . (strlen($contact['message']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($contact['created_at']))); ?></td>
                                    <td class="actions">
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $contact['id']; ?>">
                                            <button type="submit" name="delete_contact" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($page == 'marquee'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-marquee-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm Marquee</button>
                <div class="inner-search">
                    <form id="marquee-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="marquee">
                        <input type="text" name="search" placeholder="Tìm kiếm nội dung marquee..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="marquee-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="marquee-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th>Ngày cập nhật</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($marquees)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center;">Không tìm thấy marquee nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($marquees as $marquee): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($marquee['id']); ?></td>
                                    <td><?php echo htmlspecialchars(substr($marquee['content'], 0, 50)) . (strlen($marquee['content']) > 50 ? '...' : ''); ?></td>
                                    <td><?php echo $marquee['is_active'] ? 'Hoạt động' : 'Không hoạt động'; ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($marquee['updated_at']))); ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editMarquee(<?php echo $marquee['id']; ?>, '<?php echo addslashes($marquee['content']); ?>', <?php echo $marquee['is_active']; ?>)"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $marquee['id']; ?>">
                                            <button type="submit" name="delete_marquee" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-marquee-form" class="form-container">
                <h3>Thêm Marquee</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="content">Nội dung</label>
                        <textarea id="content" name="content" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="is_active">Hoạt động</label>
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    </div>
                    <button type="submit" name="add_marquee" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-marquee-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-marquee-form" class="form-container">
                <h3>Sửa Marquee</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-content">Nội dung</label>
                        <textarea id="edit-content" name="content" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit-is_active">Hoạt động</label>
                        <input type="checkbox" id="edit-is_active" name="is_active" value="1">
                    </div>
                    <button type="submit" name="edit_marquee" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-marquee-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'navbar'): ?>
            <div class="search-container">
                <button class="add-btn" onclick="document.getElementById('add-navbar-form').classList.add('active')"><i class="fas fa-plus"></i> Thêm Navbar</button>
                <div class="inner-search">
                    <form id="navbar-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="navbar">
                        <input type="text" name="search" placeholder="Tìm kiếm tên hoặc URL..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="navbar-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="navbar-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tên</th>
                            <th>URL</th>
                            <th>Biểu tượng</th>
                            <th>Thứ tự</th>
                            <th>Trạng thái</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($navbars)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">Không tìm thấy liên kết nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($navbars as $navbar): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($navbar['id']); ?></td>
                                    <td><?php echo htmlspecialchars($navbar['text']); ?></td>
                                    <td><?php echo htmlspecialchars($navbar['url']); ?></td>
                                    <td><?php echo htmlspecialchars($navbar['icon'] ?? 'Không có'); ?></td>
                                    <td><?php echo htmlspecialchars($navbar['order_num']); ?></td>
                                    <td><?php echo $navbar['is_active'] ? 'Hoạt động' : 'Không hoạt động'; ?></td>
                                    <td class="actions">
                                        <button class="edit-btn" onclick="editNavbar(<?php echo $navbar['id']; ?>, '<?php echo addslashes($navbar['text']); ?>', '<?php echo addslashes($navbar['url']); ?>', '<?php echo addslashes($navbar['icon'] ?? ''); ?>', <?php echo $navbar['order_num']; ?>, <?php echo $navbar['is_active']; ?>)"><i class="fas fa-edit"></i> Sửa</button>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $navbar['id']; ?>">
                                            <button type="submit" name="delete_navbar" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div id="add-navbar-form" class="form-container">
                <h3>Thêm liên kết Navbar</h3>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="text">Tên liên kết</label>
                        <input type="text" id="text" name="text" required>
                    </div>
                    <div class="form-group">
                        <label for="url">URL</label>
                        <input type="text" id="url" name="url" required>
                    </div>
                    <div class="form-group">
                        <label for="icon">Biểu tượng (Font Awesome)</label>
                        <input type="text" id="icon" name="icon" placeholder="Ví dụ: fa-solid fa-house">
                    </div>
                    <div class="form-group">
                        <label for="order_num">Thứ tự</label>
                        <input type="number" id="order_num" name="order_num" required>
                    </div>
                    <div class="form-group">
                        <label for="is_active">Hoạt động</label>
                        <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                    </div>
                    <button type="submit" name="add_navbar" class="submit-btn"><i class="fas fa-save"></i> Thêm</button>
                    <button type="button" onclick="document.getElementById('add-navbar-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
            <div id="edit-navbar-form" class="form-container">
                <h3>Sửa liên kết Navbar</h3>
                <form method="POST" action="">
                    <input type="hidden" id="edit-id" name="id">
                    <div class="form-group">
                        <label for="edit-text">Tên liên kết</label>
                        <input type="text" id="edit-text" name="text" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-url">URL</label>
                        <input type="text" id="edit-url" name="url" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-icon">Biểu tượng (Font Awesome)</label>
                        <input type="text" id="edit-icon" name="icon" placeholder="Ví dụ: fa-solid fa-house">
                    </div>
                    <div class="form-group">
                        <label for="edit-order_num">Thứ tự</label>
                        <input type="number" id="edit-order_num" name="order_num" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-is_active">Hoạt động</label>
                        <input type="checkbox" id="edit-is_active" name="is_active" value="1">
                    </div>
                    <button type="submit" name="edit_navbar" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                    <button type="button" onclick="document.getElementById('edit-navbar-form').classList.remove('active')" class="add-btn"><i class="fas fa-times"></i> Đóng</button>
                </form>
            </div>
        <?php elseif ($page == 'notification'): ?>
            <div class="search-container">
                <div class="inner-search">
                    <form id="notification-search-form" method="GET" action="">
                        <input type="hidden" name="page" value="notification">
                        <input type="text" name="search" placeholder="Tìm kiếm thông báo..." value="<?php echo htmlspecialchars($search); ?>">
                        <i class="fas fa-magnifying-glass"></i>
                    </form>
                    <button type="submit" form="notification-search-form" class="search-btn"><i class="fas fa-search"></i> Tìm kiếm</button>
                </div>
            </div>
            <div class="table-container">
                <table class="notification-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Mã Đơn Hàng</th>
                            <th>Nội dung</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Hành động</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">Không tìm thấy thông báo nào.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <?php
                                // Kiểm tra trạng thái đơn hàng
                                $order_id = $notification['order_id'];
                                $sql = "SELECT status FROM orders WHERE id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $order_id);
                                $stmt->execute();
                                $order = $stmt->get_result()->fetch_assoc();
                                $order_status = $order['status'] ?? '';
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notification['id']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['order_id']); ?></td>
                                    <td><?php echo htmlspecialchars($notification['message']); ?></td>
                                    <td><?php echo $notification['is_read'] ? 'Đã đọc' : 'Chưa đọc'; ?></td>
                                    <td><?php echo isset($order['payment_time']) && $order['payment_time'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($order['payment_time']))) : htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))); ?></td>
                                    <td class="actions">
                                        <?php if ($order_status !== 'Đã thanh toán' && !$notification['is_read']): ?>
                                            <div style="display: flex; gap: 5px;">
                                                <form method="POST" action="">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $notification['order_id']; ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" name="confirm_notification" class="confirm-btn"><i class="fas fa-check"></i> Chấp nhận</button>
                                                </form>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $notification['order_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" name="confirm_notification" class="delete-btn"><i class="fas fa-times"></i> Từ chối</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Bạn có chắc muốn xóa?');">
                                            <input type="hidden" name="id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="delete_notification" class="delete-btn"><i class="fas fa-trash"></i> Xóa</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ($page == 'footer'): ?>
            <style>
                .form-container {
                    margin-bottom: 20px;
                    padding: 20px;
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
                }

                .form-group {
                    margin-bottom: 15px;
                }

                .form-group label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 5px;
                }

                .form-group input[type="text"],
                .form-group textarea {
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    box-sizing: border-box;
                }

                .form-group textarea {
                    height: 100px;
                    resize: vertical;
                }

                .item {
                    display: flex;
                    gap: 10px;
                    align-items: center;
                    flex-wrap: wrap;
                }

                .item input {
                    flex: 1;
                    min-width: 200px;
                }

                .add-btn,
                .submit-btn,
                .delete-btn {
                    padding: 8px 12px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    transition: background 0.3s;
                }

                .add-btn {
                    background: #28a745;
                    color: white;
                }

                .add-btn:hover {
                    background: #218838;
                }

                .submit-btn {
                    background: #007bff;
                    color: white;
                }

                .submit-btn:hover {
                    background: #0056b3;
                }

                .delete-btn {
                    background: #dc3545;
                    color: white;
                }

                .delete-btn:hover {
                    background: #c82333;
                }

                h3,
                h4 {
                    color: #EC4899;
                }
            </style>
            <div class="table-container">
                <h3>Quản lý Footer</h3>
                <?php foreach (['care_links', 'about_links', 'social_links', 'payment_methods', 'bottom_text'] as $section): ?>
                    <?php
                    $section_data = $footer_sections[$section] ?? ['content' => [], 'is_active' => 1];
                    $section_title = ucwords(str_replace('_', ' ', $section));
                    ?>
                    <div class="form-container active">
                        <h4><?php echo htmlspecialchars($section_title); ?></h4>
                        <form method="POST" action="">
                            <input type="hidden" name="section" value="<?php echo htmlspecialchars($section); ?>">
                            <?php if ($section === 'bottom_text'): ?>
                                <div class="form-group">
                                    <label for="bottom_text">Nội dung</label>
                                    <textarea id="bottom_text" name="bottom_text" required><?php echo htmlspecialchars($section_data['content'] ?? ''); ?></textarea>
                                </div>
                            <?php else: ?>
                                <div id="<?php echo $section; ?>-items">
                                    <?php
                                    $items = is_array($section_data['content']) ? $section_data['content'] : [];
                                    if (empty($items)) {
                                        $items = $section === 'payment_methods' ? [['image' => '', 'alt' => '']] : [['text' => '', 'url' => '', 'icon' => '']];
                                    }
                                    ?>
                                    <?php foreach ($items as $index => $item): ?>
                                        <div class="form-group item">
                                            <?php if ($section === 'payment_methods'): ?>
                                                <label>Ảnh thanh toán</label>
                                                <input type="text" name="image[]" placeholder="Đường dẫn ảnh (ví dụ: assets/images/payment/visa.png)" value="<?php echo htmlspecialchars($item['image'] ?? ''); ?>" required>
                                                <input type="text" name="alt[]" placeholder="Tên phương thức" value="<?php echo htmlspecialchars($item['alt'] ?? ''); ?>" required>
                                            <?php else: ?>
                                                <label>Liên kết</label>
                                                <input type="text" name="text[]" placeholder="Tên liên kết" value="<?php echo htmlspecialchars($item['text'] ?? ''); ?>" required>
                                                <input type="text" name="url[]" placeholder="URL" value="<?php echo htmlspecialchars($item['url'] ?? ''); ?>" required>
                                                <?php if ($section === 'social_links'): ?>
                                                    <input type="text" name="icon[]" placeholder="Lớp Font Awesome (ví dụ: fab fa-facebook)" value="<?php echo htmlspecialchars($item['icon'] ?? ''); ?>">
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <button type="button" class="delete-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i> Xóa</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="add-btn" onclick="addItem('<?php echo $section; ?>', '<?php echo $section === 'payment_methods' ? 'payment' : 'link'; ?>')"><i class="fas fa-plus"></i> Thêm mục</button>
                            <?php endif; ?>
                            <div class="form-group">
                                <label for="is_active_<?php echo $section; ?>">Hoạt động</label>
                                <input type="checkbox" id="is_active_<?php echo $section; ?>" name="is_active" value="1" <?php echo $section_data['is_active'] ? 'checked' : ''; ?>>
                            </div>
                            <button type="submit" name="edit_footer" class="submit-btn"><i class="fas fa-save"></i> Lưu</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
            <script>
                function addItem(section, type) {
                    const container = document.getElementById(`${section}-items`);
                    const div = document.createElement('div');
                    div.className = 'form-group item';
                    if (type === 'payment') {
                        div.innerHTML = `
                    <label>Ảnh thanh toán</label>
                    <input type="text" name="image[]" placeholder="Đường dẫn ảnh (ví dụ: assets/images/payment/visa.png)" required>
                    <input type="text" name="alt[]" placeholder="Tên phương thức" required>
                    <button type="button" class="delete-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i> Xóa</button>
                `;
                    } else {
                        div.innerHTML = `
                    <label>Liên kết</label>
                    <input type="text" name="text[]" placeholder="Tên liên kết" required>
                    <input type="text" name="url[]" placeholder="URL" required>
                    ${section === 'social_links' ? '<input type="text" name="icon[]" placeholder="Lớp Font Awesome (ví dụ: fab fa-facebook)">' : ''}
                    <button type="button" class="delete-btn" onclick="this.parentElement.remove()"><i class="fas fa-trash"></i> Xóa</button>
                `;
                    }
                    container.appendChild(div);
                }
            </script>
        <?php endif; ?>
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
    </div>
    <script>
    function editCategory(id, name, description) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-description').value = description;
        document.getElementById('edit-category-form').classList.add('active');
    }

    function editProduct(id, name, category, price) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-category').value = category;
        document.getElementById('edit-price').value = price;
        document.getElementById('edit-product-form').classList.add('active');
    }

    function editOrder(id, status) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-status').value = status;
        document.getElementById('edit-order-form').classList.add('active');
    }

    function editSlider(id, name, image, link, order) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-image').value = image;
        document.getElementById('edit-link').value = link;
        document.getElementById('edit-order').value = order;
        document.getElementById('edit-slider-form').classList.add('active');
    }

    function editCustomer(id, name, email, phone) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-name').value = name;
        document.getElementById('edit-email').value = email;
        document.getElementById('edit-phone').value = phone;
        document.getElementById('edit-customer-form').classList.add('active');
    }

    function editVoucher(id, code, discount, discount_type, min_order_value, expires_at, is_active) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-code').value = code;
        document.getElementById('edit-discount').value = discount;
        document.getElementById('edit-discount_type').value = discount_type;
        document.getElementById('edit-min_order_value').value = min_order_value;
        document.getElementById('edit-expires_at').value = expires_at;
        document.getElementById('edit-is_active').checked = is_active;
        document.getElementById('edit-voucher-form').classList.add('active');
    }

    function editMarquee(id, content, is_active) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-content').value = content;
        document.getElementById('edit-is_active').checked = is_active;
        document.getElementById('edit-marquee-form').classList.add('active');
    }

    function editNavbar(id, text, url, icon, order_num, is_active) {
        document.getElementById('edit-id').value = id;
        document.getElementById('edit-text').value = text;
        document.getElementById('edit-url').value = url;
        document.getElementById('edit-icon').value = icon;
        document.getElementById('edit-order_num').value = order_num;
        document.getElementById('edit-is_active').checked = is_active;
        document.getElementById('edit-navbar-form').classList.add('active');
    }

    // Quản lý trạng thái sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const toggleBtn = document.getElementById('toggle-sidebar');
        const mobileToggleBtn = document.getElementById('mobile-toggle-sidebar');

        // Kiểm tra kích thước màn hình và trạng thái sidebar
        if (window.innerWidth <= 768) {
            // Trên mobile: ẩn sidebar mặc định
            sidebar.classList.add('hidden');
            localStorage.setItem('sidebarHiddenMobile', 'true');
        } else {
            // Trên desktop: luôn hiển thị sidebar
            sidebar.classList.remove('hidden');
            localStorage.removeItem('sidebarHiddenMobile'); // Xóa trạng thái mobile
        }

        // Toggle sidebar
        function toggleSidebar() {
            sidebar.classList.toggle('hidden');
            if (window.innerWidth <= 768) {
                const isHidden = sidebar.classList.contains('hidden');
                localStorage.setItem('sidebarHiddenMobile', isHidden.toString());
            }
        }

        // Sự kiện click cho nút toggle
        if (toggleBtn) toggleBtn.addEventListener('click', toggleSidebar);
        if (mobileToggleBtn) mobileToggleBtn.addEventListener('click', toggleSidebar);

        // Đóng sidebar khi click bên ngoài trên mobile
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768 && !sidebar.contains(event.target) && !toggleBtn.contains(event.target) && !mobileToggleBtn.contains(event.target)) {
                sidebar.classList.add('hidden');
                localStorage.setItem('sidebarHiddenMobile', 'true');
            }
        });

        // Cập nhật trạng thái khi thay đổi kích thước màn hình
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('hidden'); // Luôn hiển thị trên desktop
                localStorage.removeItem('sidebarHiddenMobile');
            } else {
                const isHidden = localStorage.getItem('sidebarHiddenMobile') === 'true';
                if (isHidden) {
                    sidebar.classList.add('hidden');
                } else {
                    sidebar.classList.remove('hidden');
                }
            }
        });
    });

    <?php if (!empty($monthlyRevenue) || !empty($monthlyProductsSold) || !empty($monthlyComments)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            // Dữ liệu ban đầu
            const initialData = <?php
                $allMonths = [];
                foreach ($monthlyRevenue as $item) {
                    $allMonths[$item['month']] = true;
                }
                foreach ($monthlyProductsSold as $item) {
                    $allMonths[$item['month']] = true;
                }
                foreach ($monthlyComments as $item) {
                    $allMonths[$item['month']] = true;
                }
                ksort($allMonths);
                $labels = [];
                $revenues = [];
                $productsSold = [];
                $comments = [];
                $totalRevenue = 0;
                $totalProductsSold = 0;
                $totalComments = 0;

                foreach (array_keys($allMonths) as $month) {
                    $date = DateTime::createFromFormat('Y-m', $month);
                    $labels[] = $date->format('m/Y');
                    $revenue = 0;
                    foreach ($monthlyRevenue as $item) {
                        if ($item['month'] === $month) {
                            $revenue = (float)$item['revenue'];
                            break;
                        }
                    }
                    $revenues[] = $revenue;
                    $totalRevenue += $revenue;
                    $sold = 0;
                    foreach ($monthlyProductsSold as $item) {
                        if ($item['month'] === $month) {
                            $sold = (int)$item['total_sold'];
                            break;
                        }
                    }
                    $productsSold[] = $sold;
                    $totalProductsSold += $sold;
                    $comment = 0;
                    foreach ($monthlyComments as $item) {
                        if ($item['month'] === $month) {
                            $comment = (int)$item['total_comments'];
                            break;
                        }
                    }
                    $comments[] = $comment;
                    $totalComments += $comment;
                }

                echo json_encode([
                    'labels' => $labels,
                    'revenues' => $revenues,
                    'productsSold' => $productsSold,
                    'comments' => $comments,
                    'totalRevenue' => $totalRevenue,
                    'totalProductsSold' => $totalProductsSold,
                    'totalComments' => $totalComments
                ]);
            ?>;

            // Biểu đồ Doughnut
            const ctxDoughnut = document.getElementById('statsDoughnut').getContext('2d');
            const doughnutChart = new Chart(ctxDoughnut, {
                type: 'doughnut',
                data: {
                    labels: ['Doanh thu', 'Sản phẩm bán', 'Bình luận'],
                    datasets: [{
                        data: [
                            initialData.totalRevenue,
                            initialData.totalProductsSold,
                            initialData.totalComments
                        ],
                        backgroundColor: ['#EC4899', '#10B981', '#3B82F6'],
                        borderColor: ['#DB2777', '#059669', '#2563EB'],
                        borderWidth: 2,
                        hoverOffset: 10
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                },
                                color: '#4B5563'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Tỷ lệ tổng quan (Năm)',
                            font: {
                                size: 18,
                                weight: 'bold'
                            },
                            color: '#EC4899',
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.parsed;
                                    if (label === 'Doanh thu') {
                                        value = value.toLocaleString('vi-VN', {
                                            style: 'currency',
                                            currency: 'VND'
                                        });
                                    } else {
                                        value = value.toLocaleString('vi-VN');
                                    }
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    },
                    animation: {
                        animateRotate: true,
                        animateScale: true
                    }
                }
            });

            // Biểu đồ Line
            const ctxLine = document.getElementById('statsLine').getContext('2d');
            const lineChart = new Chart(ctxLine, {
                type: 'line',
                data: {
                    labels: initialData.labels,
                    datasets: [{
                            label: 'Doanh thu',
                            data: initialData.revenues,
                            borderColor: '#EC4899',
                            backgroundColor: 'rgba(236, 72, 153, 0.2)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Sản phẩm đã bán',
                            data: initialData.productsSold,
                            borderColor: '#10B981',
                            backgroundColor: 'rgba(16, 185, 129, 0.2)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y1'
                        },
                        {
                            label: 'Số lượng bình luận',
                            data: initialData.comments,
                            borderColor: '#3B82F6',
                            backgroundColor: 'rgba(59, 130, 246, 0.2)',
                            borderWidth: 2,
                            tension: 0.3,
                            fill: true,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            position: 'left',
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('vi-VN', {
                                        style: 'currency',
                                        currency: 'VND'
                                    });
                                },
                                font: {
                                    size: 12
                                },
                                color: '#6B7280'
                            },
                            title: {
                                display: true,
                                text: 'Doanh thu (VNĐ)',
                                font: {
                                    size: 14
                                },
                                color: '#4B5563'
                            },
                            grid: {
                                color: '#E5E7EB',
                                borderDash: [5, 5]
                            }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('vi-VN');
                                },
                                font: {
                                    size: 12
                                },
                                color: '#6B7280'
                            },
                            title: {
                                display: true,
                                text: 'Số lượng',
                                font: {
                                    size: 14
                                },
                                color: '#4B5563'
                            },
                            grid: {
                                drawOnChartArea: false
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Tháng',
                                font: {
                                    size: 14
                                },
                                color: '#4B5563'
                            },
                            ticks: {
                                font: {
                                    size: 12
                                },
                                color: '#6B7280'
                            },
                            grid: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: {
                                font: {
                                    size: 14
                                },
                                color: '#4B5563'
                            }
                        },
                        title: {
                            display: true,
                            text: 'Xu hướng theo tháng',
                            font: {
                                size: 18,
                                weight: 'bold'
                            },
                            color: '#EC4899',
                            padding: {
                                top: 10,
                                bottom: 20
                            }
                        },
                        tooltip: {
                            mode: 'index',
                            intersect: false,
                            backgroundColor: '#FFFFFF',
                            titleColor: '#333',
                            bodyColor: '#333',
                            borderColor: '#E5E7EB',
                            borderWidth: 1,
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    const datasetLabel = context.dataset.label || '';
                                    let value = context.parsed.y;
                                    if (context.datasetIndex === 0) {
                                        value = value.toLocaleString('vi-VN', {
                                            style: 'currency',
                                            currency: 'VND'
                                        });
                                    } else {
                                        value = value.toLocaleString('vi-VN');
                                    }
                                    return `${datasetLabel}: ${value}`;
                                }
                            }
                        }
                    },
                    animation: {
                        duration: 1000,
                        easing: 'easeInOutQuad'
                    },
                    hover: {
                        mode: 'nearest',
                        intersect: true
                    }
                }
            });

            // Cập nhật biểu đồ
            function updateChart() {
                fetch('get_stats.php')
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        const allMonths = {};
                        (data.monthlyRevenue || []).forEach(item => allMonths[item.month] = true);
                        (data.monthlyProductsSold || []).forEach(item => allMonths[item.month] = true);
                        (data.monthlyComments || []).forEach(item => allMonths[item.month] = true);
                        const sortedMonths = Object.keys(allMonths).sort();
                        const labels = sortedMonths.map(month => {
                            const date = new Date(month + '-01');
                            return date.toLocaleString('vi-VN', {
                                month: 'numeric',
                                year: 'numeric'
                            });
                        });
                        const revenues = sortedMonths.map(month => {
                            const item = (data.monthlyRevenue || []).find(i => i.month === month);
                            return item ? item.revenue : 0;
                        });
                        const productsSold = sortedMonths.map(month => {
                            const item = (data.monthlyProductsSold || []).find(i => i.month === month);
                            return item ? item.total_sold : 0;
                        });
                        const comments = sortedMonths.map(month => {
                            const item = (data.monthlyComments || []).find(i => i.month === month);
                            return item ? item.total_comments : 0;
                        });
                        const totalRevenue = revenues.reduce((a, b) => a + b, 0);
                        const totalProductsSold = productsSold.reduce((a, b) => a + b, 0);
                        const totalComments = comments.reduce((a, b) => a + b, 0);

                        doughnutChart.data.labels = ['Doanh thu', 'Sản phẩm bán', 'Bình luận'];
                        doughnutChart.data.datasets[0].data = [totalRevenue, totalProductsSold, totalComments];
                        doughnutChart.update();

                        lineChart.data.labels = labels;
                        lineChart.data.datasets[0].data = revenues;
                        lineChart.data.datasets[1].data = productsSold;
                        lineChart.data.datasets[2].data = comments;
                        lineChart.update();
                    })
                    .catch(error => {
                        console.error('Error fetching data:', error);
                        alert('Không thể cập nhật biểu đồ. Vui lòng thử lại.');
                    });
            }

            setInterval(updateChart, 10000);
            updateChart();
        });
    <?php endif; ?>
</script>



</body>

</html>
<?php $conn->close(); ?>