<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Quên mật khẩu - Luna Beauty</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #ffe4ec, #fff0f5);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .box {
            background-color: white;
            padding: 40px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }
        h2 {
            color: #e84a70;
            margin-bottom: 20px;
        }
        input[type="text"], input[type="submit"] {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            background-color: #e84a70;
            color: white;
            font-weight: bold;
            cursor: pointer;
        }
        a {
            color: #e84a70;
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="box">
        <h2>Quên mật khẩu</h2>
        <p>Nhập số điện thoại để nhận mã OTP</p>
        <form method="post" action="">
            <input type="text" name="phone" placeholder="Số điện thoại" required>
            <input type="submit" value="Nhận mã OTP">
        </form>
        <a href="login.php">Quay lại đăng nhập</a>
    </div>
</body>
</html>