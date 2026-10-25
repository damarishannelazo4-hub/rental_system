<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* =========================
   COMBO SETTINGS
   A combo = 2 or more different items picked together.
   Price = total per-day price of the items minus the discount.
========================= */

const COMBO_DISCOUNT_PERCENT = 10;
const COMBO_MIN_ITEMS        = 2;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function require_csrf() {
    $token = $_POST['csrf_token'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($token)
        || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid request token. Refresh the page and try again.');
    }
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit;
    }
}

function require_role($role) {
    require_login();
    if (($_SESSION['role'] ?? '') !== $role) {
        header("Location: ../index.php");
        exit;
    }
}

function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function status_badge($status) {
    $map = [
        'Available' => 'success',
        'Pending' => 'warning',
        'Approved' => 'info',
        'Rejected' => 'danger',
        'Ongoing' => 'info',
        'Returned' => 'success',
        'Cancelled' => 'danger',
        'Completed' => 'success',
        'Rented' => 'warning'
    ];
    return '<span class="badge ' . ($map[$status] ?? 'muted') . '">' . e($status) . '</span>';
}

function short_date($d) {
    return date("M j, Y", strtotime($d));
}

/* Only allow files inside the uploads folder */
function upload_url($path)
{
    $path = (string)$path;
    if ($path === "" || strpos($path, "uploads/") !== 0 || strpos($path, "..") !== false) {
        return "";
    }
    return "../" . $path;
}

function go($url) {
    header("Location: $url");
    exit;
}

/* =========================
   NAMES
========================= */

// Use this wherever an account is created, then store the result in users.full_name too
function build_full_name($first, $middle, $last) {
    return trim(preg_replace('/\s+/', ' ', trim($first) . ' ' . trim($middle) . ' ' . trim($last)));
}

/* =========================
   COLORS
   Items that need a color choice (same list as admin/walkin.php).
   Items not listed here have no color.
========================= */

function color_options($itemName) {
    static $map = [
        "Monoblock Chair"    => ["White", "Black", "Brown", "Red", "Blue", "Yellow"],
        "Backdrop"           => ["White", "Black", "Red", "Blue", "Pink", "Yellow", "Brown", "Gray"],
        "Pleated Tablecloth" => ["White", "Black", "Red", "Blue", "Pink"],
        "Tent Square"        => ["White", "Green", "Blue", "Yellow"]
    ];

    return $map[trim((string)$itemName)] ?? [];
}

/* =========================
   STOCK
   available = total_qty
             - quantity of rentals still 'Ongoing'
             - quantity of reservations still 'Pending'
   (an Approved reservation already became a rental, so it is
    counted through the rentals table, not twice)
   Items inside a combo are saved as normal reservation rows,
   so they are already counted here. Color does not change stock.
========================= */

function get_items_with_stock($conn, array $ids = []) {
    $where = "";
    if ($ids) {
        $ids = array_map('intval', $ids);
        $where = "WHERE i.item_id IN (" . implode(",", $ids) . ")";
    }

    $sql = "
        SELECT i.*,
          GREATEST(0, i.total_qty
            - COALESCE((
                SELECT SUM(r.quantity)
                FROM rentals r
                WHERE r.item_id = i.item_id AND r.status = 'Ongoing'
              ), 0)
            - COALESCE((
                SELECT SUM(rs.quantity)
                FROM reservations rs
                WHERE rs.item_id = i.item_id AND rs.status = 'Pending'
              ), 0)
          ) AS available_qty
        FROM items i
        $where
        ORDER BY i.item_id DESC";

    return $conn->query($sql);
}

/* =========================
   COMBO PRICE HELPERS
========================= */

// How much the discount takes off (per day)
function combo_discount_amount($perDayTotal) {
    return round((float)$perDayTotal * COMBO_DISCOUNT_PERCENT / 100, 2);
}

/* =========================
   RESERVATION DRAFT
   $_SESSION['draft'][item_id] = qty
       ordinary items
   $_SESSION['draft_colors'][item_id] = color
       color of the ordinary item (only for items with colors)

   $_SESSION['draft_combos'][n] = [item_id => qty, ...]
       combos built by the user (n = 1, 2, 3 ...)
   $_SESSION['draft_combo_colors'][n][item_id] = color
========================= */

/* Ordinary items only (also drops keys left over from older versions) */

function draft_get() {
    $out = [];

    foreach (($_SESSION['draft'] ?? []) as $k => $q) {
        if (is_int($k) && (int)$q > 0) {
            $out[$k] = (int)$q;
        }
    }

    return $out;
}

function draft_set($item_id, $qty) {
    $item_id = (int)$item_id;
    $qty = (int)$qty;

    if ($qty <= 0) {
        unset($_SESSION['draft'][$item_id], $_SESSION['draft_colors'][$item_id]);
    } else {
        $_SESSION['draft'][$item_id] = $qty;
    }
}

function draft_colors_get() {
    return $_SESSION['draft_colors'] ?? [];
}

// An empty color removes it
function draft_color_set($item_id, $color) {
    $item_id = (int)$item_id;
    $color   = trim((string)$color);

    if ($color === '') {
        unset($_SESSION['draft_colors'][$item_id]);
    } else {
        $_SESSION['draft_colors'][$item_id] = $color;
    }
}

/* Combos */

function draft_combos_get() {
    return $_SESSION['draft_combos'] ?? [];
}

function draft_combo_colors_get() {
    return $_SESSION['draft_combo_colors'] ?? [];
}

// Returns the new combo number, or null if it has too few different items
// $colors = [item_id => color] (optional)
function draft_combo_add(array $items, array $colors = []) {

    $clean = [];

    foreach ($items as $item_id => $qty) {
        $item_id = (int)$item_id;
        $qty     = (int)$qty;

        if ($item_id > 0 && $qty > 0) {
            $clean[$item_id] = $qty;
        }
    }

    if (count($clean) < COMBO_MIN_ITEMS) {
        return null;
    }

    $combos = draft_combos_get();
    $n = $combos ? max(array_keys($combos)) + 1 : 1;

    $_SESSION['draft_combos'][$n] = $clean;

    foreach ($clean as $item_id => $qty) {
        $c = trim((string)($colors[$item_id] ?? ''));

        if ($c !== '') {
            $_SESSION['draft_combo_colors'][$n][$item_id] = $c;
        }
    }

    return $n;
}

// Change the pcs of one item inside a combo (pcs must be at least 1)
function draft_combo_set($n, $item_id, $qty) {
    $n       = (int)$n;
    $item_id = (int)$item_id;
    $qty     = (int)$qty;

    if ($qty >= 1 && isset($_SESSION['draft_combos'][$n][$item_id])) {
        $_SESSION['draft_combos'][$n][$item_id] = $qty;
    }
}

// Change the color of one item inside a combo (empty removes it)
function draft_combo_color_set($n, $item_id, $color) {
    $n       = (int)$n;
    $item_id = (int)$item_id;
    $color   = trim((string)$color);

    if (!isset($_SESSION['draft_combos'][$n][$item_id])) {
        return;
    }

    if ($color === '') {
        unset($_SESSION['draft_combo_colors'][$n][$item_id]);
    } else {
        $_SESSION['draft_combo_colors'][$n][$item_id] = $color;
    }
}

function draft_combo_remove($n) {
    unset($_SESSION['draft_combos'][(int)$n], $_SESSION['draft_combo_colors'][(int)$n]);
}

/* Everything the draft needs, per item: ordinary pcs + pcs inside combos */

function draft_need() {
    $need = draft_get();

    foreach (draft_combos_get() as $items) {
        foreach ($items as $item_id => $qty) {
            $need[(int)$item_id] = ($need[(int)$item_id] ?? 0) + (int)$qty;
        }
    }

    return $need;
}

function draft_clear() {
    unset(
        $_SESSION['draft'],
        $_SESSION['draft_colors'],
        $_SESSION['draft_combos'],
        $_SESSION['draft_combo_colors']
    );
}
?>