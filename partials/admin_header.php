<?php

require_once __DIR__ . "/../functions.php";
require_once __DIR__ . "/../db.php";

require_role("admin");

$base = "../";
$page_title = $page_title ?? "Admin";


/* =========================
   NOTIFICATIONS
   Each event shows ONCE. After the dropdown is opened they are
   marked as seen (admin_seen = 1).
   1) a customer rents (new reservation request)
   2) items were returned
========================= */

/* 1) New reservation requests (one notification per order) */

$newRes = [];

$result = $conn->query("
    SELECT r.reservation_id, r.order_ref, r.rental_date, r.return_date, u.full_name
    FROM reservations r
    JOIN users u ON u.user_id = r.user_id
    WHERE r.status = 'Pending'
      AND r.admin_seen = 0
    ORDER BY r.reservation_id DESC
");

while ($result && ($row = $result->fetch_assoc())) {

    $key = (($row["order_ref"] ?? "") !== "")
        ? $row["order_ref"]
        : "#" . $row["reservation_id"];

    if (!isset($newRes[$key])) {
        $newRes[$key] = [
            "ref"      => $key,
            "customer" => $row["full_name"],
            "from"     => $row["rental_date"],
            "to"       => $row["return_date"],
            "lines"    => 0,
            "ids"      => []
        ];
    }

    $newRes[$key]["lines"]++;
    $newRes[$key]["ids"][] = (int)$row["reservation_id"];
}

$newRes = array_slice($newRes, 0, 8, true);

$resIds = [];

foreach ($newRes as $n) {
    $resIds = array_merge($resIds, $n["ids"]);
}


/* 2) Returned items (one notification per customer and return date) */

$returned = [];

$result = $conn->query("
    SELECT rt.rental_id,
           rt.quantity,
           rt.actual_return_date,
           i.item_name,
           COALESCE(u.full_name, NULLIF(rt.walk_in_name, ''), 'Walk-in') AS customer
    FROM rentals rt
    JOIN items i ON i.item_id = rt.item_id
    LEFT JOIN users u ON u.user_id = rt.user_id
    WHERE rt.status = 'Returned'
      AND rt.admin_seen = 0
    ORDER BY rt.actual_return_date DESC, rt.rental_id DESC
");

while ($result && ($row = $result->fetch_assoc())) {

    $key = $row["customer"] . "|" . $row["actual_return_date"];

    if (!isset($returned[$key])) {
        $returned[$key] = [
            "customer" => $row["customer"],
            "date"     => $row["actual_return_date"],
            "items"    => [],
            "ids"      => []
        ];
    }

    $returned[$key]["items"][] = $row["item_name"] . " × " . max(1, (int)$row["quantity"]);
    $returned[$key]["ids"][]   = (int)$row["rental_id"];
}

$returned = array_slice($returned, 0, 8, true);

$rentIds = [];

foreach ($returned as $n) {
    $rentIds = array_merge($rentIds, $n["ids"]);
}

$notificationCount = count($newRes) + count($returned);


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

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($page_title) ?> | Local Rentals</title>

<link rel="stylesheet" href="<?= $base ?>assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">

<style>

/* ADMIN ACCOUNT DROPDOWN */

.account-area { position: relative; }

.account-chip {
    cursor: pointer;
    user-select: none;
    padding: 10px 14px;
    border-radius: 8px;
    transition: background 0.2s ease;
}

.account-chip:hover { background: #eef4fa; }

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

.account-dropdown.show { display: block; }

.dropdown-header {
    padding: 16px 18px;
    border-bottom: 1px solid #edf0f4;
    font-weight: 700;
    color: #17395f;
}

.dropdown-section { padding: 14px 18px; }

.dropdown-section + .dropdown-section { border-top: 1px solid #edf0f4; }

.dropdown-title {
    font-size: 13px;
    font-weight: 700;
    color: #17395f;
    margin-bottom: 10px;
}

.dropdown-item {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 9px 0;
    color: #333;
    text-decoration: none;
    font-size: 14px;
}

.dropdown-item:hover { color: #287fc9; }

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

.dropdown-text { line-height: 1.4; }

.dropdown-text strong { display: block; color: #17395f; }

.dropdown-text small { color: #718096; }

.dropdown-link-row {
    align-items: center;
    font-weight: 600;
    color: #17395f;
}

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

<script src="https://code.iconify.design/iconify-icon/2.1.0/iconify-icon.min.js" defer></script>
</head>

<body>

<div class="app-shell">

<noscript><style>@media(max-width:700px){.sidebar{display:flex}}</style></noscript>
<aside class="sidebar" id="site-sidebar" aria-label="Main navigation">

    <div class="brand-mini">
        <img src="<?= $base ?>images/logo.jpeg" alt="Logo">
        <div>Rental System</div>
    </div>

    <nav>

        <a class="<?= ($active ?? '') === 'dashboard' ? 'active' : '' ?>"
           href="<?= $base ?>admin/dashboard.php">
            <iconify-icon icon="solar:widget-2-outline" aria-hidden="true"></iconify-icon> <span>Dashboard</span>
        </a>

        <a class="<?= ($active ?? '') === 'items' ? 'active' : '' ?>"
           href="<?= $base ?>admin/items.php">
            <iconify-icon icon="solar:box-outline" aria-hidden="true"></iconify-icon> <span>Items</span>
        </a>

        <a class="<?= ($active ?? '') === 'customers' ? 'active' : '' ?>"
           href="<?= $base ?>admin/customers.php">
            <iconify-icon icon="solar:users-group-rounded-outline" aria-hidden="true"></iconify-icon> <span>Customers</span>
        </a>

        <a class="<?= ($active ?? '') === 'reservations' ? 'active' : '' ?>"
           href="<?= $base ?>admin/reservations.php">
            <iconify-icon icon="solar:calendar-outline" aria-hidden="true"></iconify-icon> <span>Reservations</span>
        </a>

        <a class="<?= ($active ?? '') === 'walkin' ? 'active' : '' ?>"
           href="<?= $base ?>admin/walkin.php">
            <iconify-icon icon="solar:pen-new-square-outline" aria-hidden="true"></iconify-icon> <span>Walk-in Rental</span>
        </a>

        <a class="<?= ($active ?? '') === 'rentals' ? 'active' : '' ?>"
           href="<?= $base ?>admin/rentals.php">
            <iconify-icon icon="solar:refresh-outline" aria-hidden="true"></iconify-icon> <span>Rentals</span>
        </a>

        <a class="<?= ($active ?? '') === 'returns' ? 'active' : '' ?>"
           href="<?= $base ?>admin/returns.php">
            <iconify-icon icon="solar:undo-left-outline" aria-hidden="true"></iconify-icon> <span>Returns</span>
        </a>

        <a class="<?= ($active ?? '') === 'reports' ? 'active' : '' ?>"
           href="<?= $base ?>admin/reports.php">
            <iconify-icon icon="solar:chart-square-outline" aria-hidden="true"></iconify-icon> <span>Reports</span>
        </a>

    </nav>

    <a class="logout-link" href="<?= $base ?>logout.php">
        <iconify-icon icon="solar:logout-2-outline" aria-hidden="true"></iconify-icon> <span>Logout</span>
    </a>

</aside>
<script src="<?= $base ?>assets/navigation.js?v=<?= filemtime(__DIR__ . '/../assets/navigation.js') ?>"></script>

<main class="main-content">

<header class="topbar">
    <button type="button" class="menu-toggle" aria-controls="site-sidebar" aria-expanded="false" aria-label="Open navigation" hidden><iconify-icon icon="solar:hamburger-menu-outline" aria-hidden="true"></iconify-icon></button>

    <div>
        <h1><?= e($page_title) ?></h1>
        <p>Local Rentals & Reservations System</p>
    </div>

    <!-- ADMIN ACCOUNT -->

    <div class="account-area">

        <div class="account-chip" id="adminAccountButton">

            <iconify-icon icon="solar:user-circle-outline" aria-hidden="true"></iconify-icon> <?= e($_SESSION["username"] ?? "Admin") ?>

            <?php if ($notificationCount > 0): ?>
                <span class="notification-badge" id="adminBadge"><?= $notificationCount ?></span>
            <?php endif; ?>

            ▾

        </div>

        <div class="account-dropdown" id="adminDropdown">

            <div class="dropdown-header">Admin Account</div>

            <!-- ACCOUNT LINKS -->

            <div class="dropdown-section">

                <a href="<?= $base ?>admin/profile.php" class="dropdown-item dropdown-link-row">
                    <div class="dropdown-icon"><iconify-icon icon="solar:user-circle-outline" aria-hidden="true"></iconify-icon></div>
                    <div class="dropdown-text">My Profile</div>
                </a>

                <a href="<?= $base ?>admin/create_account.php" class="dropdown-item dropdown-link-row">
                    <div class="dropdown-icon"><iconify-icon icon="solar:user-plus-outline" aria-hidden="true"></iconify-icon></div>
                    <div class="dropdown-text">Create Account</div>
                </a>

            </div>

            <!-- NOTIFICATIONS (shown once) -->

            <div class="dropdown-section">

                <div class="dropdown-title"><iconify-icon icon="solar:bell-outline" aria-hidden="true"></iconify-icon> Notifications</div>

                <?php foreach ($newRes as $n): ?>

                    <a href="<?= $base ?>admin/reservations.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="solar:bell-outline" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong>New rent request</strong>
                            <small>
                                <?= e($n["customer"]) ?> &middot; Ref <?= e($n["ref"]) ?><br>
                                <?= (int)$n["lines"] ?> item(s) &middot;
                                <?= e(short_date($n["from"])) ?> to <?= e(short_date($n["to"])) ?>
                            </small>
                        </div>
                    </a>

                <?php endforeach; ?>

                <?php foreach ($returned as $n): ?>

                    <?php
                    $names = array_slice($n["items"], 0, 3);
                    $extra = count($n["items"]) - count($names);
                    ?>

                    <a href="<?= $base ?>admin/returns.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="solar:undo-left-outline" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong>Items returned</strong>
                            <small>
                                <?= e($n["customer"]) ?> &middot; <?= e(short_date($n["date"])) ?><br>
                                <?= e(implode(", ", $names)) ?><?= $extra > 0 ? " +" . $extra . " more" : "" ?>
                            </small>
                        </div>
                    </a>

                <?php endforeach; ?>

                <?php if (!$newRes && !$returned): ?>

                    <div class="empty-notification">No new notifications.</div>

                <?php endif; ?>

            </div>

            <!-- REMINDERS -->

            <div class="dropdown-section">

                <div class="dropdown-title"><iconify-icon icon="solar:alarm-outline" aria-hidden="true"></iconify-icon> Reminders</div>

                <?php if ($dueToday > 0): ?>

                    <a href="<?= $base ?>admin/rentals.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="solar:alarm-outline" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong>Rental due today</strong>
                            <small><?= $dueToday ?> rental(s) are due today.</small>
                        </div>
                    </a>

                <?php endif; ?>

                <?php if ($dueTomorrow > 0): ?>

                    <a href="<?= $base ?>admin/rentals.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="solar:calendar-outline" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong>Rental due tomorrow</strong>
                            <small><?= $dueTomorrow ?> rental(s) are due tomorrow.</small>
                        </div>
                    </a>

                <?php endif; ?>

                <?php if ($dueToday === 0 && $dueTomorrow === 0): ?>

                    <div class="empty-notification">No upcoming rental reminders.</div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</header>

<?php if ($msg = flash("success")): ?>
    <div class="alert success-alert"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($msg = flash("error")): ?>
    <div class="alert danger-alert"><?= e($msg) ?></div>
<?php endif; ?>

<script>

document.addEventListener("DOMContentLoaded", function () {

    const button   = document.getElementById("adminAccountButton");
    const dropdown = document.getElementById("adminDropdown");
    const badge    = document.getElementById("adminBadge");

    /* ids of the notifications shown in this dropdown */
    const resIds  = <?= json_encode($resIds) ?>;
    const rentIds = <?= json_encode($rentIds) ?>;

    let marked = false;

    button.addEventListener("click", function (event) {

        event.stopPropagation();
        dropdown.classList.toggle("show");

        /* mark as seen only once, when it is first opened */

        if (!marked && (resIds.length || rentIds.length) && dropdown.classList.contains("show")) {

            marked = true;

            if (badge) {
                badge.remove();
            }

            fetch("<?= $base ?>admin/mark_notifications_seen.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "res=" + encodeURIComponent(resIds.join(",")) +
                      "&rent=" + encodeURIComponent(rentIds.join(","))
            }).catch(function (error) {
                console.log("Notification error:", error);
            });
        }
    });

    document.addEventListener("click", function (event) {
        if (!dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.remove("show");
        }
    });

});

</script>