<?php

session_start();

require_once "../db.php";
require_once "../functions.php";


$page_title = "My Profile";
$active = "profile";


$uid = (int)$_SESSION["user_id"];


/* UPDATE PROFILE */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["full_name"] ?? "");
    $contact = trim($_POST["contact_number"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";


    if ($password !== "") {

        $hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $conn->prepare("
            UPDATE users
            SET
                full_name = ?,
                contact_number = ?,
                address = ?,
                password = ?
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "ssssi",
            $name,
            $contact,
            $address,
            $hash,
            $uid
        );

    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET
                full_name = ?,
                contact_number = ?,
                address = ?
            WHERE user_id = ?
        ");

        $stmt->bind_param(
            "sssi",
            $name,
            $contact,
            $address,
            $uid
        );
    }


    $stmt->execute();


    /* Update session name */

    $_SESSION["full_name"] = $name;


    flash(
        "success",
        "Profile updated successfully."
    );


    header("Location: profile.php");
    exit;
}


/* GET USER INFORMATION */

$stmt = $conn->prepare("
    SELECT *
    FROM users
    WHERE user_id = ?
");

$stmt->bind_param("i", $uid);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();


require "../partials/user_header.php";

?>


<section class="panel profile-panel">

    <div class="profile-avatar">
        ●
    </div>


    <form method="POST" class="form-grid">


        <label>
            Full Name

            <input
                name="full_name"
                value="<?= e($user["full_name"]) ?>"
                required
            >
        </label>


        <label>
            Username

            <input
                value="<?= e($user["username"]) ?>"
                disabled
            >
        </label>


        <label>
            Contact Number

            <input
                name="contact_number"
                value="<?= e($user["contact_number"]) ?>"
            >
        </label>


        <label>
            Address

            <input
                name="address"
                value="<?= e($user["address"]) ?>"
            >
        </label>


        <label>
            Change Password

            <input
                type="password"
                name="password"
                placeholder="Leave blank to keep current"
            >
        </label>


        <div class="form-actions">

            <button
                type="submit"
                class="btn primary"
            >
                Save Changes
            </button>

        </div>


    </form>

</section>


<?php require "../partials/footer.php"; ?>