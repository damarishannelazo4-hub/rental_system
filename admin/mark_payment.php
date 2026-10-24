<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    go("reservations.php");
}

$ref    = trim($_POST["order_ref"] ?? "");
$status = $_POST["payment_status"] ?? "";
$back   = $_POST["back"] ?? "";

if ($ref === "" || !in_array($status, ["Unpaid", "For Verification", "Paid"], true)) {
    flash("error", "Invalid payment update.");
    go("reservations.php");
}

$stmt = $conn->prepare("UPDATE reservations SET payment_status = ? WHERE order_ref = ?");
$stmt->bind_param("ss", $status, $ref);
$stmt->execute();
$stmt->close();

flash("success", "Payment for $ref marked as $status.");

if ($back === "view") {
    go("view_reservation.php?ref=" . urlencode($ref));
}

go("reservations.php");