<?php
session_start();
require_once "connect.php";

if ($conn->connect_error) {
    die(json_encode(['error' => 'Kết nối thất bại: ' . $conn->connect_error]));
}

$monthlyRevenue = [];
$monthlyProductsSold = [];
$monthlyComments = [];
$topProducts = [];

// Query for monthly revenue
$resultRevenue = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        SUM(final_total) AS revenue
    FROM orders
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultRevenue) {
    while ($row = $resultRevenue->fetch_assoc()) {
        $monthlyRevenue[] = [
            'month' => $row['month'],
            'revenue' => (float)$row['revenue']
        ];
    }
}

// Query for total products sold per month
$resultProductsSold = $conn->query("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS month,
        SUM(oi.quantity) AS total_sold
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE YEAR(o.created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultProductsSold) {
    while ($row = $resultProductsSold->fetch_assoc()) {
        $monthlyProductsSold[] = [
            'month' => $row['month'],
            'total_sold' => (int)($row['total_sold'] ?? 0)
        ];
    }
}

// Query for total comments per month
$resultComments = $conn->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS month,
        COUNT(*) AS total_comments
    FROM product_reviews
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
");
if ($resultComments) {
    while ($row = $resultComments->fetch_assoc()) {
        $monthlyComments[] = [
            'month' => $row['month'],
            'total_comments' => (int)$row['total_comments']
        ];
    }
}

// Query for top-selling product per month
$resultTopProducts = $conn->query("
    SELECT 
        DATE_FORMAT(o.created_at, '%Y-%m') AS month,
        oi.product_name,
        SUM(oi.quantity) AS total_quantity
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE YEAR(o.created_at) = YEAR(CURDATE())
    GROUP BY DATE_FORMAT(o.created_at, '%Y-%m'), oi.product_name
    ORDER BY month ASC, total_quantity DESC
");
if ($resultTopProducts) {
    $tempTopProducts = [];
    while ($row = $resultTopProducts->fetch_assoc()) {
        $month = $row['month'];
        if (!isset($tempTopProducts[$month])) {
            $tempTopProducts[$month] = [
                'product_name' => $row['product_name'],
                'total_quantity' => (int)$row['total_quantity']
            ];
        }
    }
    foreach ($tempTopProducts as $month => $data) {
        $topProducts[] = [
            'month' => $month,
            'product_name' => $data['product_name'],
            'total_quantity' => $data['total_quantity']
        ];
    }
}

header('Content-Type: application/json');
echo json_encode([
    'monthlyRevenue' => $monthlyRevenue,
    'monthlyProductsSold' => $monthlyProductsSold,
    'monthlyComments' => $monthlyComments,
    'topProducts' => $topProducts
]);

$conn->close();
?>