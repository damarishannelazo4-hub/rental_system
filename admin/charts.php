<?php
require_once "../db.php";
require_once "../functions.php";

require_role("admin");

$page_title = "Charts";
$active = "";

require "../partials/admin_header.php";

/* the four graphs */
require "dashboard_charts.php";

require "../partials/footer.php";