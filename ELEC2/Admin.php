<?php
require_once "CRUD.php";

session_start();
$username = $_SESSION['username'];
$students = new Students();
$editData = null;

if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}


if (isset($_POST['home'])) {
    header("Location: Products.php");
    exit();
}


if (isset($_POST['delete_id'])) {
    $students->deleteStudent($_POST['delete_id']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}


if (isset($_GET['edit_id'])) {
    $editData = $students->read("students", ["id" => $_GET['edit_id']])[0] ?? null;
}


if (!empty($_POST['update_id'])) {
    $students->updateStudent($_POST['update_id'], [
        'Username' => $_POST['Username'],
        'firstname' => $_POST['firstname'],
        'lastname' => $_POST['lastname'],
        'email' => $_POST['email'],
        'address' => $_POST['address'],
        'dob' => $_POST['dob']
    ]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (!isset($_POST['update_id']) || empty($_POST['update_id'])) {
    if (isset($_POST['Username']) && (isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['email']) && isset($_POST['address']) && isset($_POST['dob']))) {

        $students->addStudent([
            'Username' => $_POST['Username'],
            'firstname' => $_POST['firstname'],
            'lastname' => $_POST['lastname'],
            'email' => $_POST['email'],
            'address' => $_POST['address'],
            'dob' => $_POST['dob']
        ]);

        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

$rows = $students->getAllStudents();
?>
<html>
<head>
    <title>Student Information</title>
    <link rel="stylesheet" href="dashboard.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  $('#toggleFormBtn').click(function () {
      $('#addStudentForm').toggle();
  });

  $('#studentForm').submit(function (e) {
      e.preventDefault();

      $.ajax({
          url: 'Dashboard.php',
          type: 'POST',
          data: $(this).serialize(),
          success: function (response) {
              $('#formResponse').text(response).css('color', 'green');
              $('#studentForm')[0].reset();
          },
          error: function () {
              $('#formResponse').text('Something went wrong.').css('color', 'red');
          }
      });
  });

  const toggle = document.getElementById('darkModeToggle');

  if (!toggle) return;

  if (localStorage.getItem('darkMode') === 'enabled') {
    document.body.classList.add('dark-mode');
    toggle.checked = true;
  }

  toggle.addEventListener('change', function() {
    if (this.checked) {
      document.body.classList.add('dark-mode');
      localStorage.setItem('darkMode', 'enabled');
    } else {
      document.body.classList.remove('dark-mode');
      localStorage.setItem('darkMode', 'disabled');
    }
  });
});
</script>

<script>
        document.addEventListener("DOMContentLoaded", () => {
    const confirmBtn = document.getElementById("homepage");

    if (confirmBtn) {
        confirmBtn.addEventListener("click", () => {
            const confirmhome = confirm("Are you sure you want to redirect to home?");

            if (confirmhome) {
                // If you have sessions, clear them here in the future
                window.location.href = "Products.php";
            }
        });
    }
});
</script>


</head>
<body>
    <div style="display: flex; justify-content: flex-end; align-items: center; gap: 15px; margin-bottom: 20px;">
  <!-- Dark Mode Toggle -->
  <label class="switch" title="Toggle Dark Mode">
    <input type="checkbox" id="darkModeToggle">
    <span class="slider round"></span>
  </label>
  
  <!-- Logout Button -->
  <form method="post" style="margin: 0;">
      <input type="submit" id="homepage" name="home" value="Home" 
             style="padding:6px 12px; background:#dc3545; color:white; border:none; border-radius:4px; cursor:pointer;">
  </form>
</div>
    <h2>Users Information</h2>
    <table>
        <tr>
            <th>Username</th>
            <th>First Name</th>
            <th>Last Name</th>
            <th>Email</th>
            <th>Course</th>
            <th>Address</th>
            <th>Date of Birth</th>
        </tr>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><?= htmlspecialchars($row['firstname']) ?></td>
                <td><?= htmlspecialchars($row['lastname']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['address']) ?></td>
                <td><?= htmlspecialchars($row['dob']) ?></td>
                <td>
                    <a href="?edit_id=<?= $row['id'] ?>" class="edit-btn">Edit</a>
                    <form method="post" style="display:inline;">
                        <input type="hidden" name="delete_id" value="<?= $row['id'] ?>">
                        <input type="submit" class="delete-btn" value="Remove">
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>

<?php if (isset($editData)): ?>
    <h2 class="text-center">Edit Users</h2>

    <form method="post" class="border p-4 rounded bg-light shadow" style="max-width: 500px; margin: auto;">
        <input type="hidden" name="update_id" value="<?= $editData['id'] ?>">
        <p>Username: <input type="text" name="Username" value="<?= $editData['username'] ?>" required></p>
        <p>First Name: <input type="text" name="firstname" value="<?= $editData['firstname'] ?>" required></p>
        <p>Last Name: <input type="text" name="lastname" value="<?= $editData['lastname'] ?>" required></p>
        <p>Email: <input type="text" name="email" value="<?= $editData['email'] ?>" required></p>
        <p>Address: <input type="text" name="address" value="<?= $editData['address'] ?>" required></p>
        <p>Date of Birth: <input type="date" name="dob" value="<?= $editData['dob'] ?>" required></p>

        <p><input type="submit" value="Update Users" class="btn btn-success"></p>
    </form>
<?php endif; ?>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const deleteForms = document.querySelectorAll('form input.delete-btn');

    deleteForms.forEach(button => {
        button.addEventListener('click', function(e) {
            const confirmed = confirm("⚠️ Are you sure you want to remove this user?");
            if (!confirmed) {
                e.preventDefault(); // Stop form from submitting
            }
        });
    });
});
</script>


</body>
</html>
