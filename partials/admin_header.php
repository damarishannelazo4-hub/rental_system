<?php

require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../db.php";

require_role("admin");

$base = "../";
$page_title = $page_title ?? "Admin";


/* =========================
   NOTIFICATIONS
   ========================= */

/* Pending reservations */

$pendingCount = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE status = 'Pending'
");

if ($result) {
    $pendingCount = (int)$result->fetch_assoc()["total"];
}


/* =========================
   REMINDERS
   ========================= */

/* Rentals due today */

$dueToday = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE status = 'Ongoing'
    AND return_date = CURDATE()
");

if ($result) {
    $dueToday = (int)$result->fetch_assoc()["total"];
}


/* Rentals due tomorrow */

$dueTomorrow = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE status = 'Ongoing'
    AND return_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");

if ($result) {
    $dueTomorrow = (int)$result->fetch_assoc()["total"];
}


/* Total notification count */

$notificationCount = $pendingCount + $dueToday + $dueTomorrow;

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($page_title) ?> | Local Rentals</title>

<link rel="stylesheet" href="<?= $base ?>assets/style.css">


<style>

/* =========================
   ADMIN ACCOUNT DROPDOWN
   ========================= */

.account-area {
    position: relative;
}


.account-chip {
    cursor: pointer;
    user-select: none;
    padding: 10px 14px;
    border-radius: 8px;
    transition: background 0.2s ease;
}


.account-chip:hover {
    background: #eef4fa;
}


.account-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 330px;
    background: #ffffff;
    border: 1px solid #e1e7ee;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    z-index: 9999;
    overflow: hidden;
}


.account-dropdown.show {
    display: block;
}


/* Header */

.dropdown-header {
    padding: 16px 18px;
    border-bottom: 1px solid #edf0f4;
    font-weight: 700;
    color: #17395f;
}


/* Sections */

.dropdown-section {
    padding: 14px 18px;
}


.dropdown-section + .dropdown-section {
    border-top: 1px solid #edf0f4;
}


.dropdown-title {
    font-size: 13px;
    font-weight: 700;
    color: #17395f;
    margin-bottom: 10px;
}


/* Notification item */

.dropdown-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 9px 0;
    color: #333;
    text-decoration: none;
    font-size: 14px;
}


.dropdown-item:hover {
    color: #287fc9;
}


.dropdown-icon {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #edf5fc;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}


.dropdown-text {
    line-height: 1.4;
}


.dropdown-text strong {
    display: block;
    color: #17395f;
}


.dropdown-text small {
    color: #718096;
}


/* Number badge */

.notification-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 5px;
    border-radius: 20px;
    background: #e74c3c;
    color: white;
    font-size: 11px;
    font-weight: 700;
}


.empty-notification {
    color: #8995a3;
    font-size: 13px;
    padding: 5px 0;
}


</style>

</head>


<body>

<div class="app-shell">


<aside class="sidebar">

    <div class="brand-mini">

        <img
            src="<?= $base ?>images/logo.jpeg"
            alt="Logo"
        >

        <div>Rental System</div>

    </div>


    <nav>

        <a
            class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>"
            href="<?= $base ?>admin/dashboard.php"
        >
            ⌂ <span>Dashboard</span>
        </a>


        <a
            class="<?= ($active ?? '') === 'items' ? 'active' : '' ?>"
            href="<?= $base ?>admin/items.php"
        >
            ▣ <span>Items</span>
        </a>


        <a
            class="<?= ($active ?? '') === 'customers' ? 'active' : '' ?>"
            href="<?= $base ?>admin/customers.php"
        >
            ♙ <span>Customers</span>
        </a>


        <a
            class="<?= ($active ?? '') === 'reservations' ? 'active' : '' ?>"
            href="<?= $base ?>admin/reservations.php"
        >
            ▤ <span>Reservations</span>
        </a>


        <a
            class="<?= ($active ?? '') === 'rentals' ? 'active' : '' ?>"
            href="<?= $base ?>admin/rentals.php"
        >
            ↻ <span>Rentals & Returns</span>
        </a>


        <a
            class="<?= ($active ?? '') === 'reports' ? 'active' : '' ?>"
            href="<?= $base ?>admin/reports.php"
        >
            ▥ <span>Reports</span>
        </a>

    </nav>


    <a
        class="logout-link"
        href="<?= $base ?>logout.php"
    >
        ↪ <span>Logout</span>
    </a>

</aside>


<main class="main-content">


<header class="topbar">

    <div>

        <h1>
            <?= e($page_title) ?>
        </h1>

        <p>
            Local Rentals & Reservations System
        </p>

    </div>


    <!-- ADMIN ACCOUNT -->

    <div class="account-area">

        <div
            class="account-chip"
            id="adminAccountButton"
        >

            ● Admin

            <?php if ($notificationCount > 0): ?>

                <span class="notification-badge">
                    <?= $notificationCount ?>
                </span>

            <?php endif; ?>

            ▾

        </div>


        <!-- DROPDOWN -->

        <div
            class="account-dropdown"
            id="adminDropdown"
        >


            <div class="dropdown-header">

                Admin Notifications

            </div>


            <!-- NOTIFICATIONS -->

            <div class="dropdown-section">

                <div class="dropdown-title">
                    🔔 Notifications
                </div>


                <?php if ($pendingCount > 0): ?>

                    <a
                        href="<?= $base ?>admin/reservations.php"
                        class="dropdown-item"
                    >

                        <div class="dropdown-icon">
                            🔔
                        </div>

                        <div class="dropdown-text">

                            <strong>
                                New reservation request
                            </strong>

                            <small>
                                <?= $pendingCount ?>
                                pending reservation(s) waiting for approval.
                            </small>

                        </div>

                    </a>

                <?php else: ?>

                    <div class="empty-notification">
                        No new reservation requests.
                    </div>

                <?php endif; ?>

            </div>


            <!-- REMINDERS -->

            <div class="dropdown-section">

                <div class="dropdown-title">
                    ⏰ Reminders
                </div>


                <?php if ($dueToday > 0): ?>

                    <a
                        href="<?= $base ?>admin/rentals.php"
                        class="dropdown-item"
                    >

                        <div class="dropdown-icon">
                            ⏰
                        </div>

                        <div class="dropdown-text">

                            <strong>
                                Rental due today
                            </strong>

                            <small>
                                <?= $dueToday ?>
                                rental(s) are due today.
                            </small>

                        </div>

                    </a>

                <?php endif; ?>


                <?php if ($dueTomorrow > 0): ?>

                    <a
                        href="<?= $base ?>admin/rentals.php"
                        class="dropdown-item"
                    >

                        <div class="dropdown-icon">
                            📅
                        </div>

                        <div class="dropdown-text">

                            <strong>
                                Rental due tomorrow
                            </strong>

                            <small>
                                <?= $dueTomorrow ?>
                                rental(s) are due tomorrow.
                            </small>

                        </div>

                    </a>

                <?php endif; ?>


                <?php if ($dueToday === 0 && $dueTomorrow === 0): ?>

                    <div class="empty-notification">
                        No upcoming rental reminders.
                    </div>

                <?php endif; ?>

            </div>


        </div>

    </div>

</header>


<?php if ($msg = flash("success")): ?>

    <div class="alert success-alert">
        <?= e($msg) ?>
    </div>

<?php endif; ?>


<?php if ($msg = flash("error")): ?>

    <div class="alert danger-alert">
        <?= e($msg) ?>
    </div>

<?php endif; ?>


<script>

document.addEventListener("DOMContentLoaded", function () {

    const button = document.getElementById("adminAccountButton");
    const dropdown = document.getElementById("adminDropdown");


    button.addEventListener("click", function (event) {

        event.stopPropagation();

        dropdown.classList.toggle("show");

    });


    document.addEventListener("click", function (event) {

        if (
            !dropdown.contains(event.target) &&
            !button.contains(event.target)
        ) {

            dropdown.classList.remove("show");

        }

    });

});

</script>