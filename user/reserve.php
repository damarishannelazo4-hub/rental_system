<?php

require_once "../db.php";
require_once "../functions.php";

require_role("user");


/* =========================================================
   HELPER: save an uploaded JPG/PNG (max 5 MB)
   Returns relative path (e.g. uploads/ids/xxx.jpg) or null
========================================================= */

function save_image_upload(array $file, string $subdir, string $prefix, int $uid, string $label, string &$error): ?string
{
    if (!isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
        $error = "Please upload a valid $label before submitting your reservation.";
        return null;
    }

    if ($file["size"] > 5 * 1024 * 1024) {
        $error = "$label must not be larger than 5 MB.";
        return null;
    }

    if (@getimagesize($file["tmp_name"]) === false) {
        $error = "The uploaded $label is not a valid image.";
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file["tmp_name"]);

    $allowed = ["image/jpeg" => "jpg", "image/png" => "png"];

    if (!isset($allowed[$mime])) {
        $error = "Only JPG, JPEG, and PNG files are allowed for the $label.";
        return null;
    }

    $dir = dirname(__DIR__) . "/uploads/$subdir/";

    if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
        $error = "Unable to create the upload folder for the $label.";
        return null;
    }

    $name = $prefix . "_" . $uid . "_" . date("Ymd_His") . "_" . bin2hex(random_bytes(5)) . "." . $allowed[$mime];

    if (!move_uploaded_file($file["tmp_name"], $dir . $name)) {
        $error = "Failed to save the $label. Please try again.";
        return null;
    }

    return "uploads/$subdir/$name";
}

function remove_upload(?string $relPath): void
{
    if ($relPath) {
        $full = dirname(__DIR__) . "/" . $relPath;
        if (file_exists($full)) {
            @unlink($full);
        }
    }
}


/* =========================================================
   HELPER: items with stock, keyed by item_id
========================================================= */

function stock_of($conn, array $ids): array
{
    $out = [];

    if (!$ids) {
        return $out;
    }

    $res = get_items_with_stock($conn, $ids);

    while ($r = $res->fetch_assoc()) {
        $out[(int)$r["item_id"]] = $r;
    }

    return $out;
}


/* =========================================================
   DIRECT VISIT
========================================================= */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    go("items.php");
}


$action  = $_POST["action"] ?? "";
$item_id = (int)($_POST["item_id"] ?? 0);

$qtyRaw = $_POST["qty"] ?? 0;
$qty    = is_array($qtyRaw) ? 0 : (int)$qtyRaw;

/* single color (add / update an item) */
$colorRaw = $_POST["color"] ?? "";
$color    = is_string($colorRaw) ? trim($colorRaw) : "";


/* =========================================================
   CLEAR ALL
========================================================= */

if ($action === "clear") {

    draft_clear();

    flash('draft', "Reservation cleared.");

    go("items.php");
}


/* =========================================================
   REMOVE AN ITEM
========================================================= */

if ($action === "remove") {

    draft_set($item_id, 0);

    go("items.php#your-reservation");
}


/* =========================================================
   ADD / UPDATE AN ITEM
========================================================= */

if (in_array($action, ["add", "update"], true)) {

    /* pcs must be entered first (default is 0) */

    if ($action === "add" && $qty < 1) {
        flash('draft', "Please enter the pcs first before adding to your reservation.");
        go("items.php");
    }

    $stock = stock_of($conn, [$item_id]);
    $row   = $stock[$item_id] ?? null;

    if (!$row) {
        flash('draft', "Item not found.");
        go("items.php");
    }

    $name      = $row["item_name"];
    $available = (int)$row["available_qty"];
    $opts      = color_options($name);

    /* color is required for items that have colors */

    if ($opts && !in_array($color, $opts, true)) {
        flash('draft', "Please select a color for $name.");
        go("items.php#your-reservation");
    }

    /* pcs of this item already used inside combos */

    $needAll = draft_need();
    $draft   = draft_get();
    $current = $draft[$item_id] ?? 0;
    $inCombo = ($needAll[$item_id] ?? 0) - $current;

    /* one color per ordinary item */

    $savedColor = draft_colors_get()[$item_id] ?? "";

    if ($action === "add" && $current > 0 && $opts && $savedColor !== "" && $savedColor !== $color) {
        flash('draft', "$name is already in your reservation in $savedColor. Change its color below, or remove it first.");
        go("items.php#your-reservation");
    }

    $maxAllowed = max(0, $available - $inCombo);

    if ($maxAllowed <= 0) {
        draft_set($item_id, 0);
        flash('draft', "$name is out of stock.");
        go("items.php");
    }

    $newQty = ($action === "add")
        ? $current + max(1, $qty)
        : $qty;

    if ($newQty > $maxAllowed) {

        $newQty = $maxAllowed;

        flash('draft', "Only $maxAllowed pcs of $name available. Quantity adjusted.");

    } elseif ($action === "add") {

        flash('draft', "$name added to your reservation.");
    }

    draft_set($item_id, $newQty);
    draft_color_set($item_id, $opts ? $color : "");

    go("items.php#your-reservation");
}


/* =========================================================
   ADD A COMBO (built on the Browse Items page)
========================================================= */

if ($action === "add_combo") {

    $raw       = is_array($_POST["qty"] ?? null) ? $_POST["qty"] : [];
    $rawColors = is_array($_POST["color"] ?? null) ? $_POST["color"] : [];
    $picked    = [];

    foreach ($raw as $id => $q) {

        $id = (int)$id;
        $q  = (int)$q;

        if ($id > 0 && $q > 0) {
            $picked[$id] = $q;
        }
    }

    if (count($picked) < COMBO_MIN_ITEMS) {
        flash('draft', "A combo needs at least " . COMBO_MIN_ITEMS . " different items. Enter pcs for " . COMBO_MIN_ITEMS . " or more items.");
        go("items.php#build-combo");
    }

    $stock  = stock_of($conn, array_keys($picked));
    $need   = draft_need();
    $colors = [];

    foreach ($picked as $id => $q) {

        $row = $stock[$id] ?? null;

        if (!$row) {
            flash('draft', "An item in your combo was not found.");
            go("items.php#build-combo");
        }

        $free = (int)$row["available_qty"] - (int)($need[$id] ?? 0);

        if ($q > $free) {
            flash('draft', "Not enough stock for " . $row["item_name"] . " (available: " . max(0, $free) . ").");
            go("items.php#build-combo");
        }

        $opts = color_options($row["item_name"]);
        $c    = is_string($rawColors[$id] ?? null) ? trim($rawColors[$id]) : "";

        if ($opts && !in_array($c, $opts, true)) {
            flash('draft', "Please select a color for " . $row["item_name"] . ".");
            go("items.php#build-combo");
        }

        $colors[$id] = $opts ? $c : "";
    }

    draft_combo_add($picked, $colors);

    flash('draft', "Combo added (" . COMBO_DISCOUNT_PERCENT . "% off).");

    go("items.php#your-reservation");
}


/* =========================================================
   UPDATE / REMOVE A COMBO
========================================================= */

if ($action === "combo_update" || $action === "combo_remove") {

    $n      = (int)($_POST["combo"] ?? 0);
    $combos = draft_combos_get();

    if (!isset($combos[$n])) {
        go("items.php#your-reservation");
    }

    if ($action === "combo_remove") {
        draft_combo_remove($n);
        go("items.php#your-reservation");
    }

    $raw       = is_array($_POST["qty"] ?? null) ? $_POST["qty"] : [];
    $rawColors = is_array($_POST["color"] ?? null) ? $_POST["color"] : [];
    $need      = draft_need();
    $stock     = stock_of($conn, array_keys($combos[$n]));

    $adjusted = false;

    foreach ($combos[$n] as $iid => $cur) {

        $q   = max(1, (int)($raw[$iid] ?? $cur));
        $row = $stock[$iid] ?? null;

        if (!$row) {
            continue;
        }

        $others = (int)($need[$iid] ?? 0) - (int)$cur;
        $max    = max(1, (int)$row["available_qty"] - $others);

        if ($q > $max) {
            $q = $max;
            $adjusted = true;
        }

        draft_combo_set($n, $iid, $q);

        /* new color (kept as it was if the choice is not valid) */

        $opts = color_options($row["item_name"]);
        $c    = is_string($rawColors[$iid] ?? null) ? trim($rawColors[$iid]) : "";

        if ($opts && in_array($c, $opts, true)) {
            draft_combo_color_set($n, $iid, $c);
        }
    }

    if ($adjusted) {
        flash('draft', "Some pcs were adjusted to the available stock.");
    }

    go("items.php#your-reservation");
}


/* =========================================================
   SUBMIT RESERVATION
========================================================= */

if ($action === "submit") {

    /* LATEST PCS + COLORS FROM THE FORM
       items:  qty[id], color[id]
       combos: cq[n][id], cc[n][id] */

    if (is_array($_POST["qty"] ?? null)) {

        $now = draft_get();

        foreach ($_POST["qty"] as $k => $v) {

            $k = (int)$k;

            if (isset($now[$k]) && (int)$v >= 1) {
                draft_set($k, (int)$v);
            }
        }
    }

    if (is_array($_POST["color"] ?? null)) {

        $now = draft_get();

        foreach ($_POST["color"] as $k => $v) {

            $k = (int)$k;

            if (isset($now[$k])) {
                draft_color_set($k, is_string($v) ? $v : "");
            }
        }
    }

    if (is_array($_POST["cq"] ?? null)) {

        foreach ($_POST["cq"] as $n => $items) {

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $iid => $v) {
                draft_combo_set((int)$n, (int)$iid, max(1, (int)$v));
            }
        }
    }

    if (is_array($_POST["cc"] ?? null)) {

        foreach ($_POST["cc"] as $n => $items) {

            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $iid => $v) {
                draft_combo_color_set((int)$n, (int)$iid, is_string($v) ? $v : "");
            }
        }
    }


    /* CHECK DRAFT */

    $need = draft_need();

    if (!$need) {
        flash('draft', "Add at least one item first.");
        go("items.php");
    }


    /* DATES */

    $rental_date = $_POST["rental_date"] ?? "";
    $return_date = $_POST["return_date"] ?? "";

    $t1 = strtotime($rental_date);
    $t2 = strtotime($return_date);

    if (
        !$t1 ||
        !$t2 ||
        $rental_date < date("Y-m-d") ||
        $return_date < $rental_date
    ) {
        flash('draft', "Please enter valid rental and return dates.");
        go("items.php#your-reservation");
    }

    $days = max(1, (int)round(($t2 - $t1) / 86400));


    /* USER */

    $uid = (int)$_SESSION["user_id"];


    /* PAYMENT METHOD */

    $paymentMethod = $_POST["payment_method"] ?? "Cash";

    if (!in_array($paymentMethod, ["Cash", "Online"], true)) {
        $paymentMethod = "Cash";
    }

    $paymentRef = trim($_POST["payment_ref"] ?? "");

    if ($paymentMethod === "Online" && $paymentRef === "") {
        flash('draft', "Please enter the reference number of your online payment.");
        go("items.php#your-reservation");
    }

    if ($paymentMethod === "Cash") {
        $paymentRef = null;
    }

    $paymentStatus = ($paymentMethod === "Online") ? "For Verification" : "Unpaid";


    /* ID PHOTO (required) */

    $err = "";

    $idPhotoPath = save_image_upload(
        $_FILES["id_photo"] ?? [],
        "ids",
        "id",
        $uid,
        "ID photo",
        $err
    );

    if ($idPhotoPath === null) {
        flash('draft', $err);
        go("items.php#your-reservation");
    }


    /* PAYMENT PROOF (required only for Online) */

    $paymentProofPath = null;

    if ($paymentMethod === "Online") {

        $paymentProofPath = save_image_upload(
            $_FILES["payment_proof"] ?? [],
            "payments",
            "pay",
            $uid,
            "payment screenshot",
            $err
        );

        if ($paymentProofPath === null) {
            remove_upload($idPhotoPath);
            flash('draft', $err);
            go("items.php#your-reservation");
        }
    }


    /* ORDER REFERENCE */

    $ref =
        "R" .
        date("ymd") .
        strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));


    /* ITEM IDS */

    $ids = array_keys($need);
    sort($ids);


    /* TRANSACTION */

    $conn->begin_transaction();

    try {

        /* LOCK ITEMS */

        $idList = implode(",", array_map("intval", $ids));

        $conn->query(
            "SELECT item_id
             FROM items
             WHERE item_id IN ($idList)
             FOR UPDATE"
        );


        /* CHECK STOCK, MAX DAYS AND COLORS
           (pcs of ordinary items and pcs inside combos are added together) */

        $stock = stock_of($conn, $ids);

        foreach ($need as $iid => $total) {

            $it = $stock[$iid] ?? null;

            if (!$it) {
                throw new Exception("An item in your list no longer exists.");
            }

            if ($total > (int)$it["available_qty"]) {
                throw new Exception(
                    "Not enough stock for " . $it["item_name"] .
                    " (available: " . (int)$it["available_qty"] . ")."
                );
            }

            if ((int)$it["max_days"] > 0 && $days > (int)$it["max_days"]) {
                throw new Exception(
                    $it["item_name"] . " can only be rented for up to " .
                    (int)$it["max_days"] . " day(s)."
                );
            }
        }

        $colors  = draft_colors_get();
        $ccolors = draft_combo_colors_get();

        foreach (draft_get() as $iid => $q) {

            $opts = color_options($stock[$iid]["item_name"]);

            if ($opts && !in_array($colors[$iid] ?? "", $opts, true)) {
                throw new Exception("Please select a color for " . $stock[$iid]["item_name"] . ".");
            }
        }

        foreach (draft_combos_get() as $n => $items) {

            foreach ($items as $iid => $q) {

                $opts = color_options($stock[$iid]["item_name"]);

                if ($opts && !in_array($ccolors[$n][$iid] ?? "", $opts, true)) {
                    throw new Exception("Please select a color for " . $stock[$iid]["item_name"] . " in Combo " . (int)$n . ".");
                }
            }
        }


        /* INSERT RESERVATIONS
           - ordinary item : combo_id and combo_discount are NULL
           - combo item    : combo_id = combo number inside this order
                             (1, 2, 3 ...), combo_discount = % off
           - item_color    : only for items that have colors */

        $stmt = $conn->prepare(
            "INSERT INTO reservations
                (
                    user_id,
                    item_id,
                    quantity,
                    rental_date,
                    return_date,
                    status,
                    order_ref,
                    id_photo,
                    payment_method,
                    payment_status,
                    payment_ref,
                    payment_proof,
                    combo_id,
                    combo_discount,
                    item_color
                )
             VALUES
                (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );

        if (!$stmt) {
            throw new Exception("Unable to prepare reservation.");
        }

        $bItem  = 0;
        $bQty   = 0;
        $bCombo = null;
        $bDisc  = null;
        $bColor = null;

        $stmt->bind_param(
            "iiissssssssids",
            $uid,
            $bItem,
            $bQty,
            $rental_date,
            $return_date,
            $ref,
            $idPhotoPath,
            $paymentMethod,
            $paymentStatus,
            $paymentRef,
            $paymentProofPath,
            $bCombo,
            $bDisc,
            $bColor
        );


        /* Ordinary items */

        foreach (draft_get() as $iid => $q) {

            $bItem  = (int)$iid;
            $bQty   = (int)$q;
            $bCombo = null;
            $bDisc  = null;
            $bColor = color_options($stock[$iid]["item_name"])
                ? ($colors[$iid] ?? null)
                : null;

            if (!$stmt->execute()) {
                throw new Exception("Unable to save your reservation.");
            }
        }


        /* Combos */

        $comboNo = 0;

        foreach (draft_combos_get() as $n => $items) {

            $comboNo++;

            foreach ($items as $iid => $q) {

                $bItem  = (int)$iid;
                $bQty   = (int)$q;
                $bCombo = $comboNo;
                $bDisc  = (float)COMBO_DISCOUNT_PERCENT;
                $bColor = color_options($stock[$iid]["item_name"])
                    ? ($ccolors[$n][$iid] ?? null)
                    : null;

                if (!$stmt->execute()) {
                    throw new Exception("Unable to save your reservation.");
                }
            }
        }

        $stmt->close();

        $conn->commit();

        draft_clear();

        if ($paymentMethod === "Cash") {
            $msg = "Reservation submitted (Ref: $ref). Please wait for admin approval. Pay in cash at the shop when you pick up your items.";
        } else {
            $msg = "Reservation submitted (Ref: $ref). Your online payment will be verified by the admin.";
        }

        flash('success', $msg);

        go("reservations.php");

    } catch (Throwable $ex) {

        $conn->rollback();

        /* Remove uploaded files if reservation failed */
        remove_upload($idPhotoPath);
        remove_upload($paymentProofPath);

        flash('draft', $ex->getMessage());

        go("items.php#your-reservation");
    }
}


/* =========================================================
   DEFAULT
========================================================= */

go("items.php");