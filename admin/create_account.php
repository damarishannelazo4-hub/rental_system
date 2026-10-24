<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$error = "";
$success = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first    = trim($_POST["first_name"] ?? "");
    $middle   = trim($_POST["middle_name"] ?? "");
    $last     = trim($_POST["last_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $contact  = trim($_POST["contact_number"] ?? "");
    $address  = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";
    $role     = ($_POST["role"] ?? "user") === "admin" ? "admin" : "user";

    if ($first === "" || $last === "" || $username === "" || $password === "") {
        $error = "Please fill in first name, last name, username and password.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $check->bind_param("s", $username);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc();
        $check->close();

        if ($exists) {
            $error = "That username is already taken.";
        } else {
            $full = build_full_name($first, $middle, $last);
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, first_name, middle_name, last_name, username, password, role, contact_number, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssssssss", $full, $first, $middle, $last, $username, $hash, $role, $contact, $address);

            if ($stmt->execute()) {
                $success = "Account created for $full ($role). They can log in now.";
            } else {
                $error = "Could not create account: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$users = $conn->query("SELECT user_id, full_name, username, role, contact_number FROM users ORDER BY user_id DESC LIMIT 20");

$page_title = "Create Account";
$active = "users";

require "../partials/admin_header.php";
?>

<section class="panel">
    <div class="panel-head">
        <h2>Create Account</h2>
    </div>

    <?php if ($error): ?>
        <div class="alert danger-alert"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" class="form-grid" autocomplete="off">
        <label>First Name
            <input type="text" name="first_name" required>
        </label>

        <label>Middle Name (optional)
            <input type="text" name="middle_name">
        </label>

        <label>Last Name
            <input type="text" name="last_name" required>
        </label>

        <label>Username
            <input type="text" name="username" autocomplete="off" required>
        </label>

        <label>Contact Number
            <input type="text" name="contact_number">
        </label>

        <label>Address
            <input type="text" name="address">
        </label>

        <label>Password
            <input type="password" name="password" autocomplete="new-password" required>
        </label>

        <label>Account Type
            <select name="role">
                <option value="user">User (customer)</option>
                <option value="admin">Admin</option>
            </select>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn primary">Create Account</button>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Recent Accounts</h2>
    </div>

    <table class="table">
        <thead>
            <tr><th>ID</th><th>Name</th><th>Username</th><th>Contact</th><th>Type</th></tr>
        </thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
            <tr>
                <td><?= (int)$u["user_id"] ?></td>
                <td><?= e($u["full_name"]) ?></td>
                <td><?= e($u["username"]) ?></td>
                <td><?= e($u["contact_number"]) ?></td>
                <td><?= e($u["role"]) ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</section>

<?php require "../partials/footer.php"; ?>