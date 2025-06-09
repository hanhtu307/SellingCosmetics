
<?php
// Xử lý đăng ký
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = [];

    // Kiểm tra dữ liệu
    if (empty($username) || empty($phone) || empty($password) || empty($confirm_password)) {
        $errors[] = "Vui lòng điền đầy đủ thông tin.";
    } elseif (!preg_match('/^0[0-9]{9}$/', $phone)) {
        $errors[] = "Số điện thoại không hợp lệ.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Mật khẩu không khớp.";
    }

    // Nếu hợp lệ, lưu vào CSDL
    if (empty($errors)) {
        require_once 'connect.php'; // file kết nối CSDL

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, phone, password) VALUES (?, ?, ?)");

        if ($stmt) {
            $stmt->bind_param("sss", $username, $phone, $hashed_password);
            $stmt->execute();
            $stmt->close();
        } else {
            echo "Lỗi khi chuẩn bị truy vấn: " . $conn->error;
        }

        $conn->close();
    } else {
        foreach ($errors as $error) {
            echo "<p style='color:red;'>$error</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng ký tài khoản - Luna Beauty</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(to right, #ffe4ec, #fff0f5);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .register-box {
            background-color: white;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        .register-box h2 {
            color: #e84a70;
            margin-bottom: 10px;
        }

        .register-box p {
            color: #888;
            margin-bottom: 25px;
        }

        .register-box input[type="text"],
        .register-box input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .register-box input[type="submit"] {
            background-color: #e84a70;
            color: white;
            padding: 12px;
            width: 100%;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
        }

        .social-login {
            margin-top: 20px;
        }

        .social-login span {
            display: block;
            margin-bottom: 10px;
            color: #666;
        }

        .social-icons a {
            display: inline-block;
            width: 44px;
            height: 44px;
            line-height: 44px;
            border-radius: 50%;
            margin: 0 8px;
            font-size: 20px;
            background-color: #f0f0f0;
            color: #333;
            text-decoration: none;
            transition: 0.3s;
        }

        .social-icons a:hover {
            transform: scale(1.1);
        }

        .social-icons .fb {
            background-color: #3b5998;
            color: white;
        }

        .social-icons .gg {
            background-color: #db4437;
            color: white;
        }

        .alert {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            animation: fadeIn 0.4s ease-in-out;
            text-align: left;
        }

        .alert p {
            margin: 0;
        }

        .success-alert {
            background-color: #e0f8ec;
            color: #2e7d32;
            border-left: 5px solid #2e7d32;
        }

        .error-alert {
            background-color: #fcebea;
            color: #d32f2f;
            border-left: 5px solid #d32f2f;
        }

        .alert i {
            font-size: 18px;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(-6px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body>
    <div class="register-box">
        <h2>Đăng ký Luna Beauty</h2>
        <p>Trở thành thành viên để nhận ưu đãi đặc biệt!</p>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo "<p>$err</p>"; ?>
            </div>
        <?php endif; ?>
        <form method="post" action="">
            <?php if (!empty($errors)): ?>
                <div class="alert error-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php foreach ($errors as $err) echo "<p>$err</p>"; ?>
                </div>
            <?php endif; ?>

            <?php if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($errors)): ?>
                <div class="alert success-alert">
                    <i class="fas fa-check-circle"></i>
                    <p>Đăng ký thành công! <a href='login.php'>Đăng nhập</a></p>
                </div>
            <?php endif; ?>

            <input type="text" name="username" placeholder="Tên đăng nhập" required>
            <input type="text" name="phone" placeholder="Số điện thoại" required pattern="0[0-9]{9}" title="Số điện thoại phải có 10 chữ số bắt đầu bằng 0">
            <input type="password" name="password" placeholder="Mật khẩu" required>
            <input type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
            <input type="submit" value="Đăng ký">
        </form>


        <div class="social-login">
            <span>Hoặc đăng ký bằng</span>
            <div class="social-icons">
                <a href="https://www.facebook.com/" class="fb"><i class="fab fa-facebook-f"></i></a>
                <a href="https://www.google.com.vn/" class="gg"><i class="fab fa-google"></i></a>
            </div>
        </div>
    </div>
</body>

</html>
