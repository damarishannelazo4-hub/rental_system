<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

/* Automatic discount on the late penalty (percent) */
if (!defined("PENALTY_DISCOUNT_PERCENT")) {
    define("PENALTY_DISCOUNT_PERCENT", 10);
}

$today = date("Y-m-d");
$id    = (int)($_GET["id"] ?? 0);

/* The rental that was clicked */
$stmt = $conn->prepare("
    SELECT rt.*, COALESCE(u.full_name, rt.walk_in_name, 'Walk-in') AS customer_name
    FROM rentals rt
    LEFT JOIN users u ON u.user_id = rt.user_id
    WHERE rt.rental_id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$first = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$first) {
    die("Rental not found.");
}

/* All items rented together (same customer and same dates) */
if ($first["user_id"] !== null) {

    $stmt = $conn->prepare("
        SELECT rt.*, i.item_name, i.price_per_day, i.penalty_per_day
        FROM rentals rt
        JOIN items i ON i.item_id = rt.item_id
        WHERE rt.user_id = ? AND rt.rental_date = ? AND rt.return_date = ?
        ORDER BY rt.rental_id ASC
    ");
    $stmt->bind_param("iss", $first["user_id"], $first["rental_date"], $first["return_date"]);

} else {

    $name    = $first["walk_in_name"] ?? "";
    $contact = $first["walk_in_contact"] ?? "";

    $stmt = $conn->prepare("
        SELECT rt.*, i.item_name, i.price_per_day, i.penalty_per_day
        FROM rentals rt
        JOIN items i ON i.item_id = rt.item_id
        WHERE rt.user_id IS NULL
          AND COALESCE(rt.walk_in_name, '') = ?
          AND COALESCE(rt.walk_in_contact, '') = ?
          AND rt.rental_date = ? AND rt.return_date = ?
        ORDER BY rt.rental_id ASC
    ");
    $stmt->bind_param("ssss", $name, $contact, $first["rental_date"], $first["return_date"]);
}

$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$days = max(1, (int)round(
    (strtotime($first["return_date"]) - strtotime($first["rental_date"])) / 86400
));

$subtotal  = 0;
$comboSave = 0;
$penalty   = 0;
$discount  = 0;
$lateDays  = 0;
$returned  = true;
$estimated = false;

foreach ($rows as &$r) {

    $qty   = max(1, (int)$r["quantity"]);
    $gross = $qty * (float)$r["price_per_day"] * $days;

    $r["qty"]   = $qty;
    $r["gross"] = $gross;

    $subtotal  += $gross;
    $comboSave += (float)($r["combo_saved"] ?? 0);

    if ($r["status"] === "Returned") {

        /* Saved values from the return */
        $late = (int)($r["late_days"] ?? 0);
        $pen  = (float)($r["penalty"] ?? 0);
        $disc = (float)($r["discount"] ?? 0);

    } else {

        /* Not returned yet: estimate until today */
        $returned = false;

        $late = 0;
        if ($today > $r["return_date"]) {
            $late = (int)((strtotime($today) - strtotime($r["return_date"])) / 86400);
        }

        $pen  = $late * (float)($r["penalty_per_day"] ?? 0) * $qty;
        $disc = round($pen * PENALTY_DISCOUNT_PERCENT / 100, 2);

        if ($late > 0) {
            $estimated = true;
        }
    }

    $penalty  += $pen;
    $discount += $disc;
    $lateDays  = max($lateDays, $late);
}
unset($r);

$total = $subtotal - $comboSave + $penalty - $discount;

$receiptNo = "R-" . str_pad((string)$rows[0]["rental_id"], 5, "0", STR_PAD_LEFT);
$payMethod = $first["payment_method"] ?? "Cash";
$payStatus = $first["payment_status"] ?? "Unpaid";
$statusTxt = $returned ? "Returned" : "Ongoing";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Receipt <?= e($receiptNo) ?></title>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; background: #eef2f7; margin: 0; padding: 20px; color: #111; }
    .receipt { max-width: 720px; margin: 0 auto; background: #fff; padding: 32px; border: 1px solid #ccd5e0; }
    .head { text-align: center; border-bottom: 2px solid #111; padding-bottom: 12px; margin-bottom: 16px; }
    .head h1 { margin: 0; font-size: 22px; }
    .head p { margin: 4px 0 0; font-size: 13px; color: #555; }
    .meta { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 16px; }
    .meta div { line-height: 1.7; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { padding: 8px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background: #f4f6f9; }
    td.num, th.num { text-align: right; }
    .totals { margin-top: 12px; margin-left: auto; width: 320px; font-size: 13px; }
    .totals div { display: flex; justify-content: space-between; padding: 4px 0; }
    .totals .grand { border-top: 2px solid #111; margin-top: 6px; padding-top: 8px; font-size: 16px; font-weight: bold; }
    .policy { margin-top: 20px; padding: 10px 12px; border: 1px solid #999; font-size: 12px; line-height: 1.6; }
    .policy strong { display: block; margin-bottom: 4px; text-transform: uppercase; }
    .est { margin-top: 8px; font-size: 11.5px; color: #444; }
    .sign { display: flex; justify-content: space-between; gap: 40px; margin-top: 60px; }
    .sign div { flex: 1; text-align: center; font-size: 12px; }
    .sign .line { border-top: 1px solid #111; padding-top: 6px; }
    .actions { max-width: 720px; margin: 0 auto 14px; display: flex; gap: 8px; }
    .actions button, .actions a { padding: 8px 16px; border: 0; border-radius: 6px; background: #287fc9; color: #fff; font-size: 13px; cursor: pointer; text-decoration: none; }
    .actions a { background: #6b7a8c; }
    @media print {
        body { background: #fff; padding: 0; }
        .receipt { border: 0; max-width: 100%; }
        .actions { display: none; }
    }
</style>
</head>
<body>

<div class="actions">
    <button type="button" onclick="window.print()">Print Receipt</button>
    <a href="rentals.php">Back</a>
</div>

<div class="receipt">

    <div class="head">
        <h1>Rental System</h1>
        <p>Local Rentals &amp; Reservations</p>
        <p><strong>RENTAL RECEIPT</strong></p>
    </div>

    <div class="meta">
        <div>
            <strong>Receipt No:</strong> <?= e($receiptNo) ?><br>
            <strong>Customer:</strong> <?= e($first["customer_name"]) ?><br>
            <?php if (!empty($first["walk_in_contact"])): ?>
                <strong>Contact:</strong> <?= e($first["walk_in_contact"]) ?><br>
            <?php endif; ?>
            <strong>Status:</strong> <?= e($statusTxt) ?>
        </div>
        <div style="text-align:right;">
            <strong>Rent Date:</strong> <?= e($first["rental_date"]) ?><br>
            <strong>Due Date:</strong> <?= e($first["return_date"]) ?><br>
            <?php if ($returned && !empty($first["actual_return_date"])): ?>
                <strong>Returned On:</strong> <?= e($first["actual_return_date"]) ?><br>
            <?php endif; ?>
            <strong>Payment:</strong> <?= e($payMethod) ?> (<?= e($payStatus) ?>)
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th class="num">Pcs</th>
                <th class="num">Price/Day</th>
                <th class="num">Days</th>
                <th class="num">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <?= e($r["item_name"]) ?>
                        <?= !empty($r["item_color"]) ? " (" . e($r["item_color"]) . ")" : "" ?>
                    </td>
                    <td class="num"><?= (int)$r["qty"] ?></td>
                    <td class="num">₱<?= number_format((float)$r["price_per_day"], 2) ?></td>
                    <td class="num"><?= $days ?></td>
                    <td class="num">₱<?= number_format($r["gross"], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totals">
        <div><span>Subtotal</span><span>₱<?= number_format($subtotal, 2) ?></span></div>

        <?php if ($comboSave > 0): ?>
            <div><span>Combo discount</span><span>&minus;₱<?= number_format($comboSave, 2) ?></span></div>
        <?php endif; ?>

        <?php if ($penalty > 0 || $returned): ?>
            <div>
                <span>Late penalty<?= $lateDays > 0 ? " (" . $lateDays . " day/s)" : "" ?><?= $estimated ? " *" : "" ?></span>
                <span>₱<?= number_format($penalty, 2) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($discount > 0): ?>
            <div>
                <span>Penalty discount (<?= (int)PENALTY_DISCOUNT_PERCENT ?>%)<?= $estimated ? " *" : "" ?></span>
                <span>&minus;₱<?= number_format($discount, 2) ?></span>
            </div>
        <?php endif; ?>

        <div class="grand">
            <span><?= $returned ? "TOTAL PAID" : "TOTAL (est.)" ?></span>
            <span>₱<?= number_format($total, 2) ?></span>
        </div>
    </div>

    <?php if ($estimated): ?>
        <p class="est">
            * Estimated until today. The penalty keeps growing every day the items are not returned.
            The final amount is set when the items are returned.
        </p>
    <?php endif; ?>

    <div class="policy">
        <strong>Rental &amp; Penalty Policy</strong>
        1. Items must be returned on or before the due date.<br>
        2. A penalty is charged for every day of late return (penalty per day &times; pcs).<br>
        3. A <?= (int)PENALTY_DISCOUNT_PERCENT ?>% discount is automatically applied to the late penalty.<br>
        4. The penalty <u>must be paid</u> before the return is accepted. Items with an unpaid penalty
           cannot be marked as returned.<br>
        5. Damaged or lost items are the responsibility of the customer.
    </div>

    <div class="sign">
        <div>
            <div class="line">Customer's Signature<br>over Printed Name</div>
        </div>
        <div>
            <div class="line">Received by / Admin</div>
        </div>
    </div>

</div>

</body>
</html>