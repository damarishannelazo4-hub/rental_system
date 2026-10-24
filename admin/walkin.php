<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Walk-in Rental";
$active = "walkin";

$today    = date("Y-m-d");
$tomorrow = date("Y-m-d", strtotime("+1 day"));

$imageMap = [
    "Utensils" => "utensils.jpg",
    "Pleated Tablecloth" => "pleated_tablecloth.jpg",
    "Cleopatra Chair" => "cleopatra_chair.jpg",
    "Backdrop" => "backdrop.jpg",
    "Monoblock Chair" => "monoblock_chair.jpg",
    "Long Table (Buffet)" => "long_table_buffet.jpg",
    "Half Moon Table" => "half_moon_table.jpg",
    "Tent Square" => "tent_square.jpg"
];

/* Color choices for the items that have colors */
$colorOptions = [
    "Monoblock Chair"    => ["White", "Black", "Brown", "Red", "Blue", "Yellow"],
    "Backdrop"           => ["White", "Black", "Red", "Blue", "Pink", "Yellow", "Brown", "Gray"],
    "Pleated Tablecloth" => ["White", "Black", "Red", "Blue", "Pink"],
    "Tent Square"        => ["White", "Green", "Blue", "Yellow"]
];


/*
|--------------------------------------------------------------------------
| Save the walk-in rental
|   lines[i][item_id], lines[i][color], lines[i][qty], lines[i][combo]
|   combo = 0 (no combo) or the number of the combo (1, 2, 3 ...)
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name       = trim($_POST["walk_in_name"] ?? "");
    $contact    = trim($_POST["walk_in_contact"] ?? "");
    $rentalDate = $_POST["rental_date"] ?? "";
    $returnDate = $_POST["return_date"] ?? "";
    $method     = (($_POST["payment_method"] ?? "Cash") === "GCash") ? "GCash" : "Cash";
    $payStatus  = (($_POST["payment_status"] ?? "Unpaid") === "Paid") ? "Paid" : "Unpaid";
    $linesRaw   = is_array($_POST["lines"] ?? null) ? $_POST["lines"] : [];

    $error = "";

    $validDate = function ($d) {
        $dt = DateTime::createFromFormat("Y-m-d", $d);
        return $dt && $dt->format("Y-m-d") === $d;
    };

    if (!$validDate($rentalDate) || !$validDate($returnDate)) {
        $error = "Invalid date.";
    } elseif ($returnDate <= $rentalDate) {
        $error = "Due date must be after the rent date.";
    }

    $lines = [];

    if ($error === "") {

        foreach ($linesRaw as $L) {

            if (!is_array($L)) {
                continue;
            }

            $id = (int)($L["item_id"] ?? 0);
            $q  = (int)($L["qty"] ?? 0);

            if ($id > 0 && $q > 0) {
                $lines[] = [
                    "item_id" => $id,
                    "color"   => trim((string)($L["color"] ?? "")),
                    "qty"     => $q,
                    "combo"   => max(0, (int)($L["combo"] ?? 0))
                ];
            }
        }

        if (!$lines) {
            $error = "Add at least one item to the rental.";
        }
    }

    /* every combo needs enough different items */

    if ($error === "") {

        $groups = [];

        foreach ($lines as $L) {
            if ($L["combo"] > 0) {
                $groups[$L["combo"]][$L["item_id"]] = true;
            }
        }

        foreach ($groups as $g => $ids) {
            if (count($ids) < COMBO_MIN_ITEMS) {
                $error = "A combo needs at least " . COMBO_MIN_ITEMS . " different items.";
                break;
            }
        }
    }

    /* stock according to the same function the user side uses */

    $stockMap = [];

    if ($error === "") {
        $res = get_items_with_stock($conn);

        while ($row = $res->fetch_assoc()) {
            $stockMap[(int)$row["item_id"]] = (int)$row["available_qty"];
        }
    }

    if ($error === "") {

        $conn->begin_transaction();

        try {

            $days = (int)((strtotime($returnDate) - strtotime($rentalDate)) / 86400);

            /* total pcs per item (all colors and combos together) */

            $need = [];

            foreach ($lines as $L) {
                $need[$L["item_id"]] = ($need[$L["item_id"]] ?? 0) + $L["qty"];
            }

            ksort($need);

            $itemRows = [];

            foreach ($need as $itemId => $q) {

                $st = $conn->prepare("
                    SELECT item_name, total_qty, max_days, status, price_per_day
                    FROM items
                    WHERE item_id = ?
                    FOR UPDATE
                ");
                $st->bind_param("i", $itemId);
                $st->execute();
                $item = $st->get_result()->fetch_assoc();
                $st->close();

                if (!$item) {
                    throw new Exception("Item not found.");
                }

                if ($item["status"] === "Maintenance") {
                    throw new Exception($item["item_name"] . " is under maintenance.");
                }

                if ((int)$item["max_days"] > 0 && $days > (int)$item["max_days"]) {
                    throw new Exception(
                        $item["item_name"] . " can only be rented up to " . (int)$item["max_days"] . " day(s)."
                    );
                }

                $st = $conn->prepare("
                    SELECT COALESCE(SUM(quantity), 0) AS used
                    FROM rentals
                    WHERE item_id = ? AND status = 'Ongoing'
                ");
                $st->bind_param("i", $itemId);
                $st->execute();
                $used = (int)$st->get_result()->fetch_assoc()["used"];
                $st->close();

                $available = (int)$item["total_qty"] - $used;

                if (isset($stockMap[$itemId])) {
                    $available = min($available, $stockMap[$itemId]);
                }

                if ($q > $available) {
                    throw new Exception(
                        $item["item_name"] . ": only " . max(0, $available) . " pc(s) available."
                    );
                }

                $itemRows[$itemId] = $item;
            }

            /* colors */

            foreach ($lines as $L) {

                $it   = $itemRows[$L["item_id"]];
                $opts = $colorOptions[trim($it["item_name"])] ?? [];

                if ($opts && !in_array($L["color"], $opts, true)) {
                    throw new Exception("Please select a color for " . trim($it["item_name"]) . ".");
                }
            }

            /* each combo gets its own number, unique across all rentals */

            $comboMap = [];
            $next     = null;

            foreach ($lines as $L) {

                $g = $L["combo"];

                if ($g > 0 && !isset($comboMap[$g])) {

                    if ($next === null) {
                        $m = $conn->query("SELECT COALESCE(MAX(combo_id), 0) + 1 AS n FROM rentals FOR UPDATE");
                        $next = (int)$m->fetch_assoc()["n"];
                    }

                    $comboMap[$g] = $next++;
                }
            }

            /* save every line as one rental row */

            $nm = ($name !== "") ? $name : null;
            $ct = ($contact !== "") ? $contact : null;

            $bItem  = 0;
            $bColor = null;
            $bQty   = 0;
            $bCombo = null;
            $bDisc  = null;
            $bFee   = 0.0;
            $bSaved = 0.0;

            $ins = $conn->prepare("
                INSERT INTO rentals
                    (reservation_id, user_id, item_id, item_color, walk_in_name, walk_in_contact,
                     rental_date, return_date, quantity, status, payment_method, payment_status,
                     combo_id, combo_discount, rental_fee, combo_saved)
                VALUES
                    (NULL, NULL, ?, ?, ?, ?, ?, ?, ?, 'Ongoing', ?, ?, ?, ?, ?, ?)
            ");

            if (!$ins) {
                throw new Exception("Unable to save rental.");
            }

            $ins->bind_param(
                "isssssissiddd",
                $bItem, $bColor, $nm, $ct, $rentalDate, $returnDate,
                $bQty, $method, $payStatus, $bCombo, $bDisc, $bFee, $bSaved
            );

            foreach ($lines as $L) {

                $it      = $itemRows[$L["item_id"]];
                $opts    = $colorOptions[trim($it["item_name"])] ?? [];
                $inCombo = $L["combo"] > 0;

                $bItem  = (int)$L["item_id"];
                $bColor = $opts ? $L["color"] : null;
                $bQty   = (int)$L["qty"];
                $bCombo = $inCombo ? $comboMap[$L["combo"]] : null;
                $bDisc  = $inCombo ? (float)COMBO_DISCOUNT_PERCENT : null;

                $gross  = $bQty * (float)$it["price_per_day"] * $days;
                $bSaved = $inCombo ? round($gross * $bDisc / 100, 2) : 0.0;
                $bFee   = round($gross - $bSaved, 2);

                if (!$ins->execute()) {
                    throw new Exception("Failed to save rental.");
                }
            }

            $ins->close();

            $conn->commit();

        } catch (Throwable $e) {

            try {
                $conn->rollback();
            } catch (Throwable $ignored) {
            }

            $error = $e->getMessage();
        }
    }

    if ($error !== "") {
        flash("error", $error);
        header("Location: walkin.php");
    } else {
        flash("success", "Walk-in rental added.");
        header("Location: rentals.php");
    }

    exit;
}


/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/
$result = get_items_with_stock($conn);
$all = [];

while ($row = $result->fetch_assoc()) {
    $all[$row["item_id"]] = $row;
}

/* data for the page script */

$jsItems = [];

foreach ($all as $id => $item) {

    $nm = trim($item["item_name"]);

    $jsItems[(int)$id] = [
        "name"   => $nm,
        "price"  => (float)$item["price_per_day"],
        "avail"  => max(0, (int)$item["available_qty"]),
        "colors" => $colorOptions[$nm] ?? []
    ];
}

require "../partials/admin_header.php";
?>

<style>
/* WALK-IN: items on the left, "Your Rental" always visible on the right */

.wk-wrap {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 350px;
    gap: 12px;
    height: calc(100vh - 150px);
    min-height: 480px;
}

@media (max-width: 1000px) {
    .wk-wrap {
        grid-template-columns: 1fr;
        height: auto;
    }
}

.wk-left,
.wk-right {
    background: #fff;
    border: 1px solid #e1e7ee;
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    min-height: 0;
    box-sizing: border-box;
}

.wk-tabs {
    display: flex;
    gap: 6px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.wk-tab {
    border: 1px solid #d9e2eb;
    background: #f4f8fc;
    color: #17395f;
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.wk-tab.active {
    background: #287fc9;
    border-color: #287fc9;
    color: #fff;
}

.wk-body {
    flex: 1;
    min-height: 0;
    overflow: auto;
}

/* ITEM CARDS */

.wk-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}

@media (max-width: 1250px) { .wk-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 760px)  { .wk-grid { grid-template-columns: repeat(2, 1fr); } }

.wk-item {
    border: 1px solid #d9e2eb;
    border-radius: 10px;
    padding: 6px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: #fff;
}

.wk-item.out { opacity: .5; }

.wk-img {
    width: 100%;
    height: 62px;
    object-fit: contain;
    display: block;
}

.wk-name  { font-size: 13px; font-weight: 700; color: #17395f; }
.wk-price { font-size: 12px; font-weight: 700; color: #17395f; }

.wk-avail {
    font-size: 10px;
    font-weight: 700;
    color: #1b7a4a;
    background: #e6f6ee;
    border-radius: 20px;
    padding: 1px 8px;
    align-self: flex-start;
}

.wk-avail.out { color: #b42318; background: #fdecea; }

.wk-item select,
.wk-item input[type=number] {
    width: 100%;
    padding: 4px 6px;
    font-size: 12px;
    border: 1px solid #d9e2eb;
    border-radius: 6px;
    box-sizing: border-box;
}

.wk-row {
    display: flex;
    gap: 5px;
    margin-top: auto;
    padding-top: 4px;
}

.wk-row input { width: 56px !important; flex: 0 0 56px; }

.wk-row .btn {
    flex: 1;
    padding: 4px 6px;
    font-size: 11px;
}

/* COMBO TABLE */

.wk-ctable { width: 100%; border-collapse: collapse; }

.wk-ctable th,
.wk-ctable td {
    padding: 3px 6px;
    font-size: 12px;
    text-align: left;
    border-bottom: 1px solid #eef2f6;
}

.wk-ctable th {
    font-size: 11px;
    color: #718096;
    text-transform: uppercase;
}

.wk-ctable img {
    width: 24px;
    height: 24px;
    object-fit: contain;
    vertical-align: middle;
    margin-right: 6px;
}

.wk-ctable select,
.wk-ctable input[type=number] {
    padding: 3px 5px;
    font-size: 12px;
    border: 1px solid #d9e2eb;
    border-radius: 6px;
}

.wk-ctable select { width: 100px; }
.wk-ctable input[type=number] { width: 56px; }

.wk-cbox {
    margin-top: 8px;
    max-width: 360px;
    padding: 8px 12px;
    border: 1px solid #cfe4fa;
    border-radius: 10px;
    background: #f4f9ff;
    font-size: 12px;
}

.wk-cbox .r { display: flex; justify-content: space-between; padding: 2px 0; }
.wk-cbox .d { color: #1b7a4a; }
.wk-cbox .n { border-top: 1px solid #cfe4fa; margin-top: 4px; padding-top: 4px; font-weight: 700; color: #17395f; }
.wk-cbox .h { margin-top: 4px; font-size: 11px; font-weight: 700; color: #b42318; }

/* YOUR RENTAL */

.wk-right form {
    display: flex;
    flex-direction: column;
    gap: 6px;
    height: 100%;
    min-height: 0;
}

.wk-right h3 {
    margin: 0;
    font-size: 16px;
    color: #17395f;
}

.wk-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.wk-field {
    padding: 6px 8px;
    border: 1px solid #d9e2eb;
    border-radius: 7px;
    font-size: 12px;
    width: 100%;
    box-sizing: border-box;
}

.wk-right label {
    font-size: 11px;
    font-weight: 700;
    color: #4a5568;
    display: block;
}

.wk-right label .wk-field { margin-top: 2px; font-weight: 400; }

.wk-lines {
    flex: 1;
    min-height: 70px;
    overflow: auto;
    border: 1px solid #eef2f6;
    border-radius: 8px;
    padding: 4px 6px;
    background: #fbfcfe;
}

.wk-empty { color: #8995a3; font-size: 12px; padding: 8px 2px; }

.wk-line {
    display: grid;
    grid-template-columns: 1fr 52px 74px 22px;
    gap: 4px;
    align-items: center;
    padding: 3px 0;
    border-bottom: 1px solid #eef2f6;
    font-size: 12px;
}

.wk-line small { color: #718096; }

.wk-line input {
    width: 100%;
    padding: 2px 4px;
    font-size: 12px;
    border: 1px solid #d9e2eb;
    border-radius: 5px;
    box-sizing: border-box;
}

.wk-line-sub { text-align: right; }

.wk-x {
    border: none;
    background: #fdecea;
    color: #b42318;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 700;
    line-height: 1;
    padding: 3px 0;
}

.wk-grp {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 6px;
    font-size: 12px;
    font-weight: 700;
    color: #17395f;
}

.wk-grp .bad { color: #b42318; font-size: 11px; }

.wk-gx {
    border: none;
    background: none;
    color: #b42318;
    font-size: 11px;
    cursor: pointer;
    text-decoration: underline;
}

.wk-sum {
    border: 1px solid #cfe4fa;
    border-radius: 10px;
    background: #f4f9ff;
    padding: 6px 10px;
    font-size: 12px;
}

.wk-sum .r { display: flex; justify-content: space-between; padding: 1px 0; }
.wk-sum .d { color: #1b7a4a; }
.wk-sum .t { border-top: 1px solid #cfe4fa; margin-top: 3px; padding-top: 3px; font-weight: 700; color: #17395f; font-size: 14px; }
</style>


<div class="wk-wrap">

    <!-- LEFT: ITEMS / BUILD A COMBO -->

    <section class="wk-left">

        <div class="wk-tabs">
            <button type="button" class="wk-tab active" data-pane="items">Items</button>
            <button type="button" class="wk-tab" data-pane="combo">
                Build a Combo (<?= COMBO_MIN_ITEMS ?>+ items, <?= COMBO_DISCOUNT_PERCENT ?>% off)
            </button>
        </div>

        <!-- ITEMS -->

        <div class="wk-body" id="pane-items">
            <div class="wk-grid">

                <?php foreach ($all as $item): ?>

                    <?php
                    $nm    = trim($item["item_name"]);
                    $img   = $imageMap[$nm] ?? $item["image"];
                    $avail = max(0, (int)$item["available_qty"]);
                    $opts  = $colorOptions[$nm] ?? [];
                    ?>

                    <div class="wk-item <?= $avail <= 0 ? 'out' : '' ?>" data-id="<?= (int)$item["item_id"] ?>">

                        <img class="wk-img" src="../images/<?= e($img) ?>" alt="<?= e($nm) ?>">

                        <div class="wk-name"><?= e($nm) ?></div>
                        <div class="wk-price">₱<?= number_format((float)$item["price_per_day"], 2) ?>/day</div>

                        <?php if ($avail > 0): ?>

                            <div class="wk-avail">Available: <?= $avail ?> pcs</div>

                            <?php if ($opts): ?>
                                <select class="wk-color">
                                    <option value="">Select color</option>
                                    <?php foreach ($opts as $c): ?>
                                        <option value="<?= e($c) ?>"><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <div class="wk-row">
                                <input type="number" class="wk-pcs" min="0" max="<?= $avail ?>" value="0">
                                <button type="button" class="btn tiny primary wk-add">Add to rental</button>
                            </div>

                        <?php else: ?>

                            <div class="wk-avail out">Out of stock</div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>
        </div>

        <!-- BUILD A COMBO -->

        <div class="wk-body" id="pane-combo" style="display:none;">

            <table class="wk-ctable">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price/day</th>
                        <th>Avail.</th>
                        <th>Color</th>
                        <th>Pcs</th>
                        <th>Subtotal/day</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($all as $item): ?>
                    <?php
                    $nm    = trim($item["item_name"]);
                    $img   = $imageMap[$nm] ?? $item["image"];
                    $avail = max(0, (int)$item["available_qty"]);
                    $opts  = $colorOptions[$nm] ?? [];
                    ?>
                    <tr>
                        <td><img src="../images/<?= e($img) ?>" alt=""><?= e($nm) ?></td>

                        <td>₱<?= number_format((float)$item["price_per_day"], 2) ?></td>

                        <td><?= $avail > 0 ? $avail . " pcs" : "Out" ?></td>

                        <td>
                            <?php if ($opts && $avail > 0): ?>
                                <select class="wk-ccolor" data-id="<?= (int)$item["item_id"] ?>">
                                    <option value="">Select color</option>
                                    <?php foreach ($opts as $c): ?>
                                        <option value="<?= e($c) ?>"><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($avail > 0): ?>
                                <input type="number" class="wk-cpcs" data-id="<?= (int)$item["item_id"] ?>" min="0" max="<?= $avail ?>" value="0">
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>

                        <td id="cb-sub-<?= (int)$item["item_id"] ?>">—</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="wk-cbox">
                <div class="r"><span>Per day (combo items)</span><span id="cb-gross">₱0.00</span></div>
                <div class="r d"><span>Combo discount (<?= COMBO_DISCOUNT_PERCENT ?>%)</span><span id="cb-disc">-₱0.00</span></div>
                <div class="r n"><span>Combo per day</span><span id="cb-net">₱0.00</span></div>
                <div class="h" id="cb-hint">Enter pcs for at least <?= COMBO_MIN_ITEMS ?> different items.</div>
            </div>

            <p style="margin:8px 0 0;">
                <button type="button" class="btn primary" id="cb-add" disabled>Add combo to rental</button>
            </p>

        </div>

    </section>


    <!-- RIGHT: YOUR RENTAL -->

    <aside class="wk-right">

        <form method="POST" id="walkin-form">

            <h3>Your Rental</h3>

            <div class="wk-two">
                <input type="text" name="walk_in_name" class="wk-field" placeholder="Customer name">
                <input type="text" name="walk_in_contact" class="wk-field" placeholder="Contact no.">
            </div>

            <div class="wk-two">
                <label>Rent date
                    <input type="date" name="rental_date" id="rental_date" class="wk-field" required>
                </label>
                <label>Due date
                    <input type="date" name="return_date" id="return_date" class="wk-field" required>
                </label>
            </div>

            <div class="wk-two">
                <label>Payment
                    <select name="payment_method" class="wk-field">
                        <option value="Cash">Cash</option>
                        <option value="GCash">GCash</option>
                    </select>
                </label>
                <label>Status
                    <select name="payment_status" class="wk-field">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Paid">Paid</option>
                    </select>
                </label>
            </div>

            <div class="wk-lines" id="wk-lines"></div>

            <div class="wk-sum">
                <div class="r"><span>Per day (all items)</span><span id="sum-perday">₱0.00</span></div>
                <div class="r"><span>Days</span><span id="sum-days">1</span></div>
                <div class="r d" id="sum-disc-row" style="display:none;">
                    <span>Combo discount (<?= COMBO_DISCOUNT_PERCENT ?>%)</span>
                    <span id="sum-disc">-₱0.00</span>
                </div>
                <div class="r t"><span>Total</span><span id="sum-total">₱0.00</span></div>
            </div>

            <div id="wk-hidden"></div>

            <button type="submit" class="btn primary full">Save Walk-in Rental</button>

        </form>

    </aside>

</div>


<script>
(function () {

    const ITEMS   = <?= json_encode($jsItems, JSON_UNESCAPED_UNICODE) ?>;
    const PERCENT = <?= (int)COMBO_DISCOUNT_PERCENT ?>;
    const MIN     = <?= (int)COMBO_MIN_ITEMS ?>;

    /* cart: [{id, color, qty, group}]   group 0 = no combo */
    let cart    = [];
    let groupNo = 0;

    function $(id) { return document.getElementById(id); }

    function peso(n) {
        return "₱" + n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c];
        });
    }

    function getDays() {
        const rd = $("rental_date").value;
        const dd = $("return_date").value;

        if (!rd || !dd) {
            return 1;
        }

        return Math.max(1, Math.round((new Date(dd) - new Date(rd)) / 86400000));
    }

    /* pcs of one item already in the rental (all colors and combos) */

    function usedQty(id, skipIndex) {
        let n = 0;

        cart.forEach(function (l, i) {
            if (l.id === id && i !== skipIndex) {
                n += l.qty;
            }
        });

        return n;
    }


    /* =========================
       TABS
    ========================== */

    document.querySelectorAll(".wk-tab").forEach(function (tab) {
        tab.addEventListener("click", function () {

            document.querySelectorAll(".wk-tab").forEach(function (t) {
                t.classList.toggle("active", t === tab);
            });

            $("pane-items").style.display = tab.dataset.pane === "items" ? "" : "none";
            $("pane-combo").style.display = tab.dataset.pane === "combo" ? "" : "none";
        });
    });


    /* =========================
       ADD AN ITEM
    ========================== */

    $("pane-items").addEventListener("click", function (event) {

        const btn = event.target.closest(".wk-add");

        if (!btn) {
            return;
        }

        const card = btn.closest(".wk-item");
        const id   = parseInt(card.dataset.id, 10);
        const it   = ITEMS[id];
        const sel  = card.querySelector(".wk-color");
        const pcs  = card.querySelector(".wk-pcs");

        const color = sel ? sel.value : "";
        let   qty   = parseInt(pcs.value, 10) || 0;

        if (qty < 1) {
            alert("Enter the pcs first.");
            return;
        }

        if (it.colors.length && color === "") {
            alert("Please select a color for " + it.name + ".");
            return;
        }

        const free = it.avail - usedQty(id, -1);

        if (free <= 0) {
            alert("No more stock of " + it.name + ".");
            return;
        }

        if (qty > free) {
            alert("Only " + free + " pc(s) of " + it.name + " left. Quantity adjusted.");
            qty = free;
        }

        const found = cart.find(function (l) {
            return l.id === id && l.color === color && l.group === 0;
        });

        if (found) {
            found.qty += qty;
        } else {
            cart.push({ id: id, color: color, qty: qty, group: 0 });
        }

        pcs.value = 0;

        if (sel) {
            sel.value = "";
        }

        render();
    });


    /* =========================
       BUILD A COMBO
    ========================== */

    function comboEntries() {

        const list = [];

        document.querySelectorAll(".wk-cpcs").forEach(function (inp) {

            const qty = parseInt(inp.value || 0, 10) || 0;

            if (qty > 0) {
                const id  = parseInt(inp.dataset.id, 10);
                const sel = document.querySelector('.wk-ccolor[data-id="' + id + '"]');

                list.push({ id: id, qty: qty, color: sel ? sel.value : "" });
            }
        });

        return list;
    }

    function updateComboBox() {

        let gross = 0;
        let count = 0;
        let noColor = 0;

        document.querySelectorAll(".wk-cpcs").forEach(function (inp) {

            const id   = inp.dataset.id;
            const qty  = parseInt(inp.value || 0, 10) || 0;
            const cell = $("cb-sub-" + id);

            if (qty > 0) {

                gross += qty * ITEMS[id].price;
                count++;
                cell.textContent = peso(qty * ITEMS[id].price);

                const sel = document.querySelector('.wk-ccolor[data-id="' + id + '"]');

                if (sel && sel.value === "") {
                    noColor++;
                }

            } else {
                cell.textContent = "—";
            }
        });

        const enough = count >= MIN;
        const ok     = enough && noColor === 0;
        const disc   = enough ? Math.round(gross * PERCENT) / 100 : 0;

        $("cb-gross").textContent = peso(gross);
        $("cb-disc").textContent  = "-" + peso(disc);
        $("cb-net").textContent   = peso(gross - disc);

        $("cb-hint").textContent = !enough
            ? "Enter pcs for at least " + MIN + " different items."
            : "Select a color for each item that has pcs.";

        $("cb-hint").style.display = ok ? "none" : "block";
        $("cb-add").disabled = !ok;
    }

    document.querySelectorAll(".wk-cpcs, .wk-ccolor").forEach(function (el) {
        el.addEventListener("input", updateComboBox);
        el.addEventListener("change", updateComboBox);
    });

    $("cb-add").addEventListener("click", function () {

        const entries = comboEntries();

        if (entries.length < MIN) {
            return;
        }

        /* check everything first, then add */

        for (let i = 0; i < entries.length; i++) {

            const e = entries[i];
            const it = ITEMS[e.id];

            if (it.colors.length && e.color === "") {
                alert("Please select a color for " + it.name + ".");
                return;
            }

            if (e.qty > it.avail - usedQty(e.id, -1)) {
                alert("Not enough stock for " + it.name + ".");
                return;
            }
        }

        groupNo++;

        entries.forEach(function (e) {
            cart.push({ id: e.id, color: e.color, qty: e.qty, group: groupNo });
        });

        /* clear the combo table */

        document.querySelectorAll(".wk-cpcs").forEach(function (inp) { inp.value = 0; });
        document.querySelectorAll(".wk-ccolor").forEach(function (sel) { sel.value = ""; });

        updateComboBox();
        render();
    });


    /* =========================
       YOUR RENTAL (list + totals)
    ========================== */

    function lineRow(l, i) {

        const it = ITEMS[l.id];

        return '<div class="wk-line">' +
            '<div>' + esc(it.name) + (l.color ? ' <small>(' + esc(l.color) + ')</small>' : '') + '</div>' +
            '<input type="number" class="wk-line-qty" min="1" value="' + l.qty + '" data-i="' + i + '">' +
            '<div class="wk-line-sub">' + peso(l.qty * it.price) + '</div>' +
            '<button type="button" class="wk-x" data-i="' + i + '" title="Remove">×</button>' +
            '</div>';
    }

    function render() {

        const days = getDays();

        /* different items inside each combo */

        const groupItems = {};

        cart.forEach(function (l) {
            if (l.group > 0) {
                (groupItems[l.group] = groupItems[l.group] || {})[l.id] = true;
            }
        });

        function groupOk(g) {
            return groupItems[g] && Object.keys(groupItems[g]).length >= MIN;
        }

        let perDay   = 0;
        let discount = 0;
        let html     = "";

        cart.forEach(function (l) {
            perDay += l.qty * ITEMS[l.id].price;

            if (l.group > 0 && groupOk(l.group)) {
                discount += Math.round(l.qty * ITEMS[l.id].price * days * PERCENT) / 100;
            }
        });

        /* ordinary items */

        cart.forEach(function (l, i) {
            if (l.group === 0) {
                html += lineRow(l, i);
            }
        });

        /* combos */

        Object.keys(groupItems).sort(function (a, b) { return a - b; }).forEach(function (g) {

            html += '<div class="wk-grp"><span>Combo ' + g +
                ' <span class="badge info">&minus;' + PERCENT + '%</span>' +
                (groupOk(g) ? '' : ' <span class="bad">needs ' + MIN + '+ different items</span>') +
                '</span><button type="button" class="wk-gx" data-g="' + g + '">remove combo</button></div>';

            cart.forEach(function (l, i) {
                if (String(l.group) === g) {
                    html += lineRow(l, i);
                }
            });
        });

        $("wk-lines").innerHTML = html !== ""
            ? html
            : '<div class="wk-empty">No items yet. Press "Add to rental" on the items.</div>';

        discount = Math.round(discount * 100) / 100;

        $("sum-perday").textContent = peso(perDay);
        $("sum-days").textContent   = days;
        $("sum-disc").textContent   = "-" + peso(discount);
        $("sum-disc-row").style.display = discount > 0 ? "flex" : "none";
        $("sum-total").textContent  = peso(perDay * days - discount);
    }

    $("wk-lines").addEventListener("click", function (event) {

        const x = event.target.closest(".wk-x");
        const g = event.target.closest(".wk-gx");

        if (x) {
            cart.splice(parseInt(x.dataset.i, 10), 1);
            render();
        }

        if (g) {
            const n = parseInt(g.dataset.g, 10);
            cart = cart.filter(function (l) { return l.group !== n; });
            render();
        }
    });

    $("wk-lines").addEventListener("change", function (event) {

        const inp = event.target.closest(".wk-line-qty");

        if (!inp) {
            return;
        }

        const i  = parseInt(inp.dataset.i, 10);
        const l  = cart[i];
        const it = ITEMS[l.id];

        let qty = parseInt(inp.value, 10) || 1;

        if (qty < 1) {
            qty = 1;
        }

        const free = it.avail - usedQty(l.id, i);

        if (qty > free) {
            alert("Only " + free + " pc(s) of " + it.name + " available.");
            qty = Math.max(1, free);
        }

        l.qty = qty;
        render();
    });

    $("rental_date").addEventListener("change", render);
    $("return_date").addEventListener("change", render);


    /* =========================
       SAVE
    ========================== */

    $("walkin-form").addEventListener("submit", function (event) {

        if (!cart.length) {
            event.preventDefault();
            alert("Add at least one item to the rental.");
            return;
        }

        if (!$("rental_date").value || !$("return_date").value || $("return_date").value <= $("rental_date").value) {
            event.preventDefault();
            alert("The due date must be after the rent date.");
            return;
        }

        /* every combo needs enough different items */

        const groupItems = {};

        cart.forEach(function (l) {
            if (l.group > 0) {
                (groupItems[l.group] = groupItems[l.group] || {})[l.id] = true;
            }
        });

        for (const g in groupItems) {
            if (Object.keys(groupItems[g]).length < MIN) {
                event.preventDefault();
                alert("Combo " + g + " needs at least " + MIN + " different items.");
                return;
            }
        }

        /* send the list with the form */

        const box = $("wk-hidden");
        box.innerHTML = "";

        function addHidden(name, value) {
            const h = document.createElement("input");
            h.type  = "hidden";
            h.name  = name;
            h.value = value;
            box.appendChild(h);
        }

        cart.forEach(function (l, i) {
            addHidden("lines[" + i + "][item_id]", l.id);
            addHidden("lines[" + i + "][color]", l.color);
            addHidden("lines[" + i + "][qty]", l.qty);
            addHidden("lines[" + i + "][combo]", l.group);
        });
    });

    updateComboBox();
    render();

})();
</script>

<?php require "../partials/footer.php"; ?>