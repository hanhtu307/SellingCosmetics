<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $errors = [];

    if (empty($username) || empty($password)) {
        $errors[] = "Vui lòng nhập tên đăng nhập và mật khẩu.";
    }

    if (empty($errors)) {
        require_once 'connect.php';

        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($id, $user, $hashed_password);
            $stmt->fetch();

            if (password_verify($password, $hashed_password)) {
                $_SESSION['user_id'] = $id;
                $_SESSION['username'] = $user;
                header("Location: home.php");
                exit;
            } else {
                $errors[] = "Mật khẩu không đúng.";
            }
        } else {
            $errors[] = "Tên đăng nhập không tồn tại.";
        }

        $stmt->close();
        $conn->close();
    }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Luna Beauty</title>
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

        .login-box {
            background-color: white;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            width: 400px;
            text-align: center;
        }

        .login-box h2 {
            color: #e84a70;
            margin-bottom: 10px;
        }

        .login-box p {
            color:red;
            margin-bottom: 25px;
        }

        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }

        .login-box input[type="submit"] {
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

        .error {
            color: red;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .register-link {
            margin-top: 15px;
            display: block;
            color: black;
            text-decoration: none;
        }

        .register-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h2>Đăng nhập Luna Beauty</h2>
        <p>Chào mừng bạn quay trở lại!</p>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <?php foreach ($errors as $err) echo "<p>$err</p>"; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="text" name="username" placeholder="Tên đăng nhập" required>
            <input type="password" name="password" placeholder="Mật khẩu" required>
            <input type="submit" value="Đăng nhập">
            <div style="margin-top: 10px; text-align: right;">
                <a href="forgot_password.php" style="color: black; font-size: 14px; text-decoration: none;">Quên mật khẩu?</a>
            </div>
        </form>

        <div class="social-login">
            <span>Hoặc đăng nhập bằng</span>
            <div class="social-icons">
                <a href="#" class="fb"><i class="fab fa-facebook-f"></i></a>
                <a href="#" class="gg"><i class="fab fa-google"></i></a>
            </div>
        </div>

        <a href="register.php" class="register-link">Chưa có tài khoản? Đăng ký ngay</a>
    </div>
</body>

</html>