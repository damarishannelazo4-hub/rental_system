<?php

require_once "../db.php";
require_once "../functions.php";

require_role("admin");

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["success" => false]);
    exit;
}

/* turn "1,2,3" into a clean list of ids */

function id_list($text) {
    $ids = array_filter(
        array_map("intval", explode(",", (string)$text)),
        function ($v) { return $v > 0; }
    );

    return implode(",", $ids);
}

$res  = id_list($_POST["res"]  ?? "");
$rent = id_list($_POST["rent"] ?? "");

if ($res !== "") {
    $conn->query("UPDATE reservations SET admin_seen = 1 WHERE reservation_id IN ($res)");
}

if ($rent !== "") {
    $conn->query("UPDATE rentals SET admin_seen = 1 WHERE rental_id IN ($rent)");
}

echo json_encode(["success" => true]);
exit;