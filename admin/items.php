<?php

require_once "../db.php";

$page_title = "Item Management";
$active = "items";


/* =========================
   IMAGE MAPPING
========================= */

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


/* =========================
   DELETE ITEM
========================= */

if (isset($_GET["delete"])) {

    $id = (int) $_GET["delete"];

    $stmt = $conn->prepare(
        "DELETE FROM items WHERE item_id = ?"
    );

    $stmt->bind_param("i", $id);

    if (!$stmt->execute()) {
        die("Error deleting item: " . $stmt->error);
    }

    $stmt->close();

    header("Location: items.php");
    exit;
}


/* =========================
   ADD / UPDATE ITEM
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["item_name"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $price = (float) ($_POST["price_per_day"] ?? 0);
    $id = (int) ($_POST["item_id"] ?? 0);


    /* CHECK REQUIRED FIELDS */

    if ($name === "" || $category === "" || $price <= 0) {

        die("Please complete all required fields.");

    }


    /* GET IMAGE AUTOMATICALLY */

    $image = $imageMap[$name] ?? "";


    /* =========================
       UPDATE
    ========================= */

    if ($id > 0) {

        $stmt = $conn->prepare(
            "UPDATE items
             SET item_name = ?,
                 category = ?,
                 price_per_day = ?,
                 image = ?
             WHERE item_id = ?"
        );

        $stmt->bind_param(
            "ssdsi",
            $name,
            $category,
            $price,
            $image,
            $id
        );

        if (!$stmt->execute()) {
            die("Error updating item: " . $stmt->error);
        }

        $stmt->close();

    }


    /* =========================
       ADD
    ========================= */

    else {

        $stmt = $conn->prepare(
            "INSERT INTO items
            (
                item_name,
                category,
                price_per_day,
                image,
                status
            )
            VALUES (?, ?, ?, ?, 'Available')"
        );

        $stmt->bind_param(
            "ssds",
            $name,
            $category,
            $price,
            $image
        );

        if (!$stmt->execute()) {
            die("Error adding item: " . $stmt->error);
        }

        $stmt->close();

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

    $stmt = $conn->prepare(
        "SELECT *
         FROM items
         WHERE item_id = ?"
    );

    $stmt->bind_param("i", $id);

    $stmt->execute();

    $result = $stmt->get_result();

    $edit = $result->fetch_assoc();

    $stmt->close();
}


/* =========================
   HEADER
========================= */

require "../partials/admin_header.php";


/* =========================
   GET ALL ITEMS
========================= */

$items = $conn->query(
    "SELECT *
     FROM items
     ORDER BY item_id DESC"
);

?>


<div class="two-col">


    <!-- =========================
         ADD / EDIT ITEM
    ========================== -->

    <section class="panel">

        <div class="panel-head">

            <h2>
                <?= $edit ? "Edit Item" : "Add Item" ?>
            </h2>

        </div>


        <form method="POST" class="form-grid">


            <!-- ITEM ID -->

            <input
                type="hidden"
                name="item_id"
                value="<?= e($edit["item_id"] ?? 0) ?>"
            >


            <!-- ITEM NAME -->

            <label>

                Item Name

                <input
                    type="text"
                    name="item_name"
                    value="<?= e($edit["item_name"] ?? "") ?>"
                    required
                >

            </label>


            <!-- CATEGORY -->

            <label>

                Category

                <input
                    type="text"
                    name="category"
                    value="<?= e($edit["category"] ?? "") ?>"
                    placeholder="Example: Tables"
                    required
                >

            </label>


            <!-- PRICE -->

            <label>

                Rental Price / Day

                <input
                    type="number"
                    name="price_per_day"
                    step="0.01"
                    min="1"
                    value="<?= e($edit["price_per_day"] ?? "") ?>"
                    required
                >

            </label>


            <!-- IMAGE FILE -->

            <label>

                Image File

                <select name="image" required>

                    <?php foreach ($imageMap as $itemName => $filename): ?>

                        <option
                            value="<?= e($filename) ?>"
                            <?= (($edit["image"] ?? "") === $filename)
                                ? "selected"
                                : "" ?>
                        >

                            <?= e($itemName) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </label>


            <!-- BUTTON -->

            <div class="form-actions">

                <button
                    class="btn primary"
                    type="submit"
                >

                    <?= $edit
                        ? "Update Item"
                        : "Add Item"
                    ?>

                </button>


                <?php if ($edit): ?>

                    <a
                        class="btn"
                        href="items.php"
                    >
                        Cancel
                    </a>

                <?php endif; ?>

            </div>

        </form>

    </section>



    <!-- =========================
         ITEMS
    ========================== -->

    <section class="panel">

        <div class="panel-head">

            <h2>Items</h2>

            <span class="muted-text">
                <?= $items->num_rows ?> items
            </span>

        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>

                        <th>IMAGE</th>
                        <th>ITEM</th>
                        <th>CATEGORY</th>
                        <th>PRICE/DAY</th>
                        <th>STATUS</th>
                        <th>ACTIONS</th>

                    </tr>

                </thead>


                <tbody>


                <?php while ($item = $items->fetch_assoc()): ?>

                    <?php

                    $itemName = trim($item["item_name"]);

                    /*
                     * USE NEW IMAGE FILENAMES
                     */

                    if (isset($imageMap[$itemName])) {

                        $displayImage = $imageMap[$itemName];

                    } else {

                        $displayImage = $item["image"];

                    }

                    ?>


                    <tr>


                        <!-- IMAGE -->

                        <td>

                            <img
                                class="thumb"
                                src="../images/<?= e($displayImage) ?>"
                                alt="<?= e($itemName) ?>"
                            >

                        </td>


                        <!-- ITEM -->

                        <td>

                            <?= e($itemName) ?>

                        </td>


                        <!-- CATEGORY -->

                        <td>

                            <?= e($item["category"]) ?>

                        </td>


                        <!-- PRICE -->

                        <td>

                            ₱<?= number_format(
                                $item["price_per_day"],
                                2
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <?= status_badge(
                                $item["status"]
                            ) ?>

                        </td>


                        <!-- ACTIONS -->

                        <td>

                            <a
                                class="icon-btn edit"
                                href="?edit=<?= $item["item_id"] ?>"
                                title="Edit Item"
                            >
                                ✎
                            </a>


                            <a
                                class="icon-btn delete"
                                href="?delete=<?= $item["item_id"] ?>"
                                onclick="return confirm('Delete this item?')"
                                title="Delete Item"
                            >
                                ✕
                            </a>

                        </td>

                    </tr>

                <?php endwhile; ?>


                <?php if ($items->num_rows === 0): ?>

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;"
                        >

                            No items found.

                        </td>

                    </tr>

                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </section>

</div>


<?php

require "../partials/footer.php";

?>