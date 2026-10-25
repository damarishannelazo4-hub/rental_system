<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Customer Management";
$active = "customers";

if ($_SERVER["REQUEST_METHOD"] === "POST" || isset($_GET["delete"])) {
    require_csrf();
}

if (isset($_POST["delete"])) {
    $id = (int)$_POST["delete"];
    if ($id === (int)$_SESSION["user_id"]) {
        flash("error", "You cannot delete your own admin account.");
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE user_id=? AND role='user'");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        flash("success", "Customer deleted.");
    }
    header("Location: customers.php"); exit;
}

// Update lang. Sa Create Account na gagawa ng bagong customer.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id       = (int)($_POST["user_id"] ?? 0);
    $first    = trim($_POST["first_name"] ?? "");
    $middle   = trim($_POST["middle_name"] ?? "");
    $last     = trim($_POST["last_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $contact  = trim($_POST["contact_number"] ?? "");
    $address  = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($id > 0) {
        if ($first === "" || $last === "" || $username === "") {
            flash("error", "First name, last name and username are required.");
        } else {
            $full = build_full_name($first, $middle, $last);

            if ($password !== "") {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET full_name=?, first_name=?, middle_name=?, last_name=?, username=?, contact_number=?, address=?, password=? WHERE user_id=? AND role='user'");
                $stmt->bind_param("ssssssssi", $full, $first, $middle, $last, $username, $contact, $address, $hash, $id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET full_name=?, first_name=?, middle_name=?, last_name=?, username=?, contact_number=?, address=? WHERE user_id=? AND role='user'");
                $stmt->bind_param("sssssssi", $full, $first, $middle, $last, $username, $contact, $address, $id);
            }

            if ($stmt->execute()) flash("success", "Customer updated.");
            else flash("error", "Username may already exist.");
        }
    }
    header("Location: customers.php"); exit;
}

$edit = null;
if (isset($_GET["edit"])) {
    $id = (int)$_GET["edit"];
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id=? AND role='user'");
    $stmt->bind_param("i", $id); $stmt->execute();
    $edit = $stmt->get_result()->fetch_assoc();
}

require "../partials/admin_header.php";
$customers = $conn->query("SELECT * FROM users WHERE role='user' ORDER BY user_id DESC");
?>
<div class="<?= $edit ? 'two-col' : '' ?>">
<?php if ($edit): ?>
<section class="panel">
    <div class="panel-head"><h2>Edit Customer</h2></div>
    <form method="POST" class="form-grid" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="user_id" value="<?= (int)$edit["user_id"] ?>">
        <label>First Name<input name="first_name" value="<?= e($edit["first_name"] ?: $edit["full_name"]) ?>" required></label>
        <label>Middle Name (optional)<input name="middle_name" value="<?= e($edit["middle_name"] ?? "") ?>"></label>
        <label>Last Name<input name="last_name" value="<?= e($edit["last_name"] ?? "") ?>" required></label>
        <label>Username<input name="username" value="<?= e($edit["username"]) ?>" autocomplete="off" required></label>
        <label>Contact Number<input name="contact_number" value="<?= e($edit["contact_number"] ?? "") ?>"></label>
        <label>Address<input name="address" value="<?= e($edit["address"] ?? "") ?>"></label>
        <label>Password (leave blank to keep current)<input type="password" name="password" autocomplete="new-password"></label>
        <div class="form-actions">
            <button class="btn primary">Save Changes</button>
            <a class="btn" href="customers.php">Cancel</a>
        </div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <div class="panel-head">
        <h2>Customers</h2>
    </div>
    <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Username</th><th>Contact</th><th>Address</th><th>Actions</th></tr></thead>
    <tbody>
    <?php while ($c = $customers->fetch_assoc()): ?>
    <tr>
        <td><?= e($c["full_name"]) ?></td>
        <td><?= e($c["username"]) ?></td>
        <td><?= e($c["contact_number"]) ?></td>
        <td><?= e($c["address"]) ?></td>
        <td>
            <a class="icon-btn edit" href="?edit=<?= (int)$c["user_id"] ?>" aria-label="Edit customer"><iconify-icon icon="solar:pen-outline" aria-hidden="true"></iconify-icon></a>
            <form method="POST" style="display:inline" onsubmit="return confirm('Delete customer?')">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button class="icon-btn delete" name="delete" value="<?= (int)$c["user_id"] ?>" aria-label="Delete customer"><iconify-icon icon="solar:trash-bin-trash-outline" aria-hidden="true"></iconify-icon></button>
            </form>
        </td>
    </tr>
    <?php endwhile; ?>
    </tbody></table></div>
</section>
</div>
<?php require "../partials/footer.php"; ?>