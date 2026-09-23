<?php
require_once "../db.php";
$page_title = "Customer Management";
$active = "customers";

if (isset($_GET["delete"])) {
    $id = (int)$_GET["delete"];
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

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = (int)($_POST["user_id"] ?? 0);
    $name = trim($_POST["full_name"] ?? "");
    $username = trim($_POST["username"] ?? "");
    $contact = trim($_POST["contact_number"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($id > 0) {
        if ($password !== "") {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, contact_number=?, address=?, password=? WHERE user_id=? AND role='user'");
            $stmt->bind_param("sssssi", $name,$username,$contact,$address,$hash,$id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET full_name=?, username=?, contact_number=?, address=? WHERE user_id=? AND role='user'");
            $stmt->bind_param("ssssi", $name,$username,$contact,$address,$id);
        }
        $stmt->execute();
        flash("success", "Customer updated.");
    } else {
        $hash = password_hash($password ?: "user123", PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (full_name, username, password, role, contact_number, address) VALUES (?,?,?,?,?,?)");
        $role = "user";
        $stmt->bind_param("ssssss", $name,$username,$hash,$role,$contact,$address);
        if ($stmt->execute()) flash("success", "Customer added.");
        else flash("error", "Username may already exist.");
    }
    header("Location: customers.php"); exit;
}

$edit = null;
if (isset($_GET["edit"])) {
    $id=(int)$_GET["edit"];
    $stmt=$conn->prepare("SELECT * FROM users WHERE user_id=? AND role='user'");
    $stmt->bind_param("i",$id); $stmt->execute();
    $edit=$stmt->get_result()->fetch_assoc();
}

require "../partials/admin_header.php";
$customers=$conn->query("SELECT * FROM users WHERE role='user' ORDER BY user_id DESC");
?>
<div class="two-col">
<section class="panel">
    <div class="panel-head"><h2><?= $edit ? "Edit Customer" : "Add Customer" ?></h2></div>
    <form method="POST" class="form-grid">
        <input type="hidden" name="user_id" value="<?= e($edit["user_id"] ?? 0) ?>">
        <label>Full Name<input name="full_name" value="<?= e($edit["full_name"] ?? "") ?>" required></label>
        <label>Username<input name="username" value="<?= e($edit["username"] ?? "") ?>" required></label>
        <label>Contact Number<input name="contact_number" value="<?= e($edit["contact_number"] ?? "") ?>"></label>
        <label>Address<input name="address" value="<?= e($edit["address"] ?? "") ?>"></label>
        <label>Password <?= $edit ? "(leave blank to keep current)" : "" ?><input type="password" name="password" <?= $edit ? "" : "required" ?>></label>
        <div class="form-actions"><button class="btn primary">Save Customer</button><?php if($edit): ?><a class="btn" href="customers.php">Cancel</a><?php endif; ?></div>
    </form>
</section>
<section class="panel">
    <div class="panel-head"><h2>Customers</h2></div>
    <div class="table-wrap"><table>
    <thead><tr><th>Name</th><th>Username</th><th>Contact</th><th>Address</th><th>Actions</th></tr></thead>
    <tbody>
    <?php while($c=$customers->fetch_assoc()): ?>
    <tr><td><?= e($c["full_name"]) ?></td><td><?= e($c["username"]) ?></td><td><?= e($c["contact_number"]) ?></td><td><?= e($c["address"]) ?></td>
    <td><a class="icon-btn edit" href="?edit=<?= $c["user_id"] ?>">✎</a><a class="icon-btn delete" onclick="return confirm('Delete customer?')" href="?delete=<?= $c["user_id"] ?>">✕</a></td></tr>
    <?php endwhile; ?>
    </tbody></table></div>
</section>
</div>
<?php require "../partials/footer.php"; ?>
