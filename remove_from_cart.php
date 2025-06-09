
<?php
session_start();
require_once 'connect.php';

// XÓA 1 SẢN PHẨM QUA GET
if (isset($_GET['product_id'])) {
    $product_id = $_GET['product_id'];

    // Xóa khỏi session
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
    }

    // Nếu không còn sản phẩm nào thì xóa luôn giỏ
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }

    // Nếu bạn lưu cart vào database thì có thể thêm truy vấn xóa ở đây (nếu cần)
    // $conn->query("DELETE FROM cart_items WHERE id = $product_id");
}

// XÓA NHIỀU SẢN PHẨM ĐƯỢC CHỌN QUA POST
if (isset($_POST['selected_items']) && is_array($_POST['selected_items'])) {
    $selected_items = $_POST['selected_items'];

    // Xóa khỏi database
    $placeholders = implode(',', array_fill(0, count($selected_items), '?'));
    $sql = "DELETE FROM cart_items WHERE id IN ($placeholders)";
    $stmt = $conn->prepare($sql);

    // Bind các tham số
    $types = str_repeat('i', count($selected_items));
    $stmt->bind_param($types, ...$selected_items);

    if ($stmt->execute()) {
        // Đồng thời xóa khỏi session
        foreach ($selected_items as $id) {
            if (isset($_SESSION['cart'][$id])) {
                unset($_SESSION['cart'][$id]);
            }
        }

        // Nếu giỏ trống thì xóa luôn session giỏ hàng
        if (empty($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }

        $_SESSION['toast_message'] = "🎉 Đã xóa các sản phẩm đã chọn!";
    } else {
        $_SESSION['toast_message'] = "❌ Lỗi khi xóa sản phẩm!";
    }
} elseif (!isset($_GET['product_id'])) {
    // Trường hợp không có GET và không có POST — không có gì để xóa
    $_SESSION['toast_message'] = "❌ Bạn chưa chọn sản phẩm nào để xóa!";
}

// Quay lại trang giỏ hàng
header("Location: home.php");
exit;

$conn->close();
