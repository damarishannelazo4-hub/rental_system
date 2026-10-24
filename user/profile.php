<?php

session_start();

require_once "../db.php";
require_once "../functions.php";

require_role("user");

$page_title = "My Profile";
$active = "profile";

$uid = (int)$_SESSION["user_id"];


/* UPDATE PROFILE */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name     = trim($_POST["full_name"] ?? "");
    $contact  = trim($_POST["contact_number"] ?? "");
    $address  = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";

    /* Load current data so blank fields keep their current value */
    $cur = $conn->prepare("SELECT full_name, contact_number, address FROM users WHERE user_id = ? LIMIT 1");
    $cur->bind_param("i", $uid);
    $cur->execute();
    $me = $cur->get_result()->fetch_assoc();
    $cur->close();

    if ($name === "")    $name    = $me["full_name"] ?? "";
    if ($contact === "") $contact = $me["contact_number"] ?? "";
    if ($address === "") $address = $me["address"] ?? "";

    if ($password !== "") {

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, contact_number = ?, address = ?, password = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("ssssi", $name, $contact, $address, $hash, $uid);

    } else {

        $stmt = $conn->prepare("
            UPDATE users
            SET full_name = ?, contact_number = ?, address = ?
            WHERE user_id = ?
        ");
        $stmt->bind_param("sssi", $name, $contact, $address, $uid);
    }

    $stmt->execute();

    /* Update session name */
    $_SESSION["full_name"] = $name;

    flash("success", "Profile updated successfully.");

    header("Location: profile.php");
    exit;
}


/* GET USER INFORMATION */

$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();

$user = $stmt->get_result()->fetch_assoc();


/* Initials for avatar (max 2 letters) */
$initials = "";
foreach (preg_split('/\s+/', trim($user["full_name"])) as $part) {
    if ($part !== "" && strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($initials === "") {
    $initials = "U";
}


require "../partials/user_header.php";

?>

<style>
.pf-wrap{
    max-width:860px;
    display:grid;
    gap:20px
}

.pf-hero{
    display:flex;
    align-items:center;
    gap:20px;
    padding:24px;
    background:linear-gradient(135deg,var(--navy) 0%,var(--blue) 100%);
    color:#fff;
    border-radius:14px;
    box-shadow:var(--shadow)
}

.pf-avatar{
    width:78px;
    height:78px;
    border-radius:50%;
    background:rgba(255,255,255,.18);
    border:3px solid rgba(255,255,255,.55);
    display:grid;
    place-items:center;
    font-size:28px;
    font-weight:700;
    letter-spacing:1px;
    flex-shrink:0
}

.pf-hero h2{
    margin:0 0 4px;
    font-size:22px
}

.pf-hero p{
    margin:0;
    font-size:13px;
    opacity:.85
}

.pf-card{
    background:#fff;
    border:1px solid var(--line);
    border-radius:14px;
    box-shadow:var(--shadow);
    padding:24px
}

.pf-card h3{
    margin:0 0 4px;
    font-size:16px;
    color:var(--navy)
}

.pf-card > .muted-text{
    display:block;
    margin-bottom:18px
}

.pf-grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px
}

.pf-field label{
    display:block;
    font-size:12px;
    font-weight:700;
    color:#46566a;
    margin-bottom:7px
}

.pf-field input{
    width:100%;
    padding:12px 13px;
    border:1px solid #d9e2eb;
    border-radius:8px;
    font-size:14px;
    background:#fff;
    color:var(--text);
    transition:border-color .2s, box-shadow .2s
}

.pf-field input:focus{
    outline:none;
    border-color:var(--blue);
    box-shadow:0 0 0 3px #d9ebfb
}

.pf-field input:disabled{
    background:#f3f6f9;
    color:#7b8a9b;
    cursor:not-allowed
}

.pf-field small{
    display:block;
    margin-top:6px;
    font-size:11px;
    color:var(--muted)
}

.pf-actions{
    display:flex;
    justify-content:flex-end;
    gap:10px
}

.pf-actions .btn{
    padding:11px 20px;
    font-size:13px
}

@media(max-width:700px){
    .pf-grid{grid-template-columns:1fr}
    .pf-hero{flex-direction:column;text-align:center}
    .pf-actions{flex-direction:column-reverse}
    .pf-actions .btn{width:100%}
}
</style>


<form method="POST" class="pf-wrap">

    <!-- HERO -->
    <div class="pf-hero">
        <div class="pf-avatar"><?= e($initials) ?></div>
        <div>
            <h2><?= e($user["full_name"]) ?></h2>
            <p>@<?= e($user["username"]) ?></p>
        </div>
    </div>


    <!-- PERSONAL INFORMATION -->
    <div class="pf-card">
        <h3>Personal Information</h3>
        <span class="muted-text">Leave a field blank to keep the current value.</span>

        <div class="pf-grid">

            <div class="pf-field">
                <label for="full_name">Full Name</label>
                <input id="full_name" name="full_name" value="" autocomplete="off">
            </div>

            <div class="pf-field">
                <label for="username">Username</label>
                <input id="username" value="" disabled>
                <small>Used for logging in. Cannot be changed.</small>
            </div>

            <div class="pf-field">
                <label for="contact_number">Contact Number</label>
                <input id="contact_number" name="contact_number" value="">
            </div>

            <div class="pf-field">
                <label for="address">Address</label>
                <input id="address" name="address" value="">
            </div>

        </div>
    </div>


    <!-- SECURITY -->
    <div class="pf-card">
        <h3>Security</h3>
        <span class="muted-text">Set a new password only if you want to change it.</span>

        <div class="pf-grid">
            <div class="pf-field">
                <label for="password">New Password</label>
                <input id="password" type="password" name="password" placeholder="Leave blank to keep current" autocomplete="new-password">
                <small>Leave this empty if you don't want to change your password.</small>
            </div>
        </div>
    </div>


    <!-- ACTIONS -->
    <div class="pf-actions">
        <a href="items.php" class="btn">Cancel</a>
        <button type="submit" class="btn primary">Save Changes</button>
    </div>

</form>


<?php require "../partials/footer.php"; ?>