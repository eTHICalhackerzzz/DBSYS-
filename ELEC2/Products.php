<?php
require_once "pdo.php";
require_once "CRUD.php";
session_start();
$students = new Students();
$editData = null;

$username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';

// If the user is NOT logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}


// Optionally load PDO if you have pdo.php in project root
$hasPdo = false;
if (file_exists(__DIR__ . '/pdo.php')) {
    require_once __DIR__ . '/pdo.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $hasPdo = true;
    }
}

// If PDO available, ensure cart table exists
if ($hasPdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS cart (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id VARCHAR(100) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            image VARCHAR(255) DEFAULT '',
            quantity INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_product (user_id, product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
}

// Create session cart fallback
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add-to-cart POST (from modal form)
if (isset($_POST['add_to_cart'])) {
    // sanitize inputs
    $product_id = isset($_POST['id']) ? trim($_POST['id']) : '0';
    $name = isset($_POST['name']) ? trim($_POST['name']) : 'Product';
    $price_raw = isset($_POST['price']) ? trim($_POST['price']) : '0';
    // remove any non-digit/decimal characters
    $price = floatval(preg_replace('/[^\d\.]/', '', $price_raw));
    $image = isset($_POST['image']) ? trim($_POST['image']) : '';
    $qty = isset($_POST['qty']) ? max(1, intval($_POST['qty'])) : 1;

    if ($hasPdo) {
        try {
            $user_id = $_SESSION['user_id'];

            // if exists, update quantity, else insert
            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? LIMIT 1");
            $stmt->execute([$user_id, $product_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $newQty = $row['quantity'] + $qty;
                $up = $pdo->prepare("UPDATE cart SET quantity = ?, price = ?, image = ? WHERE id = ?");
                $up->execute([$newQty, $price, $image, $row['id']]);
            } else {
                $ins = $pdo->prepare("INSERT INTO cart (user_id, product_id, product_name, price, image, quantity) VALUES (?, ?, ?, ?, ?, ?)");
                $ins->execute([$user_id, $product_id, $name, $price, $image, $qty]);
            }

            $_SESSION['success'] = $name . " added to cart!";
            header("Location: cart.php");
            exit();
        } catch (Exception $e) {
            // DB error fallback to session cart
            $_SESSION['error'] = "Database error adding to cart. Using session cart fallback.";
        }
    }

    // Fallback: session cart (if no PDO or DB failed)
    if (!$hasPdo || isset($_SESSION['error'])) {
        // use product_id as key
        if (isset($_SESSION['cart'][$product_id])) {
            $_SESSION['cart'][$product_id]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$product_id] = [
                'name' => $name,
                'price' => $price,
                'image' => $image,
                'qty' => $qty
            ];
        }
        $_SESSION['success'] = $name . " added to cart (session).";
        header("Location: cart.php");
        exit();
    }
}

$user_id = $_SESSION['user_id'] ?? null;
$hasOrderHistory = false;

// Load cart from DB
$cart = [];

if ($hasPdo && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT quantity FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Count items
$cartCount = 0;

if (!empty($cart)) {
    foreach ($cart as $item) {
        $cartCount += intval($item['quantity']);
    }
} elseif (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += intval($item['qty']);
    }
}


if ($user_id) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $stmt->execute([$user_id]);
    if ($stmt->fetchColumn() > 0) {
        $hasOrderHistory = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Click2Cart - Online Shopping</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/feather-icons"></script>

    <style>
    /* Modal styles (kept local) */
    .modal {
        position: fixed;
        top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7);
        display: flex; justify-content: center; align-items: center;
        z-index: 10000;
    }
    .modal.hidden { display: none; }

    .modal-content {
        background: white;
        padding: 22px;
        border-radius: 12px;
        width: 420px;
        max-width: 95%;
        text-align: center;
        position: relative;
        animation: pop 0.22s ease-out;
    }
    @keyframes pop { from { transform: scale(0.95); opacity: 0 } to { transform: scale(1); opacity: 1 } }

    .modal-image { width: 100%; height: 260px; object-fit: cover; border-radius: 10px; margin-bottom: 12px; }
    .close { position: absolute; top: 10px; right: 14px; font-size: 26px; cursor: pointer; }

    /* quantity box */
    .qty-selector { display:flex; gap:8px; justify-content:center; align-items:center; margin-top:10px; }
    .qty-selector button { width:36px; height:36px; border-radius:8px; border:none; background:#eee; cursor:pointer; font-size:18px; }
    .qty-selector input { width:64px; text-align:center; font-size:16px; padding:8px; border-radius:8px; border:1px solid #ddd; }

    .add-to-cart-button-modal { margin-top: 14px; background: #ff4f84; color: #fff; padding: 12px 18px; border-radius: 8px; border: none; cursor: pointer; font-size:16px; }
    .add-to-cart-button-modal:hover { background:#e63b70; }
    </style>
</head>
<body>
    <main>
        <!-- Hero Section -->
        <section class="hero-section">
            <button id="AddToCart" class="AddToCart">
                <i data-feather="shopping-cart" class="cartIcon"></i>
                <span id="cart-count" class="cart-count"><?= $cartCount ?></span>
            </button>
            <div class="hero-content">
                <div class="circle">
                    <span class="text">C</span>
                </div>
                <h1>Shop with Ease</h1>
                <form method="post" style="display:inline;">
                    <button id="logoutBtn" name="logout" class="logout-btn">Logout</button>
                </form>
                <?php if ($hasOrderHistory): ?>
                    <a href="order_history.php" class="order-history-btn">
                        Order History
                    </a>
                <?php endif; ?>
                <p>Discover amazing products at unbeatable prices</p>
                <div class="search-container">
                    <input type="text"  placeholder="Search for products..." class="search-input">
                    <button class="search-button">
                        <i data-feather="search"></i>
                    </button>
                </div>

            </div>

            <div class="user-dropdown">
                <button class="user-icon">
                    <i data-feather="user"></i>
                </button>
                <div class="dropdown-menu">
                    <p class="username">Hello, <?= htmlentities($username) ?></p>
                    <a href="account_settings.php" class="account-settings">Account Settings</a>
                </div>
            </div>
        </section>

        <!-- Categories -->
        <h2 class="text-5xl font-[cursive] text-gray-800 mb-10 text-center tracking-wide">Brands</h2>
        <section class="categories-section py-12 bg-pink-200">
            <div class="flex flex-wrap justify-center gap-8">
                <!-- Brand Card -->
                <a href="#Dickies-Section" class="group w-48 h-56 backdrop-blur-md bg-white/30 border border-white/40 rounded-2xl p-6 flex flex-col items-center justify-center shadow-lg transition-all duration-300 hover:scale-105 hover:bg-white/40 hover:shadow-2xl">
                    <div class="w-28 h-28 flex items-center justify-center rounded-full overflow-hidden shadow-md transition-all duration-300 group-hover:scale-110">
                        <img src="images/Dickies.jpg" class="w-full h-full object-cover">
                    </div>
                    <p class="mt-4 text-xl font-semibold text-gray-800 group-hover:text-black transition">Dickies</p>
                </a>

                <!-- Brand Card -->
                <a href="#Stussy-Section" class="group w-48 h-56 backdrop-blur-md bg-white/30 border border-white/40 rounded-2xl p-6 flex flex-col items-center justify-center shadow-lg transition-all duration-300 hover:scale-105 hover:bg-white/40 hover:shadow-2xl">
                    <div class="w-28 h-28 flex items-center justify-center rounded-full overflow-hidden shadow-md transition-all duration-300 group-hover:scale-110">
                        <img src="images/stussy.png" class="w-full h-full object-cover">
                    </div>
                    <p class="mt-4 text-xl font-semibold text-gray-800 group-hover:text-black transition">Stussy</p>
                </a>

                <!-- Brand Card -->
                <a href="#GAP-Section" class="group w-48 h-56 backdrop-blur-md bg-white/30 border border-white/40 rounded-2xl p-6 flex flex-col items-center justify-center shadow-lg transition-all duration-300 hover:scale-105 hover:bg-white/40 hover:shadow-2xl">
                    <div class="w-28 h-28 flex items-center justify-center rounded-full overflow-hidden shadow-md transition-all duration-300 group-hover:scale-110">
                        <img src="images/gap.png" class="w-full h-full object-cover">
                    </div>
                    <p class="mt-4 text-xl font-semibold text-gray-800 group-hover:text-black transition">GAP</p>
                </a>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="featured-products-section">
            <div class="section-header">
                <h2>Featured Products</h2>
                <a href="#" class="view-all-link">View all</a>
            </div>

            <div class="products-grid">
                <!-- Product Card 1 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/dickies1.jpg"
                             alt="Cargo Pants"
                             class="product-image product-click"
                             data-id="dickies-1"
                             data-name="Cargo Pants"
                             data-price="499"
                             data-image="images/dickies1.jpg"
                             data-description="High-quality durable Dickies cargo pants.">
                    </div>

                    <div class="product-info">
                        <h3>Cargo Pants</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(128)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱499.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-1">
                                <input type="hidden" name="name" value="Cargo Pants">
                                <input type="hidden" name="price" value="499">
                                <input type="hidden" name="image" value="images/dickies1.jpg">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussyhoodie.jfif"
                             alt="Blue Hoodie Stussy"
                             class="product-image product-click"
                             data-id="product-2"
                             data-name="Blue Hoodie Stussy"
                             data-price="359"
                             data-image="images/stussyhoodie.jfif"
                             data-description="Stussy x CPFM 8 Ball Pigment Dyed Hoodie Blue Men's - SS22 - US.">
                    </div>
                    <div class="product-info">
                        <h3>Blue Hoodie Stussy</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(87)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱359.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="product-2">
                                <input type="hidden" name="name" value="Blue Hoodie Stussy">
                                <input type="hidden" name="price" value="359">
                                <input type="hidden" name="image" value="images/stussyhoodie.jfif">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gaphood.jfif"
                             alt="gaphood"
                             class="product-image product-click"
                             data-id="product-3"
                             data-name="GapHoodie"
                             data-price="550"
                             data-image="images/gaphood.jfif"
                             data-description="GAP Hoodie black limited edition.">
                    </div>
                    <div class="product-info">
                        <h3>Gap Hoodie</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(214)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱550.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="product-3">
                                <input type="hidden" name="name" value="GapHoodie">
                                <input type="hidden" name="price" value="550">
                                <input type="hidden" name="image" value="images/gaphood.jfif">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/Dickies_airforce.jpg"
                             alt="Dickies_airforce"
                             class="product-image product-click"
                             data-id="product-4"
                             data-name="Dickies_Airforce"
                             data-price="800"
                             data-image="images/Dickies_airforce.jpg"
                             data-description="Secure card solution with rewards.">
                    </div>
                    <div class="product-info">
                        <h3>Dickies Airforce</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(76)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱800</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="product-4">
                                <input type="hidden" name="name" value="Dickies_Airforce">
                                <input type="hidden" name="price" value="800">
                                <input type="hidden" name="image" value="images/Dickies_airforce.jpg">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Flash Sale (unchanged) -->
        <section class="flash-sale-section" id="Dickies-Section">
            <div class="flash-sale-header">
                <h1>Dickies Section</h1>
            </div>
            <div class="flash-sale-grid">
                <div class="products-grid">
                <!-- Product Card 1  -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/dickies-cargo.webp"
                             alt="Loose Fit Cargo Pants black Geometric"
                             class="product-image product-click"
                             data-id="dickies-2"
                             data-name="Loose Fit Cargo Pants black Geometric"
                             data-price="599"
                             data-image="images/dickies-cargo.webp"
                             data-description="dickies skate cargo pants 2026 Dickies Skateboarding Loose Fit Cargo Pants black Geometric">
                    </div>

                    <div class="product-info">
                        <h3>Loose Fit Cargo Pants black Geometric</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(128)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱599.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-2">
                                <input type="hidden" name="name" value="Loose Fit Cargo Pants black Geometric">
                                <input type="hidden" name="price" value="599">
                                <input type="hidden" name="image" value="images/dickies-cargo.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-cargo2.webp"
                             alt="Dark Olive Cargo"
                             class="product-image product-click"
                             data-id="dickies-3"
                             data-name="Dark Olive Cargo"
                             data-price="900"
                             data-image="images/dickies-cargo2.webp"
                             data-description="Dickies Skateboarding Pant Regular Fit Dark Olive WPSK67DV9 NI">
                    </div>
                    <div class="product-info">
                        <h3>Dark Olive Cargo</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(87)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱900.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-3">
                                <input type="hidden" name="name" value="Dark Olive Cargo">
                                <input type="hidden" name="price" value="900">
                                <input type="hidden" name="image" value="images/dickies-cargo2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-short.jfif"
                             alt="Multi pocket Men"
                             class="product-image product-click"
                             data-id="dickies-4"
                             data-name="Multi pocket Men"
                             data-price="550"
                             data-image="images/dickies-short.jfif"
                             data-description="Men's 13 Multi-Pocket Work Short">
                    </div>
                    <div class="product-info">
                        <h3>Multi pocket Men</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(214)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱550.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-4">
                                <input type="hidden" name="name" value="Multi pocket Men">
                                <input type="hidden" name="price" value="550">
                                <input type="hidden" name="image" value="images/dickies-short.jfif">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-short2.webp"
                             alt="Dickies loose short"
                             class="product-image product-click"
                             data-id="dickies-5"
                             data-name="Dickies loose short"
                             data-price="1500"
                             data-image="images/dickies-short2.webp"
                             data-description="Dickies 42283 Loose Fit Shorts">
                    </div>
                    <div class="product-info">
                        <h3>Dickies Loose Fit Short</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(676)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱1500</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-5">
                                <input type="hidden" name="name" value="Dickies loose short">
                                <input type="hidden" name="price" value="1500">
                                <input type="hidden" name="image" value="images/dickies-short2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
                    <div class="products-grid">
                <!-- Product Card 1 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/dickies-short3.webp"
                             alt="Loose Short Navy Blue"
                             class="product-image product-click"
                             data-id="dickies-6"
                             data-name="Loose Short Navy Blue"
                             data-price="1000"
                             data-image="images/dickies-short3.webp"
                             data-description="Dickies 42283 Loose Fit Multi-Pocket Men's Shorts Navy">
                    </div>

                    <div class="product-info">
                        <h3>Loose Short Navy Blue</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(1258)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱1000.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-6">
                                <input type="hidden" name="name" value="Loose Short Navy Blue">
                                <input type="hidden" name="price" value="499">
                                <input type="hidden" name="image" value="images/dickies-short3.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-short4.webp"
                             alt="Dickies Shorts Women"
                             class="product-image product-click"
                             data-id="dickies-7"
                             data-name="Dickies Shorts Women"
                             data-price="799"
                             data-image="images/dickies-short4.webp"
                             data-description="Dickies Shorts: Women's FR327 RBK Black 10-Inch Relaxed Fit Cargo Shorts">
                    </div>
                    <div class="product-info">
                        <h3>Dickies Cargo Shorts Women</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(877)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱799.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-7">
                                <input type="hidden" name="name" value="Dickies Shorts Women">
                                <input type="hidden" name="price" value="799">
                                <input type="hidden" name="image" value="images/dickies-short4.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-shirt.webp"
                             alt="oversized tshirt"
                             class="product-image product-click"
                             data-id="dickies-8"
                             data-name="oversized tshirt"
                             data-price="550"
                             data-image="images/dickies-shirt.webp"
                             data-description="330 T-Shirt">
                    </div>
                    <div class="product-info">
                        <h3>Oversized T-Shirt - Black</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(214)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱550.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-8">
                                <input type="hidden" name="name" value="oversized tshirt">
                                <input type="hidden" name="price" value="550">
                                <input type="hidden" name="image" value="images/dickies-shirt.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/dickies-shirt2.webp"
                             alt="Dickies Black Tee"
                             class="product-image product-click"
                             data-id="dickies-9"
                             data-name="Dickies Black Tee"
                             data-price="300"
                             data-image="images/dickies-shirt2.webp"
                             data-description="DICKIES | Landascape Ss Tee | DK0A4Z8V | MÄRKESKLÄDER - OUTLET">
                    </div>
                    <div class="product-info">
                        <h3>Dickies Black Tee</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(276)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱300</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="dickies-9">
                                <input type="hidden" name="name" value="Dickies Black Tee">
                                <input type="hidden" name="price" value="300">
                                <input type="hidden" name="image" value="images/dickies-shirt2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        

        <section class="flash-sale-section" id="Stussy-Section">
            <div class="flash-sale-header">
                <h1>Stussy Section</h1>
            </div>
            <div class="flash-sale-grid">
            <div class="products-grid">
                <!-- Product Card 1 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/stussy-shirt.webp"
                             alt="Stussy black thermal tshirt"
                             class="product-image product-click"
                             data-id="stussy-1"
                             data-name="Stussy black thermal tshirt"
                             data-price="499"
                             data-image="images/stussy-shirt.webp"
                             data-description="Stussy - BLACK THERMAL STOCK T-SHIRT">
                    </div>

                    <div class="product-info">
                        <h3>Stussy black thermal tshirt</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(168)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱499.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-1">
                                <input type="hidden" name="name" value="Stussy black thermal tshirt">
                                <input type="hidden" name="price" value="499">
                                <input type="hidden" name="image" value="images/stussy-shirt.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussyhoodie.jfif"
                             alt="Blue Hoodie Stussy"
                             class="product-image product-click"
                             data-id="product-2"
                             data-name="Blue Hoodie Stussy"
                             data-price="359"
                             data-image="images/stussyhoodie.jfif"
                             data-description="Stussy x CPFM 8 Ball Pigment Dyed Hoodie Blue Men's - SS22 - US.">
                    </div>
                    <div class="product-info">
                        <h3>Blue Hoodie Stussy</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(87)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱359.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="product-2">
                                <input type="hidden" name="name" value="Blue Hoodie Stussy">
                                <input type="hidden" name="price" value="359">
                                <input type="hidden" name="image" value="images/stussyhoodie.jfif">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussy-shirt2.webp"
                             alt="Stussy Oversized White Tee Shirt"
                             class="product-image product-click"
                             data-id="stussy-2"
                             data-name="Stussy Oversized White Tee Shirt"
                             data-price="690"
                             data-image="images/stussy-shirt2.webp"
                             data-description="Stussy Oversized Tee Solid Stock Logo Short Sleeve T-Shirt">
                    </div>
                    <div class="product-info">
                        <h3>Stussy Oversized White Tee Shirt</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(814)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱690.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-2">
                                <input type="hidden" name="name" value="Stussy Oversized White Tee Shirt">
                                <input type="hidden" name="price" value="690">
                                <input type="hidden" name="image" value="images/stussy-shirt2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussy-hood.webp"
                             alt="World Tour Hoodie black"
                             class="product-image product-click"
                             data-id="stussy-3"
                             data-name="World Tour Hoodie black"
                             data-price="1360"
                             data-image="images/stussy-hood.webp"
                             data-description="Stussy World Tour Hoodie 'Black">
                    </div>
                    <div class="product-info">
                        <h3>World Tour Hoodie black</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(746)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱1360</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-3">
                                <input type="hidden" name="name" value="World Tour Hoodie black">
                                <input type="hidden" name="price" value="1360">
                                <input type="hidden" name="image" value="images/stussy-hood.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>                
            </div>
        </section>

                <div class="products-grid">
                <!-- Product Card 1  -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/stussy-hood2.webp"
                             alt="World tour flags hoodie sweatshirt"
                             class="product-image product-click"
                             data-id="stussy-4"
                             data-name="World tour flags hoodie sweatshirt"
                             data-price="1170"
                             data-image="images/stussy-hood2.webp"
                             data-description="Stussy World Tour Flags Pullover Hoodie Sweatshirt">
                    </div>

                    <div class="product-info">
                        <h3>World tour flags hoodie sweatshirt</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(928)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱1170.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-4">
                                <input type="hidden" name="name" value="World tour flags hoodie sweatshirt">
                                <input type="hidden" name="price" value="1170">
                                <input type="hidden" name="image" value="images/stussy-hood2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussy-cargo.webp"
                             alt="Stussy Pigment Black cargo"
                             class="product-image product-click"
                             data-id="stussy-5"
                             data-name="Stussy Pigment Black cargo"
                             data-price="559"
                             data-image="images/stussy-cargo.webp"
                             data-description="Stussy Surplus Cargo Pant in Pigment Black">
                    </div>
                    <div class="product-info">
                        <h3>Stussy Pigment Black cargo</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(87)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱559.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-5">
                                <input type="hidden" name="name" value="Stussy Pigment Black cargo">
                                <input type="hidden" name="price" value="559">
                                <input type="hidden" name="image" value="images/stussy-cargo.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussy-cargo2.webp"
                             alt="Green Cargo Stussy"
                             class="product-image product-click"
                             data-id="stussy-6"
                             data-name="Green Cargo Stussy"
                             data-price="850"
                             data-image="images/stussy-cargo2.webp"
                             data-description="Ripstop Surplus Cargo">
                    </div>
                    <div class="product-info">
                        <h3>Green Cargo Stussy</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(1114)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱850.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-6">
                                <input type="hidden" name="name" value="Green Cargo Stussy">
                                <input type="hidden" name="price" value="850">
                                <input type="hidden" name="image" value="images/stussy-cargo2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/stussy-shoes.webp"
                             alt="stussy x nike"
                             class="product-image product-click"
                             data-id="stussy-7"
                             data-name="stussy x nike"
                             data-price="3700"
                             data-image="images/stussy-shoes.webp"
                             data-description="Stüssy x Nike Air Force 1">
                    </div>
                    <div class="product-info">
                        <h3>stussy x nike</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(76)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱3700</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="stussy-7">
                                <input type="hidden" name="name" value="stussy x nike">
                                <input type="hidden" name="price" value="3700">
                                <input type="hidden" name="image" value="images/stussy-shoes.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>        

        <section class="flash-sale-section" id="GAP-Section">
            <div class="flash-sale-header">
                <h1>GAP Section</h1>
            </div>
            <div class="flash-sale-grid">
                           <div class="flash-sale-grid">
                <div class="products-grid">
                <!-- Product Card 1  -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/gap-hood.webp"
                             alt="kids gap zip hoodie"
                             class="product-image product-click"
                             data-id="gap-1"
                             data-name="kids gap zip hoodie"
                             data-price="285"
                             data-image="images/gap-hood.webp"
                             data-description="Kids Gap Logo Zip Hoodie">
                    </div>

                    <div class="product-info">
                        <h3>kids gap zip hoodie</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(128)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱285.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-1">
                                <input type="hidden" name="name" value="kids gap zip hoodie">
                                <input type="hidden" name="price" value="285">
                                <input type="hidden" name="image" value="images/gap-hood.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-hood2.webp"
                             alt="Vintage Soft Blue Gap"
                             class="product-image product-click"
                             data-id="gap-2"
                             data-name="Vintage Soft Blue Gap"
                             data-price="989"
                             data-image="images/gap-hood2.webp"
                             data-description="Vintage Soft Oversized Logo Hoodie">
                    </div>
                    <div class="product-info">
                        <h3>Vintage Soft Blue Gap</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(87)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱989.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-2">
                                <input type="hidden" name="name" value="Vintage Soft Blue Gap">
                                <input type="hidden" name="price" value="989">
                                <input type="hidden" name="image" value="images/gap-hood2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-short.webp"
                             alt="gap short"
                             class="product-image product-click"
                             data-id="gap-3"
                             data-name="gap short"
                             data-price="299"
                             data-image="images/gap-short.webp"
                             data-description="GAP Logo Shorts Tapestry Navy">
                    </div>
                    <div class="product-info">
                        <h3>Gap short - Navy Color</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(214)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱299.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-3">
                                <input type="hidden" name="name" value="gap short">
                                <input type="hidden" name="price" value="299">
                                <input type="hidden" name="image" value="images/gap-short.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-short2.webp"
                             alt="kids gap pull on short"
                             class="product-image product-click"
                             data-id="gap-4"
                             data-name="kids gap pull on short"
                             data-price="600"
                             data-image="images/gap-short2.webp"
                             data-description="Kids Relaxed Gap Logo Pull-On Shorts">
                    </div>
                    <div class="product-info">
                        <h3>Gap Pull-on Shorts</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-empty"></i>
                            </div>
                            <span class="rating-count">(76)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱600</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-4">
                                <input type="hidden" name="name" value="kids gap pull on short">
                                <input type="hidden" name="price" value="600">
                                <input type="hidden" name="image" value="images/gap-short2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </section>
                   <div class="flash-sale-grid">
                <div class="products-grid">
                <!-- Product Card 1  -->
                <div class="product-card">
                    <div class="product-image-container">
                        <!-- NOTE: data-price MUST be numeric (no $) -->
                        <img src="images/gap-pants.webp"
                             alt="GAP Jogger Pants"
                             class="product-image product-click"
                             data-id="gap-5"
                             data-name="GAP Jogger Pants"
                             data-price="2099"
                             data-image="images/gap-pants.webp"
                             data-description="Buy GAP Basic Heritage Jogger Pants 2025">
                    </div>

                    <div class="product-info">
                        <h3>GAP Jogger Pants</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(1328)</span>
                        </div>

                        <div class="price-cart-container">
                            <span class="product-price">₱2099.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-5">
                                <input type="hidden" name="name" value="GAP Jogger Pants">
                                <input type="hidden" name="price" value="2099">
                                <input type="hidden" name="image" value="images/gap-pants.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 (example) -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-pants2.webp"
                             alt="High waisted Wide Leg Denim"
                             class="product-image product-click"
                             data-id="gap-6"
                             data-name="High waisted Wide Leg Denim"
                             data-price="3259"
                             data-image="images/gap-pants2.webp"
                             data-description="Dôen x Gap DÔEN High Waisted Wide Leg Denim Trousers">
                    </div>
                    <div class="product-info">
                        <h3>High waisted Wide Leg Denim</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(4507)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱3259.00</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-6">
                                <input type="hidden" name="name" value="High waisted Wide Leg Denim">
                                <input type="hidden" name="price" value="3259">
                                <input type="hidden" name="image" value="images/gap-pants2.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-hood3.webp"
                             alt="oversized hoodie 90s"
                             class="product-image product-click"
                             data-id="gap-7"
                             data-name="oversized hoodie 90s"
                             data-price="2830"
                             data-image="images/gap-hood3.webp"
                             data-description="Oversized Hoodie 90s Gap Hoodie Rare!! Vintage 90s GAP Gray Hoodie Embroidered Big Logo Sweater">
                    </div>
                    <div class="product-info">
                        <h3>Oversized Hoodie 90s</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(2514)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱2830.00</span>

                                <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-7">
                                <input type="hidden" name="name" value="oversized hoodie 90s">
                                <input type="hidden" name="price" value="2830">
                                <input type="hidden" name="image" value="images/gap-hood3.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Product Card 4 -->
                <div class="product-card">
                    <div class="product-image-container">
                        <img src="images/gap-hood4.webp"
                             alt="Vintage GAP Hoodie Sweater"
                             class="product-image product-click"
                             data-id="gap-8"
                             data-name="Vintage GAP Hoodie Sweater"
                             data-price="1800"
                             data-image="images/gap-hood4.webp"
                             data-description="Vintage GAP Hoodie Sweater Big Logo Embroidery Blue Made in Cambodia Pullover Jumper Size M - Etsy">
                    </div>
                    <div class="product-info">
                        <h3>Vintage GAP Hoodie Sweater</h3>
                        <div class="rating-container">
                            <div class="stars">
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                                <i data-feather="star" class="star-filled"></i>
                            </div>
                            <span class="rating-count">(966)</span>
                        </div>
                        <div class="price-cart-container">
                            <span class="product-price">₱1800</span>

                            <form method="POST" class="inline">
                                <input type="hidden" name="add_to_cart" value="1">
                                <input type="hidden" name="id" value="gap-8">
                                <input type="hidden" name="name" value="Vintage GAP Hoodie Sweater">
                                <input type="hidden" name="price" value="1800">
                                <input type="hidden" name="image" value="images/gap-hood4.webp">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="add-to-cart-btn">
                                    <i data-feather="shopping-cart" class="cart-icon"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
    </main>

    <custom-footer></custom-footer>

    <script>feather.replace();</script>
    <script src="components/navbar.js"></script>
    <script src="components/footer.js"></script>
    <script src="script.js"></script>

    <script>
    // Logout confirmation
    document.addEventListener("DOMContentLoaded", () => {
        const logoutBtn = document.getElementById("logoutBtn");
        if (logoutBtn) {
            logoutBtn.addEventListener("click", (e) => {
                // letting the form submit will handle logout server-side
                if (!confirm("Are you sure you want to logout?")) e.preventDefault();
            });
        }
    });
    </script>

    <script>
    // user dropdown
    const userIcon = document.querySelector('.user-icon');
    const dropdownMenu = document.querySelector('.user-dropdown .dropdown-menu');
    if (userIcon) {
        userIcon.addEventListener('click', () => {
            dropdownMenu.style.display = dropdownMenu.style.display === 'block' ? 'none' : 'block';
        });
        window.addEventListener('click', (e) => {
            if (!e.target.closest('.user-dropdown')) dropdownMenu.style.display = 'none';
        });
    }
    </script>

    <script>
    // cart button

    document.addEventListener('DOMContentLoaded', () => {
        const cartBtn = document.getElementById('AddToCart');
        if (cartBtn) {
            cartBtn.addEventListener('click', () => {
                window.location.href = 'cart.php';
            });
        }
    });

        document.querySelectorAll('form.inline').forEach(form => {
        form.addEventListener('submit', () => {
            let count = document.getElementById("cart-count");
            count.textContent = parseInt(count.textContent) + 1;
        });
    });
    </script>

    <!-- PRODUCT DETAILS + QUANTITY MODAL -->
    <div id="productModal" class="modal hidden" aria-hidden="true">
        <div class="modal-content">
            <span id="closeModal" class="close">&times;</span>

            <img id="modalImage" class="modal-image" src="" alt="Product image">
            <h2 id="modalName"></h2>
            <p id="modalDesc"></p>
            <h3 id="modalPrice"></h3>

            <form method="POST" class="inline" id="modalAddForm">
                <input type="hidden" name="add_to_cart" value="1">
                <input type="hidden" name="id" id="modalProductId" value="">
                <input type="hidden" name="name" id="modalProductName" value="">
                <input type="hidden" name="price" id="modalProductPrice" value="">
                <input type="hidden" name="image" id="modalProductImage" value="">

                <div class="qty-selector">
                    <button type="button" id="minusQty">-</button>
                    <input type="text" id="qtyField" name="qty" value="1" />
                    <button type="button" id="plusQty">+</button>
                </div>

                <button type="submit" name="add_to_cart" class="add-to-cart-button-modal">Add to Cart</button>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", () => {
        const images = document.querySelectorAll(".product-click");
        const modal = document.getElementById("productModal");
        const closeModal = document.getElementById("closeModal");
        const modalName = document.getElementById("modalName");
        const modalDesc = document.getElementById("modalDesc");
        const modalPrice = document.getElementById("modalPrice");
        const modalImage = document.getElementById("modalImage");

        const modalProductId = document.getElementById("modalProductId");
        const modalProductName = document.getElementById("modalProductName");
        const modalProductPrice = document.getElementById("modalProductPrice");
        const modalProductImage = document.getElementById("modalProductImage");

        const qtyField = document.getElementById("qtyField");
        const plusQty = document.getElementById("plusQty");
        const minusQty = document.getElementById("minusQty");

        images.forEach(img => {
            img.addEventListener("click", () => {
                // fill modal values
                const name = img.dataset.name || "Product";
                const price = img.dataset.price || "0";
                const desc = img.dataset.description || "";
                const image = img.dataset.image || img.src;
                const pid = img.dataset.id || "";

                modalName.textContent = name;
                modalDesc.textContent = desc;
                modalPrice.textContent = "₱" + parseFloat(price).toFixed(2);
                modalImage.src = image;

                modalProductId.value = pid;
                modalProductName.value = name;
                modalProductPrice.value = price;
                modalProductImage.value = image;

                qtyField.value = 1;
                modal.classList.remove("hidden");
                modal.setAttribute('aria-hidden','false');
            });
        });

        closeModal.addEventListener("click", () => {
            modal.classList.add("hidden");
            modal.setAttribute('aria-hidden','true');
        });

        modal.addEventListener("click", (e) => {
            if (e.target === modal) {
                modal.classList.add("hidden");
                modal.setAttribute('aria-hidden','true');
            }
        });

        plusQty.addEventListener("click", () => {
            qtyField.value = Math.max(1, parseInt(qtyField.value || "1") + 1);
        });
        minusQty.addEventListener("click", () => {
            qtyField.value = Math.max(1, parseInt(qtyField.value || "1") - 1);
        });

        // optional: keyboard support: Esc to close
        document.addEventListener('keydown', (e) => {
            if (e.key === "Escape") {
                modal.classList.add("hidden");
                modal.setAttribute('aria-hidden','true');
            }
        });
    });
    </script>

    

</body>
</html>
