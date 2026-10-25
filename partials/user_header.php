<?php
require_once __DIR__ . "/../db.php";
require_once __DIR__ . "/../functions.php";

require_role("user");

$base = "../";
$page_title = $page_title ?? "User";

$uid = (int)$_SESSION["user_id"];
$userName = $_SESSION["full_name"] ?? "User";


/* =========================
   HELPERS
========================= */

/* Text + style for the due date of a rental */
function due_text($returnDate) {
    $today = strtotime(date("Y-m-d"));
    $due   = strtotime($returnDate);
    $diff  = (int)round(($due - $today) / 86400);

    if ($diff < 0)  return ["Overdue by " . abs($diff) . " day(s)", "late"];
    if ($diff === 0) return ["Due today", "soon"];
    if ($diff === 1) return ["Due tomorrow", "soon"];

    return [$diff . " days left", "ok"];
}


/* =========================
   NOTIFICATIONS
   Each approved / rejected reservation shows ONCE.
   After the dropdown is opened it is marked as seen (user_seen = 1).
========================= */

$notifications = [];

$stmt = $conn->prepare("
    SELECT reservation_id, order_ref, status, rental_date, return_date
    FROM reservations
    WHERE user_id = ?
      AND status IN ('Approved', 'Rejected')
      AND user_seen = 0
    ORDER BY reservation_id DESC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $ref = (($row["order_ref"] ?? "") !== "")
        ? $row["order_ref"]
        : "#" . $row["reservation_id"];

    $key = $ref . "|" . $row["status"];

    if (!isset($notifications[$key])) {
        $notifications[$key] = [
            "ref"         => $ref,
            "status"      => $row["status"],
            "rental_date" => $row["rental_date"],
            "return_date" => $row["return_date"],
            "ids"         => []
        ];
    }

    $notifications[$key]["ids"][] = (int)$row["reservation_id"];
}

$stmt->close();

/* show at most 8 at a time; only the shown ones are marked as seen */
$notifications = array_slice($notifications, 0, 8, true);

$notifIds = [];

foreach ($notifications as $n) {
    $notifIds = array_merge($notifIds, $n["ids"]);
}

$newNotifications = count($notifications);


/* =========================
   REMINDERS
   Ongoing rentals with their due date (the last day of use).
========================= */

$reminders = [];

$stmt = $conn->prepare("
    SELECT rt.quantity, rt.return_date, i.item_name
    FROM rentals rt
    JOIN items i ON i.item_id = rt.item_id
    WHERE rt.user_id = ?
      AND rt.status = 'Ongoing'
    ORDER BY rt.return_date ASC, rt.rental_id ASC
");
$stmt->bind_param("i", $uid);
$stmt->execute();
$reminders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$reminderTotal = count($reminders);
$reminderShown = array_slice($reminders, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($page_title) ?> | Local Rentals</title>
<link rel="stylesheet" href="<?= $base ?>assets/style.css?v=<?= filemtime(__DIR__ . '/../assets/style.css') ?>">

<style>
.account-area { position: relative; }

.account-chip {
    cursor: pointer;
    user-select: none;
    padding: 10px 14px;
    border-radius: 8px;
    transition: background 0.2s ease;
}
.account-chip:hover { background: #eef4fa; }

.user-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: calc(100% + 10px);
    width: 350px;
    background: #ffffff;
    border: 1px solid #e1e7ee;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(0,0,0,0.12);
    z-index: 9999;
    overflow: hidden;
}
.user-dropdown.show { display: block; }

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
    width: 30px;
    height: 30px;
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

.dropdown-text small.late { color: #c0392b; font-weight: 700; }
.dropdown-text small.soon { color: #d97706; font-weight: 700; }

.dropdown-more {
    display: block;
    margin-top: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #287fc9;
    text-decoration: none;
}

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

<!-- SIDEBAR -->
<aside class="sidebar" id="site-sidebar" aria-label="Main navigation">

    <div class="brand-mini">
        <img src="<?= $base ?>images/logo.jpeg" alt="Logo">
        <div>Rental System</div>
    </div>

    <nav>
        <a class="<?= ($active ?? '') === 'items' ? 'active' : '' ?>" href="<?= $base ?>user/items.php">
            <iconify-icon icon="solar:magnifer-outline" aria-hidden="true"></iconify-icon> <span>Browse Items</span>
        </a>

        <a class="<?= ($active ?? '') === 'reservations' ? 'active' : '' ?>" href="<?= $base ?>user/reservations.php">
            <iconify-icon icon="solar:calendar-outline" aria-hidden="true"></iconify-icon> <span>My Reservations</span>
        </a>

        <a class="<?= ($active ?? '') === 'rentals' ? 'active' : '' ?>" href="<?= $base ?>user/rentals.php">
            <iconify-icon icon="solar:box-outline" aria-hidden="true"></iconify-icon> <span>My Rentals</span>
        </a>

        <a class="<?= ($active ?? '') === 'profile' ? 'active' : '' ?>" href="<?= $base ?>user/profile.php">
            <iconify-icon icon="solar:user-circle-outline" aria-hidden="true"></iconify-icon> <span>Profile</span>
        </a>
    </nav>

    <a class="logout-link" href="<?= $base ?>logout.php">
        <iconify-icon icon="solar:logout-2-outline" aria-hidden="true"></iconify-icon> <span>Logout</span>
    </a>

</aside>
<script src="<?= $base ?>assets/navigation.js?v=1"></script>

<!-- MAIN CONTENT -->
<main class="main-content">

<header class="topbar">
    <button type="button" class="menu-toggle" aria-controls="site-sidebar" aria-expanded="false" aria-label="Open navigation" hidden><iconify-icon icon="solar:hamburger-menu-outline" aria-hidden="true"></iconify-icon></button>

    <div>
        <h1><?= e($page_title) ?></h1>
        <p>Welcome, <?= e($userName) ?></p>
    </div>

    <div class="account-area">

        <div class="account-chip" id="userAccountButton">
            <iconify-icon icon="solar:user-circle-outline" aria-hidden="true"></iconify-icon> <?= e($userName) ?>
            <?php if ($newNotifications > 0): ?>
                <span class="user-notification-badge" id="notificationBadge"><?= $newNotifications ?></span>
            <?php endif; ?>
            ▾
        </div>

        <div class="user-dropdown" id="userDropdown">

            <div class="dropdown-header"><?= e($userName) ?></div>

            <!-- NOTIFICATIONS (shown once) -->
            <div class="dropdown-section">
                <div class="dropdown-title"><iconify-icon icon="solar:bell-outline" aria-hidden="true"></iconify-icon> Notifications</div>

                <?php foreach ($notifications as $n): ?>

                    <a href="<?= $base ?>user/reservations.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="<?= $n["status"] === "Approved" ? 'solar:check-circle-outline' : 'solar:danger-circle-outline' ?>" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong>Reservation <?= $n["status"] === "Approved" ? "approved" : "rejected" ?></strong>
                            <small>
                                Ref <?= e($n["ref"]) ?> &middot;
                                <?= e(short_date($n["rental_date"])) ?> to <?= e(short_date($n["return_date"])) ?>
                            </small>
                        </div>
                    </a>

                <?php endforeach; ?>

                <?php if (!$notifications): ?>
                    <div class="empty-notification">No new notifications.</div>
                <?php endif; ?>
            </div>

            <!-- REMINDERS -->
            <div class="dropdown-section">
                <div class="dropdown-title">⏰ Reminders</div>

                <?php foreach ($reminderShown as $r): ?>

                    <?php [$dueLabel, $dueClass] = due_text($r["return_date"]); ?>

                    <a href="<?= $base ?>user/rentals.php" class="dropdown-item">
                        <div class="dropdown-icon"><iconify-icon icon="<?= $dueClass === "ok" ? 'solar:calendar-outline' : 'solar:alarm-outline' ?>" aria-hidden="true"></iconify-icon></div>
                        <div class="dropdown-text">
                            <strong><?= e($r["item_name"]) ?> × <?= max(1, (int)$r["quantity"]) ?></strong>
                            <small>Return by <?= e(short_date($r["return_date"])) ?></small>
                            <small class="<?= e($dueClass) ?>"> &middot; <?= e($dueLabel) ?></small>
                        </div>
                    </a>

                <?php endforeach; ?>

                <?php if ($reminderTotal > count($reminderShown)): ?>
                    <a href="<?= $base ?>user/rentals.php" class="dropdown-more">
                        + <?= $reminderTotal - count($reminderShown) ?> more rental(s)
                    </a>
                <?php endif; ?>

                <?php if ($reminderTotal === 0): ?>
                    <div class="empty-notification">No ongoing rentals.</div>
                <?php endif; ?>
            </div>

        </div>
    </div>

</header>

<!-- FLASH MESSAGES -->
<?php if ($msg = flash("success")): ?>
    <div class="alert success-alert"><?= e($msg) ?></div>
<?php endif; ?>

<?php if ($msg = flash("error")): ?>
    <div class="alert danger-alert"><?= e($msg) ?></div>
<?php endif; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const button   = document.getElementById("userAccountButton");
    const dropdown = document.getElementById("userDropdown");
    const badge    = document.getElementById("notificationBadge");

    /* reservation ids of the notifications shown in this dropdown */
    const shownIds = <?= json_encode($notifIds) ?>;

    let marked = false;

    if (!button || !dropdown) {
        return;
    }

    /* the dropdown opens only when the name is clicked */

    button.addEventListener("click", function (event) {

        event.stopPropagation();
        dropdown.classList.toggle("show");

        /* mark as seen only once, when it is first opened */

        if (!marked && shownIds.length > 0 && dropdown.classList.contains("show")) {

            marked = true;

            if (badge) {
                badge.remove();
            }

            fetch("<?= $base ?>user/mark_notifications_seen.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "ids=" + encodeURIComponent(shownIds.join(","))
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