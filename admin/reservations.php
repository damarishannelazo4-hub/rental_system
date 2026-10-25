<?php

require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Reservation Management";
$active = "reservations";


/* =========================================================
   APPROVE / REJECT A WHOLE ORDER
   (all items of one order_ref are handled together, so the
    items of a combo are never split)
========================================================= */

if (isset($_GET["action"]) && in_array($_GET["action"], ["approve", "reject"], true)) {
    http_response_code(405);
    header("Allow: POST");
    exit("Use the reservation action form.");
}

if (isset($_POST["action"]) && in_array($_POST["action"], ["approve", "reject"], true)) {
    require_csrf();

    $action = $_POST["action"];
    $ref    = trim($_POST["ref"] ?? "");
    $id     = (int)($_POST["id"] ?? 0);

    $conn->begin_transaction();
    try {
    if ($ref !== "") {

        $stmt = $conn->prepare("
            SELECT *
            FROM reservations
            WHERE order_ref = ? AND status = 'Pending'
            ORDER BY reservation_id ASC
            FOR UPDATE
        ");
        $stmt->bind_param("s", $ref);

    } else {

        /* older reservations that have no order_ref */
        $stmt = $conn->prepare("
            SELECT *
            FROM reservations
            WHERE reservation_id = ? AND status = 'Pending'
            FOR UPDATE
        ");
        $stmt->bind_param("i", $id);
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();


    if ($rows) {

        /* APPROVE */

        if ($action === "approve") {

                $ins = $conn->prepare("
                    INSERT INTO rentals
                    (
                        reservation_id,
                        user_id,
                        item_id,
                        quantity,
                        rental_date,
                        return_date,
                        status,
                        combo_id,
                        combo_discount,
                        rental_fee,
                        combo_saved,
                        item_color
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 'Ongoing', ?, ?, ?, ?, ?)
                ");

                $getPrice = $conn->prepare("
                    SELECT price_per_day FROM items WHERE item_id = ?
                ");

                $setRes = $conn->prepare("
                    UPDATE reservations
                    SET status = 'Approved'
                    WHERE reservation_id = ?
                ");

                $setItem = $conn->prepare("
                    UPDATE items
                    SET status = 'Rented'
                    WHERE item_id = ?
                ");

                if (!$ins || !$getPrice || !$setRes || !$setItem) {
                    throw new Exception("Unable to prepare.");
                }

                /* A combo number in reservations is only unique inside
                   its order, so each one gets a fresh number in rentals */

                $comboMap  = [];
                $nextCombo = null;

                foreach ($rows as $res) {

                    $rid  = (int)$res["reservation_id"];
                    $uid  = (int)$res["user_id"];
                    $iid  = (int)$res["item_id"];
                    $qty  = max(1, (int)$res["quantity"]);
                    $rd   = $res["rental_date"];
                    $ret  = $res["return_date"];

                    $color = (($res["item_color"] ?? "") !== "") ? $res["item_color"] : null;

                    $comboId   = null;
                    $comboDisc = null;

                    if (!empty($res["combo_id"])) {

                        $local = (int)$res["combo_id"];

                        if (!isset($comboMap[$local])) {

                            if ($nextCombo === null) {
                                $m = $conn->query("
                                    SELECT COALESCE(MAX(combo_id), 0) + 1 AS n
                                    FROM rentals
                                    FOR UPDATE
                                ");
                                $nextCombo = (int)$m->fetch_assoc()["n"];
                            }

                            $comboMap[$local] = $nextCombo++;
                        }

                        $comboId   = $comboMap[$local];
                        $comboDisc = ($res["combo_discount"] !== null)
                            ? (float)$res["combo_discount"]
                            : (float)COMBO_DISCOUNT_PERCENT;
                    }

                    /* Rental fee (after combo discount) and amount saved */

                    $getPrice->bind_param("i", $iid);
                    $getPrice->execute();
                    $pr = $getPrice->get_result()->fetch_assoc();

                    if (!$pr) {
                        throw new Exception("Item not found.");
                    }

                    $rdays = max(1, (int)round((strtotime($ret) - strtotime($rd)) / 86400));
                    $gross = $qty * (float)$pr["price_per_day"] * $rdays;
                    $saved = ($comboDisc !== null) ? round($gross * $comboDisc / 100, 2) : 0.0;
                    $fee   = round($gross - $saved, 2);

                    $ins->bind_param(
                        "iiiissiddds",
                        $rid,
                        $uid,
                        $iid,
                        $qty,
                        $rd,
                        $ret,
                        $comboId,
                        $comboDisc,
                        $fee,
                        $saved,
                        $color
                    );
                    $ins->execute();

                    $setRes->bind_param("i", $rid);
                    $setRes->execute();

                    $setItem->bind_param("i", $iid);
                    $setItem->execute();
                }

                flash("success", "Reservation approved and rental started.");


        /* REJECT */

        } else {

            $setRes = $conn->prepare("
                UPDATE reservations
                SET status = 'Rejected'
                WHERE reservation_id = ?
            ");

            foreach ($rows as $res) {
                $rid = (int)$res["reservation_id"];
                $setRes->bind_param("i", $rid);
                $setRes->execute();
            }

            flash("success", "Reservation rejected.");
        }
    }

    $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        flash("error", "Could not update reservation.");
    }

    header("Location: reservations.php");
    exit;
}


require "../partials/admin_header.php";


/* =========================================================
   HELPERS
========================================================= */

function pay_badge_class($s)
{
    return $s === "Paid" ? "success" : ($s === "For Verification" ? "info" : "muted");
}


/* =========================================================
   RESERVATIONS (one row per order)
========================================================= */

$list = $conn->query("
    SELECT
        r.*,
        u.full_name,
        i.item_name,
        i.price_per_day
    FROM reservations r
    JOIN users u ON u.user_id = r.user_id
    JOIN items i ON i.item_id = r.item_id
    ORDER BY r.reservation_id DESC
");

$orders = [];

while ($r = $list->fetch_assoc()) {

    $key = (($r["order_ref"] ?? "") !== "")
        ? $r["order_ref"]
        : "#" . $r["reservation_id"];

    if (!isset($orders[$key])) {

        $days = max(1, (int)round(
            (strtotime($r["return_date"]) - strtotime($r["rental_date"])) / 86400
        ));

        $orders[$key] = [
            "label"       => $key,
            "ref"         => $r["order_ref"] ?? "",
            "id"          => (int)$r["reservation_id"],
            "customer"    => $r["full_name"],
            "contact"     => "",
            "walkin"      => false,
            "rental_date" => $r["rental_date"],
            "return_date" => $r["return_date"],
            "days"        => $days,
            "pay_method"  => $r["payment_method"] ?? "Cash",
            "pay_status"  => $r["payment_status"] ?? "Unpaid",
            "id_photo"    => $r["id_photo"] ?? "",
            "statuses"    => [],
            "lines"       => [],
            "total"       => 0
        ];
    }

    $o =& $orders[$key];

    $o["statuses"][] = $r["status"];

    $qty       = max(1, (int)$r["quantity"]);
    $lineTotal = $qty * (float)$r["price_per_day"] * $o["days"];

    if (!empty($r["combo_id"])) {

        $pct = ($r["combo_discount"] !== null)
            ? (float)$r["combo_discount"]
            : (float)COMBO_DISCOUNT_PERCENT;

        $k = "c" . (int)$r["combo_id"];

        if (!isset($o["lines"][$k])) {
            $o["lines"][$k] = ["combo" => true, "pct" => $pct, "parts" => []];
        }

        $o["lines"][$k]["parts"][] = [
            "name"  => $r["item_name"],
            "qty"   => $qty,
            "color" => $r["item_color"] ?? ""
        ];

        $o["total"] += $lineTotal - round($lineTotal * $pct / 100, 2);

    } else {

        $o["lines"][] = [
            "combo" => false,
            "name"  => $r["item_name"],
            "color" => $r["item_color"] ?? "",
            "qty"   => $qty,
            "parts" => []
        ];

        $o["total"] += $lineTotal;
    }

    unset($o);
}

/* Order status: pending ones are shown first, the rest keep
   the order of the query (newest first) */

$pendingOrders = [];
$otherOrders   = [];

foreach ($orders as $key => $o) {

    $o["status"] = in_array("Pending", $o["statuses"], true)
        ? "Pending"
        : $o["statuses"][0];

    if ($o["status"] === "Pending") {
        $pendingOrders[] = $o;
    } else {
        $otherOrders[] = $o;
    }
}


/* =========================================================
   WALK-IN RENTALS
   They are saved straight into "rentals" (no reservation,
   no approval). Rows of one walk-in are saved one after
   another with the same customer, dates and payment, so rows
   that follow each other with the same details are shown as
   one order. Newest saved is shown first.
========================================================= */

$wres = $conn->query("
    SELECT rt.*, i.item_name, i.price_per_day
    FROM rentals rt
    JOIN items i ON i.item_id = rt.item_id
    WHERE rt.user_id IS NULL
    ORDER BY rt.rental_id ASC
");

$walkins = [];
$prevSig = null;
$curKey  = null;

if ($wres) {

    while ($w = $wres->fetch_assoc()) {

        $sig = implode("|", [
            $w["walk_in_name"] ?? "",
            $w["walk_in_contact"] ?? "",
            $w["rental_date"],
            $w["return_date"],
            $w["payment_method"] ?? "",
            $w["payment_status"] ?? ""
        ]);

        if ($sig !== $prevSig) {

            $curKey  = "W" . (int)$w["rental_id"];
            $prevSig = $sig;

            $wdays = max(1, (int)round(
                (strtotime($w["return_date"]) - strtotime($w["rental_date"])) / 86400
            ));

            $walkins[$curKey] = [
                "label"       => "Walk-in #" . (int)$w["rental_id"],
                "ref"         => "",
                "id"          => (int)$w["rental_id"],
                "customer"    => (($w["walk_in_name"] ?? "") !== "") ? $w["walk_in_name"] : "Walk-in",
                "contact"     => $w["walk_in_contact"] ?? "",
                "walkin"      => true,
                "rental_date" => $w["rental_date"],
                "return_date" => $w["return_date"],
                "days"        => $wdays,
                "pay_method"  => $w["payment_method"] ?? "Cash",
                "pay_status"  => $w["payment_status"] ?? "Unpaid",
                "id_photo"    => "",
                "statuses"    => [],
                "lines"       => [],
                "total"       => 0
            ];
        }

        $o =& $walkins[$curKey];

        $o["statuses"][] = $w["status"];

        $qty = max(1, (int)$w["quantity"]);
        $pct = (!empty($w["combo_id"]) && $w["combo_discount"] !== null)
            ? (float)$w["combo_discount"]
            : null;

        if ($w["rental_fee"] !== null) {

            $fee = (float)$w["rental_fee"];

        } else {

            $g   = $qty * (float)$w["price_per_day"] * $o["days"];
            $fee = $g - ($pct !== null ? round($g * $pct / 100, 2) : 0);
        }

        $o["total"] += $fee;

        if (!empty($w["combo_id"])) {

            $k = "c" . (int)$w["combo_id"];

            if (!isset($o["lines"][$k])) {
                $o["lines"][$k] = [
                    "combo" => true,
                    "pct"   => ($pct !== null ? $pct : (float)COMBO_DISCOUNT_PERCENT),
                    "parts" => []
                ];
            }

            $o["lines"][$k]["parts"][] = [
                "name"  => $w["item_name"],
                "qty"   => $qty,
                "color" => $w["item_color"] ?? ""
            ];

        } else {

            $o["lines"][] = [
                "combo" => false,
                "name"  => $w["item_name"],
                "color" => $w["item_color"] ?? "",
                "qty"   => $qty,
                "parts" => []
            ];
        }

        unset($o);
    }
}

$walkinOrders = [];

foreach ($walkins as $o) {

    $o["status"] = in_array("Ongoing", $o["statuses"], true)
        ? "Ongoing"
        : $o["statuses"][0];

    $walkinOrders[] = $o;
}

/* newest saved first */

usort($walkinOrders, function ($a, $b) {
    return $b["id"] <=> $a["id"];
});


/* =========================================================
   GROUPS SHOWN IN THE TABLE
========================================================= */

$groups = [
    ["title" => "Pending approval", "rows" => $pendingOrders],
    ["title" => "Walk-in rentals",  "rows" => $walkinOrders],
    ["title" => "Reservations",     "rows" => $otherOrders]
];

$countReservations = count($pendingOrders) + count($otherOrders);
$countWalkins      = count($walkinOrders);
$countAll          = $countReservations + $countWalkins;

?>

<style>
.res-id-thumb {
    display: block;
    width: 64px;
    height: 46px;
    margin-top: 6px;
    object-fit: cover;
    border: 1px solid var(--line);
    border-radius: 6px;
    background: #fff;
}
.res-pay small { display: block; margin-bottom: 4px; color: var(--muted); }

.res-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }

.res-tab {
    border: 1px solid #d9e2eb;
    background: #f4f8fc;
    color: #17395f;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.res-tab.active {
    background: #287fc9;
    border-color: #287fc9;
    color: #fff;
}

.res-group td {
    background: #f4f8fc;
    color: #17395f;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .04em;
}
</style>

<section class="panel">

    <div class="panel-head">
        <h2>Reservations</h2>
        <span class="muted-text"><?= count($pendingOrders) ?> pending</span>
    </div>


    <div class="res-tabs">
        <button type="button" class="res-tab active" data-filter="all">All (<?= $countAll ?>)</button>
        <button type="button" class="res-tab" data-filter="reservation">Reservations (<?= $countReservations ?>)</button>
        <button type="button" class="res-tab" data-filter="walkin">Walk-in (<?= $countWalkins ?>)</button>
    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Ref</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Rental Date</th>
                    <th>Return Date</th>
                    <th>Est. Total</th>
                    <th>Payment</th>
                    <th>Status</th>
                </tr>

            </thead>


            <tbody>

                <?php if (!$countAll): ?>

                    <tr>
                        <td colspan="8" style="text-align:center;">No reservations yet.</td>
                    </tr>

                <?php endif; ?>


                <?php foreach ($groups as $g): ?>

                    <?php
                    if (!$g["rows"]) {
                        continue;
                    }

                    $gType = ($g["title"] === "Walk-in rentals") ? "walkin" : "reservation";
                    ?>

                    <tr class="res-group" data-type="<?= $gType ?>">
                        <td colspan="8"><?= e($g["title"]) ?> (<?= count($g["rows"]) ?>)</td>
                    </tr>


                    <?php foreach ($g["rows"] as $o): ?>

                        <?php $idUrl = upload_url($o["id_photo"]); ?>

                        <tr data-type="<?= $o["walkin"] ? "walkin" : "reservation" ?>">

                            <td><?= e($o["label"]) ?></td>

                            <td>

                                <?= e($o["customer"]) ?>

                                <?php if ($o["walkin"]): ?>

                                    <span class="badge info">Walk-in</span>

                                    <?php if ($o["contact"] !== ""): ?>
                                        <br><small class="muted-text"><?= e($o["contact"]) ?></small>
                                    <?php endif; ?>

                                <?php endif; ?>

                                <?php if ($idUrl): ?>
                                    <?php if ($o["ref"] !== ""): ?>
                                        <a href="view_reservation.php?ref=<?= urlencode($o["ref"]) ?>" title="View ID and details">
                                            <img class="res-id-thumb" src="<?= e($idUrl) ?>" alt="ID">
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= e($idUrl) ?>" target="_blank" title="Open ID">
                                            <img class="res-id-thumb" src="<?= e($idUrl) ?>" alt="ID">
                                        </a>
                                    <?php endif; ?>
                                <?php endif; ?>

                            </td>

                            <td>

                                <?php foreach ($o["lines"] as $line): ?>

                                    <?php if ($line["combo"]): ?>

                                        <span class="badge info">
                                            Combo &minus;<?= rtrim(rtrim(number_format($line["pct"], 2), "0"), ".") ?>%
                                        </span>
                                        <br>

                                        <?php foreach ($line["parts"] as $p): ?>
                                            &nbsp;&nbsp;<?= (int)$p["qty"] ?> × <?= e($p["name"]) ?><?= !empty($p["color"]) ? " (" . e($p["color"]) . ")" : "" ?><br>
                                        <?php endforeach; ?>

                                    <?php else: ?>

                                        <?= (int)$line["qty"] ?> × <?= e($line["name"]) ?><?= !empty($line["color"]) ? " (" . e($line["color"]) . ")" : "" ?><br>

                                    <?php endif; ?>

                                <?php endforeach; ?>

                            </td>

                            <td><?= e($o["rental_date"]) ?></td>

                            <td><?= e($o["return_date"]) ?></td>

                            <td>₱<?= number_format($o["total"], 2) ?></td>

                            <td class="res-pay">
                                <small><?= e($o["pay_method"]) ?></small>
                                <span class="badge <?= pay_badge_class($o["pay_status"]) ?>">
                                    <?= e($o["pay_status"]) ?>
                                </span>
                            </td>

                            <td>
                                <?= status_badge($o["status"]) ?>
                                <?php if (!$o["walkin"] && $o["status"] === "Pending"): ?>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="ref" value="<?= e($o["ref"]) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$o["id"] ?>">
                                        <button name="action" value="approve">Approve</button>
                                        <button name="action" value="reject">Reject</button>
                                    </form>
                                <?php endif; ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>


<script>
(function () {

    const tabs = document.querySelectorAll(".res-tab");
    const rows = document.querySelectorAll("tr[data-type]");

    tabs.forEach(function (tab) {

        tab.addEventListener("click", function () {

            const f = tab.dataset.filter;

            tabs.forEach(function (t) {
                t.classList.toggle("active", t === tab);
            });

            rows.forEach(function (r) {
                r.style.display = (f === "all" || r.dataset.type === f) ? "" : "none";
            });
        });
    });

})();
</script>


<?php require "../partials/footer.php"; ?>