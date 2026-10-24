<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

if (!defined("PENALTY_DISCOUNT_PERCENT")) {
    define("PENALTY_DISCOUNT_PERCENT", 10);
}

$page_title = "Rentals";
$active = "rentals";

$today = date("Y-m-d");

/*
|--------------------------------------------------------------------------
| Process Return
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require "return_process.php";
    exit;
}

/*
|--------------------------------------------------------------------------
| Get Ongoing Rentals
|--------------------------------------------------------------------------
*/
$ongoing = $conn->query("
    SELECT
        rt.*,
        COALESCE(u.full_name, rt.walk_in_name, 'Walk-in') AS customer_name,
        (rt.user_id IS NULL) AS is_walkin,
        i.item_name,
        i.penalty_per_day,
        i.price_per_day
    FROM rentals rt
    LEFT JOIN users u ON u.user_id = rt.user_id
    JOIN items i ON i.item_id = rt.item_id
    WHERE rt.status = 'Ongoing'
    ORDER BY rt.return_date ASC, rt.rental_id DESC
");

require "../partials/admin_header.php";
?>

<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Ongoing Rentals</h2>
            <p class="muted-text">Manage active rentals and process returns.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>

            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Pcs</th>
                    <th>Rent Date</th>
                    <th>Due Date</th>
                    <th>Rental Fee</th>
                    <th>Status</th>
                    <th>Return Date</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$ongoing || $ongoing->num_rows === 0): ?>

                <tr>
                    <td colspan="9" class="empty">No ongoing rentals.</td>
                </tr>

            <?php else: ?>

                <?php while ($r = $ongoing->fetch_assoc()): ?>

                    <?php
                    $rid = (int)$r["rental_id"];
                    $qty = max(1, (int)$r["quantity"]);

                    $lateDays = 0;

                    if ($today > $r["return_date"]) {
                        $lateDays = (int)(
                            (strtotime($today) - strtotime($r["return_date"])) / 86400
                        );
                    }

                    $penaltyPerDay    = (float)$r["penalty_per_day"];
                    $estimatedPenalty = $lateDays * $penaltyPerDay * $qty;
                    $estimatedDisc    = round($estimatedPenalty * PENALTY_DISCOUNT_PERCENT / 100, 2);
                    $penaltyToPay     = $estimatedPenalty - $estimatedDisc;

                    /* Rental fee: use the saved value, or compute it for old rows */
                    $isCombo = !empty($r["combo_id"]) && $r["combo_discount"] !== null;
                    $pct     = $isCombo ? (float)$r["combo_discount"] : 0;

                    if ($r["rental_fee"] !== null) {

                        $fee   = (float)$r["rental_fee"];
                        $saved = (float)($r["combo_saved"] ?? 0);

                    } else {

                        $plannedDays = max(1, (int)round(
                            (strtotime($r["return_date"]) - strtotime($r["rental_date"])) / 86400
                        ));

                        $gross = $qty * (float)$r["price_per_day"] * $plannedDays;
                        $saved = round($gross * $pct / 100, 2);
                        $fee   = $gross - $saved;
                    }
                    ?>

                    <tr>

                        <td>
                            <?= e($r["customer_name"]) ?>
                            <?php if ($r["is_walkin"]): ?>
                                <span class="badge info">Walk-in</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= e($r["item_name"]) ?>
                            <?php if (!empty($r["item_color"])): ?>
                                <small class="muted-text">(<?= e($r["item_color"]) ?>)</small>
                            <?php endif; ?>
                            <?php if ($isCombo): ?>
                                <br>
                                <span class="badge info">
                                    Combo &minus;<?= rtrim(rtrim(number_format($pct, 2), "0"), ".") ?>%
                                </span>
                            <?php endif; ?>
                        </td>

                        <td><?= $qty ?></td>

                        <td><?= e($r["rental_date"]) ?></td>

                        <td><?= e($r["return_date"]) ?></td>

                        <td>
                            ₱<?= number_format($fee, 2) ?>
                            <?php if ($saved > 0): ?>
                                <br>
                                <small class="muted-text">
                                    Combo saved ₱<?= number_format($saved, 2) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>

                            <?php if ($lateDays > 0): ?>

                                <span class="badge danger">
                                    Overdue <?= $lateDays ?> day(s)
                                </span>

                                <?php if ($estimatedPenalty > 0): ?>
                                    <br>
                                    <small class="muted-text">
                                        Penalty: ₱<?= number_format($estimatedPenalty, 2) ?>
                                    </small>
                                    <br>
                                    <small class="muted-text">
                                        Discount (<?= (int)PENALTY_DISCOUNT_PERCENT ?>%):
                                        &minus;₱<?= number_format($estimatedDisc, 2) ?>
                                    </small>
                                    <br>
                                    <small class="muted-text">
                                        <strong>To pay: ₱<?= number_format($penaltyToPay, 2) ?></strong>
                                    </small>
                                <?php endif; ?>

                            <?php else: ?>

                                <span class="badge info">On time</span>

                            <?php endif; ?>

                        </td>

                        <!-- RETURN DATE (linked to the form in the Actions column) -->
                        <td>
                            <input
                                type="date"
                                name="actual_return_date"
                                form="return-form-<?= $rid ?>"
                                value="<?= e($today) ?>"
                                required
                                style="padding:6px;border:1px solid #d9e2eb;border-radius:7px;font-size:12px;"
                            >
                        </td>

                        <!-- ACTIONS -->
                        <td>

                            <form
                                id="return-form-<?= $rid ?>"
                                method="POST"
                                style="display:flex;flex-direction:column;gap:6px;align-items:flex-start;"
                                onsubmit="return confirm('Mark this rental as returned?')"
                            >

                                <input type="hidden" name="rental_id" value="<?= $rid ?>">

                                <?php if ($estimatedPenalty > 0): ?>
                                    <label style="display:flex;gap:6px;align-items:center;font-size:12px;">
                                        <input type="checkbox" name="penalty_paid" value="1" required>
                                        Penalty collected (₱<?= number_format($penaltyToPay, 2) ?>)
                                    </label>
                                <?php endif; ?>

                                <button class="btn tiny primary" type="submit">
                                    Mark as Returned
                                </button>

                                <a
                                    class="btn tiny"
                                    href="receipt.php?id=<?= $rid ?>"
                                    target="_blank"
                                >Receipt</a>

                            </form>

                        </td>

                    </tr>

                <?php endwhile; ?>

            <?php endif; ?>

            </tbody>

        </table>
    </div>

</section>

<?php require "../partials/footer.php"; ?>