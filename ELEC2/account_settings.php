<?php
session_start();
require_once "pdo.php";

/* Require login */
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

/* Fetch logged-in user */
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

/* Save changes */
if (isset($_POST['save_changes'])) {

    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $email     = trim($_POST['email']);
    $username  = trim($_POST['username']);
    $newPass   = $_POST['new_password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';

    /* Check if anything actually changed */
    $noChanges =
        $firstname === $user['firstname'] &&
        $lastname  === $user['lastname'] &&
        $email     === $user['email'] &&
        $username  === $user['username'] &&
        empty($newPass) &&
        empty($confirm);

    if ($noChanges) {
        $message = "No changes detected.";
    } else {

        /* Update profile info */
        if (
            $firstname !== $user['firstname'] ||
            $lastname  !== $user['lastname'] ||
            $email     !== $user['email'] ||
            $username  !== $user['username']
        ) {
            $stmt = $pdo->prepare("
                UPDATE students 
                SET firstname = ?, lastname = ?, email = ?, username = ?
                WHERE id = ?
            ");
            $stmt->execute([$firstname, $lastname, $email, $username, $user_id]);
        }

        /* Password update (optional) */
        if (!empty($newPass)) {
            if ($newPass === $confirm) {
                $hashed = password_hash($newPass, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE students SET password = ? WHERE id = ?")
                    ->execute([$hashed, $user_id]);
            } else {
                $message = "Passwords do not match!";
            }
        }

        if (!$message) {
            $message = "Account updated successfully!";
        }
    }
}

/* Delete account */
if (isset($_POST['delete_account'])) {
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$user_id]);
    session_destroy();
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Account Settings</title>
    <style>
        body {
            background: linear-gradient(135deg, #fce7f3, #ede9fe, #e0f2fe);
            font-family: Arial, sans-serif;
        }
        .account-card {
            max-width: 480px;
            margin: 50px auto;
            background: white;
            padding: 30px;
            border-radius: 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        .back-btn {
            display: inline-block;
            margin-bottom: 15px;
            text-decoration: none;
            color: #7c3aed;
            font-weight: bold;
        }
        .back-btn:hover {
            text-decoration: underline;
        }
        .field-group {
            margin-top: 14px;
        }
        label {
            font-weight: bold;
            font-size: 14px;
        }
        .input-edit {
            display: flex;
            gap: 8px;
        }
        input {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ccc;
        }
        input[readonly] {
            background: #f3f4f6;
            cursor: not-allowed;
        }
        .edit-btn {
            background: #a855f7;
            border: none;
            color: white;
            border-radius: 8px;
            padding: 6px 10px;
            cursor: pointer;
        }
        .edit-btn:hover {
            background: #9333ea;
        }
        .save-btn {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #22c55e;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }
        .save-btn:hover {
            background: #16a34a;
        }
        .delete-btn {
            width: 100%;
            margin-top: 15px;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #dc2626;
            color: white;
            cursor: pointer;
        }
        .delete-btn:hover {
            background: #b91c1c;
        }
        .msg {
            text-align: center;
            color: green;
            margin-bottom: 10px;
        }
        hr {
            margin: 25px 0;
        }
    </style>
</head>

<body>

<div class="account-card">
    <a href="Products.php" class="back-btn">← Back to Products</a>

    <h2>Account Settings</h2>

    <?php if ($message): ?>
        <p class="msg"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <form method="post">

        <?php
        function editableField($label, $name, $value) {
            echo "
            <div class='field-group'>
                <label>$label</label>
                <div class='input-edit'>
                    <input type='text' name='$name' value='".htmlspecialchars($value)."' readonly>
                    <button type='button' class='edit-btn'>✏️</button>
                </div>
            </div>";
        }

        editableField("First Name", "firstname", $user['firstname']);
        editableField("Last Name", "lastname", $user['lastname']);
        editableField("Email", "email", $user['email']);
        editableField("Username", "username", $user['username']);
        ?>

        <hr>

        <label>New Password (optional)</label>
        <input type="password" name="new_password">

        <label style="margin-top:8px;">Confirm Password</label>
        <input type="password" name="confirm_password">

        <button class="save-btn" name="save_changes">Save Changes</button>
    </form>

    <form method="post" onsubmit="return confirm('Are you sure you want to delete your account?');">
        <button class="delete-btn" name="delete_account">Delete Account</button>
    </form>
</div>

<script>
document.querySelectorAll(".edit-btn").forEach(btn => {
    btn.addEventListener("click", () => {
        const input = btn.previousElementSibling;
        input.removeAttribute("readonly");
        input.focus();
        input.style.background = "#fff";
    });
});
</script>

</body>
</html>
