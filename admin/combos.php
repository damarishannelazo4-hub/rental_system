<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Combos";
$active = "combos";

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

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /* CREATE COMBO */

    if ($action === "create") {

        $name  = trim($_POST["combo_name"] ?? "");
        $price = (float)($_POST["price_per_day"] ?? 0);
        $qtys  = $_POST["qty"] ?? [];

        $lines = [];

        foreach ($qtys as $id => $q) {
            $id = (int)$id;
            $q  = (int)$q;
            if ($id > 0 && $q > 0) {
                $lines[$id] = $q;
            }
        }

        $error = "";

        if ($name === "") {
            $error = "Please enter the combo name.";
        } elseif ($price <= 0) {
            $error = "Please enter the combo price per day.";
        } elseif (count($lines) < 2) {
            $error = "A combo needs at least 2 different items.";
        }

        if ($error === "") {

            $conn->begin_transaction();

            try {

                $st = $conn->prepare("INSERT INTO combos (combo_name, price_per_day) VALUES (?, ?)");
                $st->bind_param("sd", $name, $price);
                $st->execute();
                $comboId = (int)$conn->insert_id;
                $st->close();

                $st = $conn->prepare("INSERT INTO combo_items (combo_id, item_id, qty) VALUES (?, ?, ?)");

                foreach ($lines as $iid => $q) {
                    $st->bind_param("iii", $comboId, $iid, $q);
                    $st->execute();
                }

                $st->close();

                $conn->commit();

                flash("success", "Combo created.");

            } catch (Throwable $ex) {

                $conn->rollback();

                flash("error", "Could not create the combo.");
            }

        } else {

            flash("error", $error);
        }

        header("Location: combos.php");
        exit;
    }


    /* ACTIVATE / DEACTIVATE */

    if ($action === "toggle") {

        $cid = (int)($_POST["combo_id"] ?? 0);

        $st = $conn->prepare("
            UPDATE combos
            SET status = IF(status = 'Active', 'Inactive', 'Active')
            WHERE combo_id = ?
        ");
        $st->bind_param("i", $cid);
        $st->execute();
        $st->close();

        flash("success", "Combo updated.");

        header("Location: combos.php");
        exit;
    }


    /* DELETE */

    if ($action === "delete") {

        $cid = (int)($_POST["combo_id"] ?? 0);

        $st = $conn->prepare("DELETE FROM combo_items WHERE combo_id = ?");
        $st->bind_param("i", $cid);
        $st->execute();
        $st->close();

        $st = $conn->prepare("DELETE FROM combos WHERE combo_id = ?");
        $st->bind_param("i", $cid);
        $st->execute();
        $st->close();

        flash("success", "Combo deleted.");

        header("Location: combos.php");
        exit;
    }

    header("Location: combos.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Load items (with live stock)
|--------------------------------------------------------------------------
*/
$itemsById = [];

$res = get_items_with_stock($conn);

while ($row = $res->fetch_assoc()) {
    $itemsById[(int)$row["item_id"]] = $row;
}


/*
|--------------------------------------------------------------------------
| Load combos
|--------------------------------------------------------------------------
*/
$combos = [];

$cr = $conn->query("SELECT * FROM combos ORDER BY combo_id DESC");

if ($cr) {
    while ($c = $cr->fetch_assoc()) {
        $c["items"] = [];
        $combos[(int)$c["combo_id"]] = $c;
    }
}

if ($combos) {

    $ir = $conn->query("SELECT combo_id, item_id, qty FROM combo_items ORDER BY combo_item_id");

    if ($ir) {
        while ($x = $ir->fetch_assoc()) {
            $cid = (int)$x["combo_id"];
            if (isset($combos[$cid])) {
                $combos[$cid]["items"][] = $x;
            }
        }
    }
}

require "../partials/admin_header.php";
?>

<style>
.combo-pick {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 10px;
}

.combo-row {
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #d9e2eb;
    border-radius: 10px;
    padding: 8px 10px;
    background: #fff;
}

.combo-row.on {
    border-color: #287fc9;
    background: #f4f9ff;
}

.combo-row img {
    width: 46px;
    height: 46px;
    object-fit: contain;
    border-radius: 8px;
    background: #fff;
    border: 1px solid #edf0f4;
}

.combo-row .info { flex: 1; min-width: 0; }

.combo-row .nm {
    font-weight: 700;
    color: #17395f;
    font-size: 13px;
}

.combo-row .sm {
    font-size: 11px;
    color: #718096;
}

.combo-row input[type=number] {
    width: 64px;
    padding: 6px;
    border: 1px solid #d9e2eb;
    border-radius: 7px;
}

.combo-field {
    padding: 8px;
    border: 1px solid #d9e2eb;
    border-radius: 7px;
}
</style>

<section class="panel">

    <div class="panel-head">
        <div>
            <h2>Create Combo</h2>
            <p class="muted-text">Pick the items and pcs inside the combo, then set one price per day.</p>
        </div>
    </div>

    <form method="POST" style="padding:0 16px 16px;display:grid;gap:14px;">

        <input type="hidden" name="action" value="create">

        <div style="display:flex;gap:10px;flex-wrap:wrap;max-width:620px;">
            <input type="text" name="combo_name" placeholder="Combo name (e.g. Utensils + Chair)"
                   class="combo-field" style="flex:2;min-width:220px;" required>

            <input type="number" name="price_per_day" placeholder="Combo price per day"
                   min="1" step="0.01" class="combo-field" style="flex:1;min-width:160px;" required>
        </div>

        <div>
            <div class="dropdown-title" style="margin-bottom:8px;">
                Items inside the combo (put pcs, leave 0 if not included)
            </div>

            <div class="combo-pick">

                <?php foreach ($itemsById as $it): ?>

                    <?php
                    $img = $imageMap[trim($it["item_name"])] ?? $it["image"];
                    ?>

                    <div class="combo-row">

                        <img src="../images/<?= e($img) ?>" alt="">

                        <div class="info">
                            <div class="nm"><?= e($it["item_name"]) ?></div>
                            <div class="sm">
                                ₱<?= number_format((float)$it["price_per_day"], 2) ?>/day
                                &middot; <?= (int)$it["available_qty"] ?> available
                            </div>
                        </div>

                        <input type="number"
                               name="qty[<?= (int)$it["item_id"] ?>]"
                               min="0"
                               value="0">

                    </div>

                <?php endforeach; ?>

            </div>
        </div>

        <div>
            <button type="submit" class="btn primary">Save Combo</button>
        </div>

    </form>

</section>


<section class="panel">

    <div class="panel-head">
        <div>
            <h2>All Combos</h2>
            <p class="muted-text">Active combos appear on Browse Items and Walk-in Rental.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>

            <thead>
                <tr>
                    <th>Combo</th>
                    <th>Inside</th>
                    <th>Regular/day</th>
                    <th>Combo/day</th>
                    <th>Sets available</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$combos): ?>

                <tr>
                    <td colspan="7" class="empty">No combos yet.</td>
                </tr>

            <?php else: ?>

                <?php foreach ($combos as $c): ?>

                    <?php
                    $regular = 0;
                    $sets    = null;
                    $parts   = [];

                    foreach ($c["items"] as $ci) {

                        $it = $itemsById[(int)$ci["item_id"]] ?? null;

                        if (!$it) {
                            $sets = 0;
                            continue;
                        }

                        $q = max(1, (int)$ci["qty"]);

                        $regular += $q * (float)$it["price_per_day"];
                        $parts[]  = e($it["item_name"]) . " ×" . $q;

                        $can = intdiv((int)$it["available_qty"], $q);

                        $sets = ($sets === null) ? $can : min($sets, $can);
                    }

                    $sets = (int)($sets ?? 0);
                    ?>

                    <tr>

                        <td><strong><?= e($c["combo_name"]) ?></strong></td>

                        <td><?= implode("<br>", $parts) ?></td>

                        <td>₱<?= number_format($regular, 2) ?></td>

                        <td>
                            <strong>₱<?= number_format((float)$c["price_per_day"], 2) ?></strong>

                            <?php if ($regular > (float)$c["price_per_day"]): ?>
                                <br>
                                <small class="muted-text">
                                    Save ₱<?= number_format($regular - (float)$c["price_per_day"], 2) ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($sets > 0): ?>
                                <span class="badge success"><?= $sets ?> set(s)</span>
                            <?php else: ?>
                                <span class="badge danger">Out of stock</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($c["status"] === "Active"): ?>
                                <span class="badge success">Active</span>
                            <?php else: ?>
                                <span class="badge muted">Inactive</span>
                            <?php endif; ?>
                        </td>

                        <td>

                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="combo_id" value="<?= (int)$c["combo_id"] ?>">
                                <button class="btn tiny" type="submit">
                                    <?= $c["status"] === "Active" ? "Deactivate" : "Activate" ?>
                                </button>
                            </form>

                            <form method="POST" style="display:inline;"
                                  onsubmit="return confirm('Delete this combo?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="combo_id" value="<?= (int)$c["combo_id"] ?>">
                                <button class="btn tiny danger-btn" type="submit">Delete</button>
                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

            </tbody>

        </table>
    </div>

</section>

<script>
document.querySelectorAll(".combo-row input[type=number]").forEach(function (inp) {
    inp.addEventListener("input", function () {
        inp.closest(".combo-row").classList.toggle("on", parseInt(inp.value || 0, 10) > 0);
    });
});
</script>

<?php require "../partials/footer.php"; ?>