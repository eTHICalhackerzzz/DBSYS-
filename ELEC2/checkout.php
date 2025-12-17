<?php
session_start();
require_once "pdo.php"; // database connection

// Require login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Load cart (DB version if available)
$cart = [];
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT product_id, product_name AS name, price, image, quantity AS qty
    FROM cart WHERE user_id = ?
");
$stmt->execute([$user_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($rows as $r) {
    $cart[$r['product_id']] = [
        'name' => $r['name'],
        'price' => floatval($r['price']),
        'image' => $r['image'],
        'qty' => intval($r['qty'])
    ];
}

// Redirect if cart empty
if (empty($cart)) {
    header("Location: cart.php");
    exit();
}

// Validate request origin
if (!isset($_POST['from_cart']) && !isset($_POST['place_order'])) {
    header("Location: cart.php");
    exit();
}

// Handle placing order
if (isset($_POST['place_order'])) {

    $fullname = $_POST['fullname'];
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    // Calculate total
    $total = 0;
    foreach ($cart as $item) {
        $total += $item['qty'] * $item['price'];
    }

    // Insert into orders table
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, full_name, address, phone, email, total_price)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $fullname, $address, $phone, $email, $total]);

    $order_id = $pdo->lastInsertId();
    $_SESSION['checkout'] = true;
    $_SESSION['last_order_id'] = $order_id;


    // Insert items into order_items
    $item_stmt = $pdo->prepare("
        INSERT INTO order_items (order_id, product_name, price, quantity, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($cart as $item) {
        $subtotal = $item['qty'] * $item['price'];
        $item_stmt->execute([
            $order_id,
            $item['name'],
            $item['price'],
            $item['qty'],
            $subtotal
        ]);
    }

    // Clear cart in DB
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    // Redirect to success page
    $_SESSION['success'] = "Order placed successfully!";
    header("Location: order_success.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Checkout</title>
    <style>
        body {
            background: linear-gradient(135deg, #1f1c2c, #928dab);
            font-family: Arial, sans-serif;
        }
        .checkout-container {
            max-width: 900px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
        }
        .section-title {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .input-box {
            width: 100%;
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }
        .order-summary {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .summary-total {
            font-weight: bold;
            font-size: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .place-order-btn {
            width: 100%;
            background: #28a745;
            padding: 12px;
            margin-top: 20px;
            border-radius: 8px;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
        }
        .place-order-btn:hover {
            background: #218838;
        }
    </style>
</head>

<body>

<div class="checkout-container">
    <h2>Checkout</h2>

    <form method="post">

        <h3 class="section-title">Billing Information</h3>

        <input type="text" name="fullname" class="input-box" placeholder="Full Name" required>
        <input type="text" name="address" class="input-box" placeholder="Complete Address" required>
        <input type="text" name="phone" class="input-box" placeholder="Phone Number" required>
        <input type="email" name="email" class="input-box" placeholder="Email Address" required>

        <!-- Order Summary -->
        <div class="order-summary">
            <h3 class="section-title">Order Summary</h3>

            <?php
            $total = 0;
            foreach ($cart as $item):
                $subtotal = $item['qty'] * $item['price'];
                $total += $subtotal;
            ?>
                <div class="summary-row">
                    <span><?= htmlspecialchars($item['name']) ?> (x<?= $item['qty'] ?>)</span>
                    <span>₱<?= number_format($subtotal, 2) ?></span>
                </div>
            <?php endforeach; ?>

            <div class="summary-row summary-total">
                <span>Total:</span>
                <span>₱<?= number_format($total, 2) ?></span>
            </div>
        </div>
        
        <form action="place_order.php" method="POST">
        <button type="submit" name="place_order" class="place-order-btn">Place Order</button>
        </form>
    </form>
</div>

</body>
</html>
