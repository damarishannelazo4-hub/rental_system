<?php

require_once "../db.php";
require_once "../functions.php";

require_role("user");

$uid = (int)$_SESSION["user_id"];


/*
 * Get the current number of notifications.
 */

$approved = 0;
$rejected = 0;
$pending = 0;
$dueToday = 0;
$dueTomorrow = 0;


/* Approved */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Approved'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $approved = (int)$result->fetch_assoc()["total"];
}


/* Rejected */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Rejected'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $rejected = (int)$result->fetch_assoc()["total"];
}


/* Pending */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM reservations
    WHERE user_id = ?
    AND status = 'Pending'
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $pending = (int)$result->fetch_assoc()["total"];
}


/* Due today */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE user_id = ?
    AND status = 'Ongoing'
    AND return_date = CURDATE()
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $dueToday = (int)$result->fetch_assoc()["total"];
}


/* Due tomorrow */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM rentals
    WHERE user_id = ?
    AND status = 'Ongoing'
    AND return_date = DATE_ADD(
        CURDATE(),
        INTERVAL 1 DAY
    )
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$result = $stmt->get_result();

if ($result) {
    $dueTomorrow = (int)$result->fetch_assoc()["total"];
}


/*
 * Save the current count as already seen.
 */

$totalNotifications =
    $approved +
    $rejected +
    $pending +
    $dueToday +
    $dueTomorrow;


$_SESSION["notifications_seen_count"] =
    $totalNotifications;


/*
 * Return response.
 */

header("Content-Type: application/json");

echo json_encode([
    "success" => true
]);

exit;