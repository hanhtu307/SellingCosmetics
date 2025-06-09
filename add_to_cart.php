<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $product_id = (int)$_POST['product_id']; // Lấy product_id từ form
    $option = isset($_POST['product_option']) ? trim($_POST['product_option']) : ''; // Lấy product_option từ form
    $price = isset($_POST['product_price']) ? (float)$_POST['product_price'] : 0; // Lấy price từ form

    // Kiểm tra product_id hợp lệ
    if ($product_id <= 0) {
        $_SESSION['toast_message'] = "❌ Lỗi: Product ID không hợp lệ!";
        header("Location: product_detail.php?error=invalid_product_id");
        exit();
    }

    // Kiểm tra sản phẩm tồn tại trong bảng product (chỉ để xác nhận, không lấy price)
    $sql = "SELECT name, product_image FROM product WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $product_name = trim($row['name']);
        $product_image = $row['product_image'];
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi: Sản phẩm không tồn tại!";
        header("Location: product_detail.php?error=product_not_found");
        exit();
    }

    // Kiểm tra price hợp lệ
    if ($price <= 0) {
        $_SESSION['toast_message'] = "❌ Lỗi: Giá không hợp lệ!";
        header("Location: product_detail.php?error=invalid_price");
        exit();
    }

    $qty = isset($_POST['product_qty']) ? (int)$_POST['product_qty'] : 1;
    $session_id = session_id();

    // Cập nhật hoặc thêm vào $_SESSION['cart']
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id] += $qty;
    } else {
        $_SESSION['cart'][$product_id] = $qty;
    }

    // Kiểm tra sản phẩm đã tồn tại trong giỏ chưa, bao gồm product_option
    $sql = "SELECT * FROM cart_items WHERE session_id = ? AND product_id = ? AND product_option = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sis", $session_id, $product_id, $option);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        // Nếu đã có, tăng số lượng
        $new_qty = $row['quantity'] + $qty;
        $update = $conn->prepare("UPDATE cart_items SET quantity = ?, price = ? WHERE id = ?");
        $update->bind_param("isi", $new_qty, $price, $row['id']);
        $update->execute();
    } else {
        // Nếu chưa có, thêm mới và lưu cả product_id, product_option, và price từ form
        $insert = $conn->prepare("INSERT INTO cart_items (session_id, product_id, product_name, product_option, price, quantity, product_image, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $insert->bind_param("sisssis", $session_id, $product_id, $product_name, $option, $price, $qty, $product_image);
        $insert->execute();
    }

    $conn->close();

    // Thông báo toast + chuyển hướng
    $_SESSION['toast_message'] = "🎉 Đã thêm vào giỏ hàng!";
    header("Location: cart.php");
    exit();
}
?>