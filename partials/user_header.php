<?php

require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../functions.php";

require_role("user");

$base = "../";
$page_title = $page_title ?? "User";


/* =========================
   USER INFORMATION
   ========================= */

$uid = (int)$_SESSION["user_id"];
$userName = $_SESSION["full_name"] ?? "User";


/* =========================
   NOTIFICATIONS
   ========================= */

$approvedCount = 0;
$rejectedCount = 0;
$pendingCount = 0;


/* Approved reservations */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Approved'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $approvedCount = (int)$result->fetch_assoc()["total"];
}


/* Rejected reservations */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Rejected'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $rejectedCount = (int)$result->fetch_assoc()["total"];
}


/* Pending reservations */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Pending'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $pendingCount = (int)$result->fetch_assoc()["total"];
}


/* =========================
   REMINDERS
   ========================= */

$dueToday = 0;
$dueTomorrow = 0;


/* Due today */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE user_id = ?
    AND status = 'Ongoing'
    AND return_date = CURDATE()
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $dueToday = (int)$result->fetch_assoc()["total"];
}


/* Due tomorrow */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE user_id = ?
    AND status = 'Ongoing'
    AND return_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $dueTomorrow = (int)$result->fetch_assoc()["total"];
}


/* =========================
   TOTAL NOTIFICATIONS
   ========================= */

$totalNotifications =
    $approvedCount +
    $rejectedCount +
    $pendingCount +
    $dueToday +
    $dueTomorrow;


/*
   Store the number that has already
   been seen by the user.
*/

$seenCount = (int)($_SESSION["notifications_seen_count"] ?? 0);


/*
   Only show the NEW notification count.
*/

$newNotifications = max(
    0,
    $totalNotifications - $seenCount
);

?>

<!DOCTYPE html>

<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    <?= e($page_title) ?> | Local Rentals
</title>

<link
    rel="stylesheet"
    href="<?= $base ?>assets/style.css"
>


<style>

/* =========================
   ACCOUNT AREA
   ========================= */

.account-area {
    position: relative;
}


/* Account button */

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


/* =========================
   DROPDOWN
   ========================= */

.user-dropdown {
    display: none;

    position: absolute;

    right: 0;

    top: calc(100% + 10px);

    width: 350px;

    background: #ffffff;

    border: 1px solid #e1e7ee;

    border-radius: 12px;

    box-shadow:
        0 12px 30px rgba(0,0,0,0.12);

    z-index: 9999;

    overflow: hidden;
}


.user-dropdown.show {
    display: block;
}


/* Dropdown header */

.dropdown-header {
    padding: 16px 18px;

    border-bottom: 1px solid #edf0f4;

    font-weight: 700;

    color: #17395f;
}


/* Dropdown sections */

.dropdown-section {
    padding: 14px 18px;
}


.dropdown-section + .dropdown-section {
    border-top: 1px solid #edf0f4;
}


/* Section title */

.dropdown-title {
    font-size: 13px;

    font-weight: 700;

    color: #17395f;

    margin-bottom: 10px;
}


/* Dropdown item */

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


/* Icon */

.dropdown-icon {
    width: 30px;

    height: 30px;

    border-radius: 50%;

    background: #edf5fc;

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;
}


/* Text */

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


/* =========================
   RED NOTIFICATION BADGE
   ========================= */

.user-notification-badge {
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


/* Empty notification */

.empty-notification {
    color: #8995a3;

    font-size: 13px;

    padding: 5px 0;
}

</style>

</head>


<body>


<div class="app-shell">


<!-- =========================
     SIDEBAR
     ========================= -->

<aside class="sidebar">


    <div class="brand-mini">

        <img
            src="<?= $base ?>images/logo.jpeg"
            alt="Logo"
        >

        <div>
            Rental System
        </div>

    </div>


    <nav>


        <a
            class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>"
            href="<?= $base ?>user/dashboard.php"
        >

            ⌂

            <span>
                Dashboard
            </span>

        </a>


        <a
            class="<?= ($active ?? '') === 'items' ? 'active' : '' ?>"
            href="<?= $base ?>user/items.php"
        >

            ⌕

            <span>
                Browse Items
            </span>

        </a>


        <a
            class="<?= ($active ?? '') === 'reservations' ? 'active' : '' ?>"
            href="<?= $base ?>user/reservations.php"
        >

            ▤

            <span>
                My Reservations
            </span>

        </a>


        <a
            class="<?= ($active ?? '') === 'rentals' ? 'active' : '' ?>"
            href="<?= $base ?>user/rentals.php"
        >

            ▣

            <span>
                My Rentals
            </span>

        </a>


        <a
            class="<?= ($active ?? '') === 'profile' ? 'active' : '' ?>"
            href="<?= $base ?>user/profile.php"
        >

            ♙

            <span>
                Profile
            </span>

        </a>


    </nav>


    <a
        class="logout-link"
        href="<?= $base ?>logout.php"
    >

        ↪

        <span>
            Logout
        </span>

    </a>


</aside>


<!-- =========================
     MAIN CONTENT
     ========================= -->

<main class="main-content">


<header class="topbar">


    <div>

        <h1>
            <?= e($page_title) ?>
        </h1>

        <p>
            Welcome, <?= e($userName) ?>
        </p>

    </div>


    <!-- =========================
         ACCOUNT BUTTON
         ========================= -->

    <div class="account-area">


        <div
            class="account-chip"
            id="userAccountButton"
        >

            ● <?= e($userName) ?>


            <?php if ($newNotifications > 0): ?>

                <span
                    class="user-notification-badge"
                    id="notificationBadge"
                >

                    <?= $newNotifications ?>

                </span>

            <?php endif; ?>


            ▾

        </div>


        <!-- =========================
             DROPDOWN
             ========================= -->

        <div
            class="user-dropdown"
            id="userDropdown"
        >


            <div class="dropdown-header">

                <?= e($userName) ?>

            </div>


            <!-- =========================
                 NOTIFICATIONS
                 ========================= -->

            <div class="dropdown-section">


                <div class="dropdown-title">

                    🔔 Notifications

                </div>


                <?php if ($approvedCount > 0): ?>


                    <a
                        href="<?= $base ?>user/reservations.php"
                        class="dropdown-item"
                    >


                        <div class="dropdown-icon">
                            ✓
                        </div>


                        <div class="dropdown-text">

                            <strong>
                                Reservation approved
                            </strong>

                            <small>
                                <?= $approvedCount ?>
                                approved reservation(s).
                            </small>

                        </div>


                    </a>


                <?php endif; ?>


                <?php if ($rejectedCount > 0): ?>


                    <a
                        href="<?= $base ?>user/reservations.php"
                        class="dropdown-item"
                    >


                        <div class="dropdown-icon">
                            !
                        </div>


                        <div class="dropdown-text">

                            <strong>
                                Reservation rejected
                            </strong>

                            <small>
                                <?= $rejectedCount ?>
                                rejected reservation(s).
                            </small>

                        </div>


                    </a>


                <?php endif; ?>


                <?php if ($pendingCount > 0): ?>


                    <a
                        href="<?= $base ?>user/reservations.php"
                        class="dropdown-item"
                    >


                        <div class="dropdown-icon">
                            🔔
                        </div>


                        <div class="dropdown-text">

                            <strong>
                                Reservation pending
                            </strong>

                            <small>
                                <?= $pendingCount ?>
                                reservation(s) waiting for approval.
                            </small>

                        </div>


                    </a>


                <?php endif; ?>


                <?php if (
                    $approvedCount === 0 &&
                    $rejectedCount === 0 &&
                    $pendingCount === 0
                ): ?>


                    <div class="empty-notification">

                        No reservation notifications.

                    </div>


                <?php endif; ?>


            </div>


            <!-- =========================
                 REMINDERS
                 ========================= -->

            <div class="dropdown-section">


                <div class="dropdown-title">

                    ⏰ Reminders

                </div>


                <?php if ($dueToday > 0): ?>


                    <a
                        href="<?= $base ?>user/rentals.php"
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
                        href="<?= $base ?>user/rentals.php"
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


                <?php if (
                    $dueToday === 0 &&
                    $dueTomorrow === 0
                ): ?>


                    <div class="empty-notification">

                        No upcoming rental reminders.

                    </div>


                <?php endif; ?>


            </div>


        </div>


    </div>


</header>


<!-- =========================
     FLASH MESSAGES
     ========================= -->

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

/* =========================
   ACCOUNT DROPDOWN
   ========================= */

document.addEventListener(
    "DOMContentLoaded",
    function () {


        const button =
            document.getElementById(
                "userAccountButton"
            );


        const dropdown =
            document.getElementById(
                "userDropdown"
            );


        const badge =
            document.getElementById(
                "notificationBadge"
            );


        if (button && dropdown) {


            button.addEventListener(
                "click",
                function (event) {


                    event.stopPropagation();


                    dropdown.classList.toggle(
                        "show"
                    );


                    /*
                     * Remove red badge
                     * immediately after opening.
                     */

                    if (badge) {

                        badge.remove();

                    }


                    /*
                     * Remember that the
                     * notification was seen.
                     */

                    fetch(
                        "<?= $base ?>user/mark_notifications_seen.php",
                        {
                            method: "POST"
                        }
                    )
                    .then(function(response) {

                        return response.json();

                    })
                    .then(function(data) {

                        console.log(
                            "Notifications marked as seen."
                        );

                    })
                    .catch(function(error) {

                        console.log(
                            "Notification error:",
                            error
                        );

                    });


                }
            );


            /*
             * Close dropdown when clicking
             * outside.
             */

            document.addEventListener(
                "click",
                function(event) {


                    if (
                        !dropdown.contains(
                            event.target
                        )
                        &&
                        !button.contains(
                            event.target
                        )
                    ) {


                        dropdown.classList.remove(
                            "show"
                        );


                    }


                }
            );


        }


    }
);

</script>