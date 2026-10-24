<?php

require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Item Management";
$active = "items";


/* =========================
   IMAGE MAPPING
========================= */

$imageMap = [
    "Tent Square"         => "tent_square.jpg",
    "Monoblock Chair"     => "monoblock_chair.jpg",
    "Utensils"            => "utensils.jpg",
    "Half Moon Table"     => "half_moon_table.jpg",
    "Backdrop"            => "backdrop.jpg",
    "Cleopatra Chair"     => "cleopatra_chair.jpg",
    "Long Table (Buffet)" => "long_table_buffet.jpg",
    "Pleated Tablecloth"  => "pleated_tablecloth.jpg"
];


/* =========================
   DELETE ITEM
========================= */

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare("DELETE FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        die("Error deleting item: " . $stmt->error);
    }

    $stmt->close();

    header("Location: items.php");
    exit;
}


/* =========================
   ADD STOCK (quick add)
   Dinadagdagan ang total_qty, ito rin ang nakikita ng user
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "add_stock") {

    $id  = (int) ($_POST["item_id"] ?? 0);
    $qty = (int) ($_POST["add_qty"] ?? 0);

    if ($id > 0 && $qty > 0) {

        $stmt = $conn->prepare("UPDATE items SET total_qty = total_qty + ? WHERE item_id = ?");
        $stmt->bind_param("ii", $qty, $id);

        if ($stmt->execute()) {
            flash("success", "Added $qty pc(s) to stock.");
        } else {
            flash("error", "Could not add stock.");
        }

        $stmt->close();

    } else {
        flash("error", "Enter a quantity greater than 0.");
    }

    header("Location: items.php");
    exit;
}


/* =========================
   ADD / UPDATE ITEM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST["item_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price    = (float) ($_POST["price_per_day"] ?? 0);
    $total    = max(0, (int) ($_POST["total_qty"] ?? 0));
    $id       = (int) ($_POST["item_id"] ?? 0);


    /* CHECK REQUIRED FIELDS */

    if ($name === "" || $category === "" || $price <= 0) {
        die("Please complete all required fields.");
    }


    /* IMAGE: gamitin ang pinili sa dropdown, kung wala ay sa pangalan */

    $postedImage = $_POST["image"] ?? "";

    if (in_array($postedImage, $imageMap, true)) {
        $image = $postedImage;
    } else {
        $image = $imageMap[$name] ?? "";
    }


    /* UPDATE (hindi ginagalaw ang status) */

    if ($id > 0) {

        $stmt = $conn->prepare(
            "UPDATE items
             SET item_name = ?,
                 category = ?,
                 price_per_day = ?,
                 image = ?,
                 total_qty = ?
             WHERE item_id = ?"
        );

        $stmt->bind_param("ssdsii", $name, $category, $price, $image, $total, $id);

        if (!$stmt->execute()) {
            die("Error updating item: " . $stmt->error);
        }

        $stmt->close();
        flash("success", "Item updated.");
    }


    /* ADD */

    else {

        $stmt = $conn->prepare(
            "INSERT INTO items
                (item_name, category, price_per_day, image, total_qty, status)
             VALUES (?, ?, ?, ?, ?, 'Available')"
        );

        $stmt->bind_param("ssdsi", $name, $category, $price, $image, $total);

        if (!$stmt->execute()) {
            die("Error adding item: " . $stmt->error);
        }

        $stmt->close();
        flash("success", "Item added.");
    }

    header("Location: items.php");
    exit;
}


/* =========================
   EDIT ITEM
========================= */

$edit = null;

if (isset($_GET["edit"])) {

    $id = (int) $_GET["edit"];

    $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $edit = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}


/* Alin ang naka-select sa Image File dropdown */

$selectedImage = "";

if ($edit) {
    if (in_array($edit["image"], $imageMap, true)) {
        $selectedImage = $edit["image"];
    } else {
        $selectedImage = $imageMap[trim($edit["item_name"])] ?? "";
    }
}


/* =========================
   HEADER
========================= */

require "../partials/admin_header.php";


/* =========================
   GET ALL ITEMS
========================= */

$items = $conn->query("SELECT * FROM items ORDER BY item_id DESC");


/* Available pcs (parehong bilang na nakikita ng customer) */

$avail = [];

$stockRes = get_items_with_stock($conn);

while ($sr = $stockRes->fetch_assoc()) {
    $avail[(int) $sr["item_id"]] = (int) $sr["available_qty"];
}

?>

<style>
.items-stack {
    display: flex;
    flex-direction: column;
    gap: 24px;
}

/* FORM NAKAHILERA */

.item-form {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
    gap: 16px;
    align-items: end;
}

.item-form label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    font-weight: 700;
    color: #3b4a5c;
}

.item-form input,
.item-form select {
    width: 100%;
    box-sizing: border-box;
    padding: 12px 14px;
    border: 1px solid #d5dde6;
    border-radius: 10px;
    background: #fff;
    font-size: 15px;
    font-weight: 400;
}

.item-form-actions {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* STOCK NAKAHILERA */

.stock-cell {
    display: flex;
    align-items: center;
    gap: 14px;
    white-space: nowrap;
}

.stock-numbers {
    line-height: 1.5;
    min-width: 110px;
}

.stock-numbers strong { color: #17395f; }
.stock-numbers small { color: #718096; }

.stock-form {
    display: flex;
    gap: 6px;
    margin: 0;
}

.stock-form input {
    width: 80px;
    padding: 8px 10px;
    border: 1px solid #d5dde6;
    border-radius: 8px;
    font-size: 13px;
}

.stock-form button {
    padding: 8px 14px;
    border: 0;
    border-radius: 8px;
    background: #287fc9;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.badge-out {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 20px;
    background: #fde8e8;
    color: #c0392b;
    font-size: 12px;
    font-weight: 700;
}
</style>

<div class="items-stack">


    <!-- ADD / EDIT ITEM (nasa taas, pahiga) -->

    <section class="panel">

        <div class="panel-head">
            <h2><?= $edit ? "Edit Item" : "Add Item" ?></h2>
        </div>

        <form method="POST" class="item-form">

            <input type="hidden" name="item_id" value="<?= e($edit["item_id"] ?? 0) ?>">

            <label>
                Item Name
                <input type="text" name="item_name" value="<?= e($edit["item_name"] ?? "") ?>" required>
            </label>

            <label>
                Category
                <input type="text" name="category" value="<?= e($edit["category"] ?? "") ?>" placeholder="Example: Tables" required>
            </label>

            <label>
                Rental Price / Day
                <input type="number" name="price_per_day" step="0.01" min="1" value="<?= e($edit["price_per_day"] ?? "") ?>" required>
            </label>

            <label>
                Total Stock (pcs)
                <input type="number" name="total_qty" min="0" step="1" value="<?= e($edit["total_qty"] ?? 1) ?>" required>
            </label>

            <label>
                Image File
                <select name="image" required>
                    <?php foreach ($imageMap as $itemName => $filename): ?>
                        <option value="<?= e($filename) ?>" <?= ($selectedImage === $filename) ? "selected" : "" ?>>
                            <?= e($itemName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="item-form-actions">

                <button class="btn primary" type="submit">
                    <?= $edit ? "Update Item" : "Add Item" ?>
                </button>

                <?php if ($edit): ?>
                    <a class="btn" href="items.php">Cancel</a>
                <?php endif; ?>

            </div>

        </form>

    </section>


    <!-- ITEMS (buong lapad, nasa ilalim) -->

    <section class="panel">

        <div class="panel-head">
            <h2>Items</h2>
            <span class="muted-text"><?= $items->num_rows ?> items</span>
        </div>

        <div class="table-wrap">

            <table>

                <thead>
                    <tr>
                        <th>IMAGE</th>
                        <th>ITEM</th>
                        <th>CATEGORY</th>
                        <th>PRICE/DAY</th>
                        <th>STOCK</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>
                    </tr>
                </thead>

                <tbody>

                <?php while ($item = $items->fetch_assoc()): ?>

                    <?php
                    $itemName = trim($item["item_name"]);
                    $displayImage = $imageMap[$itemName] ?? $item["image"];
                    $total = (int) $item["total_qty"];
                    $available = $avail[(int) $item["item_id"]] ?? $total;
                    ?>

                    <tr>

                        <td>
                            <img class="thumb" src="../images/<?= e($displayImage) ?>" alt="<?= e($itemName) ?>">
                        </td>

                        <td><?= e($itemName) ?></td>

                        <td><?= e($item["category"]) ?></td>

                        <td>₱<?= number_format($item["price_per_day"], 2) ?></td>

                        <!-- STOCK + ADD STOCK (pahiga) -->

                        <td>
                            <div class="stock-cell">

                                <div class="stock-numbers">
                                    <strong>Available: <?= $available ?></strong><br>
                                    <small>Total: <?= $total ?> pcs</small>
                                </div>

                                <form method="POST" class="stock-form">
                                    <input type="hidden" name="action" value="add_stock">
                                    <input type="hidden" name="item_id" value="<?= (int) $item["item_id"] ?>">
                                    <input type="number" name="add_qty" min="1" placeholder="+ pcs" required>
                                    <button type="submit">Add</button>
                                </form>

                            </div>
                        </td>

                        <!-- STATUS -->

                        <td>
                            <?php if ($available <= 0): ?>
                                <span class="badge-out">Out of Stock</span>
                            <?php else: ?>
                                <?= status_badge($item["status"]) ?>
                            <?php endif; ?>
                        </td>

                        <!-- ACTIONS -->

                        <td style="white-space:nowrap;">
                            <a class="icon-btn edit" href="?edit=<?= (int) $item["item_id"] ?>" title="Edit Item">✎</a>

                            <a class="icon-btn delete" href="?delete=<?= (int) $item["item_id"] ?>"
                               onclick="return confirm('Delete this item?')" title="Delete Item">✕</a>
                        </td>

                    </tr>

                <?php endwhile; ?>

                <?php if ($items->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align:center;">No items found.</td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</div>

<?php require "../partials/footer.php"; ?>