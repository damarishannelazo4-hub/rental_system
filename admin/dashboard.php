<?php
require_once "../db.php";
$page_title = "Admin Dashboard";
$active = "dashboard";
require "../partials/admin_header.php";

$total_items = $conn->query("SELECT COUNT(*) c FROM items")->fetch_assoc()["c"];
$total_customers = $conn->query("SELECT COUNT(*) c FROM users WHERE role='user'")->fetch_assoc()["c"];
$total_reservations = $conn->query("SELECT COUNT(*) c FROM reservations")->fetch_assoc()["c"];
$active_rentals = $conn->query("SELECT COUNT(*) c FROM rentals WHERE status='Ongoing'")->fetch_assoc()["c"];

$recent = $conn->query("SELECT r.*, u.full_name, i.item_name FROM reservations r
                        JOIN users u ON u.user_id=r.user_id
                        JOIN items i ON i.item_id=r.item_id
                        ORDER BY r.created_at DESC LIMIT 5");
?>
<div class="stats-grid">
    <div class="stat-card"><span class="stat-icon">▣</span><div><small>Total Items</small><strong><?= $total_items ?></strong></div></div>
    <div class="stat-card"><span class="stat-icon green">♙</span><div><small>Total Customers</small><strong><?= $total_customers ?></strong></div></div>
    <div class="stat-card"><span class="stat-icon orange">▤</span><div><small>Total Reservations</small><strong><?= $total_reservations ?></strong></div></div>
    <div class="stat-card"><span class="stat-icon purple">↻</span><div><small>Active Rentals</small><strong><?= $active_rentals ?></strong></div></div>
</div>

<section class="panel">
    <div class="panel-head"><h2>Recent Reservations</h2><a class="btn small" href="reservations.php">View All</a></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Customer</th><th>Item</th><th>Rental Date</th><th>Return Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php if ($recent->num_rows): while($r = $recent->fetch_assoc()): ?>
        <tr>
            <td><?= e($r["full_name"]) ?></td>
            <td><?= e($r["item_name"]) ?></td>
            <td><?= e($r["rental_date"]) ?></td>
            <td><?= e($r["return_date"]) ?></td>
            <td><?= status_badge($r["status"]) ?></td>
        </tr>
        <?php endwhile; else: ?>
        <tr><td colspan="5" class="empty">No reservations yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>
</section>
<?php require "../partials/footer.php"; ?>
