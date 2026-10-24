<?php

require_once "../db.php";
require_once "../functions.php";

$page_title = "My Rentals";
$active = "rentals";

require "../partials/user_header.php";


/* IMAGE MAPPING */

$imageMap = [
    "Tent Square" => "tent_square.jpg",
    "Monoblock Chair" => "monoblock_chair.jpg",
    "Utensils" => "utensils.jpg",
    "Half Moon Table" => "half_moon_table.jpg",
    "Backdrop" => "backdrop.jpg",
    "Cleopatra Chair" => "cleopatra_chair.jpg",
    "Long Table (Buffet)" => "long_table_buffet.jpg",
    "Pleated Tablecloth" => "pleated_tablecloth.jpg"
];


$uid = (int)$_SESSION["user_id"];

$stmt = $conn->prepare("
    SELECT
        rt.*,
        i.item_name,
        i.image,
        i.category,
        i.price_per_day
    FROM rentals rt
    JOIN items i
        ON i.item_id = rt.item_id
    WHERE rt.user_id = ?
    ORDER BY rt.rental_id DESC
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$list = $stmt->get_result();

?>

<section class="panel">

    <div class="panel-head">
        <h2>My Rentals</h2>
    </div>

    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Item</th>
                    <th>Pcs</th>
                    <th>Rental Date</th>
                    <th>Return Date</th>
                    <th>Rental Fee</th>
                    <th>Status</th>
                </tr>

            </thead>

            <tbody>

            <?php if($list->num_rows): ?>

                <?php while($r = $list->fetch_assoc()): ?>

                    <?php

                    $image = $imageMap[$r["item_name"]] ?? $r["image"];

                    $qty     = max(1, (int)$r["quantity"]);
                    $isCombo = !empty($r["combo_id"]) && $r["combo_discount"] !== null;
                    $pct     = $isCombo ? (float)$r["combo_discount"] : 0;

                    /* Saved fee, or compute it for old rows */
                    if ($r["rental_fee"] !== null) {

                        $fee   = (float)$r["rental_fee"];
                        $saved = (float)($r["combo_saved"] ?? 0);

                    } else {

                        $days  = max(1, (int)round(
                            (strtotime($r["return_date"]) - strtotime($r["rental_date"])) / 86400
                        ));
                        $gross = $qty * (float)$r["price_per_day"] * $days;
                        $saved = round($gross * $pct / 100, 2);
                        $fee   = $gross - $saved;
                    }

                    ?>

                    <tr>

                        <td class="item-cell">

                            <img
                                class="mini-thumb"
                                src="../images/<?= e($image) ?>"
                                alt="<?= e($r["item_name"]) ?>"
                            >

                            <span>
                                <?= e($r["item_name"]) ?>

                                <small>
                                    <?= e($r["category"]) ?>
                                </small>

                                <?php if (!empty($r["item_color"])): ?>
                                    <small>Color: <?= e($r["item_color"]) ?></small>
                                <?php endif; ?>

                                <?php if ($isCombo): ?>
                                    <span class="badge info">
                                        Combo &minus;<?= rtrim(rtrim(number_format($pct, 2), "0"), ".") ?>%
                                    </span>
                                <?php endif; ?>
                            </span>

                        </td>

                        <td><?= $qty ?></td>

                        <td><?= e($r["rental_date"]) ?></td>

                        <td><?= e($r["return_date"]) ?></td>

                        <td>
                            ₱<?= number_format($fee, 2) ?>
                            <?php if ($saved > 0): ?>
                                <br>
                                <small class="muted-text">Combo saved ₱<?= number_format($saved, 2) ?></small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= status_badge($r["status"]) ?>

                            <?php if ($r["status"] === "Returned"): ?>
                                <br>
                                <small class="muted-text">
                                    <?php if ((float)$r["penalty"] > 0): ?>
                                        Penalty ₱<?= number_format((float)$r["penalty"], 2) ?> &middot;
                                    <?php endif; ?>
                                    Paid ₱<?= number_format((float)($r["total_paid"] ?? 0), 2) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="6" class="empty">
                        No rental records yet.
                    </td>
                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require "../partials/footer.php"; ?>