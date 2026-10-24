<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Returns";
$active = "returns";

/* Search (optional) */
$search = trim($_GET["q"] ?? "");

$sql = "SELECT r.*, u.full_name, i.item_name
        FROM rentals r
        LEFT JOIN users u ON u.user_id = r.user_id
        LEFT JOIN items i ON i.item_id = r.item_id
        WHERE r.status = 'Returned'";

if ($search !== "") {
    $sql .= " AND (u.full_name LIKE ? OR i.item_name LIKE ? OR r.walk_in_name LIKE ?)";
    $stmt = $conn->prepare($sql . " ORDER BY r.actual_return_date DESC, r.rental_id DESC");
    $like = "%" . $search . "%";
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $returns = $stmt->get_result();
} else {
    $returns = $conn->query($sql . " ORDER BY r.actual_return_date DESC, r.rental_id DESC");
}

require "../partials/admin_header.php";
?>

<section class="panel">

    <div class="panel-head">
        <h2>Return History</h2>
        <span class="muted-text"><?= $returns->num_rows ?> returned</span>
    </div>

    <form method="GET" style="display:flex; gap:10px; margin-bottom:18px;">
        <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search customer or item"
               style="flex:1; max-width:340px; padding:10px 14px; border:1px solid #d5dde6; border-radius:10px;">
        <button class="btn primary" type="submit">Search</button>
        <?php if ($search !== ""): ?>
            <a class="btn" href="returns.php">Clear</a>
        <?php endif; ?>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Due Date</th>
                    <th>Returned On</th>
                    <th>Late</th>
                    <th>Rental Fee</th>
                    <th>Penalty</th>
                    <th>Discount</th>
                    <th>Total Paid</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
            <?php while ($r = $returns->fetch_assoc()): ?>
                <?php
                $lateDays = (int) $r["late_days"];
                $hasFee   = $r["rental_fee"] !== null;
                $saved    = (float) ($r["combo_saved"] ?? 0);
                ?>
                <tr>
                    <td><?= e($r["full_name"] ?? ($r["walk_in_name"] ?: "Walk-in")) ?></td>
                    <td>
                        <?= e($r["item_name"] ?? "") ?>
                        <?php if (!empty($r["combo_id"])): ?>
                            <br><span class="badge info">Combo</span>
                        <?php endif; ?>
                    </td>
                    <td><?= (int) $r["quantity"] ?></td>
                    <td><?= e($r["return_date"]) ?></td>
                    <td><?= e($r["actual_return_date"] ?? "-") ?></td>
                    <td>
                        <?php if ($lateDays > 0): ?>
                            <span style="color:#c0392b; font-weight:700;"><?= $lateDays ?> day(s)</span>
                        <?php else: ?>
                            On time
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($hasFee): ?>
                            ₱<?= number_format((float) $r["rental_fee"], 2) ?>
                            <?php if ($saved > 0): ?>
                                <br><small class="muted-text">Combo saved ₱<?= number_format($saved, 2) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="muted-text">—</span>
                        <?php endif; ?>
                    </td>
                    <td>₱<?= number_format((float) $r["penalty"], 2) ?></td>
                    <td>₱<?= number_format((float) $r["discount"], 2) ?></td>
                    <td>₱<?= number_format((float) ($r["total_paid"] ?? 0), 2) ?></td>
                    <td><?= status_badge($r["status"]) ?></td>
                </tr>
            <?php endwhile; ?>

            <?php if ($returns->num_rows === 0): ?>
                <tr>
                    <td colspan="11" style="text-align:center;">No returned items yet.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

</section>

<?php require "../partials/footer.php"; ?>