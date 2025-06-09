<?php
$host = "localhost";
$user = "root"; // Tên tài khoản MySQL (XAMPP mặc định là 'root')
$password = ""; // Mật khẩu MySQL (XAMPP mặc định để trống)
$database = "db_mypham";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Kết nối thất bại: " . $conn->connect_error);
}
?>
