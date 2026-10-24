<?php
/* Included by reserve.php when action = submit */


/* =========================================================
   GET RESERVATION DRAFT
========================================================= */

$draft = draft_get();

if (!$draft) {

    flash(
        'draft',
        "Add at least one item first."
    );

    go("items.php");
}


/* =========================================================
   GET DATES
========================================================= */

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

    flash(
        'draft',
        "Please enter valid rental and return dates."
    );

    go("items.php#your-reservation");
}


$days = max(
    1,
    (int)round(($t2 - $t1) / 86400)
);


$uid = (int)$_SESSION["user_id"];


/* =========================================================
   VALID ID CHECK
========================================================= */

if (
    !isset($_FILES["id_photo"]) ||
    $_FILES["id_photo"]["error"] !== UPLOAD_ERR_OK
) {

    flash(
        'draft',
        "Please upload or take a photo of your valid ID before submitting."
    );

    go("items.php#your-reservation");
}


$idFile = $_FILES["id_photo"];


/* =========================================================
   CHECK FILE SIZE
========================================================= */

if ($idFile["size"] <= 0) {

    flash(
        'draft',
        "The ID photo is empty or invalid."
    );

    go("items.php#your-reservation");
}


if ($idFile["size"] > 5 * 1024 * 1024) {

    flash(
        'draft',
        "The ID photo must not be larger than 5 MB."
    );

    go("items.php#your-reservation");
}


/* =========================================================
   CHECK THAT FILE IS REALLY AN IMAGE
========================================================= */

$imageInfo = @getimagesize(
    $idFile["tmp_name"]
);

if ($imageInfo === false) {

    flash(
        'draft',
        "The uploaded file is not a valid image."
    );

    go("items.php#your-reservation");
}


/* =========================================================
   CHECK MIME TYPE
========================================================= */

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file(
    $idFile["tmp_name"]
);


$allowedMime = [
    "image/jpeg" => "jpg",
    "image/png"  => "png"
];


if (!isset($allowedMime[$mime])) {

    flash(
        'draft',
        "Only JPG, JPEG, and PNG ID photos are allowed."
    );

    go("items.php#your-reservation");
}


/* =========================================================
   CREATE UPLOAD FOLDER
========================================================= */

/*
 * Main uploads folder:
 *
 * C:\xampp\htdocs\rental_system\uploads
 */

$uploadBase =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "uploads";


/*
 * ID folder:
 *
 * C:\xampp\htdocs\rental_system\uploads\ids
 */

$uploadDir =
    $uploadBase .
    DIRECTORY_SEPARATOR .
    "ids" .
    DIRECTORY_SEPARATOR;


/*
 * Create uploads folder first.
 */

if (!is_dir($uploadBase)) {

    @mkdir(
        $uploadBase,
        0775
    );
}


/*
 * Create ids folder second.
 */

if (
    is_dir($uploadBase) &&
    !is_dir($uploadDir)
) {

    @mkdir(
        $uploadDir,
        0775
    );
}


/*
 * Make sure the folder really exists.
 */

if (!is_dir($uploadDir)) {

    flash(
        'draft',
        "Unable to create the ID upload folder. Please make sure this folder exists: " .
        $uploadDir
    );

    go("items.php#your-reservation");
}


/*
 * Check if the folder is writable.
 */

if (!is_writable($uploadDir)) {

    flash(
        'draft',
        "The ID upload folder is not writable. Path: " .
        $uploadDir
    );

    go("items.php#your-reservation");
}


/* =========================================================
   CREATE UNIQUE FILE NAME
========================================================= */

$extension =
    $allowedMime[$mime];


$fileName =
    "id_" .
    $uid .
    "_" .
    date("Ymd_His") .
    "_" .
    bin2hex(random_bytes(5)) .
    "." .
    $extension;


$targetPath =
    $uploadDir .
    $fileName;


/* =========================================================
   SAVE ID PHOTO
========================================================= */

if (!move_uploaded_file(
    $idFile["tmp_name"],
    $targetPath
)) {

    flash(
        'draft',
        "Failed to save the ID photo. Please try again."
    );

    go("items.php#your-reservation");
}


/*
 * This is the path saved in the database.
 *
 * Example:
 *
 * uploads/ids/id_1_20261005_153000_a1b2c3d4e5.jpg
 */

$idPhotoPath =
    "uploads/ids/" .
    $fileName;


/* =========================================================
   CREATE ORDER REFERENCE
========================================================= */

$ref =
    "R" .
    date("ymd") .
    strtoupper(
        substr(
            bin2hex(random_bytes(3)),
            0,
            5
        )
    );


/* =========================================================
   GET ITEM IDS
========================================================= */

$ids = array_keys($draft);

sort($ids);


/* =========================================================
   START DATABASE TRANSACTION
========================================================= */

$conn->begin_transaction();


try {


    /* =====================================================
       LOCK ITEMS
    ===================================================== */

    $idList =
        implode(
            ",",
            array_map(
                "intval",
                $ids
            )
        );


    $conn->query(
        "SELECT item_id
         FROM items
         WHERE item_id IN ($idList)
         FOR UPDATE"
    );


    /* =====================================================
       GET CURRENT STOCK
    ===================================================== */

    $stock = [];


    $res =
        get_items_with_stock(
            $conn,
            $ids
        );


    while ($r = $res->fetch_assoc()) {

        $stock[
            (int)$r["item_id"]
        ] = $r;
    }


    /* =====================================================
       PREPARE INSERT
    ===================================================== */

    $stmt =
        $conn->prepare(
            "INSERT INTO reservations
                (
                    user_id,
                    item_id,
                    quantity,
                    rental_date,
                    return_date,
                    status,
                    order_ref,
                    id_photo
                )
             VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    'Pending',
                    ?,
                    ?
                )"
        );


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare reservation."
        );
    }


    /* =====================================================
       INSERT EACH ITEM
    ===================================================== */

    foreach ($ids as $iid) {


        $q =
            (int)$draft[$iid];


        $it =
            $stock[$iid] ?? null;


        /* ITEM EXISTS */

        if (!$it) {

            throw new Exception(
                "An item in your list no longer exists."
            );
        }


        /* STOCK CHECK */

        if (
            $q >
            (int)$it["available_qty"]
        ) {

            throw new Exception(
                "Not enough stock for " .
                $it["item_name"] .
                " (available: " .
                (int)$it["available_qty"] .
                ")."
            );
        }


        /* MAX RENTAL DAYS */

        if (
            $days >
            (int)$it["max_days"]
        ) {

            throw new Exception(
                $it["item_name"] .
                " can only be rented for up to " .
                (int)$it["max_days"] .
                " day(s)."
            );
        }


        /*
         * The same ID photo and order reference
         * are saved for every item belonging
         * to this reservation.
         */

        $stmt->bind_param(
            "iiissss",
            $uid,
            $iid,
            $q,
            $rental_date,
            $return_date,
            $ref,
            $idPhotoPath
        );


        $stmt->execute();

    }


    $stmt->close();


    /* =====================================================
       COMMIT
    ===================================================== */

    $conn->commit();


    /* =====================================================
       CLEAR DRAFT
    ===================================================== */

    draft_clear();


    /* =====================================================
       SUCCESS
    ===================================================== */

    flash(
        'success',
        "Reservation submitted (Ref: $ref). Please wait for admin approval."
    );


    go("reservations.php");


} catch (Exception $ex) {


    /* =====================================================
       ROLLBACK
    ===================================================== */

    $conn->rollback();


    /*
     * Delete the uploaded ID if the
     * reservation was not successfully saved.
     */

    if (
        file_exists($targetPath)
    ) {

        @unlink($targetPath);
    }


    flash(
        'draft',
        $ex->getMessage()
    );


    go("items.php#your-reservation");
}