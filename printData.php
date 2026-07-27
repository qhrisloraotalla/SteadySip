<?php
header('Content-Type: application/json'); 
error_reporting(0); // hide notices/warnings

include "connection.php";

// instantiate Database
$db = new Database();
$conn = $db->conn;

// GET FILTERS
$type = $_GET['type'] ?? '';
$value = $_GET['value'] ?? '';

$output = [];

// Validate inputs
if (!$type || !$value) {
    echo json_encode(['error' => 'Missing type or value']);
    exit;
}

// ========================= ORDERS
$sqlOrders = "";
if ($type == "daily") {
    $sqlOrders = "
        SELECT s.id AS order_id, s.order_date, s.customer_name, s.payment_method, s.type, s.discount, SUM(si.quantity * mi.price) AS total_amount
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale' AND DATE(s.order_date)='$value'
        GROUP BY s.id
    ";
} elseif ($type == "weekly") {
    list($year, $week) = explode("-W", $value);
    $sqlOrders = "
        SELECT s.id AS order_id, s.order_date, s.customer_name, s.payment_method, s.type, s.discount, SUM(si.quantity * mi.price) AS total_amount
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND YEAR(s.order_date) = '$year'
          AND WEEK(s.order_date, 1) = '$week'
        GROUP BY s.id
    ";
} elseif ($type == "monthly") {
    $sqlOrders = "
        SELECT s.id AS order_id, s.order_date, s.customer_name, s.payment_method, s.type, s.discount, SUM(si.quantity * mi.price) AS total_amount
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND DATE_FORMAT(s.order_date,'%Y-%m')='$value'
        GROUP BY s.id
    ";
} elseif ($type == "yearly") {
    $sqlOrders = "
        SELECT s.id AS order_id, s.order_date, s.customer_name, s.payment_method, s.type, s.discount, SUM(si.quantity * mi.price) AS total_amount
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND YEAR(s.order_date)='$value'
        GROUP BY s.id
    ";
}

// ========================= SALES TOTALS (for chart)
$sqlTotals = "";
if ($type == "daily") {
    $sqlTotals = "
        SELECT DATE(s.order_date) AS label, SUM(si.quantity * mi.price) AS total_sales
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale' AND DATE(s.order_date)='$value'
        GROUP BY DATE(s.order_date)
    ";
} elseif ($type == "weekly") {
    list($year, $week) = explode("-W", $value);
    $sqlTotals = "
        SELECT CONCAT(YEAR(s.order_date), '-W', WEEK(s.order_date,1)) AS label, SUM(si.quantity * mi.price) AS total_sales
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND YEAR(s.order_date)='$year'
          AND WEEK(s.order_date,1)='$week'
        GROUP BY WEEK(s.order_date,1)
    ";
} elseif ($type == "monthly") {
    $sqlTotals = "
        SELECT DATE_FORMAT(s.order_date,'%Y-%m') AS label, SUM(si.quantity * mi.price) AS total_sales
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND DATE_FORMAT(s.order_date,'%Y-%m')='$value'
        GROUP BY DATE_FORMAT(s.order_date,'%Y-%m')
    ";
} elseif ($type == "yearly") {
    $sqlTotals = "
        SELECT YEAR(s.order_date) AS label, SUM(si.quantity * mi.price) AS total_sales
        FROM sales s
        JOIN sale_items si ON si.sale_id = s.id
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE s.status='sale'
          AND YEAR(s.order_date)='$value'
        GROUP BY YEAR(s.order_date)
    ";
}

// Execute queries
$resOrders = $conn->query($sqlOrders);
$orders = [];
if ($resOrders) {
    while ($row = $resOrders->fetch_assoc()) {
        $orders[] = $row;
    }
}
$output['orders'] = $orders;

$resTotals = $conn->query($sqlTotals);
$labels = [];
$totals = [];
if ($resTotals) {
    while ($row = $resTotals->fetch_assoc()) {
        $labels[] = $row['label'];
        $totals[] = $row['total_sales'];
    }
}
$output['dates'] = $labels;
$output['totals'] = $totals;

// ========================= INVENTORY SPENDING
$sqlP = '';
switch ($type) {
    case "daily":
        $sqlP = "SELECT DATE(purchase_date) AS period, SUM(total_cost) AS total_spent
                 FROM purchases WHERE DATE(purchase_date)='$value' GROUP BY DATE(purchase_date)";
        break;
    case "weekly":
        list($year, $week) = explode("-W", $value);
        $sqlP = "SELECT CONCAT(YEAR(purchase_date), '-W', WEEK(purchase_date,1)) AS period, SUM(total_cost) AS total_spent
                 FROM purchases WHERE YEAR(purchase_date)='$year' AND WEEK(purchase_date,1)='$week'
                 GROUP BY YEARWEEK(purchase_date)";
        break;
    case "monthly":
        $sqlP = "SELECT DATE_FORMAT(purchase_date,'%Y-%m') AS period, SUM(total_cost) AS total_spent
                 FROM purchases WHERE DATE_FORMAT(purchase_date,'%Y-%m')='$value'
                 GROUP BY DATE_FORMAT(purchase_date,'%Y-%m')";
        break;
    case "yearly":
        $sqlP = "SELECT YEAR(purchase_date) AS period, SUM(total_cost) AS total_spent
                 FROM purchases WHERE YEAR(purchase_date)='$value'
                 GROUP BY YEAR(purchase_date)";
        break;
}

$resP = $conn->query($sqlP);
$periods = [];
$inventory = [];
if ($resP) {
    while ($row = $resP->fetch_assoc()) {
        $periods[] = $row['period'];
        $inventory[] = (float)$row['total_spent'];
    }
}
$output['periods'] = $periods;
$output['inventory_spending'] = $inventory;

// ========================= BEST SELLERS
$sqlBest = "
    SELECT mi.name, SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN menu_items mi ON si.menu_item_id = mi.id
    JOIN sales s ON si.sale_id = s.id
    WHERE s.status='sale'
    GROUP BY mi.id
    ORDER BY total_sold DESC
    LIMIT 5
";
$resBest = $conn->query($sqlBest);
$items = [];
$qty = [];
if ($resBest) {
    while ($row = $resBest->fetch_assoc()) {
        $items[] = $row['name'];
        $qty[] = (int)$row['total_sold'];
    }
}
$output['items'] = $items;
$output['quantities'] = $qty;

// ========================= LABEL/TITLE
$output['label_title'] = ucfirst($type) . " Report";

// RETURN JSON
echo json_encode($output, JSON_NUMERIC_CHECK);
exit;
?>
