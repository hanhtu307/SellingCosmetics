
<?php
session_start();
require 'connect.php'; // file chứa thông tin kết nối CSDL
$avatarPath = "uploads/avatars/" . htmlspecialchars($user['avatar'] ?? "default-avatar.png");

$sql = "SELECT username, phone, email, avatar FROM users WHERE id = ?";
// Giả sử bạn đã lưu user_id khi đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$sql = "SELECT username, phone, email, avt FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
} else {
    echo "Không tìm thấy người dùng.";
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Tài khoản & Bảo mật</title>
    <style>
        body {
            font-family: Arial;
            background: #f5f5f5;
        }

        .security-container {
            max-width: 700px;
            margin: 40px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .security-section {
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid #ddd;
        }

        h2 {
            color: #e94c64;
        }

        .security-section a {
            color: #0055aa;
            text-decoration: none;
        }

        .security-section a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="security-container">
        <h2>Tài khoản & Bảo mật</h2>

        <!-- Avatar + Thông tin -->
        <div style="display: flex; align-items: center; margin-bottom: 25px;">
            <img src="assets/<?= htmlspecialchars($user['avt'] ?? 'default.jpg') ?>" alt="Avatar" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid #ddd; margin-right: 20px;">
            <div>
                <p><strong>Họ tên:</strong> <?= htmlspecialchars($user['username']) ?></p>
                <p><strong>Số điện thoại:</strong> <?= htmlspecialchars($user['phone']) ?></p>
                <p><strong>Email:</strong>
                    <?= empty($user['email']) ? ' Chưa thêm email' : htmlspecialchars($user['email']) ?>
                </p>
                <a href="#">Thay đổi thông tin</a>
            </div>
        </div>

        <!-- Đổi mật khẩu -->
        <div class="security-section">
            <h3>Đổi mật khẩu</h3>
            <a href="change_password.php">Thay đổi mật khẩu</a>
        </div>

        <!-- Xác minh 2 bước -->
        <div class="security-section">
            <h3>Xác minh 2 bước</h3>
            <p>(Tùy chọn chưa được kích hoạt)</p>
        </div>
    </div>
</body>

</html>
