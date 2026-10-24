<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Admin Dashboard";
$active = "dashboard";


/* =========================================================
   FOUR STAT BOXES
========================================================= */

function dash_count($conn, $sql)
{
    try {
        $q = $conn->query($sql);
        return $q ? (int)$q->fetch_assoc()["total"] : 0;
    } catch (Throwable $e) {
        return null;
    }
}

$totalItems        = dash_count($conn, "SELECT COUNT(*) AS total FROM items") ?? 0;

/* customers only (falls back to all users if there is no role column) */
$totalCustomers    = dash_count($conn, "SELECT COUNT(*) AS total FROM users WHERE role = 'user'")
                  ?? dash_count($conn, "SELECT COUNT(*) AS total FROM users")
                  ?? 0;

$totalReservations = dash_count($conn, "SELECT COUNT(*) AS total FROM reservations") ?? 0;
$activeRentals     = dash_count($conn, "SELECT COUNT(*) AS total FROM rentals WHERE status = 'Ongoing'") ?? 0;


/* =========================================================
   MOST RENTED ITEMS (top 5, by pcs rented)
========================================================= */

$topItems = [];

$q = $conn->query("
    SELECT i.item_name,
           COUNT(*)        AS times,
           SUM(r.quantity) AS pcs
    FROM rentals r
    JOIN items i ON i.item_id = r.item_id
    GROUP BY i.item_id, i.item_name
    ORDER BY pcs DESC
    LIMIT 5
");

if ($q) {
    while ($r = $q->fetch_assoc()) {
        $topItems[] = $r;
    }
}


/* =========================================================
   RECENT RESERVATIONS (latest 5)
========================================================= */

$recent = $conn->query("
    SELECT u.full_name,
           i.item_name,
           r.rental_date,
           r.return_date,
           r.status
    FROM reservations r
    JOIN users u ON u.user_id = r.user_id
    JOIN items i ON i.item_id = r.item_id
    ORDER BY r.reservation_id DESC
    LIMIT 5
");

require "../partials/admin_header.php";
?>

<style>
.dash-chart { position: relative; height: 230px; max-width: 900px; }

.dash-empty {
    height: 230px;
    display: grid;
    place-items: center;
    color: var(--muted);
    font-size: 13px;
    background: #f8fafc;
    border: 1px dashed var(--line);
    border-radius: 10px;
}
</style>


<!-- FOUR STAT BOXES -->

<div class="stats-grid">

    <div class="stat-card">
        <div class="stat-icon">▣</div>
        <div>
            <small>Total Items</small>
            <strong><?= $totalItems ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">♙</div>
        <div>
            <small>Total Customers</small>
            <strong><?= $totalCustomers ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon orange">▤</div>
        <div>
            <small>Total Reservations</small>
            <strong><?= $totalReservations ?></strong>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon purple">↻</div>
        <div>
            <small>Active Rentals</small>
            <strong><?= $activeRentals ?></strong>
        </div>
    </div>

</div>


<!-- MOST RENTED GRAPH -->

<section class="panel">

    <div class="panel-head">
        <h2>Most Rented Items</h2>
        <span class="muted-text">Top 5 by pcs rented</span>
    </div>

    <?php if ($topItems): ?>

        <div class="dash-chart">
            <canvas id="mostRentedChart"></canvas>
        </div>

    <?php else: ?>

        <div class="dash-empty">No rentals yet.</div>

    <?php endif; ?>

</section>


<!-- RECENT RESERVATIONS -->

<section class="panel">

    <div class="panel-head">
        <h2>Recent Reservations</h2>
        <a class="btn small" href="reservations.php">View All</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Rental Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>

            <?php if ($recent && $recent->num_rows > 0): ?>

                <?php while ($r = $recent->fetch_assoc()): ?>

                    <tr>
                        <td><?= e($r["full_name"]) ?></td>
                        <td><?= e($r["item_name"]) ?></td>
                        <td><?= e($r["rental_date"]) ?></td>
                        <td><?= e($r["return_date"]) ?></td>
                        <td><?= status_badge($r["status"]) ?></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="5" style="text-align:center;">No reservations yet.</td>
                </tr>

            <?php endif; ?>

            </tbody>
        </table>
    </div>

</section>


<?php if ($topItems): ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function () {

    var data   = <?= json_encode($topItems) ?>;
    var canvas = document.getElementById("mostRentedChart");

    if (typeof Chart === "undefined") {
        canvas.parentNode.innerHTML = "<p class='muted-text'>Chart could not load. Check your internet connection.</p>";
        return;
    }

    new Chart(canvas, {
        type: "bar",
        data: {
            labels: data.map(function (r) { return r.item_name; }),
            datasets: [{
                label: "Pcs rented",
                data: data.map(function (r) { return Number(r.pcs); }),
                backgroundColor: ["#287fc9", "#45b979", "#e9a83b", "#8b6edb", "#d9535b"],
                borderRadius: 6,
                maxBarThickness: 42
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        afterLabel: function (c) {
                            return "Rented " + data[c.dataIndex].times + " time(s)";
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 },
                    title: { display: true, text: "Pcs rented" }
                }
            }
        }
    });

})();
</script>

<?php endif; ?>


<?php require "../partials/footer.php"; ?>