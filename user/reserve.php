<?php

require_once "../db.php";
require_once "../functions.php";

require_role("user");


/* =========================
   GET ITEM ID
========================= */

$id = (int)($_GET["id"] ?? 0);


/* =========================
   GET ITEM
========================= */

$stmt = $conn->prepare(
    "SELECT *
     FROM items
     WHERE item_id = ?"
);

$stmt->bind_param("i", $id);
$stmt->execute();

$item = $stmt->get_result()->fetch_assoc();

$stmt->close();


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
   CHECK ITEM
========================= */

if (!$item || $item["status"] !== "Available") {

    flash(
        "error",
        "That item is currently unavailable."
    );

    header("Location: items.php");
    exit;
}


/* =========================
   RESERVATION
========================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $rental_date = $_POST["rental_date"] ?? "";
    $return_date = $_POST["return_date"] ?? "";


    /* VALIDATE DATES */

    if (
        $rental_date === "" ||
        $return_date === "" ||
        $return_date < $rental_date
    ) {

        $error = "Please enter valid rental and return dates.";

    }


    /* SUBMIT RESERVATION */

    else {

        $uid = (int)$_SESSION["user_id"];


        $stmt = $conn->prepare(
            "INSERT INTO reservations
            (
                user_id,
                item_id,
                rental_date,
                return_date,
                status
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                'Pending'
            )"
        );


        $stmt->bind_param(
            "iiss",
            $uid,
            $id,
            $rental_date,
            $return_date
        );


        if ($stmt->execute()) {

            $stmt->close();

            flash(
                "success",
                "Reservation submitted. Please wait for admin approval."
            );

            header("Location: reservations.php");
            exit;

        }


        $stmt->close();

        $error = "Could not submit reservation.";

    }

}


/* =========================
   IMAGE
========================= */

$itemName = trim($item["item_name"]);


if (isset($imageMap[$itemName])) {

    $imageFile = $imageMap[$itemName];

} else {

    $imageFile = $item["image"];

}


/* =========================
   PAGE
========================= */

$page_title = "Make Reservation";
$active = "items";

require "../partials/user_header.php";

?>


<div class="reservation-box">

    <section class="panel">


        <h2>Make Reservation</h2>


        <!-- ITEM -->

        <div class="reserve-item">


            <img
                src="../images/<?= e($imageFile) ?>"
                alt="<?= e($itemName) ?>"
            >


            <div>

                <h3>
                    <?= e($itemName) ?>
                </h3>


                <p>
                    <?= e($item["category"]) ?>
                </p>


                <strong>

                    ₱<?= number_format(
                        $item["price_per_day"],
                        2
                    ) ?>/day

                </strong>

            </div>


        </div>


        <!-- ERROR -->

        <?php if (isset($error)): ?>

            <div class="alert danger-alert">

                <?= e($error) ?>

            </div>

        <?php endif; ?>


        <!-- FORM -->

        <form
            method="POST"
            class="form-grid"
        >


            <!-- RENTAL DATE -->

            <label>

                Rental Date

                <input
                    type="date"
                    name="rental_date"
                    required
                >

            </label>


            <!-- RETURN DATE -->

            <label>

                Return Date

                <input
                    type="date"
                    name="return_date"
                    required
                >

            </label>


            <!-- BUTTONS -->

            <div class="form-actions">


                <button
                    type="submit"
                    class="btn primary"
                >

                    Submit Reservation

                </button>


                <a
                    class="btn"
                    href="items.php"
                >

                    Cancel

                </a>


            </div>


        </form>


    </section>

</div>


<?php

require "../partials/footer.php";

?>