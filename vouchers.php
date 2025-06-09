<?php
session_start();
require_once 'connect.php';

// Xử lý thu thập voucher
$voucherMessage = '';

if (isset($_GET['collect']) && isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $voucherId = intval($_GET['collect']);

    // Kiểm tra xem người dùng đã thu thập voucher này chưa
    $stmt = $conn->prepare("SELECT * FROM user_vouchers WHERE user_id = ? AND voucher_id = ?");
    $stmt->bind_param("ii", $userId, $voucherId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        // Thu thập voucher
        $insertStmt = $conn->prepare("INSERT INTO user_vouchers (user_id, voucher_id) VALUES (?, ?)");
        $insertStmt->bind_param("ii", $userId, $voucherId);
        if ($insertStmt->execute()) {
            $voucherMessage = "🎉 Bạn đã thu thập voucher thành công!";
        } else {
            $voucherMessage = "❌ Lỗi khi thu thập voucher.";
        }
    } else {
        $voucherMessage = "⚠️ Bạn đã thu thập voucher này rồi.";
    }
}

// Lấy danh sách voucher
$voucherSql = "SELECT * FROM vouchers";
$voucherResult = $conn->query($voucherSql);

// Lấy danh sách voucher đã thu thập nếu đăng nhập
$collectedVouchers = [];
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $userVoucherSql = "SELECT voucher_id FROM user_vouchers WHERE user_id = $userId";
    $userVoucherResult = $conn->query($userVoucherSql);
    while ($row = $userVoucherResult->fetch_assoc()) {
        $collectedVouchers[] = $row['voucher_id'];
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Danh sách Voucher</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #fffbea;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .voucher-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
        }

        .voucher-card {
            background-color: #fef9c3;
            border: 2px dashed #facc15;
            border-radius: 1rem;
            padding: 1.2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .voucher-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
        }

        .voucher-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #ca8a04;
            margin-bottom: 0.5rem;
        }

        .voucher-details p {
            margin: 0.25rem 0;
            color: #4b5563;
            font-size: 0.95rem;
        }

        .voucher-button {
            margin-top: auto;
            padding: 0.5rem 1rem;
            background-color: #facc15;
            border: none;
            color: #000;
            font-weight: 600;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .voucher-button:hover {
            background-color: #eab308;
        }

        .voucher-button:disabled {
            background-color: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .voucher-message {
            text-align: center;
            font-size: 1rem;
            margin-top: 1rem;
            color: #2563eb;
        }

        .home-button {
            display: inline-block;
            margin-bottom: 1rem;
            padding: 0.5rem 1rem;
            background-color: #3b82f6;
            color: #fff;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }

        .home-button:hover {
            background-color: #2563eb;
        }
    </style>
</head>
<body>
    <div class="max-w-7xl mx-auto py-8 px-4">
        <h1 class="text-3xl font-bold text-center text-yellow-600 mb-6">🎁 Danh sách Voucher</h1>

        <?php if ($voucherMessage): ?>
            <p class="voucher-message"><?= $voucherMessage ?></p>
        <?php endif; ?>

        <div class="voucher-grid">
            <?php while ($voucher = $voucherResult->fetch_assoc()): ?>
                <div class="voucher-card">
                    <div class="voucher-title"><?= htmlspecialchars($voucher['code']) ?></div>
                    <div class="voucher-details">
                        <p>Giảm: <?php echo $voucher['discount_type'] === 'percentage' ? ($voucher['discount'] * 100) . '%' : number_format($voucher['discount']) . 'đ'; ?></p>
                        <p><strong>HSD:</strong> <?= htmlspecialchars($voucher['expires_at']) ?></p>
                    </div>

                    <?php if (isset($_SESSION['user_id'])): ?>
                        <?php if (in_array($voucher['id'], $collectedVouchers)): ?>
                            <button class="voucher-button" disabled>Đã thu thập</button>
                        <?php else: ?>
                            <a href="?collect=<?= $voucher['id'] ?>" class="voucher-button">Thu thập</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <a href="login.php" class="voucher-button">Đăng nhập để thu thập</a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
        <a href="home.php" class="home-button">Quay về Trang Chủ</a>
    </div>
</body>
</html>