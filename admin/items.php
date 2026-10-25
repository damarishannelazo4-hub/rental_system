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

if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET["delete"])) {
    require_csrf();
}

if (isset($_POST["delete"])) {

    $id = (int) $_POST["delete"];

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
        $image = $imageMap[$name] ?? 'tent_square.jpg';
        if ($id > 0) {
            $current = $conn->prepare('SELECT image FROM items WHERE item_id = ?');
            $current->bind_param('i', $id);
            $current->execute();
            $image = $current->get_result()->fetch_assoc()['image'] ?? $image;
            $current->close();
        }
    }


    $uploadedPath = null;
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['item_image'];
        $mime = $file['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) : false;
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] <= 0 || $file['size'] > 5 * 1024 * 1024
            || !isset($extensions[$mime]) || !@getimagesize($file['tmp_name'])) {
            flash('error', 'Choose a valid JPG, PNG or WebP image, up to 5 MB.');
            header('Location: items.php');
            exit;
        }
        $image = 'item_' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        $uploadedPath = dirname(__DIR__) . '/images/' . $image;
        if (!move_uploaded_file($file['tmp_name'], $uploadedPath)) {
            flash('error', 'Image could not be saved. Please try again.');
            header('Location: items.php');
            exit;
        }
    }

    try {
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
    } catch (Throwable $error) {
        if ($uploadedPath !== null) unlink($uploadedPath);
        error_log($error->getMessage());
        flash('error', 'Item could not be saved. Please try again.');
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


    <!-- Form fields reused by the edit modal. -->
    <template id="item-form-template">

        <div class="panel-head">
            <h2><?= $edit ? "Edit Item" : "Add Item" ?></h2>
        </div>

        <form method="POST" class="item-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">

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

            <label class="item-image-choice">
                Image File
                <select name="image">
                    <option value="">Keep current / default image</option>
                    <?php foreach ($imageMap as $itemName => $filename): ?>
                        <option value="<?= e($filename) ?>" <?= ($selectedImage === $filename) ? "selected" : "" ?>>
                            <?= e($itemName) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="item-image-upload">
                Upload image
                <input type="file" name="item_image" accept="image/jpeg,image/png,image/webp">
                <small>JPG, PNG or WebP, up to 5 MB. Upload overrides the selected image.</small>
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

    </template>


    <!-- ITEMS (buong lapad, nasa ilalim) -->

    <section class="panel">

        <div class="panel-head items-toolbar">
            <div class="items-toolbar-title">
                <h2>Items</h2>
                <span class="muted-text"><?= $items->num_rows ?> items</span>
            </div>
            <button type="button" class="btn primary" id="create-item"><iconify-icon icon="solar:add-circle-outline" aria-hidden="true"></iconify-icon><span>Create new item</span></button>
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
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
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
                            <button type="button" class="icon-btn edit" data-edit-item="<?= e(json_encode($item, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" title="Edit Item" aria-label="Edit item"><iconify-icon icon="solar:pen-outline" aria-hidden="true"></iconify-icon></button>

                            <form method="POST" style="display:inline" data-confirm-delete>
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <button class="icon-btn delete" name="delete" value="<?= (int) $item["item_id"] ?>" aria-label="Delete item"><iconify-icon icon="solar:trash-bin-trash-outline" aria-hidden="true"></iconify-icon></button>
                            </form>
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

<dialog class="action-modal" id="edit-item-modal" aria-labelledby="edit-item-title">
    <div class="modal-heading"><div><h2 id="edit-item-title">Edit item</h2><p>Update item details and inventory.</p></div><button type="button" class="modal-close" aria-label="Close edit dialog" onclick="this.closest('dialog').close()"><iconify-icon icon="solar:close-circle-outline" aria-hidden="true"></iconify-icon></button></div>
    <div id="edit-item-fields"></div>
</dialog>
<dialog class="action-modal delete-modal" id="delete-item-modal" aria-labelledby="delete-item-title" aria-describedby="delete-item-description">
    <div class="modal-heading"><div class="delete-symbol"><iconify-icon icon="solar:trash-bin-trash-outline" aria-hidden="true"></iconify-icon></div><button type="button" class="modal-close" aria-label="Close delete dialog" onclick="this.closest('dialog').close()"><iconify-icon icon="solar:close-circle-outline" aria-hidden="true"></iconify-icon></button></div>
    <h2 id="delete-item-title">Delete item?</h2>
    <p id="delete-item-description">This permanently removes the item. This cannot be undone.</p>
    <div class="form-actions">
        <button type="button" class="btn" id="cancel-item-delete">Cancel</button>
        <button type="button" class="btn danger" id="confirm-item-delete">Delete item</button>
    </div>
</dialog>
<script>
(function () {
    const modal = document.getElementById('edit-item-modal');
    const source = document.getElementById('item-form-template').content.querySelector('.item-form');
    const form = source.cloneNode(true);
    form.action = 'items.php';
    const actions = form.querySelector('.item-form-actions');
    actions.replaceChildren();
    const save = document.createElement('button');
    save.type = 'submit';
    save.className = 'btn primary';
    save.innerHTML = '<iconify-icon icon="solar:diskette-outline" aria-hidden="true"></iconify-icon> Save changes';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn';
    cancel.textContent = 'Cancel';
    cancel.addEventListener('click', function () { modal.close(); });
    actions.append(save, cancel);
    document.getElementById('edit-item-fields').append(form);
    document.getElementById('create-item').addEventListener('click', function () {
        form.reset();
        form.elements.namedItem('item_id').value = '0';
        form.elements.namedItem('item_name').value = '';
        form.elements.namedItem('category').value = '';
        form.elements.namedItem('price_per_day').value = '';
        form.elements.namedItem('total_qty').value = '1';
        form.elements.namedItem('image').value = '';
        document.getElementById('edit-item-title').textContent = 'Create new item';
        save.textContent = 'Create item';
        modal.showModal();
    });
    document.querySelectorAll('[data-edit-item]').forEach(function (button) {
        button.addEventListener('click', function () {
            const item = JSON.parse(button.dataset.editItem);
            document.getElementById('edit-item-title').textContent = 'Edit item';
            save.textContent = 'Save changes';
            form.reset();
            ['item_id', 'item_name', 'category', 'price_per_day', 'total_qty', 'image'].forEach(function (name) {
                form.elements.namedItem(name).value = item[name] ?? '';
            });
            if (!form.elements.namedItem('image').value) {
                form.elements.namedItem('image').value = '';
            }
            modal.showModal();
        });
    });
    const confirmation = document.getElementById('delete-item-modal');
    let pending;
    document.querySelectorAll('[data-confirm-delete]').forEach(function (deleteForm) {
        deleteForm.addEventListener('submit', function (event) {
            event.preventDefault();
            pending = { form: deleteForm, button: event.submitter };
            const name = deleteForm.closest('tr').querySelector('[data-edit-item]');
            const item = name ? JSON.parse(name.dataset.editItem) : null;
            document.getElementById('delete-item-title').textContent = item ? 'Delete ' + item.item_name + '?' : 'Delete item?';
            confirmation.showModal();
            document.getElementById('cancel-item-delete').focus();
        });
    });
    document.getElementById('cancel-item-delete').addEventListener('click', function () { confirmation.close(); });
    document.getElementById('confirm-item-delete').addEventListener('click', function () {
        if (!pending) return;
        const id = document.createElement('input');
        id.type = 'hidden';
        id.name = 'delete';
        id.value = pending.button.value;
        pending.form.append(id);
        pending.form.submit();
    });
    confirmation.addEventListener('close', function () { pending = null; });
})();
</script>
<?php require "../partials/footer.php"; ?>