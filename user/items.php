<?php
require_once "../db.php";
require_once "../functions.php";

$page_title = "Browse Items";
$active = "items";

require "../partials/user_header.php";

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

$result = get_items_with_stock($conn);
$all = [];
while ($row = $result->fetch_assoc()) {
    $all[$row["item_id"]] = $row;
}

$draft = draft_get();
$need  = draft_need();
$msg   = flash('draft');
?>

<?php if ($msg): ?>
    <div class="alert"><?= e($msg) ?></div>
<?php endif; ?>

<style>
/* BROWSE ITEMS: items on the left, "Your Reservation" always visible on the right */

.wk-wrap {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 370px;
    gap: 12px;
    align-items: start;
}

@media (max-width: 1000px) {
    .wk-wrap {
        grid-template-columns: 1fr;
        height: auto;
    }
    .wk-left .wk-body { max-height: none; }
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
    min-width: 0;
}

/* ITEM CARDS */

.wk-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
    gap: 8px;
}

.wk-item {
    border: 1px solid #d9e2eb;
    border-radius: 10px;
    padding: 6px 8px 8px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    background: #fff;
    margin: 0;
}

.wk-item.out { opacity: .55; }

.wk-img {
    width: 100%;
    height: 70px;
    object-fit: contain;
    display: block;
}

.wk-name  { font-size: 13px; font-weight: 700; color: #17395f; }
.wk-cat   { font-size: 11px; color: #718096; }
.wk-price { font-size: 12px; font-weight: 700; color: #17395f; }
.wk-meta  { font-size: 11px; color: #718096; }

.wk-incl {
    font-size: 11px;
    color: #718096;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

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

/* BUILD A COMBO */

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

.wk-ctable select { width: 110px; }
.wk-ctable input[type=number] { width: 60px; }

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

/* YOUR RESERVATION */
#your-reservation {
    padding: 18px;
}
#your-reservation .wk-rhead {
    padding-bottom: 14px;
    margin-bottom: 0;
    border-bottom: 1px solid var(--line);
}
#your-reservation .wk-rform { gap: 16px; }
#your-reservation .wk-rscroll { gap: 16px; padding-right: 0; }
#your-reservation .wk-two { gap: 10px; }
#your-reservation .wk-rlabel { font-size: 12px; min-width: 0; }
#your-reservation .wk-field {
    min-width: 0;
    min-height: 44px;
    margin-top: 6px;
    padding: 10px;
    font-size: 14px;
    background: var(--white);
    color: var(--text);
}
#your-reservation .wk-field[readonly] { background: var(--bg); color: var(--muted); }
#your-reservation .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 44px;
}
#your-reservation .wk-sec {
    padding: 14px;
    border: 1px dashed #b8cadc;
    border-radius: 10px;
    background: var(--bg);
}
#your-reservation .wk-sec h4 { font-size: 14px; margin-bottom: 8px; }
#your-reservation .wk-sec .muted-text { line-height: 1.5; font-size: 12px !important; margin-bottom: 12px !important; }
#your-reservation .wk-lines { padding: 10px; }
#your-reservation .wk-sum { padding: 14px; margin-top: 0; }
#your-reservation .wk-sum .r { padding: 4px 0; font-size: 13px; }
#your-reservation .wk-sum .t { padding-top: 10px; margin-top: 8px; font-size: 16px; }
#your-reservation .full { margin-top: 0; }
#your-reservation :focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
@media(max-width:1000px) {
    #your-reservation .wk-rform,
    #your-reservation .wk-rscroll { flex: initial; }
}
@media(max-width:700px) {
    #your-reservation { padding: 16px; }
    #your-reservation .wk-field { font-size: 16px; }
}
@media(max-width:360px) {
    #your-reservation .wk-two { grid-template-columns: minmax(0,1fr); }
}

.wk-rform {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
}

.wk-rhead {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.wk-rhead h3 {
    margin: 0;
    font-size: 16px;
    color: #17395f;
}

.wk-rscroll {
    min-height: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-right: 4px;
}

.wk-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 6px;
}

.wk-rlabel {
    font-size: 11px;
    font-weight: 700;
    color: #4a5568;
    display: block;
}

.wk-field {
    margin-top: 2px;
    padding: 6px 8px;
    border: 1px solid #d9e2eb;
    border-radius: 7px;
    font-size: 12px;
    width: 100%;
    box-sizing: border-box;
}

.wk-sec h4 {
    margin: 0 0 4px;
    font-size: 13px;
    color: #17395f;
}

.wk-lines {
    border: 1px solid #eef2f6;
    border-radius: 8px;
    padding: 4px 8px;
    background: #fbfcfe;
}

.wk-empty { color: #8995a3; font-size: 12px; padding: 8px 2px; }

.wk-line {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 56px 70px 22px;
    gap: 4px;
    align-items: start;
    padding: 5px 0;
    border-bottom: 1px solid #eef2f6;
    font-size: 12px;
}

.wk-line:last-child { border-bottom: none; }

.wk-lname small { display: block; color: #718096; }

.wk-line select {
    width: 100%;
    margin-top: 3px;
    padding: 3px 5px;
    font-size: 12px;
    border: 1px solid #d9e2eb;
    border-radius: 5px;
    box-sizing: border-box;
}

.wk-line input[type=number] {
    width: 100%;
    padding: 3px 4px;
    font-size: 12px;
    border: 1px solid #d9e2eb;
    border-radius: 5px;
    box-sizing: border-box;
}

.wk-line-sub { text-align: right; padding-top: 3px; }

.wk-x {
    border: none;
    background: #fdecea;
    color: #b42318;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 700;
    line-height: 1;
    padding: 4px 0;
}

.wk-combo {
    margin: 6px 0;
    border: 1px solid #cfe4fa;
    border-radius: 10px;
    background: #f9fcff;
    padding: 2px 8px 4px;
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

.wk-gx {
    border: none;
    background: none;
    color: #b42318;
    font-size: 11px;
    cursor: pointer;
    text-decoration: underline;
}

.wk-cnote {
    font-size: 12px;
    color: #17395f;
    padding: 4px 0 2px;
    text-align: right;
}

.wk-sum {
    border: 1px solid #cfe4fa;
    border-radius: 10px;
    background: #f4f9ff;
    padding: 6px 10px;
    font-size: 12px;
}

.wk-sum .r { display: flex; justify-content: space-between; gap: 10px; padding: 1px 0; }
.wk-sum .d { color: #1b7a4a; }
.wk-sum .t { border-top: 1px solid #cfe4fa; margin-top: 3px; padding-top: 3px; font-weight: 700; color: #17395f; font-size: 14px; }
@media(max-width:700px) {
    .wk-wrap { gap:20px; }
    .wk-left { padding:0; border:0; background:transparent; }
    .wk-tabs { gap:8px; margin-bottom:14px; }
    .wk-tab { min-height:44px; padding:10px 12px; font-size:12px; }
    .wk-grid { grid-template-columns:minmax(0,1fr); gap:12px; }
    .wk-item {
        display:grid;
        grid-template-columns:84px minmax(0,1fr);
        align-items:start;
        gap:5px 14px;
        padding:16px;
        border-radius:14px;
    }
    .wk-item .wk-img {
        grid-column:1;
        grid-row:1 / span 5;
        width:84px;
        height:100px;
        padding:6px;
        background:var(--bg);
        border-radius:10px;
    }
    .wk-item .wk-name,
    .wk-item .wk-cat,
    .wk-item .wk-price,
    .wk-item .wk-meta,
    .wk-item .wk-avail { grid-column:2; }
    .wk-item .wk-name { font-size:15px; line-height:1.35; }
    .wk-item .wk-cat,
    .wk-item .wk-meta { font-size:12px; }
    .wk-item .wk-price { font-size:15px; }
    .wk-item .wk-avail { justify-self:start; font-size:11px; padding:3px 8px; }
    .wk-item > select,
    .wk-item .wk-row { grid-column:1 / -1; }
    .wk-item select,
    .wk-item input[type=number] { min-height:44px; font-size:16px; padding:9px 10px; }
    .wk-item .wk-row { gap:10px; margin-top:6px; padding:0; }
    .wk-item .wk-row input { width:64px !important; flex-basis:64px; }
    .wk-item .wk-row .btn { min-height:44px; font-size:13px; padding:10px; }
    .wk-left .scroll { overflow-x:auto; }
    .wk-ctable th,
    .wk-ctable td { padding:10px 6px; }
    .wk-ctable input[type=number],
    .wk-ctable select { min-height:44px; font-size:16px; }
    .reservation-jump {
        display:flex;
        align-items:center;
        justify-content:space-between;
        min-height:48px;
        padding:12px 16px;
        margin-bottom:14px;
        border:1px solid var(--line);
        border-radius:10px;
        background:var(--white);
        color:var(--navy);
        font-size:14px;
        font-weight:700;
    }
    #your-reservation { scroll-margin-top:110px; }
    .wk-grid { grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; }
    .wk-item { display:flex; flex-direction:column; padding:10px; gap:6px; min-width:0; }
    .wk-item .wk-img { width:100%; height:100px; }
    .wk-item .wk-name { font-size:13px; }
    .wk-item .wk-price { font-size:14px; }
    .wk-item .wk-cat,
    .wk-item .wk-meta { font-size:11px; }
    .wk-item .wk-row { flex-wrap:wrap; margin-top:auto; width:100%; }
    .wk-item .wk-row input { width:100% !important; flex:1 1 100%; }
    .wk-item .wk-row .btn { flex:1 1 100%; font-size:12px; }
    .wk-grid { align-items:stretch; }
    .wk-item {
        display:grid;
        grid-template-columns:minmax(0,1fr);
        grid-template-rows:64px 32px 18px 22px 44px 44px;
        align-content:start;
        gap:4px;
        padding:8px;
    }
    .wk-item .wk-img { grid-column:1; grid-row:1; height:64px; }
    .wk-item .wk-meta,
    .wk-item .wk-cat { display:none; }
    .wk-item .wk-name { grid-column:1; grid-row:2; line-height:1.35; align-self:center; }
    .wk-item .wk-cat { grid-column:1; grid-row:3; }
    .wk-item .wk-price { grid-column:1; grid-row:3; align-self:center; }
    .wk-item .wk-avail { grid-column:1; grid-row:4; align-self:start; }
    .wk-item > select { grid-column:1; grid-row:5; margin:0; }
    .wk-item .wk-row { display:contents; }
    .wk-item .wk-row input { grid-column:1; grid-row:5; width:52px !important; justify-self:start; }
    .wk-item > select { grid-column:1; grid-row:5; width:calc(100% - 60px); justify-self:end; font-size:12px; padding:8px 4px; }
    .wk-item .wk-row .btn { grid-column:1; grid-row:6; width:100%; }
    .wk-item:not(:has(> select)) .wk-row input { width:100% !important; }
    .wk-item .wk-row input { text-align:center; }
    .wk-item .wk-row .btn { line-height:1.3; padding:10px 4px; }
    .reservation-jump { width:100%; cursor:pointer; }
    .reservation-jump[hidden] { display:none; }
    .reservation-modal #your-reservation { border:0; padding:0; }
    .reservation-modal #your-reservation .wk-rhead h3 { display:none; }
    .reservation-modal #your-reservation .wk-rhead { justify-content:flex-end; }
    .reservation-modal .modal-heading { position:sticky; top:-24px; background:var(--white); z-index:2; padding-bottom:12px; }
}
@media(max-width:480px) { .reservation-modal .modal-heading { top:-20px; } }
@media(min-width:701px) { .reservation-jump { display:none; } }
</style>


<button type="button" class="reservation-jump" id="open-reservation" hidden><span>Your reservation</span><iconify-icon icon="solar:bag-outline" aria-hidden="true"></iconify-icon></button>
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
                    $itemName  = trim($item["item_name"]);
                    $imageFile = $imageMap[$itemName] ?? $item["image"];
                    $available = (int)$item["available_qty"];
                    $opts      = color_options($itemName);
                    ?>

                    <form method="POST" action="reserve.php" class="wk-item <?= $available <= 0 ? 'out' : '' ?>">

                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="item_id" value="<?= (int)$item["item_id"] ?>">

                        <img class="wk-img" src="../images/<?= e($imageFile) ?>" alt="<?= e($itemName) ?>">

                        <div class="wk-name"><?= e($itemName) ?></div>
                        <div class="wk-cat"><?= e($item["category"]) ?></div>
                        <div class="wk-price">₱<?= number_format((float)$item["price_per_day"], 2) ?>/day</div>

                        <?php if (!empty($item["inclusions"])): ?>
                            <div class="wk-incl" title="<?= e($item["inclusions"]) ?>">
                                <strong>Inclusions:</strong> <?= e($item["inclusions"]) ?>
                            </div>
                        <?php endif; ?>

                        <div class="wk-meta">
                            <?= (int)$item["max_days"] > 0 ? 'Max ' . (int)$item["max_days"] . ' day(s)' : 'No duration limit' ?>
                            <?php if ((float)$item["penalty_per_day"] > 0): ?>
                                &middot; Late penalty ₱<?= number_format((float)$item["penalty_per_day"], 2) ?>/day
                            <?php endif; ?>
                        </div>

                        <?php if ($available > 0): ?>

                            <div class="wk-avail">Available: <?= $available ?> pcs</div>

                            <?php if ($opts): ?>
                                <select name="color" required>
                                    <option value="">Select color</option>
                                    <?php foreach ($opts as $c): ?>
                                        <option value="<?= e($c) ?>"><?= e($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <div class="wk-row">
                                <input type="number" name="qty" value="0" min="1" max="<?= $available ?>" required>
                                <button class="btn tiny primary" type="submit">Add to reservation</button>
                            </div>

                        <?php else: ?>

                            <div class="wk-avail out">Out of Stock</div>

                            <div class="wk-row">
                                <a class="btn tiny primary disabled" href="#">Unavailable</a>
                            </div>

                        <?php endif; ?>

                    </form>

                <?php endforeach; ?>

            </div>
        </div>


        <!-- BUILD A COMBO -->

        <div class="wk-body" id="build-combo" style="display:none;">

            <form method="POST" action="reserve.php" id="combo-form">

                <input type="hidden" name="action" value="add_combo">

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
                        $itemName  = trim($item["item_name"]);
                        $imageFile = $imageMap[$itemName] ?? $item["image"];
                        $free      = max(0, (int)$item["available_qty"] - (int)($need[(int)$item["item_id"]] ?? 0));
                        $opts      = color_options($itemName);
                        ?>
                        <tr>
                            <td>
                                <img src="../images/<?= e($imageFile) ?>" alt="">
                                <?= e($itemName) ?>
                            </td>

                            <td>₱<?= number_format((float)$item["price_per_day"], 2) ?></td>

                            <td>
                                <?php if ($free > 0): ?>
                                    <?= $free ?> pcs
                                <?php else: ?>
                                    <span class="badge danger">Out</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($opts && $free > 0): ?>
                                    <select name="color[<?= (int)$item["item_id"] ?>]"
                                            class="combo-color"
                                            data-id="<?= (int)$item["item_id"] ?>">
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
                                <?php if ($free > 0): ?>
                                    <input type="number"
                                           name="qty[<?= (int)$item["item_id"] ?>]"
                                           class="combo-pcs"
                                           data-id="<?= (int)$item["item_id"] ?>"
                                           data-price="<?= (float)$item["price_per_day"] ?>"
                                           min="0"
                                           max="<?= $free ?>"
                                           value="0">
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
                    <button class="btn primary" type="submit" id="cb-submit" disabled>
                        Add combo to reservation
                    </button>
                </p>

            </form>

        </div>

    </section>


    <!-- RIGHT: YOUR RESERVATION -->

    <?php require "reservation_panel.php"; ?>

</div>


<dialog class="action-modal reservation-modal" id="reservation-modal" aria-labelledby="reservation-modal-title">
    <div class="modal-heading">
        <h2 id="reservation-modal-title">Your reservation</h2>
        <button type="button" class="modal-close" id="close-reservation" aria-label="Close reservation"><iconify-icon icon="solar:close-circle-outline" aria-hidden="true"></iconify-icon></button>
    </div>
</dialog>
<script>
(function () {
    const panel = document.getElementById('your-reservation');
    const modal = document.getElementById('reservation-modal');
    const button = document.getElementById('open-reservation');
    const marker = document.createComment('reservation panel');
    panel.before(marker);
    const mobile = window.matchMedia('(max-width:700px)');
    function syncReservation() {
        if (modal.open) modal.close();
        if (mobile.matches) modal.append(panel);
        else marker.after(panel);
        button.hidden = !mobile.matches;
    }
    button.addEventListener('click', function () { modal.showModal(); });
    document.getElementById('close-reservation').addEventListener('click', function () { modal.close(); });
    mobile.addEventListener('change', syncReservation);
    syncReservation();
})();
</script>
<script>
(function () {

    const PERCENT = <?= (int)COMBO_DISCOUNT_PERCENT ?>;
    const MIN     = <?= (int)COMBO_MIN_ITEMS ?>;

    function money(n) {
        return "₱" + n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }


    /* TABS */

    function showPane(name) {

        document.querySelectorAll(".wk-tab").forEach(function (t) {
            t.classList.toggle("active", t.dataset.pane === name);
        });

        document.getElementById("pane-items").style.display = name === "items" ? "" : "none";
        document.getElementById("build-combo").style.display = name === "combo" ? "" : "none";
    }

    document.querySelectorAll(".wk-tab").forEach(function (tab) {
        tab.addEventListener("click", function () {
            showPane(tab.dataset.pane);
        });
    });

    /* coming back from a combo error: open the combo tab */

    if (location.hash === "#build-combo") {
        showPane("combo");
    }


    /* COMBO BOX (only the inputs of the Build a Combo form) */

    function updateCombo() {

        let gross   = 0;
        let count   = 0;
        let missing = 0;

        document.querySelectorAll("#combo-form .combo-pcs").forEach(function (inp) {

            const qty   = parseInt(inp.value || 0, 10) || 0;
            const price = parseFloat(inp.dataset.price) || 0;
            const cell  = document.getElementById("cb-sub-" + inp.dataset.id);

            if (qty > 0) {

                gross += qty * price;
                count++;
                cell.textContent = money(qty * price);

                const sel = document.querySelector('#combo-form .combo-color[data-id="' + inp.dataset.id + '"]');

                if (sel && sel.value === "") {
                    missing++;
                }

            } else {
                cell.textContent = "—";
            }
        });

        const enough = count >= MIN;
        const ok     = enough && missing === 0;
        const disc   = enough ? Math.round(gross * PERCENT) / 100 : 0;

        const hint = document.getElementById("cb-hint");

        document.getElementById("cb-gross").textContent = money(gross);
        document.getElementById("cb-disc").textContent  = "-" + money(disc);
        document.getElementById("cb-net").textContent   = money(gross - disc);

        hint.textContent = !enough
            ? "Enter pcs for at least " + MIN + " different items."
            : "Select a color for each item that has pcs.";

        hint.style.display = ok ? "none" : "block";

        document.getElementById("cb-submit").disabled = !ok;
    }

    document.querySelectorAll("#combo-form .combo-pcs, #combo-form .combo-color").forEach(function (el) {
        el.addEventListener("input", updateCombo);
        el.addEventListener("change", updateCombo);
    });

    updateCombo();

})();
</script>

<?php require "../partials/footer.php"; ?>