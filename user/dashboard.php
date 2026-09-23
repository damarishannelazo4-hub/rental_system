<?php
require_once "../db.php";
$page_title = "User Dashboard";
$active = "dashboard";
require "../partials/user_header.php";

$uid=(int)$_SESSION["user_id"];
$available=$conn->query("SELECT COUNT(*) c FROM items WHERE status='Available'")->fetch_assoc()["c"];
$stmt=$conn->prepare("SELECT COUNT(*) c FROM reservations WHERE user_id=?");
$stmt->bind_param("i",$uid); $stmt->execute(); $my_res=$stmt->get_result()->fetch_assoc()["c"];
$stmt=$conn->prepare("SELECT COUNT(*) c FROM rentals WHERE user_id=? AND status='Ongoing'");
$stmt->bind_param("i",$uid); $stmt->execute(); $active_rent=$stmt->get_result()->fetch_assoc()["c"];
$stmt=$conn->prepare("SELECT COUNT(*) c FROM reservations WHERE user_id=? AND status='Pending'");
$stmt->bind_param("i",$uid); $stmt->execute(); $pending=$stmt->get_result()->fetch_assoc()["c"];
?>
<div class="stats-grid">
<div class="stat-card"><span class="stat-icon">▣</span><div><small>Available Items</small><strong><?= $available ?></strong></div></div>
<div class="stat-card"><span class="stat-icon green">▤</span><div><small>My Reservations</small><strong><?= $my_res ?></strong></div></div>
<div class="stat-card"><span class="stat-icon purple">↻</span><div><small>Active Rentals</small><strong><?= $active_rent ?></strong></div></div>
<div class="stat-card"><span class="stat-icon orange">⌛</span><div><small>Pending Requests</small><strong><?= $pending ?></strong></div></div>
</div>
<section class="panel welcome-panel">
<h2>Welcome, <?= e($_SESSION["full_name"]) ?>!</h2>
<p>Browse available items, send a reservation request, and track your rentals from one place.</p>
<a class="btn primary" href="items.php">Browse Items</a>
</section>
<?php require "../partials/footer.php"; ?>
