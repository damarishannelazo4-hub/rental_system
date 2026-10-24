<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$ref = trim($_GET["ref"] ?? "");

if ($ref === "") {
    go("reservations.php");
}

$stmt = $conn->prepare(
    "SELECT r.*, u.full_name, u.username, u.contact_number, u.address,
            i.item_name
     FROM reservations r
     LEFT JOIN users u ON u.user_id = r.user_id
     LEFT JOIN items i ON i.item_id = r.item_id
     WHERE r.order_ref = ?
     ORDER BY r.reservation_id ASC"
);
$stmt->bind_param("s", $ref);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$rows) {
    flash("error", "Reservation not found.");
    go("reservations.php");
}

$first = $rows[0];

$days = max(1, (int)round((strtotime($first["return_date"]) - strtotime($first["rental_date"])) / 86400));

/* Only allow files inside the uploads folder */
function upload_url($path)
{
    $path = (string)$path;
    if ($path === "" || strpos($path, "uploads/") !== 0 || strpos($path, "..") !== false) {
        return "";
    }
    return "../" . $path;
}

$idUrl    = upload_url($first["id_photo"] ?? "");
$proofUrl = upload_url($first["payment_proof"] ?? "");

$payStatus = $first["payment_status"] ?? "Unpaid";
$payClass  = $payStatus === "Paid" ? "success" : ($payStatus === "For Verification" ? "info" : "muted");

$page_title = "Reservation Details";
$active = "reservations";

require "../partials/admin_header.php";
?>

<style>
.vr-wrap { display: grid; gap: 20px; max-width: 980px; }
.vr-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.vr-card { background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: var(--shadow); padding: 22px; }
.vr-card h3 { margin: 0 0 14px; font-size: 16px; color: var(--navy); }
.vr-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #edf1f5; font-size: 14px; }
.vr-row:last-child { border-bottom: 0; }
.vr-row span:first-child { color: var(--muted); }
.vr-img { display: block; width: 100%; max-height: 380px; object-fit: contain; border: 1px solid var(--line); border-radius: 10px; background: #f8fafc; padding: 6px; }
.vr-empty { padding: 30px; text-align: center; color: var(--muted); background: #f8fafc; border: 1px dashed var(--line); border-radius: 10px; font-size: 13px; }
.vr-actions { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 14px; }
@media (max-width: 800px) { .vr-grid { grid-template-columns: 1fr; } }
</style>

<div class="vr-wrap">

    <div>
        <a class="btn" href="reservations.php">← Back to Reservations</a>
    </div>

    <!-- SUMMARY + CUSTOMER -->
    <div class="vr-grid">

        <div class="vr-card">
            <h3>Reservation</h3>
            <div class="vr-row"><span>Reference</span><strong><?= e($ref) ?></strong></div>
            <div class="vr-row"><span>Status</span><span><?= status_badge($first["status"]) ?></span></div>
            <div class="vr-row"><span>Rental date</span><span><?= e($first["rental_date"]) ?></span></div>
            <div class="vr-row"><span>Return date</span><span><?= e($first["return_date"]) ?></span></div>
            <div class="vr-row"><span>Duration</span><span><?= $days ?> day<?= $days > 1 ? "s" : "" ?></span></div>
        </div>

        <div class="vr-card">
            <h3>Customer</h3>
            <div class="vr-row"><span>Name</span><strong><?= e($first["full_name"] ?? "Walk-in") ?></strong></div>
            <div class="vr-row"><span>Username</span><span><?= e($first["username"] ?? "-") ?></span></div>
            <div class="vr-row"><span>Contact</span><span><?= e($first["contact_number"] ?? "-") ?></span></div>
            <div class="vr-row"><span>Address</span><span><?= e($first["address"] ?? "-") ?></span></div>
        </div>

    </div>

    <!-- ITEMS -->
    <div class="vr-card">
        <h3>Items</h3>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Item</th><th>Qty</th></tr></thead>
                <tbody>
                <?php foreach ($rows as $it): ?>
                    <tr>
                        <td><?= e($it["item_name"] ?? "") ?></td>
                        <td>× <?= (int)$it["quantity"] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ID PHOTO -->
    <div class="vr-card">
        <h3>Valid ID (submitted by the customer)</h3>

        <?php if ($idUrl): ?>
            <a href="<?= e($idUrl) ?>" target="_blank" title="Open full size">
                <img class="vr-img" src="<?= e($idUrl) ?>" alt="Customer ID">
            </a>
            <p class="muted-text" style="margin:8px 0 0;">Click the image to open it full size.</p>
        <?php else: ?>
            <div class="vr-empty">No ID photo on file.</div>
        <?php endif; ?>
    </div>

    <!-- PAYMENT -->
    <div class="vr-card">
        <h3>Payment</h3>

        <div class="vr-row"><span>Method</span><strong><?= e($first["payment_method"] ?? "Cash") ?></strong></div>
        <div class="vr-row"><span>Status</span><span class="badge <?= $payClass ?>"><?= e($payStatus) ?></span></div>

        <?php if (!empty($first["payment_ref"])): ?>
            <div class="vr-row"><span>Reference no.</span><strong><?= e($first["payment_ref"]) ?></strong></div>
        <?php endif; ?>

        <?php if (($first["payment_method"] ?? "Cash") === "Online"): ?>
            <div style="margin-top:14px;">
                <?php if ($proofUrl): ?>
                    <a href="<?= e($proofUrl) ?>" target="_blank" title="Open full size">
                        <img class="vr-img" src="<?= e($proofUrl) ?>" alt="Payment screenshot">
                    </a>
                <?php else: ?>
                    <div class="vr-empty">No payment screenshot on file.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="mark_payment.php" class="vr-actions">
            <input type="hidden" name="order_ref" value="<?= e($ref) ?>">
            <input type="hidden" name="back" value="view">

            <?php if ($payStatus !== "Paid"): ?>
                <button class="btn primary" name="payment_status" value="Paid" type="submit">Mark as Paid</button>
            <?php endif; ?>

            <?php if ($payStatus === "Paid"): ?>
                <button class="btn" name="payment_status" value="Unpaid" type="submit">Mark as Unpaid</button>
            <?php endif; ?>
        </form>
    </div>

</div>

<?php require "../partials/footer.php"; ?>