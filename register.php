<?php
session_start();
require_once "db.php";
require_once "functions.php";

/* already logged in: go to the right page */
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? "admin/dashboard.php" : "user/items.php"));
    exit;
}

$error    = "";
$first    = "";
$middle   = "";
$last     = "";
$username = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first    = trim($_POST["first_name"] ?? "");
    $middle   = trim($_POST["middle_name"] ?? "");
    $last     = trim($_POST["last_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirm  = $_POST["confirm"] ?? "";

    if ($first === "" || $last === "" || $username === "" || $password === "") {
        $error = "Please fill in first name, last name, username and password.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
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
            $role = "user";

            $stmt = $conn->prepare(
                "INSERT INTO users (full_name, first_name, middle_name, last_name, username, password, role)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("sssssss", $full, $first, $middle, $last, $username, $hash, $role);

            if ($stmt->execute()) {
                $stmt->close();

                /* back to the login page with a message */
                $_SESSION["register_success"] = "Account created. You can now log in.";
                header("Location: login.php");
                exit;
            } else {
                $error = "Could not create account: " . $conn->error;
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | Local Rentals & Reservations</title>
<link rel="stylesheet" href="assets/style.css?v=<?= time() ?>">

<style>
body.login-page {
    margin: 0;
    padding: 0;
    display: block;
}

/* the register form sits alone in the middle of the page */

.register-center {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    box-sizing: border-box;
}

.register-center .register-card {
    width: 100%;
    max-width: 540px;
    margin: 0 auto;
    padding: 22px 32px 18px;
    background: #ffffff;
    border-radius: 18px;
    box-shadow: 0 20px 50px rgba(23, 57, 95, 0.12);
    box-sizing: border-box;
    text-align: center;
}

.register-center .register-card form {
    text-align: left;
}

/* smaller logo, title and spacing so nothing needs scrolling */

.register-center .register-logo {
    display: block;
    width: 64px;
    height: 64px;
    margin: 0 auto 6px;
    border-radius: 50%;
    object-fit: contain;
    background: #ffffff;
    border: 1px solid #e1e7ee;
}

.register-center .register-card h2 {
    margin: 0 0 2px;
    font-size: 24px;
}

.register-center .register-card .muted-text {
    margin: 0 0 12px;
    font-size: 13px;
}

.register-center .register-card label {
    display: block;
    margin: 0 0 4px;
    font-size: 13px;
}

.register-center .register-card input {
    width: 100%;
    height: auto !important;
    padding: 9px 12px !important;
    margin: 0 0 10px !important;
    font-size: 14px;
    box-sizing: border-box;
}

.register-center .register-card .btn {
    margin-top: 4px;
    padding: 11px 14px;
}

.register-center .register-card .alert {
    margin: 0 0 10px;
    padding: 8px 12px;
    font-size: 13px;
}

.register-center .register-card p.muted-text:last-child {
    margin: 10px 0 0;
}

/* two fields side by side */

.reg-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 520px) {
    .reg-row {
        grid-template-columns: 1fr;
        gap: 0;
    }
}

/* very short screens: shrink a little more */

@media (max-height: 640px) {
    .register-center .register-logo {
        width: 48px;
        height: 48px;
    }

    .register-center .register-card {
        padding: 14px 28px 12px;
    }

    .register-center .register-card input {
        padding: 7px 10px !important;
        margin-bottom: 7px !important;
    }
}
</style>
</head>
<body class="login-page">

<div class="register-center">

    <div class="login-card register-card">

        <img class="register-logo" src="images/logo.jpeg" alt="Local Rentals & Reservations Logo">

        <h2>Create Account</h2>
        <p class="muted-text">Register to start reserving items</p>

        <?php if ($error): ?>
            <div class="alert danger-alert"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <div class="reg-row">
                <div>
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= e($first) ?>" required>
                </div>

                <div>
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= e($last) ?>" required>
                </div>
            </div>

            <label>Middle Name (optional)</label>
            <input type="text" name="middle_name" value="<?= e($middle) ?>">

            <label>Username</label>
            <input type="text" name="username" value="<?= e($username) ?>" required>

            <div class="reg-row">
                <div>
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div>
                    <label>Confirm Password</label>
                    <input type="password" name="confirm" required>
                </div>
            </div>

            <button class="btn primary full" type="submit">Register</button>
        </form>

        <p class="muted-text">
            Already have an account?
            <a href="login.php" style="color:#2f80c9;font-weight:700;text-decoration:underline;">Log in</a>
        </p>

    </div>

</div>

</body>
</html>