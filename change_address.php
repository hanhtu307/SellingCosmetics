
<?php
session_start();
// Giả sử user_id đã lưu trong session sau khi đăng nhập
$user_id = $_SESSION['user_id'] ?? 0;
$success = false;

$conn = new mysqli("localhost", "root", "", "db_mypham");
$conn->set_charset("utf8");

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}

$sql = "SELECT * FROM dia_chi WHERE user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Thay đổi địa chỉ</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f6f6f6;
            padding: 40px 20px;
            color: #333;
        }

        .address-container {
            background: #fff;
            padding: 35px 40px;
            border-radius: 16px;
            max-width: 800px;
            margin: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .address-container h2 {
            text-align: center;
            color: #e84a70;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .address-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 10px;
            background-color: #fcfcfc;
            transition: 0.3s;
            flex-direction: column;
        }

        .address-item:hover {
            background-color: #fff;
            border-color: #e84a70;
            box-shadow: 0 2px 10px rgba(232, 74, 112, 0.1);
        }

        .address-content .row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 6px;
        }

        .address-content .row span {
            font-weight: normal;
            font-size: 15px;
            color: #666;
        }

        .address-content .address-text {
            font-size: 15px;
            line-height: 1.5;
            color: #444;
        }

        .address-content .default {
            color: #28a745;
            font-size: 14px;
            font-style: italic;
            margin-top: 6px;
        }

        .btn-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.25s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: #e84a70;
            color: #fff;
        }

        .btn-primary:hover {
            background-color: #d03d61;
            box-shadow: 0 4px 10px rgba(232, 74, 112, 0.3);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background-color: #fff;
            color: #e84a70;
            border: 2px solid #e84a70;
        }

        .btn-secondary:hover {
            background-color: #ffeaf0;
            box-shadow: 0 4px 10px rgba(232, 74, 112, 0.15);
            transform: translateY(-1px);
        }

        form {
            display: flex;
            flex-direction: column;
        }
    </style>

</head>

<body>
    <div class="address-container">
        <h2>Địa chỉ giao hàng</h2>
        <?php if ($result->num_rows > 0): ?>
            <form action="confirm_order.php" method="post">
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="address-item">
                        <input type="radio" name="address_id" value="<?= $row['id'] ?>" <?= $row['mac_dinh'] ? 'checked' : '' ?>>
                        <div class="address-content">
                            <div class="row">
                                <strong><?= htmlspecialchars($row['ho_ten']) ?></strong>
                            </div>
                            <div class="row">
                                <span>SĐT: <?= htmlspecialchars($row['so_dien_thoai']) ?></span>
                            </div>
                            <div class="address-text">
                                <?= htmlspecialchars($row['dia_chi_day_du']) ?>
                            </div>
                            <?php if ($row['mac_dinh']): ?>
                                <span class="default">(Địa chỉ mặc định)</span>
                            <?php endif; ?>
                        </div>
                    </div>


                <?php endwhile; ?>
                <br>
                <div class="btn-container">
                    <a href="add_address.php" class="btn btn-secondary">+ Thêm địa chỉ mới</a>
                    <button type="submit" class="btn btn-primary">OK</button>
                </div>

            </form>
        <?php else: ?>
            <p>Bạn chưa có địa chỉ nào.</p>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="toast">🎉 Lưu địa chỉ thành công! Đang chuyển đến trang xác nhận...</div>
            <script>
                setTimeout(() => {
                    window.location.href = "confirm_order.php";
                }, 2000);
            </script>
        <?php endif; ?>
    </div>
</body>

</html>

<?php
$stmt->close();
$conn->close();
?>
