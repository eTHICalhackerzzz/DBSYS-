<?php
session_start();

// try load pdo if exists
$hasPdo = false;
if (file_exists(__DIR__ . '/pdo.php')) {
    require_once __DIR__ . '/pdo.php';
    if (isset($pdo) && $pdo instanceof PDO) $hasPdo = true;
}

// If logged in and DB available, load cart from DB
$cart = [];
if ($hasPdo && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT product_id, product_name AS name, price, image, quantity AS qty FROM cart WHERE user_id = ?");
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
} else {
    if (isset($_SESSION['cart'])) $cart = $_SESSION['cart'];
}

// Handle remove & update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['remove'])) {
        $id = $_POST['id'];
        if ($hasPdo && isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$_SESSION['user_id'], $id]);
        } else {
            unset($_SESSION['cart'][$id]);
        }
        header("Location: cart.php");
        exit;
    }

    if (isset($_POST['update_qty'])) {
        $id = $_POST['id'];
        $qty = max(1, (int)$_POST['qty']);
        if ($hasPdo && isset($_SESSION['user_id'])) {
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$qty, $_SESSION['user_id'], $id]);
        } else {
            $_SESSION['cart'][$id]['qty'] = $qty;
        }
        header("Location: cart.php");
        exit;
    }
}

$empty = empty($cart);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Your Cart</title>
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background: linear-gradient(135deg, #1f1c2c, #928dab);
            font-family: 'Segoe UI', Arial, sans-serif;
            color: white;
        }

        .cart-container { 
            max-width:800px; 
            margin:40px auto; 
            background:white; 
            padding:25px; 
            border-radius:12px; 
            box-shadow:0 6px 20px rgba(0,0,0,0.1); 
        }

        /* NEW: scrollable list */
        .scroll-container {
            max-height: 670px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .scroll-container::-webkit-scrollbar { width: 6px; }
        .scroll-container::-webkit-scrollbar-thumb {
            background: #c8c8c8;
            border-radius: 10px;
        }

        .cart-item { 
            display:flex; 
            align-items:center; 
            margin-bottom:20px; 
            border-bottom:1px solid #ddd; 
            padding-bottom:15px; 
        }

        .cart-item img { 
            width:90px; 
            height:90px; 
            border-radius:10px; 
            object-fit:cover; 
            margin-right:20px; 
        }

        .item-info { flex:1; }
        .item-name { font-size:18px; font-weight:bold; }
        .item-price { color:#444; }
        .qty-box { width:55px; padding:5px; text-align:center; }
        .remove-btn { background:#dc3545; color:white; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; }
        .cart-total { text-align:right; font-size:20px; margin-top:20px; font-weight:bold; }
        .cart-buttons { margin-top:25px; display:flex; justify-content:space-between; }
        .btn { padding:10px 18px; border-radius:6px; border:none; cursor:pointer; font-size:16px; }
        .continue-btn { background:#555; color:white; } 
        .continue-btn:hover{ background:#333; }
        .checkout-btn { background:#28a745; color:white; } 
        .checkout-btn:hover{ background:#218838; }
        .empty-cart { text-align:center; font-size:22px; color:#666; margin-top:60px; }
    </style>
</head>
<body>
    <div class="cart-container">
        <h2>Your Shopping Cart</h2>

        <?php if ($empty): ?>
            <p class="empty-cart">Your cart is empty 😢</p>
            <div style="text-align:center; margin-top:20px;">
                <a href="Products.php"><button class="btn continue-btn">Go Back to Shopping</button></a>
            </div>
        <?php else: ?>

            <!-- NEW: scrollable cart items wrapper -->
            <div class="scroll-container">

            <?php $total = 0; foreach ($cart as $id => $item): 
                $subtotal = $item['qty'] * $item['price']; 
                $total += $subtotal; 
            ?>
                <div class="cart-item">
                    <img src="<?= htmlentities($item['image']) ?>" alt="<?= htmlentities($item['name']) ?>">
                    <div class="item-info">
                        <p class="item-name"><?= htmlentities($item['name']) ?></p>
                        <p class="item-price">₱<?= number_format($item['price'], 2) ?></p>
                    </div>

                    <form method="post" style="margin-right:10px;">
                        <input type="hidden" name="id" value="<?= htmlentities($id) ?>">
                        <input type="number" name="qty" class="qty-box" value="<?= intval($item['qty']) ?>" min="1">
                        <button type="submit" name="update_qty" class="btn" style="background:#007BFF; color:white;">Update</button>
                    </form>

                    <form method="post">
                        <input type="hidden" name="id" value="<?= htmlentities($id) ?>">
                        <button name="remove" class="remove-btn">Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>

            </div> <!-- end scroll-container -->

            <p class="cart-total">Total: ₱<?= number_format($total, 2) ?></p>

            <div class="cart-buttons">
                <a href="Products.php" class="continue-btn">
                    <button class="btn continue-btn">← Continue Shopping</button></a>
                <form id="goCheckout" action="checkout.php" method="POST">
                    <input type="hidden" name="from_cart" value="1">
                    <button class="btn checkout-btn">Proceed to Checkout →</button></a>
            </div>

        <?php endif; ?>

    </div>
</body>
</html>
