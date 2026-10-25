<?php

require_once "../db.php";
require_once "../functions.php";

require_role("admin");

/* Automatic discount on the late penalty (percent) */
if (!defined("PENALTY_DISCOUNT_PERCENT")) {
    define("PENALTY_DISCOUNT_PERCENT", 10);
}

/*
|--------------------------------------------------------------------------
| This file processes the "Mark as Returned" form
| from admin/rentals.php.
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: rentals.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get submitted values
|--------------------------------------------------------------------------
*/

$rental_id = (int)($_POST["rental_id"] ?? 0);

$actual_return_date = trim($_POST["actual_return_date"] ?? "");

$penalty_paid = !empty($_POST["penalty_paid"]);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if ($rental_id <= 0) {
    header("Location: rentals.php");
    exit;
}

if ($actual_return_date === "") {
    $actual_return_date = date("Y-m-d");
}

$dateObject = DateTime::createFromFormat("Y-m-d", $actual_return_date);

if (!$dateObject || $dateObject->format("Y-m-d") !== $actual_return_date) {
    header("Location: rentals.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Get rental information
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        rt.rental_id,
        rt.item_id,
        rt.quantity,
        rt.rental_date,
        rt.return_date,
        rt.status,
        rt.rental_fee,
        rt.combo_saved,
        i.penalty_per_day
    FROM rentals rt
    JOIN items i
        ON i.item_id = rt.item_id
    WHERE rt.rental_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $rental_id);
$stmt->execute();

$rental = $stmt->get_result()->fetch_assoc();

$stmt->close();


if (!$rental) {
    header("Location: rentals.php");
    exit;
}

if ($actual_return_date < $rental["rental_date"] || $actual_return_date > date("Y-m-d")) {
    flash("error", "Return date must be between the rental start date and today.");
    header("Location: rentals.php");
    exit;
}

if ($rental["status"] === "Returned") {
    header("Location: rentals.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Late days
|--------------------------------------------------------------------------
*/

$dueDate    = new DateTime($rental["return_date"]);
$actualDate = new DateTime($actual_return_date);

$late_days = 0;

if ($actualDate > $dueDate) {
    $late_days = (int)$dueDate->diff($actualDate)->days;
}


/*
|--------------------------------------------------------------------------
| Quantity and penalty
| Penalty = Late Days x Penalty Per Day x Quantity
|--------------------------------------------------------------------------
*/

$quantity = max(1, (int)$rental["quantity"]);

$penalty_per_day = (float)$rental["penalty_per_day"];

$penalty = $late_days * $penalty_per_day * $quantity;


/*
|--------------------------------------------------------------------------
| POLICY: the late penalty must be paid before the return is accepted
|--------------------------------------------------------------------------
*/

if ($penalty > 0 && !$penalty_paid) {

    flash("error", "Penalty must be paid first. Confirm that the penalty was collected before marking this rental as returned.");

    header("Location: rentals.php");
    exit;
}


/*
|--------------------------------------------------------------------------
| Rental fee (planned days, from rent date to due date)
| Combo items get the combo discount saved with the rental.
|--------------------------------------------------------------------------
*/

$rental_fee = (float)$rental["rental_fee"];
$combo_saved = (float)$rental["combo_saved"];


/*
|--------------------------------------------------------------------------
| Discount: automatic percent of the penalty (never more than the penalty)
|--------------------------------------------------------------------------
*/

$discount = 0.0;

if ($penalty > 0) {
    $discount = round($penalty * PENALTY_DISCOUNT_PERCENT / 100, 2);
}

if ($discount > $penalty) {
    $discount = $penalty;
}


/*
|--------------------------------------------------------------------------
| Final amount = rental fee + penalty - discount
|--------------------------------------------------------------------------
*/

$total_paid = $rental_fee + $penalty - $discount;

if ($total_paid < 0) {
    $total_paid = 0;
}


/*
|--------------------------------------------------------------------------
| Save
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        UPDATE rentals
        SET
            actual_return_date = ?,
            status = 'Returned',
            late_days = ?,
            penalty = ?,
            discount = ?,
            rental_fee = ?,
            combo_saved = ?,
            total_paid = ?
        WHERE rental_id = ?
    ");

    $stmt->bind_param(
        "sidddddi",
        $actual_return_date,
        $late_days,
        $penalty,
        $discount,
        $rental_fee,
        $combo_saved,
        $total_paid,
        $rental_id
    );

    $stmt->execute();
    $stmt->close();


    /* Make item available again */

    $stmt = $conn->prepare("
        UPDATE items
        SET status = 'Available'
        WHERE item_id = ?
    ");

    $stmt->bind_param("i", $rental["item_id"]);
    $stmt->execute();
    $stmt->close();


    $conn->commit();

    header("Location: rentals.php");
    exit;

} catch (Throwable $e) {

    $conn->rollback();

    die("Return processing failed: " . e($e->getMessage()));
}