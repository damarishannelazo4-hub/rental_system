<?php
require_once "../db.php";
$page_title = "Reports";
$active = "reports";
require "../partials/admin_header.php";

$rental_count=$conn->query("SELECT COUNT(*) c FROM rentals")->fetch_assoc()["c"];
$res_count=$conn->query("SELECT COUNT(*) c FROM reservations")->fetch_assoc()["c"];
$available=$conn->query("SELECT COUNT(*) c FROM items WHERE status='Available'")->fetch_assoc()["c"];
$revenue=$conn->query("SELECT COALESCE(SUM(DATEDIFF(return_date,rental_date)+1)*0,0) total FROM rentals")->fetch_assoc()["total"];
$records=$conn->query("SELECT rt.*,u.full_name,i.item_name,i.price_per_day FROM rentals rt JOIN users u ON u.user_id=rt.user_id JOIN items i ON i.item_id=rt.item_id ORDER BY rt.rental_id DESC");
?>
<div class="stats-grid">
<div class="stat-card"><span class="stat-icon">↻</span><div><small>Rental Records</small><strong><?= $rental_count ?></strong></div></div>
<div class="stat-card"><span class="stat-icon orange">▤</span><div><small>Reservations</small><strong><?= $res_count ?></strong></div></div>
<div class="stat-card"><span class="stat-icon green">▣</span><div><small>Available Items</small><strong><?= $available ?></strong></div></div>
</div>
<section class="panel">
<div class="panel-head"><h2>Rental Records</h2><button class="btn small" onclick="window.print()">Print Report</button></div>
<div class="table-wrap"><table><thead><tr><th>ID</th><th>Customer</th><th>Item</th><th>Rental Date</th><th>Return Date</th><th>Status</th></tr></thead><tbody>
<?php while($r=$records->fetch_assoc()): ?>
<tr><td>#<?= $r["rental_id"] ?></td><td><?= e($r["full_name"]) ?></td><td><?= e($r["item_name"]) ?></td><td><?= e($r["rental_date"]) ?></td><td><?= e($r["return_date"]) ?></td><td><?= status_badge($r["status"]) ?></td></tr>
<?php endwhile; ?>
</tbody></table></div>
</section>
<?php require "../partials/footer.php"; ?>
