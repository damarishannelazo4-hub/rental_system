<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$error = "";
$success = "";
$uid = (int)$_SESSION["user_id"];

// Kunin ang kasalukuyang data (para sa mga fields na iniwang blangko)
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ? LIMIT 1");
$stmt->bind_param("i", $uid);
$stmt->execute();
$me = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first    = trim($_POST["first_name"] ?? "");
    $middle   = trim($_POST["middle_name"] ?? "");
    $last     = trim($_POST["last_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $contact  = trim($_POST["contact_number"] ?? "");
    $address  = trim($_POST["address"] ?? "");

    $current  = $_POST["current_password"] ?? "";
    $new      = $_POST["new_password"] ?? "";
    $confirm  = $_POST["confirm_password"] ?? "";

    // Blangko = panatilihin ang dating laman
    if ($first === "")    $first    = $me["first_name"] ?: $me["full_name"];
    if ($middle === "")   $middle   = $me["middle_name"] ?? "";
    if ($last === "")     $last     = $me["last_name"] ?? "";
    if ($username === "") $username = $me["username"];
    if ($contact === "")  $contact  = $me["contact_number"] ?? "";
    if ($address === "")  $address  = $me["address"] ?? "";

    $taken = false;
    if ($username !== $me["username"]) {
        $check = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id <> ? LIMIT 1");
        $check->bind_param("si", $username, $uid);
        $check->execute();
        $taken = (bool)$check->get_result()->fetch_assoc();
        $check->close();
    }

    if ($taken) {
        $error = "That username is already taken.";
    } else {
        $changePassword = ($current !== "" || $new !== "" || $confirm !== "");

        if ($changePassword) {
            if (!password_verify($current, $me["password"])) {
                $error = "Current password is incorrect.";
            } elseif (strlen($new) < 6) {
                $error = "New password must be at least 6 characters.";
            } elseif ($new !== $confirm) {
                $error = "New password and confirmation do not match.";
            }
        }

        if ($error === "") {
            $full = build_full_name($first, $middle, $last);

            if ($changePassword) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, first_name=?, middle_name=?, last_name=?, username=?, contact_number=?, address=?, password=? WHERE user_id=?");
                $stmt->bind_param("ssssssssi", $full, $first, $middle, $last, $username, $contact, $address, $hash, $uid);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, first_name=?, middle_name=?, last_name=?, username=?, contact_number=?, address=? WHERE user_id=?");
                $stmt->bind_param("sssssssi", $full, $first, $middle, $last, $username, $contact, $address, $uid);
            }

            if ($stmt->execute()) {
                $_SESSION["username"] = $username;
                $success = "Profile updated.";
            } else {
                $error = "Could not update profile: " . $conn->error;
            }
            $stmt->close();
        }
    }
}

$page_title = "My Profile";
$active = "";

/* Current values for display (hero + placeholders) */
$displayName = trim($me["full_name"] ?? "");
$initials = "";
foreach (preg_split('/\s+/', $displayName) as $part) {
    if ($part !== "" && strlen($initials) < 2) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }
}
if ($initials === "") {
    $initials = "A";
}

require "../partials/admin_header.php";
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

<form method="POST" class="pf-wrap" autocomplete="off">

    <?php if ($error): ?>
        <div class="alert danger-alert" style="margin:0;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success-alert" style="margin:0;"><?= e($success) ?></div>
    <?php endif; ?>


    <!-- HERO -->
    <div class="pf-hero">
        <div class="pf-avatar"><?= e($initials) ?></div>
        <div>
            <h2><?= e($displayName) ?></h2>
            <p>@<?= e($me["username"]) ?></p>
        </div>
    </div>


    <!-- PERSONAL INFORMATION -->
    <div class="pf-card">
        <h3>Personal Information</h3>
        <span class="muted-text">Leave a field blank to keep the current value.</span>

        <div class="pf-grid">

            <div class="pf-field">
                <label for="first_name">First Name</label>
                <input id="first_name" type="text" name="first_name" autocomplete="off">
            </div>

            <div class="pf-field">
                <label for="middle_name">Middle Name (optional)</label>
                <input id="middle_name" type="text" name="middle_name" autocomplete="off">
            </div>

            <div class="pf-field">
                <label for="last_name">Last Name</label>
                <input id="last_name" type="text" name="last_name" autocomplete="off">
            </div>

            <div class="pf-field">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" autocomplete="off">
                <small>Used for logging in.</small>
            </div>

            <div class="pf-field">
                <label for="contact_number">Contact Number</label>
                <input id="contact_number" type="text" name="contact_number" autocomplete="off">
            </div>

            <div class="pf-field">
                <label for="address">Address</label>
                <input id="address" type="text" name="address" autocomplete="off">
            </div>

        </div>
    </div>


    <!-- SECURITY -->
    <div class="pf-card">
        <h3>Security</h3>
        <span class="muted-text">Fill these in only if you want to change your password.</span>

        <div class="pf-grid">

            <div class="pf-field">
                <label for="current_password">Current Password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="new-password">
            </div>

            <div class="pf-field">
                <label for="new_password">New Password</label>
                <input id="new_password" type="password" name="new_password" autocomplete="new-password">
                <small>At least 6 characters.</small>
            </div>

            <div class="pf-field">
                <label for="confirm_password">Confirm New Password</label>
                <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password">
            </div>

        </div>
    </div>


    <!-- ACTIONS -->
    <div class="pf-actions">
        <a href="dashboard.php" class="btn">Cancel</a>
        <button type="submit" class="btn primary">Save Changes</button>
    </div>

</form>

<?php require "../partials/footer.php"; ?>