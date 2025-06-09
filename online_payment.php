<?php
session_start();
require_once "connect.php";

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Kiểm tra order_id
if (!isset($_GET['order_id'])) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Không tìm thấy đơn hàng.</h3>";
    exit();
}

$order_id = $_GET['order_id'];

// Lấy thông tin đơn hàng từ database
$sql = "SELECT * FROM orders WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    echo "<h3 style='color:red;text-align:center;'>Lỗi: Đơn hàng không tồn tại hoặc không thuộc về bạn.</h3>";
    exit();
}

// Danh sách ngân hàng phổ biến tại Việt Nam
$banks = [
    ['name' => 'Vietcombank', 'logo' => 'https://tse1.mm.bing.net/th?id=OIP.6rGzO2j2Dy_7dotwoZCvPgHaHa&pid=Api&P=0&h=220'],
    ['name' => 'Techcombank', 'logo' => 'https://tse3.mm.bing.net/th?id=OIP.h69Ev9CqbK2YyzEKbFzRaAHaHa&pid=Api&P=0&h=220'],
    ['name' => 'MB Bank', 'logo' => 'https://tse2.mm.bing.net/th?id=OIP.2UMwVoG_D1MvvUlXkYEMRwHaEk&pid=Api&P=0&h=220'],
    ['name' => 'TPBank', 'logo' => 'https://tse1.mm.bing.net/th?id=OIP.6qNoR7WjSTuCSOY6jMoYiQHaGk&pid=Api&P=0&h=220'],
    ['name' => 'Vietinbank', 'logo' => 'https://tse4.mm.bing.net/th?id=OIP.H7fQyuP3FJ1Px_-2H6CV5QHaHa&pid=Api&P=0&h=220'],
    ['name' => 'Agribank', 'logo' => 'https://tse4.mm.bing.net/th?id=OIP.1IANskBu-NeTcwgeF1IWSQHaHa&pid=Api&P=0&h=220']
];

// Đóng kết nối
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh Toán Online - Đơn Hàng #<?php echo htmlspecialchars($order_id); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gradient-to-br from-pink-100 to-red-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white max-w-lg w-full rounded-3xl shadow-xl p-8 transform ">
        <h2 class="text-3xl font-semibold text-center text-pink-600 mb-6">Thanh Toán Đơn Hàng #<?php echo htmlspecialchars($order_id); ?></h2>
        
        <!-- Order Summary -->
        <div class="bg-pink-50 p-4 rounded-xl mb-6 border border-pink-200">
            <h3 class="text-lg font-medium text-pink-600 mb-2">Tóm Tắt Đơn Hàng</h3>
            <div class="flex justify-between text-gray-700">
                <span>Tổng cộng:</span>
                <span><?php echo number_format($order['final_total']); ?>đ</span>
            </div>
            <div class="flex justify-between text-gray-700 mt-2">
                <span>Phương thức:</span>
                <span>Thanh toán online</span>
            </div>
        </div>

        <!-- Payment Options -->
        <form id="paymentForm" action="process_payment.php" method="POST">
            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order_id); ?>">
            <div class="mb-6">
                <label class="block text-gray-700 font-medium mb-2">Chọn phương thức thanh toán:</label>
                <div class="flex gap-4 mb-4">
                    <button type="button" onclick="showPaymentOption('bank')" class="flex-1 bg-pink-500 text-white py-2 rounded-lg hover:bg-pink-600 transition">Thanh toán qua ngân hàng</button>
                    <button type="button" onclick="showPaymentOption('qr')" class="flex-1 bg-pink-500 text-white py-2 rounded-lg hover:bg-pink-600 transition">Thanh toán qua QR</button>
                </div>

                <!-- Bank Payment Option -->
                <div id="bankPayment" class="hidden">
                    <label for="bank" class="block text-gray-700 font-medium mb-2">Chọn ngân hàng:</label>
                    <div class="grid grid-cols-2 gap-4">
                        <?php foreach ($banks as $bank): ?>
                            <label class="flex items-center gap-2 p-2 border rounded-lg cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="bank" value="<?php echo htmlspecialchars($bank['name']); ?>" required class="text-pink-500 focus:ring-pink-500">
                                <img src="<?php echo htmlspecialchars($bank['logo']); ?>" alt="<?php echo htmlspecialchars($bank['name']); ?>" class="w-8 h-8">
                                <span><?php echo htmlspecialchars($bank['name']); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- QR Code Payment Option -->
                <div id="qrPayment" class="hidden text-center">
                    <p class="text-gray-700 mb-4">Quét mã QR để thanh toán:</p>
                    <img src="assets/images/QRCode.png" alt="QR Code" class="mx-auto mb-4" width="100px">
                    <p class="text-sm text-gray-500">Sử dụng ứng dụng ngân hàng để quét mã QR và hoàn tất thanh toán.</p>
                </div>
            </div>

            <!-- Submit Button -->
            <button id="submitButton" type="submit" class="w-full bg-gradient-to-r from-pink-500 to-orange-500 text-white py-3 rounded-full hover:from-pink-600 hover:to-orange-600 transition-all flex items-center justify-center gap-2">
                <i class="fas fa-check-circle"></i> Tiến Hành Thanh Toán
            </button>
        </form>
    </div>

    <script>
        function showPaymentOption(option) {
            const bankPayment = document.getElementById('bankPayment');
            const qrPayment = document.getElementById('qrPayment');
            const submitButton = document.getElementById('submitButton');

            if (option === 'bank') {
                bankPayment.classList.remove('hidden');
                qrPayment.classList.add('hidden');
                submitButton.classList.remove('hidden');
                // Ensure at least one bank is selected
                document.querySelector('input[name="bank"]').required = true;
            } else if (option === 'qr') {
                bankPayment.classList.add('hidden');
                qrPayment.classList.remove('hidden');
                submitButton.classList.add('hidden'); // Hide submit for QR, as payment is done externally
                document.querySelector('input[name="bank"]').required = false;
            }
        }

        // Format number for display
        function number_format(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
    </script>
</body>
</html>