<?php
session_start();
require_once "connect.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ho_ten = $_POST["ho_ten"];
    $so_dien_thoai = $_POST["so_dien_thoai"];
    $dia_chi_day_du = $_POST["dia_chi_day_du"];
    $mac_dinh = isset($_POST["mac_dinh"]) ? 1 : 0;
    if ($mac_dinh) {
        $stmt = $conn->prepare("UPDATE dia_chi SET mac_dinh = 0 WHERE user_id = ?");
        $stmt->execute([$user_id]);
    }

    $stmt = $conn->prepare("INSERT INTO dia_chi (user_id, ho_ten, so_dien_thoai, dia_chi_day_du, mac_dinh) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $ho_ten, $so_dien_thoai, $dia_chi_day_du, $mac_dinh]);

    $success = true;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Địa Chỉ - Luna Beauty</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<style>
    /* Global Reset and Base Styles */
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', sans-serif;
        background: linear-gradient(135deg, #fff5f7 0%, #f8e9ec 100%);
        color: #333;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 20px;
    }

    /* Form Container */
    .form-container {
        background: #fff;
        max-width: 500px;
        width: 100%;
        padding: 40px;
        border-radius: 24px;
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .form-container:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 40px rgba(0, 0, 0, 0.12);
    }

    h2 {
        text-align: center;
        color: #ff6b81;
        font-size: 28px;
        font-weight: 600;
        margin-bottom: 24px;
    }

    /* Form Labels and Inputs */
    label {
        font-size: 16px;
        font-weight: 500;
        color: #333;
        display: block;
        margin-bottom: 8px;
    }

    input[type="text"],
    input[type="tel"] {
        width: 100%;
        padding: 14px;
        margin-bottom: 20px;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        font-size: 16px;
        color: #333;
        background: #fff;
        transition: all 0.3s ease;
    }

    input[type="text"]:focus,
    input[type="tel"]:focus {
        border-color: #ff6b81;
        box-shadow: 0 4px 12px rgba(255, 107, 129, 0.2);
        outline: none;
    }

    /* Checkbox */
    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 16px;
        color: #555;
        margin-bottom: 24px;
    }

    input[type="checkbox"] {
        width: 20px;
        height: 20px;
        accent-color: #ff6b81;
        cursor: pointer;
    }

    /* Submit Button */
    button {
        width: 100%;
        padding: 16px;
        background: linear-gradient(90deg, #ff6b81 0%, #ff8e53 100%);
        color: #fff;
        border: none;
        border-radius: 50px;
        font-size: 16px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    button:hover {
        background: linear-gradient(90deg, #ff4d68 0%, #ff7036 100%);
        box-shadow: 0 8px 24px rgba(255, 107, 129, 0.5);
        transform: translateY(-3px);
    }

    /* Toast Notification */
    .toast {
        position: fixed;
        top: 20px;
        right: 20px;
        background: #2ecc71;
        color: #fff;
        padding: 16px 24px;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 500;
        z-index: 9999;
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideFadeIn 0.5s ease;
    }

    .toast i {
        font-size: 20px;
    }

    /* Animations */
    @keyframes slideFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .form-container {
            max-width: 450px;
            padding: 32px;
        }

        h2 {
            font-size: 24px;
        }

        input[type="text"],
        input[type="tel"] {
            padding: 12px;
            font-size: 15px;
        }

        button {
            padding: 14px;
            font-size: 15px;
        }
    }

    @media (max-width: 600px) {
        body {
            padding: 16px;
        }

        .form-container {
            max-width: 100%;
            padding: 24px;
        }

        h2 {
            font-size: 22px;
            margin-bottom: 20px;
        }

        label {
            font-size: 15px;
        }

        input[type="text"],
        input[type="tel"] {
            padding: 10px;
            font-size: 14px;
            margin-bottom: 16px;
        }

        .checkbox-label {
            font-size: 15px;
        }

        button {
            padding: 12px;
            font-size: 14px;
        }

        .toast {
            top: 16px;
            right: 16px;
            padding: 12px 20px;
            font-size: 14px;
        }

        .toast i {
            font-size: 18px;
        }
    }
</style>
<body>
    <div class="form-container">
        <h2>Thêm Địa Chỉ</h2>
        <form method="POST">
            <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">

            <label for="ho_ten">Họ tên</label>
            <input type="text" name="ho_ten" id="ho_ten" required>

            <label for="so_dien_thoai">Số điện thoại</label>
            <input type="tel" name="so_dien_thoai" id="so_dien_thoai" required pattern="0[0-9]{9}" title="Số điện thoại phải có 10 chữ số bắt đầu bằng 0">

            <label for="dia_chi_day_du">Địa chỉ</label>
            <input type="text" name="dia_chi_day_du" id="dia_chi_day_du" required>

            <label class="checkbox-label" for="mac_dinh">
                <input type="checkbox" name="mac_dinh" id="mac_dinh">
                Đặt làm địa chỉ mặc định
            </label>

            <button type="submit">Lưu Địa Chỉ</button>
        </form>
    </div>

    <?php if ($success): ?>
        <div class="toast">
            <i class="fas fa-check-circle"></i>
            Lưu địa chỉ thành công! Đang chuyển đến trang xác nhận...
        </div>
        <script>
            setTimeout(() => {
                window.location.href = "confirm_order.php";
            }, 2000);
        </script>
    <?php endif; ?>
</body>
</html>