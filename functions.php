<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }
}

function require_role($role) {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        header("Location: ../index.php");
        exit;
    }
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function status_badge($status) {
    $map = [
        'Available' => 'success',
        'Pending' => 'warning',
        'Approved' => 'info',
        'Rejected' => 'danger',
        'Ongoing' => 'info',
        'Returned' => 'success',
        'Cancelled' => 'danger',
        'Completed' => 'success',
        'Rented' => 'warning'
    ];
    return '<span class="badge ' . ($map[$status] ?? 'muted') . '">' . e($status) . '</span>';
}

function go($url) {
    header("Location: $url");
    exit;
}
?>
