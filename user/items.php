<?php

require_once "../db.php";

$page_title = "Browse Items";
$active = "items";

require "../partials/user_header.php";


/* =========================
   IMAGE MAPPING
========================= */

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


$items = $conn->query(
    "SELECT *
     FROM items
     ORDER BY item_id DESC"
);

?>


<section class="panel">

    <div class="panel-head">

        <h2>Available & Rental Items</h2>

        <span class="muted-text">
            Choose an item to reserve
        </span>

    </div>


    <div class="product-grid">


        <?php while ($item = $items->fetch_assoc()): ?>


            <?php

            $itemName = trim($item["item_name"]);


            /*
             * USE NEW IMAGE FILE NAME
             */

            if (isset($imageMap[$itemName])) {

                $imageFile = $imageMap[$itemName];

            } else {

                $imageFile = $item["image"];

            }

            ?>


            <div class="product-card">


                <!-- IMAGE -->

                <div class="product-image">

                    <img
                        src="../images/<?= e($imageFile) ?>"
                        alt="<?= e($itemName) ?>"
                    >

                </div>


                <!-- DETAILS -->

                <div class="product-body">

                    <h3>
                        <?= e($itemName) ?>
                    </h3>


                    <p class="muted-text">

                        <?= e($item["category"]) ?>

                    </p>


                    <strong class="price">

                        ₱<?= number_format(
                            $item["price_per_day"],
                            2
                        ) ?>/day

                    </strong>


                    <div class="product-bottom">


                        <?= status_badge(
                            $item["status"]
                        ) ?>


                        <a
                            class="btn tiny primary <?= $item["status"] !== "Available" ? "disabled" : "" ?>"
                            href="<?= $item["status"] === "Available"
                                ? "reserve.php?id=" . $item["item_id"]
                                : "#" ?>"
                        >

                            <?= $item["status"] === "Available"
                                ? "Reserve"
                                : "Unavailable" ?>

                        </a>


                    </div>

                </div>

            </div>


        <?php endwhile; ?>


    </div>

</section>


<?php

require "../partials/footer.php";

?>