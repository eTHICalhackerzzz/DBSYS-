<?php
session_start();
require_once "pdo.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch orders for this user
$stmt = $pdo->prepare("
    SELECT id, total_price, created_at 
    FROM orders 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order History</title>
    <style>
        body {
            background: #f2f2f2;
            font-family: Arial;
            padding-top: 40px;
        }
        .container {
            width: 750px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .order-box {
            background: #fafafa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .total {
            text-align: right;
            font-weight: bold;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Your Order History</h2>

    <?php if (count($orders) === 0): ?>
        <p>No orders yet.</p>

    <?php else: ?>
        <?php foreach ($orders as $order): ?>

            <div class="order-box">
                <h3>Order #<?= $order['id'] ?></h3>
                <p>Date: <?= $order['created_at'] ?></p>

                <?php
                // Fetch items of this order
                $stmt_items = $pdo->prepare("
                    SELECT product_name, quantity, price, subtotal
                    FROM order_items
                    WHERE order_id = ?
                ");
                $stmt_items->execute([$order['id']]);
                $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <table>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Subtotal</th>
                    </tr>

                    <?php foreach ($items as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['product_name']) ?></td>
                            <td><?= $i['quantity'] ?></td>
                            <td>₱<?= number_format($i['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>

                <p class="total">Total: ₱<?= number_format($order['total_price'], 2) ?></p>
            </div>

        <?php endforeach; ?>
    <?php endif; ?>

    <a href="Products.php" class="btn">Back to Shop</a>
</div>

</body>
</html>
