<?php
require_once "../db.php";
require_once "../functions.php";

$page_title = "My Reservations";
$active = "reservations";

require "../partials/user_header.php";

$uid = (int)$_SESSION["user_id"];

$stmt = $conn->prepare(
    "SELECT r.reservation_id, r.order_ref, r.quantity, r.rental_date, r.return_date, r.status,
            r.created_at, r.payment_method, r.payment_status, r.payment_ref,
            r.combo_id, r.combo_discount, r.item_color,
            i.item_name, i.price_per_day
     FROM reservations r
     JOIN items i ON i.item_id = r.item_id
     WHERE r.user_id = ?
     ORDER BY r.created_at DESC, r.order_ref DESC, r.reservation_id ASC"
);
$stmt->bind_param("i", $uid);
$stmt->execute();
$res = $stmt->get_result();

/* Group rows by order reference.
   Items of the same combo (same combo_id inside an order) are shown
   together and get the combo discount saved with the reservation. */
$orders = [];

while ($row = $res->fetch_assoc()) {

    $ref = ($row["order_ref"] ?? "") !== "" ? $row["order_ref"] : "#" . $row["reservation_id"];

    if (!isset($orders[$ref])) {

        $days = max(1, (int)round((strtotime($row["return_date"]) - strtotime($row["rental_date"])) / 86400));

        $orders[$ref] = [
            "rental_date" => $row["rental_date"],
            "return_date" => $row["return_date"],
            "status"      => $row["status"],
            "pay_method"  => $row["payment_method"],
            "pay_status"  => $row["payment_status"],
            "pay_ref"     => $row["payment_ref"],
            "days"        => $days,
            "total"       => 0,
            "saved"       => 0,
            "lines"       => []
        ];
    }

    $o =& $orders[$ref];

    $qty   = (int)$row["quantity"];
    $gross = $qty * (float)$row["price_per_day"] * $o["days"];

    if (!empty($row["combo_id"])) {

        $pct = ($row["combo_discount"] !== null)
            ? (float)$row["combo_discount"]
            : (float)COMBO_DISCOUNT_PERCENT;

        $k = "c" . (int)$row["combo_id"];

        if (!isset($o["lines"][$k])) {
            $o["lines"][$k] = ["combo" => true, "pct" => $pct, "parts" => []];
        }

        $o["lines"][$k]["parts"][] = [
            "name"  => $row["item_name"],
            "qty"   => $qty,
            "color" => $row["item_color"] ?? ""
        ];

        $saved = round($gross * $pct / 100, 2);

        $o["total"] += $gross - $saved;
        $o["saved"] += $saved;

    } else {

        $o["lines"][] = [
            "combo" => false,
            "name"  => $row["item_name"],
            "color" => $row["item_color"] ?? "",
            "qty"   => $qty,
            "parts" => []
        ];

        $o["total"] += $gross;
    }

    unset($o);
}


/* Split by status: Pending always on top */
$groups = [
    "Pending"   => [],
    "Approved"  => [],
    "Rejected"  => [],
    "Cancelled" => []
];

foreach ($orders as $ref => $o) {
    $groups[$o["status"]][$ref] = $o;
}

$sections = [
    "Pending"   => ["title" => "Pending",   "hint" => "Waiting for admin approval", "color" => "#d69e2e"],
    "Approved"  => ["title" => "Approved",  "hint" => "Approved by the admin",      "color" => "#2f9e63"],
    "Rejected"  => ["title" => "Rejected",  "hint" => "Not approved by the admin",  "color" => "#c0392b"],
    "Cancelled" => ["title" => "Cancelled", "hint" => "Cancelled reservations",     "color" => "#8995a3"]
];

function status_class($s) {
    if ($s === "Approved") {
        return "success";
    }
    if ($s === "Rejected" || $s === "Cancelled") {
        return "danger";
    }
    return "warning";
}

function pay_class($s) {
    return $s === "Paid" ? "success" : ($s === "For Verification" ? "info" : "muted");
}

function render_order($ref, $o) {
    ?>
    <div class="product-card" style="margin-bottom:16px; padding:18px;">
        <div style="display:flex; justify-content:space-between; flex-wrap:wrap; gap:8px;">
            <strong>Ref: <?= e($ref) ?></strong>
            <span class="badge <?= status_class($o["status"]) ?>"><?= e($o["status"]) ?></span>
        </div>

        <p class="muted-text">
            <?= e($o["rental_date"]) ?> → <?= e($o["return_date"]) ?>
            (<?= (int)$o["days"] ?> day<?= $o["days"] > 1 ? "s" : "" ?>)
        </p>

        <p class="muted-text" style="margin:0 0 8px;">
            Payment: <strong><?= e($o["pay_method"]) ?></strong>
            <span class="badge <?= pay_class($o["pay_status"]) ?>"><?= e($o["pay_status"]) ?></span>
            <?php if (!empty($o["pay_ref"])): ?>
                &middot; Ref: <?= e($o["pay_ref"]) ?>
            <?php endif; ?>
        </p>

        <table style="width:100%; border-collapse:collapse;">
            <?php foreach ($o["lines"] as $line): ?>

                <?php if ($line["combo"]): ?>

                    <tr>
                        <td colspan="2" style="padding:8px 0 2px;">
                            <span class="badge info">
                                Combo &minus;<?= rtrim(rtrim(number_format($line["pct"], 2), "0"), ".") ?>%
                            </span>
                        </td>
                    </tr>

                    <?php foreach ($line["parts"] as $p): ?>
                        <tr>
                            <td style="padding:4px 0 4px 14px;">
                                <?= e($p["name"]) ?>
                                <?php if (!empty($p["color"])): ?>
                                    <small class="muted-text">(<?= e($p["color"]) ?>)</small>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:right;">× <?= (int)$p["qty"] ?></td>
                        </tr>
                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td style="padding:6px 0;">
                            <?= e($line["name"]) ?>
                            <?php if (!empty($line["color"])): ?>
                                <small class="muted-text">(<?= e($line["color"]) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">× <?= (int)$line["qty"] ?></td>
                    </tr>

                <?php endif; ?>

            <?php endforeach; ?>
        </table>

        <?php if ($o["saved"] > 0): ?>
            <p class="muted-text" style="text-align:right; margin:8px 0 0; color:#1b7a4a;">
                Combo discount: -₱<?= number_format($o["saved"], 2) ?>
            </p>
        <?php endif; ?>

        <p style="text-align:right; margin:10px 0 0;">
            <strong>Total: ₱<?= number_format($o["total"], 2) ?></strong>
        </p>
    </div>
    <?php
}
?>

<section class="panel">
    <div class="panel-head">
        <h2>My Reservations</h2>
        <span class="muted-text">Your submitted reservations and their status</span>
    </div>

    <?php if (!$orders): ?>
        <p class="muted-text">You have no reservations yet. <a href="items.php"><strong>Browse items</strong></a></p>
    <?php endif; ?>
</section>


<?php if ($orders): ?>

    <?php foreach ($groups as $status => $list): ?>

        <?php
        /* Cancelled (and any other status) only shows when it has something */
        if (!$list && !in_array($status, ["Pending", "Approved", "Rejected"], true)) {
            continue;
        }

        $meta = $sections[$status] ?? [
            "title" => $status,
            "hint"  => "",
            "color" => "#8995a3"
        ];
        ?>

        <section class="panel" style="border-left:5px solid <?= e($meta["color"]) ?>;">

            <div class="panel-head">
                <h2>
                    <?= e($meta["title"]) ?>
                    <span class="badge <?= status_class($status) ?>"><?= count($list) ?></span>
                </h2>
                <span class="muted-text"><?= e($meta["hint"]) ?></span>
            </div>

            <?php if (!$list): ?>
                <p class="muted-text">No <?= e(strtolower($meta["title"])) ?> reservations.</p>
            <?php endif; ?>

            <?php foreach ($list as $ref => $o): ?>
                <?php render_order($ref, $o); ?>
            <?php endforeach; ?>

        </section>

    <?php endforeach; ?>

<?php endif; ?>

<?php require "../partials/footer.php"; ?>