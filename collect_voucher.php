<?php
session_start();
require_once 'connect.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Vui lòng đăng nhập để thu thập voucher!';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$voucher_id = intval($_POST['voucher_id'] ?? 0);
$voucher_code = trim($_POST['voucher_code'] ?? '');

if (!$voucher_id || !$voucher_code) {
    $response['message'] = 'Dữ liệu không hợp lệ!';
    echo json_encode($response);
    exit;
}

// Kiểm tra voucher có tồn tại và còn hiệu lực
$sql = "SELECT * FROM vouchers WHERE id = ? AND code = ? AND is_active = 1 AND expires_at > NOW()";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $voucher_id, $voucher_code);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $response['message'] = 'Voucher không hợp lệ hoặc đã hết hạn!';
    echo json_encode($response);
    exit;
}

// Kiểm tra xem người dùng đã thu thập voucher này chưa
$sql = "SELECT * FROM user_vouchers WHERE user_id = ? AND voucher_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $voucher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $response['message'] = 'Bạn đã thu thập voucher này rồi!';
    echo json_encode($response);
    exit;
}

// Lưu voucher vào user_vouchers
$sql = "INSERT INTO user_vouchers (user_id, voucher_id, collected_at) VALUES (?, ?, NOW())";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $user_id, $voucher_id);
if ($stmt->execute()) {
    $response['success'] = true;
    $response['message'] = 'Thu thập voucher thành công!';
} else {
    $response['message'] = 'Đã có lỗi xảy ra, vui lòng thử lại!';
}

echo json_encode($response);
?>