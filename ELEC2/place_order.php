<?php
session_start();
require_once "pdo.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['cart']) || count($_SESSION['cart']) === 0) {
    header("Location: checkout.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$cart = $_SESSION['cart'];

// 1. CREATE ORDER
$stmt = $pdo->prepare("INSERT INTO orders (student_id, total_price) VALUES (?, ?)");
$total = 0;

foreach ($cart as $item) {
    $total += $item['price'] * $item['quantity'];
}

$stmt->execute([$user_id, $total]);
$order_id = $pdo->lastInsertId();

// 2. INSERT EACH ORDER ITEM
$stmt_item = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");

foreach ($cart as $item) {
    $stmt_item->execute([
        $order_id,
        $item['id'],
        $item['quantity'],
        $item['price']
    ]);
}

// 3. CLEAR CART
unset($_SESSION['cart']);

// 4. ENABLE ORDER SUCCESS PAGE
$_SESSION['checkout'] = true;
$_SESSION['last_order_id'] = $order_id;

// 5. REDIRECT TO SUCCESS PAGE
header("Location: order_success.php");
exit();
?>
