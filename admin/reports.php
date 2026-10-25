<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

/* Automatic discount on the late penalty (percent) */
if (!defined("PENALTY_DISCOUNT_PERCENT")) {
    define("PENALTY_DISCOUNT_PERCENT", 10);
}

/* =========================
   HELPERS
========================= */

function report_q($conn, $sql, $types = "", $params = [])
{
    $st = $conn->prepare($sql);
    if ($types !== "") {
        $st->bind_param($types, ...$params);
    }
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}

function valid_date($d)
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
}

/* =========================
   DATE RANGE (default: this month)
========================= */

$today = date("Y-m-d");

$from = $_GET["from"] ?? date("Y-m-01");
$to   = $_GET["to"]   ?? date("Y-m-t");

if (!valid_date($from)) $from = date("Y-m-01");
if (!valid_date($to))   $to   = date("Y-m-t");
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

$quick = [
    "Today"      => [date("Y-m-d"), date("Y-m-d")],
    "This Week"  => [date("Y-m-d", strtotime("monday this week")), date("Y-m-d", strtotime("sunday this week"))],
    "This Month" => [date("Y-m-01"), date("Y-m-t")],
    "This Year"  => [date("Y-01-01"), date("Y-12-31")],
];


/* =========================
   RENTAL DETAILS (also used by the CSV export)

   ORDER: Returned rentals first, newest actual return date on top,
          then the Ongoing ones below.
========================= */

$detailSql = "SELECT r.*, u.full_name, i.item_name, i.price_per_day, i.penalty_per_day
              FROM rentals r
              LEFT JOIN users u ON u.user_id = r.user_id
              LEFT JOIN items i ON i.item_id = r.item_id
              WHERE (r.rental_date BETWEEN ? AND ?)
                 OR (r.actual_return_date BETWEEN ? AND ?)
              ORDER BY (r.status = 'Returned') DESC,
                       r.actual_return_date DESC,
                       r.rental_id DESC";

$detail = report_q($conn, $detailSql, "ssss", [$from, $to, $from, $to]);


/* =========================
   COMPUTE EVERY RECORD

   Returned rental  -> saved late days, penalty, discount, total_paid
   Ongoing + overdue -> estimated: penalty = late days x penalty/day x pcs,
                        discount = 10% of that penalty
   Ongoing + on time -> no penalty, no discount
========================= */

$sumQty = $sumPenalty = $sumDiscount = 0;
$sumFees = 0;
$sumSales = 0;
$hasEstimate = false;
$top = [];

foreach ($detail as &$r) {

    $qty = max(1, (int)$r["quantity"]);

    $days = max(1, (int)round(
        (strtotime($r["return_date"]) - strtotime($r["rental_date"])) / 86400
    ));

    /* Walk-in customer (no user account) */

    $r["is_walkin"] = ($r["user_id"] === null);

    $r["cust_name"] = $r["is_walkin"]
        ? (($r["walk_in_name"] ?? "") !== "" ? $r["walk_in_name"] : "Walk-in")
        : ($r["full_name"] ?? "Customer");

    /* Rental fee (after combo discount) */

    if ($r["rental_fee"] !== null) {
        $fee = (float)$r["rental_fee"];
    } else {
        $gross = $qty * (float)($r["price_per_day"] ?? 0) * $days;
        $pct   = (!empty($r["combo_id"]) && $r["combo_discount"] !== null)
            ? (float)$r["combo_discount"] : 0;
        $fee   = $gross - round($gross * $pct / 100, 2);
    }

    /* Penalty and discount */

    $estimated = false;

    if ($r["status"] === "Returned") {

        $late = (int)($r["late_days"] ?? 0);
        $pen  = (float)($r["penalty"] ?? 0);
        $disc = (float)($r["discount"] ?? 0);

        $sale = ($r["total_paid"] !== null)
            ? (float)$r["total_paid"]
            : $fee + $pen - $disc;

    } else {

        $late = 0;

        if ($today > $r["return_date"]) {
            $late = (int)((strtotime($today) - strtotime($r["return_date"])) / 86400);
        }

        $pen  = $late * (float)($r["penalty_per_day"] ?? 0) * $qty;
        $disc = round($pen * PENALTY_DISCOUNT_PERCENT / 100, 2);
        $sale = $fee + $pen - $disc;

        if ($late > 0) {
            $estimated   = true;
            $hasEstimate = true;
        }
    }

    $r["c_late"]      = $late;
    $r["c_penalty"]   = $pen;
    $r["c_discount"]  = $disc;
    $r["c_sale"]      = $sale;
    $r["c_estimated"] = $estimated;

    $sumQty      += $qty;
    $sumPenalty  += $pen;
    $sumDiscount += $disc;
    $sumFees     += $fee;
    $sumSales    += $sale;

    /* Top sales per item */

    $name = $r["item_name"] ?? "Unknown";

    if (!isset($top[$name])) {
        $top[$name] = ["item" => $name, "times" => 0, "qty" => 0, "sales" => 0];
    }

    $top[$name]["times"] += 1;
    $top[$name]["qty"]   += $qty;
    $top[$name]["sales"] += $sale;
}
unset($r);

usort($top, function ($a, $b) {
    return [$b["sales"], $b["qty"]] <=> [$a["sales"], $a["qty"]];
});

$top = array_slice($top, 0, 10);


/* =========================
   CSV EXPORT
========================= */

if (isset($_GET["export"])) {
    header("Content-Type: text/csv; charset=utf-8");
    header("Content-Disposition: attachment; filename=rental_report_{$from}_to_{$to}.csv");

    $out = fopen("php://output", "w");
    fputcsv($out, ["Customer", "Item", "Qty", "Rental Date", "Due Date", "Returned On", "Late Days", "Penalty", "Discount", "Total (Paid/Est.)", "Status"]);

    foreach ($detail as $r) {
        fputcsv($out, [
            $r["cust_name"] . ($r["is_walkin"] ? " (Walk-in)" : ""),
            $r["item_name"] ?? "",
            $r["quantity"],
            $r["rental_date"],
            $r["return_date"],
            $r["actual_return_date"] ?? "",
            $r["c_late"],
            number_format($r["c_penalty"], 2, ".", ""),
            number_format($r["c_discount"], 2, ".", ""),
            number_format($r["c_sale"], 2, ".", ""),
            $r["status"] . ($r["c_estimated"] ? " (estimated)" : ""),
        ]);
    }

    fclose($out);
    exit;
}


/* =========================
   PAGE
========================= */

$page_title = "Reports";
$active = "reports";

require "../partials/admin_header.php";

$qs = "from=" . urlencode($from) . "&to=" . urlencode($to);

?>

<style>
.report {
    background: #fff;
    border: 1px solid #cfd6de;
    padding: 24px 28px;
    color: #000;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
}

/* ---- report header ---- */

.report-head {
    display: flex;
    align-items: center;
    gap: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #000;
    margin-bottom: 14px;
}

.report-head img {
    width: 56px;
    height: 56px;
    object-fit: contain;
}

.report-head h1 {
    margin: 0;
    font-size: 20px;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.report-head p {
    margin: 2px 0 0;
    font-size: 12px;
    color: #333;
}

.report-meta {
    width: 100%;
    max-width: 520px;
    margin-bottom: 18px;
    border-collapse: collapse;
}

.report-meta td {
    border: 0;
    padding: 2px 0;
    font-size: 13px;
}

.report-meta td:first-child {
    width: 130px;
    font-weight: 700;
}

/* ---- filter (screen only) ---- */

.report-filter {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: end;
    padding: 20px;
    margin-bottom: 20px;
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: 12px;
}

.report-filter label {
    display: flex;
    flex: 1 1 160px;
    max-width: 220px;
    min-width: 0;
    flex-direction: column;
    gap: 8px;
    font-size: 12px;
    font-weight: 700;
    color: var(--muted);
}

.report-filter input[type=date] {
    width: 100%;
    min-width: 0;
    min-height: 44px;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: 8px;
    background: var(--bg);
    color: var(--text);
    font: inherit;
    font-size: 14px;
}

.report-filter .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
    white-space: nowrap;
}

.report-export-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-left: auto;
}

.report-quick {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    width: 100%;
    padding-top: 16px;
    border-top: 1px solid var(--line);
    font-size: 12px;
}

.report-quick a {
    display: inline-flex;
    align-items: center;
    min-height: 44px;
    padding: 8px 14px;
    border: 1px solid var(--line);
    border-radius: 8px;
    color: var(--text);
    text-decoration: none;
}

.report-quick a.on {
    font-weight: 700;
    color: var(--navy);
    background: #eaf3fd;
    border-color: #a9cbed;
}

.report-filter :focus-visible {
    outline: 2px solid var(--blue);
    outline-offset: 3px;
}

@media(max-width:600px) {
    .report-filter { padding: 16px; gap: 12px; }
    .report-filter label { flex: 1 1 calc(50% - 12px); max-width: none; }
    .report-export-actions { margin-left: 0; flex: 1 1 100%; }
    .report-export-actions .btn { flex: 1; }
}

/* ---- Excel-style tables ---- */

.report h2 {
    margin: 22px 0 6px;
    font-size: 14px;
    text-transform: uppercase;
}

.report table.grid {
    width: 100%;
    border-collapse: collapse;
    font-size: 12.5px;
}

.report table.grid th,
.report table.grid td {
    border: 1px solid #8a8a8a;
    padding: 5px 8px;
    text-align: left;
    vertical-align: middle;
    background: #fff;
    color: #000;
}

.report table.grid th {
    background: #e4e4e4;
    font-size: 12px;
    font-weight: 700;
    text-transform: none;
    letter-spacing: 0;
}

.report table.grid tr:hover td {
    background: #fff;
}

.report table.grid .num { text-align: right; }
.report table.grid .ctr { text-align: center; }

.report table.grid tr.total td {
    font-weight: 700;
    background: #f1f1f1;
}

.report .scroll { overflow-x: auto; }

.report .walkin-tag {
    display: inline-block;
    margin-left: 6px;
    padding: 1px 8px;
    border-radius: 999px;
    background: #e3f0fb;
    color: #1a67a8;
    font-size: 11px;
    font-weight: 700;
    white-space: nowrap;
}

.report .note {
    margin: 6px 0 0;
    font-size: 11.5px;
    color: #444;
}

.report-foot {
    margin-top: 26px;
    font-size: 11px;
    color: #444;
    text-align: center;
}

@media print {
    .sidebar, .topbar, .no-print, .no-print *, .report-filter, .site-footer { display: none !important; }
    .main-content { margin: 0 !important; width: 100% !important; padding: 0 !important; }
    .report { border: 0; padding: 0; }
}
</style>


<div class="no-print">
<!-- DATE FILTER (screen only, never printed) -->

<form method="GET" class="report-filter no-print">
    <label>From
        <input type="date" name="from" value="<?= e($from) ?>" required>
    </label>

    <label>To
        <input type="date" name="to" value="<?= e($to) ?>" required>
    </label>

    <button class="btn primary" type="submit"><iconify-icon icon="solar:filter-outline" aria-hidden="true"></iconify-icon> Apply filters</button>
    <div class="report-export-actions">
        <a class="btn" href="reports.php?<?= $qs ?>&export=1"><iconify-icon icon="solar:download-outline" aria-hidden="true"></iconify-icon> Export CSV</a>
        <button class="btn" type="button" onclick="window.print()"><iconify-icon icon="solar:printer-outline" aria-hidden="true"></iconify-icon> Print</button>
    </div>

    <div class="report-quick">
        <?php foreach ($quick as $label => [$qf, $qt]): ?>
            <a href="reports.php?from=<?= $qf ?>&to=<?= $qt ?>"
               class="<?= ($from === $qf && $to === $qt) ? 'on' : '' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </div>
</form>

</div>

<div class="report">

    <!-- HEADER: which system generated this report -->

    <div class="report-head">
        <img src="../images/logo.jpeg" alt="Logo">
        <div>
            <h1>Rental System</h1>
            <p>Local Rentals &amp; Reservations System &mdash; Rental Report</p>
        </div>
    </div>

    <table class="report-meta">
        <tr>
            <td>Report period:</td>
            <td><?= e(date("F j, Y", strtotime($from))) ?> to <?= e(date("F j, Y", strtotime($to))) ?></td>
        </tr>
        <tr>
            <td>Generated on:</td>
            <td><?= e(date("F j, Y g:i A")) ?></td>
        </tr>
    </table>


    <!-- SUMMARY -->

    <h2>Summary</h2>

    <div class="scroll">
        <table class="grid" style="max-width:520px;">
            <tbody>
                <tr>
                    <td>Total Rental Fees</td>
                    <td class="num">₱<?= number_format($sumFees, 2) ?></td>
                </tr>
                <tr>
                    <td>Total Penalties</td>
                    <td class="num">₱<?= number_format($sumPenalty, 2) ?></td>
                </tr>
                <tr>
                    <td>Total Discounts (<?= (int)PENALTY_DISCOUNT_PERCENT ?>% of penalty)</td>
                    <td class="num">&minus;₱<?= number_format($sumDiscount, 2) ?></td>
                </tr>
                <tr class="total">
                    <td>TOTAL SALES</td>
                    <td class="num">₱<?= number_format($sumSales, 2) ?></td>
                </tr>
            </tbody>
        </table>
    </div>


    <!-- TOP SALES -->

    <h2>Top Sales (Top 10 Items)</h2>

    <div class="scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th class="ctr">Rank</th>
                    <th>Item</th>
                    <th class="num">Times Rented</th>
                    <th class="num">Total Pcs</th>
                    <th class="num">Sales</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$top): ?>
                <tr><td colspan="5" class="ctr">No sales in this range.</td></tr>
            <?php else: ?>
                <?php foreach ($top as $n => $t): ?>
                    <tr>
                        <td class="ctr"><?= $n + 1 ?></td>
                        <td><?= e($t["item"]) ?></td>
                        <td class="num"><?= (int)$t["times"] ?></td>
                        <td class="num"><?= (int)$t["qty"] ?></td>
                        <td class="num">₱<?= number_format($t["sales"], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>


    <!-- RENTAL RECORDS -->

    <h2>Rental Records (<?= count($detail) ?> record<?= count($detail) === 1 ? "" : "s" ?>)</h2>

    <div class="scroll">
        <table class="grid">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th>Rented</th>
                    <th>Due</th>
                    <th>Returned</th>
                    <th class="num">Late (days)</th>
                    <th class="num">Penalty</th>
                    <th class="num">Discount</th>
                    <th class="num">Total (Paid / Est.)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($detail as $r): ?>
                <tr>
                    <td>
                        <?= e($r["cust_name"]) ?>
                        <?php if ($r["is_walkin"]): ?>
                            <span class="walkin-tag">Walk-in</span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($r["item_name"] ?? "") ?></td>
                    <td class="num"><?= (int)$r["quantity"] ?></td>
                    <td><?= e($r["rental_date"]) ?></td>
                    <td><?= e($r["return_date"]) ?></td>
                    <td><?= e($r["actual_return_date"] ?? "-") ?></td>
                    <td class="num"><?= (int)$r["c_late"] ?></td>
                    <td class="num"><?= number_format($r["c_penalty"], 2) ?><?= $r["c_estimated"] ? " *" : "" ?></td>
                    <td class="num"><?= number_format($r["c_discount"], 2) ?><?= $r["c_estimated"] ? " *" : "" ?></td>
                    <td class="num"><?= number_format($r["c_sale"], 2) ?></td>
                    <td><?= e($r["status"]) ?></td>
                </tr>
            <?php endforeach; ?>

            <?php if (!$detail): ?>
                <tr><td colspan="11" class="ctr">No records in this range.</td></tr>
            <?php else: ?>
                <tr class="total">
                    <td colspan="2">TOTAL</td>
                    <td class="num"><?= $sumQty ?></td>
                    <td colspan="4"></td>
                    <td class="num"><?= number_format($sumPenalty, 2) ?></td>
                    <td class="num"><?= number_format($sumDiscount, 2) ?></td>
                    <td class="num"><?= number_format($sumSales, 2) ?></td>
                    <td></td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($hasEstimate): ?>
        <p class="note">
            * Estimated: the rental is overdue and not yet returned. The penalty keeps growing
            until it is returned. A <?= (int)PENALTY_DISCOUNT_PERCENT ?>% discount on the penalty is applied automatically.
        </p>
    <?php endif; ?>

    <p class="report-foot">
        Generated by Rental System &mdash; Local Rentals &amp; Reservations System
    </p>

</div>

<?php require "../partials/footer.php"; ?>