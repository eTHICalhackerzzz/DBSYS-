<?php
require_once "pdo.php";
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (
        !empty($_POST['username']) &&
        !empty($_POST['email']) &&
        !empty($_POST['password']) &&
        !empty($_POST['firstname']) &&
        !empty($_POST['lastname']) &&
        !empty($_POST['address']) &&
        !empty($_POST['dob'])
    ) {
        
        // --- 1. SERVER-SIDE PASSWORD LENGTH VALIDATION ---
        $password = $_POST['password'];
        if (strlen($password) < 8 || strlen($password) > 15) {
            $error = "Password must be between 8 and 15 characters long.";
            // Exit the processing block early if validation fails
        } else {
            // Proceed with registration only if validation passes
            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $sql = "INSERT INTO students 
                (username, password, email, firstname, lastname, address, dob) 
                VALUES 
                (:username, :password, :email, :firstname, :lastname, :address, :dob)";
            
            $stmt = $pdo->prepare($sql);
            try {
                $stmt->execute([
                    ':username' => $_POST['username'],
                    ':password' => $hashed,
                    ':email' => $_POST['email'],
                    ':firstname' => $_POST['firstname'],
                    ':lastname' => $_POST['lastname'],
                    ':address' => $_POST['address'],
                    ':dob' => $_POST['dob']
                ]);
                $_SESSION['success'] = "Account registered! You can now log in.";
                header("Location: login.php");
                exit;
            } catch (PDOException $e) {
                // Check for duplicate entry error (common MySQL error for unique fields)
                if (strpos($e->getMessage(), 'Integrity constraint violation: 1062 Duplicate entry') !== false) {
                    $error = "Registration failed. Username or Email already exists.";
                } else {
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    } else {
        $error = "All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Registration</title>
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js"></script>
    <script src="components/navbar.js"></script>
    <script src="components/footer.js"></script>
    
    <style>
        .text-danger {
            color: #ff4d4d; /* Red for too short */
        }
        .text-success {
            color: #2ecc71; /* Green for good */
        }
    </style>
</head>
<body class="auth-body">

    <main class ="auth-page">

        <div class="auth-container glass-card">

            <h2 class="auth-title">Create Your Account</h2>

                <?php if (isset($error)): ?>
                    <p class="error"><?= htmlentities($error) ?></p>
                <?php endif; ?>

            <form method="post">
                <label>Username<input type="text" name="username" required></label>
                <label>Email<input type="email" name="email" required></label>
                
                <label>Password
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="password" 
                            id="passwordField" 
                            required
                            minlength="8" 
                            maxlength="15"
                        >
                        <span id="togglePassword" class="eye-icon" style="cursor: pointer;">
                            <i data-feather="eye"></i>
                        </span>
                    </div>
                    <small id="passLengthMsg" style="font-size: 0.8rem; display: block; margin-top: 5px;"></small>
                </label>
                
                <label>First Name<input type="text" name="firstname" required></label>
                <label>Last Name<input type="text" name="lastname" required></label>
                <label>Address<input type="text" name="address" required></label>
                <label>Date of Birth<input type="date" name="dob" required></label>
                <button type="submit" class="auth-btn">Register</button>
                <div class="auth-footer">Already have an account? <a href="login.php">Login</a></div>
            </form>
        </div>
    </main>

    <script>
    // Initial call to replace all data-feather tags with SVG icons
    feather.replace();

    const togglePassword = document.getElementById("togglePassword");
    const passwordField = document.getElementById("passwordField");
    const lengthMsg = document.getElementById("passLengthMsg");

    // --- 1. EYE TOGGLE LOGIC ---
    togglePassword.addEventListener("click", () => {
        // Toggle the password input type
        const type = passwordField.getAttribute("type") === "password" ? "text" : "password";
        passwordField.setAttribute("type", type);
        
        // Determine the icon name
        const iconName = type === "password" ? "eye" : "eye-off";

        // Update the HTML content of the wrapper and re-render the icon
        togglePassword.innerHTML = `<i data-feather="${iconName}"></i>`;
        feather.replace();
    });
    
    // --- 2. LENGTH METER LOGIC ---
    passwordField.addEventListener("input", () => {
        const len = passwordField.value.length;

        if (len === 0) {
            lengthMsg.textContent = "";
            lengthMsg.className = "";
        } else if (len < 8) {
            lengthMsg.textContent = `Too short (Current: ${len}, Min: 8)`;
            lengthMsg.className = "text-danger";
        } else if (len >= 8 && len <= 15) {
            lengthMsg.textContent = `Perfect length (${len}/15)`;
            lengthMsg.className = "text-success";
        } else {
             // This is mostly preventative, as maxlength="15" should stop typing
            lengthMsg.textContent = "Max length reached (15)"; 
            lengthMsg.className = "text-danger";
        }
    });

</script>

</body>
</html>