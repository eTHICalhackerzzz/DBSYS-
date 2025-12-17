<?php
require_once "pdo.php";
session_start();

if (isset($_POST['email']) && isset($_POST['password'])) {

    // Fetch user by email
    $stmt = $pdo->prepare("SELECT * FROM students WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $_POST['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verify password
    if ($user && password_verify($_POST['password'], $user['password'])) {

        // Create session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['username'] = $user['username'];

        // Redirect to dashboard
        header("Location: Products.php");
        exit;

    } else {
        $error = "Incorrect email or password.";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
    <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="login-wrapper">

        <!-- LEFT SIDE -->
        <div class="login-left">
            <div class="brand-box">
                <div class="logo-circle">
                    <span class="logo-text">C</span>
                </div>
                <h1 class="brand-name">Click2Cart</h1>
                <p class="brand-tagline">
                    Your trusted online shopping partner<br>
                    in Southeast Asia
                </p>
            </div>
        </div>

        <!-- RIGHT SIDE -->
        <div class="login-right">
            <div class="login-box">

                <h2>Log In</h2>
                    <?php if (isset($error)): ?>
                        <p class="login-error"><?= htmlentities($error) ?></p>
                    <?php endif; ?>

                <form method ="post" class="login-form">
                        <label>Email<br><input type="email" name="email" required></label>
                        <label>Password<br><input type="password" name="password" required></label>
                        <button type="submit" class="auth-btn">Login</button>
                        <a href="#" class="forgot-password">Forgot Password?</a>
                </form>

                <p class="signup-text">
                    New to Click2Cart?
                    <a href="register.php">Sign Up</a>
                </p>

            </div>
        </div>
    </div>
</body>
</html>
