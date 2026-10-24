<?php

require_once "../db.php";
require_once "../functions.php";

require_role("user");

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false]);
    exit;
}

$uid = (int)$_SESSION["user_id"];

/* only the notifications that were shown are marked as seen */

$ids = array_filter(
    array_map("intval", explode(",", (string)($_POST["ids"] ?? ""))),
    function ($v) { return $v > 0; }
);

if ($ids) {

    $list = implode(",", $ids);

    $stmt = $conn->prepare("
        UPDATE reservations
        SET user_seen = 1
        WHERE user_id = ?
          AND status IN ('Approved', 'Rejected')
          AND reservation_id IN ($list)
    ");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $stmt->close();
}

echo json_encode(["success" => true]);
exit;