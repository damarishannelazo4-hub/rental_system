<?php 
require_once "../db.php"; 

$page_title = "My Reservations"; 
$active = "reservations"; 

require "../partials/user_header.php"; 


/* IMAGE MAP */
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


$uid = (int)$_SESSION["user_id"]; 

$stmt = $conn->prepare("
    SELECT 
        r.*,
        i.item_name,
        i.image,
        i.category
    FROM reservations r
    JOIN items i 
        ON i.item_id = r.item_id
    WHERE r.user_id = ?
    ORDER BY r.reservation_id DESC
"); 

$stmt->bind_param("i", $uid); 
$stmt->execute(); 

$list = $stmt->get_result(); 
?> 


<section class="panel"> 

    <div class="panel-head">
        <h2>My Reservations</h2>
    </div> 


    <div class="table-wrap">

        <table>

            <thead>

                <tr>
                    <th>Item</th>
                    <th>Rental Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                </tr>

            </thead>


            <tbody> 


            <?php if($list->num_rows): ?>

                <?php while($r = $list->fetch_assoc()): ?>

                    <?php

                    /* 
                     * Use the correct image based on item name.
                     * This fixes old/broken image filenames.
                     */

                    if (isset($imageMap[$r["item_name"]])) {
                        $image = $imageMap[$r["item_name"]];
                    } else {
                        $image = $r["image"];
                    }

                    ?>


                    <tr>

                        <td class="item-cell">

                            <img 
                                class="mini-thumb" 
                                src="../images/<?= e($image) ?>" 
                                alt="<?= e($r["item_name"]) ?>"
                            >

                            <span>
                                <?= e($r["item_name"]) ?>

                                <small>
                                    <?= e($r["category"]) ?>
                                </small>
                            </span>

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

                    </tr>


                <?php endwhile; ?>


            <?php else: ?>

                <tr>
                    <td colspan="4" class="empty">
                        You have no reservations yet.
                    </td>
                </tr>

            <?php endif; ?>


            </tbody>

        </table>

    </div>

</section> 


<?php require "../partials/footer.php"; ?>