<?php

require_once "../db.php";
require_once "../functions.php";

$page_title = "Rental & Return";
$active = "rentals";


/* MARK RENTAL AS RETURNED */

if (isset($_GET["return"])) {

    $id = (int)$_GET["return"];

    $conn->begin_transaction();

    try {

        $stmt = $conn->prepare("
            SELECT item_id 
            FROM rentals 
            WHERE rental_id = ? 
            AND status = 'Ongoing'
        ");

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $r = $stmt->get_result()->fetch_assoc();


        if ($r) {

            /* Update rental */

            $stmt = $conn->prepare("
                UPDATE rentals 
                SET 
                    status = 'Returned',
                    actual_return_date = CURDATE()
                WHERE rental_id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();


            /* Make item available again */

            $stmt = $conn->prepare("
                UPDATE items 
                SET status = 'Available'
                WHERE item_id = ?
            ");

            $stmt->bind_param("i", $r["item_id"]);
            $stmt->execute();


            $conn->commit();

            flash(
                "success",
                "Rental marked as returned."
            );

        } else {

            $conn->rollback();

            flash(
                "error",
                "Rental not found."
            );
        }


    } catch (Exception $e) {

        $conn->rollback();

        flash(
            "error",
            "Unable to record return."
        );
    }


    header("Location: rentals.php");
    exit;
}


require "../partials/admin_header.php";


/* ONGOING RENTALS */

$ongoing = $conn->query("
    SELECT 
        rt.*,
        u.full_name,
        i.item_name
    FROM rentals rt
    JOIN users u 
        ON u.user_id = rt.user_id
    JOIN items i 
        ON i.item_id = rt.item_id
    WHERE rt.status = 'Ongoing'
    ORDER BY rt.return_date
");


/* RETURNED RENTALS */

$returned = $conn->query("
    SELECT 
        rt.*,
        u.full_name,
        i.item_name
    FROM rentals rt
    JOIN users u 
        ON u.user_id = rt.user_id
    JOIN items i 
        ON i.item_id = rt.item_id
    WHERE rt.status = 'Returned'
    ORDER BY rt.rental_id DESC
");

?>


<div class="two-col">


    <!-- ONGOING RENTALS -->

    <section class="panel">

        <div class="panel-head">
            <h2>Ongoing Rentals</h2>
        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Customer</th>
                        <th>Item</th>
                        <th>Rent Date</th>
                        <th>Due Date</th>
                        <th>Action</th>
                    </tr>

                </thead>


                <tbody>

                    <?php while($r = $ongoing->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= e($r["full_name"]) ?>
                            </td>

                            <td>
                                <?= e($r["item_name"]) ?>
                            </td>

                            <td>
                                <?= e($r["rental_date"]) ?>
                            </td>

                            <td>
                                <?= e($r["return_date"]) ?>
                            </td>

                            <td>

                                <a
                                    class="btn tiny primary"
                                    href="?return=<?= $r["rental_id"] ?>"
                                    onclick="return confirm('Mark this rental as returned?')"
                                >
                                    Mark as Returned
                                </a>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </section>


    <!-- RETURNED ITEMS -->

    <section class="panel">

        <div class="panel-head">
            <h2>Returned Items</h2>
        </div>


        <div class="table-wrap">

            <table>

                <thead>

                    <tr>
                        <th>Customer</th>
                        <th>Item</th>
                        <th>Return Date</th>
                        <th>Status</th>
                    </tr>

                </thead>


                <tbody>

                    <?php while($r = $returned->fetch_assoc()): ?>

                        <tr>

                            <td>
                                <?= e($r["full_name"]) ?>
                            </td>

                            <td>
                                <?= e($r["item_name"]) ?>
                            </td>

                            <td>
                                <?= e($r["actual_return_date"]) ?>
                            </td>

                            <td>
                                <?= status_badge("Returned") ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                </tbody>

            </table>

        </div>

    </section>


</div>


<?php require "../partials/footer.php"; ?>