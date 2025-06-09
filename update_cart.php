<?php
session_start();

if (isset($_POST['delete'])) {
    unset($_SESSION['cart'][$_POST['delete']]);
}

if (isset($_POST['increase'])) {
    $_SESSION['cart'][$_POST['increase']]['qty']++;
}

if (isset($_POST['decrease'])) {
    $i = $_POST['decrease'];
    if ($_SESSION['cart'][$i]['qty'] > 1) {
        $_SESSION['cart'][$i]['qty']--;
    }
}

if (isset($_POST['qty'])) {
    foreach ($_POST['qty'] as $index => $qty) {
        $_SESSION['cart'][$index]['qty'] = max(1, intval($qty));
    }
}

header("Location: cart.php");