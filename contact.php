<?php
require_once 'connect.php'; // Include the database connection

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name']);
    $email   = trim($_POST['email']);
    $message = trim($_POST['message']);

    if ($name && $email && $message) {
        // Prepare and execute the SQL query to insert data into the contact table
        $stmt = $conn->prepare("INSERT INTO contact (name, email, message) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $email, $message);

        if ($stmt->execute()) {
            $success = "Cảm ơn bạn đã liên hệ. Chúng tôi sẽ phản hồi sớm nhất!";
        } else {
            $error = "Có lỗi xảy ra khi gửi tin nhắn. Vui lòng thử lại.";
            error_log("SQL Error (contact insert): " . $conn->error, 3, "errors.log");
        }

        $stmt->close();
    } else {
        $error = "Vui lòng điền đầy đủ thông tin.";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Liên hệ - Luna Beauty</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #fff0f5, #ffe4ec);
            margin: 0;
            padding: 0;
        }
        .container {
            width: 90%;
            max-width: 600px;
            margin: 60px auto;
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        h2 {
            color: #e84a70;
            margin-bottom: 20px;
            text-align: center;
        }
        input, textarea {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 8px;
            border: 1px solid #ccc;
            box-sizing: border-box;
            font-size: 15px;
        }
        button {
            background-color: #e84a70;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 16px;
            width: 100%;
        }
        .success {
            color: green;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
        .error {
            color: red;
            font-weight: bold;
            text-align: center;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Liên hệ với Luna Beauty</h2>

        <?php if ($success): ?>
            <p class="success"><?= htmlspecialchars($success) ?></p>
            <script>
                // Redirect to home.php after 5 seconds
                setTimeout(function() {
                    window.location.href = 'home.php';
                }, 5000); // 5000 milliseconds = 5 seconds
            </script>
        <?php elseif ($error): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="name" placeholder="Họ và tên" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
            <input type="email" name="email" placeholder="Email của bạn" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
            <textarea name="message" rows="5" placeholder="Nội dung liên hệ..." required><?= isset($_POST['message']) ? htmlspecialchars($_POST['message']) : '' ?></textarea>
            <button type="submit">Gửi liên hệ</button>
        </form>
    </div>
</body>
</html>