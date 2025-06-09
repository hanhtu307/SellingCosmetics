<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Vui lòng đăng nhập để sử dụng tính năng này.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $user_id = $_SESSION['user_id'];
    $product_id = (int)$_POST['product_id'];

    // Kiểm tra xem sản phẩm đã được yêu thích chưa
    $stmt = $conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->bind_param("ii", $user_id, $product_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        // Nếu đã yêu thích, xóa khỏi danh sách
        $stmt = $conn->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Đã xóa sản phẩm khỏi danh sách yêu thích.';
            $response['action'] = 'removed';
        } else {
            $response['message'] = 'Lỗi khi xóa sản phẩm khỏi danh sách yêu thích.';
        }
    } else {
        // Nếu chưa yêu thích, thêm vào danh sách
        $stmt = $conn->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $product_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = 'Đã thêm sản phẩm vào danh sách yêu thích.';
            $response['action'] = 'added';
        } else {
            $response['message'] = 'Lỗi khi thêm sản phẩm vào danh sách yêu thích.';
        }
    }
} else {
    $response['message'] = 'Yêu cầu không hợp lệ.';
}

echo json_encode($response);
?>
