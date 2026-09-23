<?php
session_start();
require_once "db.php";

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "admin/dashboard.php" : "user/dashboard.php"));
    exit;
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($username === "" || $password === "") {
        $error = "Please enter your username and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, full_name, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $account = $result->fetch_assoc();

        if ($account && password_verify($password, $account["password"])) {
            $_SESSION["user_id"] = $account["user_id"];
            $_SESSION["full_name"] = $account["full_name"];
            $_SESSION["username"] = $account["username"];
            $_SESSION["role"] = $account["role"];

            header("Location: " . ($account["role"] === "admin" ? "admin/dashboard.php" : "user/dashboard.php"));
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | Local Rentals & Reservations</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
<div class="login-wrap">
    <div class="login-brand">
        <img src="images/logo.jpeg" alt="Local Rentals & Reservations Logo">
        <h1>Local Item Rental &<br>Reservation System</h1>
        <p>Rent what you need, when you need it.</p>
    </div>

    <div class="login-card">
        <div class="user-icon">●</div>
        <h2>Welcome Back!</h2>
        <p class="muted-text">Log in to your account</p>

        <?php if ($error): ?>
            <div class="alert danger-alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Username</label>
            <input type="text" name="username" placeholder="Enter username" required>

            <label>Password</label>
            <input type="password" name="password" placeholder="Enter password" required>

            <button class="btn primary full" type="submit">Login</button>
        </form>

        <div class="demo-box">
            <strong>Demo Accounts</strong><br>
            Admin: <b>admin</b> / <b>admin123</b><br>
            User: <b>user</b> / <b>user123</b>
        </div>
    </div>
</div>
</body>
</html>
