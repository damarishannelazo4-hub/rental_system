<?php

require_once "../db.php";
require_once "../functions.php";

$page_title = "Reservation Management";
$active = "reservations";


if (isset($_GET["action"], $_GET["id"])) {

    $id = (int)$_GET["id"];
    $action = $_GET["action"];


    $stmt = $conn->prepare("
        SELECT * 
        FROM reservations 
        WHERE reservation_id = ?
    ");

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $res = $stmt->get_result()->fetch_assoc();


    if ($res) {

        /* APPROVE RESERVATION */

        if ($action === "approve" && $res["status"] === "Pending") {

            $conn->begin_transaction();

            try {

                /* Update reservation status */

                $stmt = $conn->prepare("
                    UPDATE reservations 
                    SET status = 'Approved' 
                    WHERE reservation_id = ?
                ");

                $stmt->bind_param("i", $id);
                $stmt->execute();


                /* Create rental */

                $stmt = $conn->prepare("
                    INSERT INTO rentals
                    (
                        reservation_id,
                        user_id,
                        item_id,
                        rental_date,
                        return_date,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, 'Ongoing')
                ");

                $stmt->bind_param(
                    "iiiss",
                    $res["reservation_id"],
                    $res["user_id"],
                    $res["item_id"],
                    $res["rental_date"],
                    $res["return_date"]
                );

                $stmt->execute();


                /* Mark item as rented */

                $stmt = $conn->prepare("
                    UPDATE items 
                    SET status = 'Rented' 
                    WHERE item_id = ?
                ");

                $stmt->bind_param("i", $res["item_id"]);
                $stmt->execute();


                $conn->commit();

                flash(
                    "success",
                    "Reservation approved and rental started."
                );

            } catch (Exception $e) {

                $conn->rollback();

                flash(
                    "error",
                    "Could not approve reservation."
                );
            }


        /* REJECT RESERVATION */

        } elseif (
            $action === "reject" &&
            $res["status"] === "Pending"
        ) {

            $stmt = $conn->prepare("
                UPDATE reservations 
                SET status = 'Rejected' 
                WHERE reservation_id = ?
            ");

            $stmt->bind_param("i", $id);
            $stmt->execute();


            flash(
                "success",
                "Reservation rejected."
            );
        }
    }


    header("Location: reservations.php");
    exit;
}


require "../partials/admin_header.php";


$list = $conn->query("
    SELECT 
        r.*,
        u.full_name,
        i.item_name
    FROM reservations r
    JOIN users u 
        ON u.user_id = r.user_id
    JOIN items i 
        ON i.item_id = r.item_id
    ORDER BY r.reservation_id DESC
");

?>

<section class="panel">

    <div class="panel-head">
        <h2>Reservations</h2>
    </div>


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Rental Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>

            </thead>


            <tbody>

                <?php while($r = $list->fetch_assoc()): ?>

                    <tr>

                        <td>
                            #<?= $r["reservation_id"] ?>
                        </td>


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
                            <?= status_badge($r["status"]) ?>
                        </td>


                        <td>

                            <?php if($r["status"] === "Pending"): ?>

                                <a
                                    class="btn tiny success-btn"
                                    href="?action=approve&id=<?= $r["reservation_id"] ?>"
                                >
                                    Approve
                                </a>


                                <a
                                    class="btn tiny danger-btn"
                                    href="?action=reject&id=<?= $r["reservation_id"] ?>"
                                >
                                    Reject
                                </a>

                            <?php else: ?>

                                <span class="muted-text">
                                    —
                                </span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</section>


<?php require "../partials/footer.php"; ?>