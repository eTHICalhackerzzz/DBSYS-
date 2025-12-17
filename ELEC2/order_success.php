<?php
session_start();
require_once "pdo.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['last_order_id'])) {
    header("Location: Products.php");
    exit();
}

$order_id = $_SESSION['last_order_id'];

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch items of this order
$stmt_items = $pdo->prepare("
    SELECT product_name, quantity, price, subtotal 
    FROM order_items 
    WHERE order_id = ?
");
$stmt_items->execute([$order_id]);
$items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Order Successful</title>
    <style>
        body {
            background: linear-gradient(135deg, #ffe6f2, #ffecf7, #e8f3ff, #f0e9ff);
            background-size: 300% 300%;
            animation: pastelFlow 18s ease infinite;
            position: relative;
            overflow-x: hidden;
            text-align: center;
            padding-top: 50px;
        }
        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle, rgba(255,255,255,0.9) 2px, transparent 3px),
                radial-gradient(circle, rgba(255,255,255,0.7) 1.5px, transparent 3px),
                radial-gradient(circle, rgba(255,255,255,0.5) 2px, transparent 3px);
            background-size: 180px 180px, 140px 140px, 220px 220px;
            animation: sparkleFloat 12s linear infinite;
            pointer-events: none;
            opacity: 0.7;
        }

        /* pastel gradient animation */
        @keyframes pastelFlow {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* floating sparkle animation */
        @keyframes sparkleFloat {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .box {
            background: white;
            padding: 40px;
            width: 550px;
            margin: auto;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            text-align: left;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }
        .btn {
            margin-top: 25px;
            display: inline-block;
            padding: 12px 20px;
            background: #28a745;
            color: white;
            border-radius: 8px;
            text-decoration: none;
        }
    </style>
</head>
<body>

<div class="box">
    <h1>🎉 Order Placed Successfully!</h1>
    <p><strong>Order ID:</strong> <?= $order['id'] ?></p>
    <p><strong>Date:</strong> <?= $order['created_at'] ?></p>
    <h3>Customer Information</h3>
    <p><strong>Name:</strong> <?= htmlspecialchars($order['full_name']) ?></p>
    <p><strong>Address:</strong> <?= nl2br(htmlspecialchars($order['address'])) ?></p>
    <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
    <p><strong>Email:</strong> <?= htmlspecialchars($order['email']) ?></p>
    <br>


    <h3>Purchased Items:</h3>

    <table>
        <tr>
            <th>Item</th>
            <th>Qty</th>
            <th>Subtotal</th>
        </tr>

        <?php foreach ($items as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['product_name']) ?></td>
                <td><?= $item['quantity'] ?></td>
                <td>₱<?= number_format($item['subtotal'], 2) ?></td>
            </tr>
        <?php endforeach; ?>

        <tr>
            <td colspan="2"><strong>Total</strong></td>
            <td><strong>₱<?= number_format($order['total_price'], 2) ?></strong></td>
        </tr>
    </table>

    <a href="Products.php" class="btn">Continue Shopping</a>
    <a href="order_history.php" class="btn" style="background:#007bff;">View Order History</a>
</div>

</body>
</html>
