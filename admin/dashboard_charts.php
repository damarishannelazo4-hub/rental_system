<?php
/* =========================================================
   ADMIN DASHBOARD CHARTS
   Include this inside admin/dashboard.php:
       <?php require "dashboard_charts.php"; ?>
   (needs $conn from db.php, already loaded by dashboard.php)
========================================================= */

/* ---------- 1. MOST RENTED ITEMS (top 5, by pcs) ---------- */

$dc_top = [];
$q = $conn->query("
    SELECT i.item_name, SUM(r.quantity) AS pcs
    FROM rentals r
    JOIN items i ON i.item_id = r.item_id
    GROUP BY i.item_id, i.item_name
    ORDER BY pcs DESC
    LIMIT 5
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $dc_top[] = $r;
    }
}


/* ---------- 2. SALES, LAST 6 MONTHS ---------- */

$dc_sales_map = [];
$q = $conn->query("
    SELECT DATE_FORMAT(actual_return_date, '%Y-%m') AS k,
           COALESCE(SUM(total_paid), 0) AS s
    FROM rentals
    WHERE status = 'Returned'
      AND actual_return_date >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)
    GROUP BY k
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $dc_sales_map[$r["k"]] = (float)$r["s"];
    }
}

$dc_sales_labels = [];
$dc_sales_values = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date("Y-m-01") . " -$i month");
    $dc_sales_labels[] = date("M Y", $ts);
    $dc_sales_values[] = $dc_sales_map[date("Y-m", $ts)] ?? 0;
}


/* ---------- 3. RESERVATIONS BY STATUS (counted per order) ---------- */

$dc_status = [];
$q = $conn->query("
    SELECT status,
           COUNT(DISTINCT COALESCE(NULLIF(order_ref, ''), CONCAT('#', reservation_id))) AS total
    FROM reservations
    GROUP BY status
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $dc_status[] = $r;
    }
}


/* ---------- 4. RENTALS BY CATEGORY (pcs) ---------- */

$dc_cat = [];
$q = $conn->query("
    SELECT COALESCE(NULLIF(i.category, ''), 'Other') AS category,
           SUM(r.quantity) AS pcs
    FROM rentals r
    JOIN items i ON i.item_id = r.item_id
    GROUP BY category
    ORDER BY pcs DESC
");
if ($q) {
    while ($r = $q->fetch_assoc()) {
        $dc_cat[] = $r;
    }
}

$dc_has_sales = array_sum($dc_sales_values) > 0;
?>

<style>
.dc-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}
.dc-box { position: relative; height: 290px; }
.dc-empty {
    height: 290px;
    display: grid;
    place-items: center;
    color: var(--muted);
    font-size: 13px;
    background: #f8fafc;
    border: 1px dashed var(--line);
    border-radius: 10px;
}
.dc-grid .panel { margin-bottom: 0; }
@media (max-width: 1000px) { .dc-grid { grid-template-columns: 1fr; } }
</style>


<div class="dc-grid">

    <!-- MOST RENTED -->
    <section class="panel">
        <div class="panel-head">
            <h2>Most Rented Items</h2>
            <span class="muted-text">Top 5 by pcs</span>
        </div>

        <?php if ($dc_top): ?>
            <div class="dc-box"><canvas id="dcTop"></canvas></div>
        <?php else: ?>
            <div class="dc-empty">No rentals yet.</div>
        <?php endif; ?>
    </section>


    <!-- SALES -->
    <section class="panel">
        <div class="panel-head">
            <h2>Sales</h2>
            <span class="muted-text">Last 6 months</span>
        </div>

        <?php if ($dc_has_sales): ?>
            <div class="dc-box"><canvas id="dcSales"></canvas></div>
        <?php else: ?>
            <div class="dc-empty">No completed rentals yet.</div>
        <?php endif; ?>
    </section>


    <!-- RESERVATION STATUS -->
    <section class="panel">
        <div class="panel-head">
            <h2>Reservations by Status</h2>
            <span class="muted-text">All time</span>
        </div>

        <?php if ($dc_status): ?>
            <div class="dc-box"><canvas id="dcStatus"></canvas></div>
        <?php else: ?>
            <div class="dc-empty">No reservations yet.</div>
        <?php endif; ?>
    </section>


    <!-- CATEGORY -->
    <section class="panel">
        <div class="panel-head">
            <h2>Rentals by Category</h2>
            <span class="muted-text">Pcs rented</span>
        </div>

        <?php if ($dc_cat): ?>
            <div class="dc-box"><canvas id="dcCat"></canvas></div>
        <?php else: ?>
            <div class="dc-empty">No rentals yet.</div>
        <?php endif; ?>
    </section>

</div>


<script>
(function () {

    var topData    = <?= json_encode($dc_top) ?>;
    var salesLabel = <?= json_encode($dc_sales_labels) ?>;
    var salesValue = <?= json_encode($dc_sales_values) ?>;
    var statusData = <?= json_encode($dc_status) ?>;
    var catData    = <?= json_encode($dc_cat) ?>;

    var palette = ["#287fc9", "#45b979", "#e9a83b", "#8b6edb", "#d9535b", "#17395f", "#5bc0de"];

    var statusColors = {
        "Pending":  "#e9a83b",
        "Approved": "#45b979",
        "Rejected": "#d9535b",
        "Cancelled": "#9aa7b5"
    };

    function draw() {

        /* Most rented (horizontal bars) */
        var elTop = document.getElementById("dcTop");
        if (elTop) {
            new Chart(elTop, {
                type: "bar",
                data: {
                    labels: topData.map(function (r) { return r.item_name; }),
                    datasets: [{
                        label: "Pcs rented",
                        data: topData.map(function (r) { return Number(r.pcs); }),
                        backgroundColor: palette,
                        borderRadius: 6,
                        maxBarThickness: 30
                    }]
                },
                options: {
                    indexAxis: "y",
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { beginAtZero: true, ticks: { precision: 0 } }
                    }
                }
            });
        }

        /* Sales (bars) */
        var elSales = document.getElementById("dcSales");
        if (elSales) {
            new Chart(elSales, {
                type: "bar",
                data: {
                    labels: salesLabel,
                    datasets: [{
                        label: "Sales (\u20B1)",
                        data: salesValue,
                        backgroundColor: "#287fc9",
                        borderRadius: 6,
                        maxBarThickness: 44
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function (c) {
                                    return "\u20B1" + Number(c.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2 });
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: function (v) { return "\u20B1" + Number(v).toLocaleString(); } }
                        }
                    }
                }
            });
        }

        /* Reservation status (doughnut) */
        var elStatus = document.getElementById("dcStatus");
        if (elStatus) {
            new Chart(elStatus, {
                type: "doughnut",
                data: {
                    labels: statusData.map(function (r) { return r.status; }),
                    datasets: [{
                        data: statusData.map(function (r) { return Number(r.total); }),
                        backgroundColor: statusData.map(function (r, i) {
                            return statusColors[r.status] || palette[i % palette.length];
                        }),
                        borderWidth: 2,
                        borderColor: "#fff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "62%",
                    plugins: { legend: { position: "bottom" } }
                }
            });
        }

        /* Category (doughnut) */
        var elCat = document.getElementById("dcCat");
        if (elCat) {
            new Chart(elCat, {
                type: "doughnut",
                data: {
                    labels: catData.map(function (r) { return r.category; }),
                    datasets: [{
                        data: catData.map(function (r) { return Number(r.pcs); }),
                        backgroundColor: catData.map(function (r, i) { return palette[i % palette.length]; }),
                        borderWidth: 2,
                        borderColor: "#fff"
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: "62%",
                    plugins: { legend: { position: "bottom" } }
                }
            });
        }
    }

    /* Load Chart.js only if the page doesn't have it yet */

    if (typeof Chart !== "undefined") {
        draw();
    } else {
        var s = document.createElement("script");
        s.src = "https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js";
        s.onload = draw;
        s.onerror = function () {
            document.querySelectorAll(".dc-box").forEach(function (b) {
                b.innerHTML = "<p class='muted-text'>Charts could not load. Check your internet connection.</p>";
            });
        };
        document.head.appendChild(s);
    }

})();
</script>