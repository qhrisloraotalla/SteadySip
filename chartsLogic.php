<?php
include "sidebar.php";

$view = $_GET['view'] ?? 'daily';

$viewConfig = [
    'daily' => [
        'sales' => "
            SELECT DATE(order_date) AS label, SUM(total_amount) AS total_sales
            FROM sales
            WHERE status = 'sale'
            GROUP BY DATE(order_date)
            ORDER BY DATE(order_date)
        ",
        'purchases' => "
            SELECT DATE(purchase_date) AS period, SUM(total_cost) AS total_spent
            FROM purchases
            GROUP BY DATE(purchase_date)
            ORDER BY period
        ",
        'title' => "Daily Inventory Spending",
    ],

    'weekly' => [
        'sales' => "
            SELECT CONCAT(YEAR(order_date), '-W', WEEK(order_date)) AS label,
                   SUM(total_amount) AS total_sales
            FROM sales
            WHERE status = 'sale'
            GROUP BY YEAR(order_date), WEEK(order_date)
            ORDER BY YEAR(order_date), WEEK(order_date)
        ",
        'purchases' => "
            SELECT CONCAT(YEAR(purchase_date), '-W', WEEK(purchase_date)) AS period,
                   SUM(total_cost) AS total_spent
            FROM purchases
            GROUP BY YEARWEEK(purchase_date)
            ORDER BY period
        ",
        'title' => "Weekly Inventory Spending",
    ],

    'monthly' => [
        'sales' => "
            SELECT DATE_FORMAT(order_date, '%Y-%m') AS label, SUM(total_amount) AS total_sales
            FROM sales
            WHERE status = 'sale'
            GROUP BY YEAR(order_date), MONTH(order_date)
            ORDER BY YEAR(order_date), MONTH(order_date)
        ",
        'purchases' => "
            SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS period, SUM(total_cost) AS total_spent
            FROM purchases
            GROUP BY DATE_FORMAT(purchase_date, '%Y-%m')
            ORDER BY period
        ",
        'title' => "Monthly Inventory Spending",
    ]
];
$config = $viewConfig[$view] ?? $viewConfig['daily'];
//  FIRST CHART
    $result = $conn->query($config['sales']);

    $dates = [];
    $totals = [];

    while ($row = $result->fetch_assoc()) {
        $dates[] = $row['label'];
        $totals[] = $row['total_sales'];
    }

    $dates_json = json_encode($dates);
    $totals_json = json_encode($totals);

//  SECOND CHART
    $sql2 = "
        SELECT c.category AS category, SUM(si.subtotal) AS total_sales
        FROM sale_items si
        JOIN menu_items mi ON si.menu_item_id = mi.id
        JOIN categories c ON mi.category_id = c.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.status = 'sale'
        GROUP BY c.id
    ";
    $result2 = $conn->query($sql2);

    $categories = [];
    $sales = [];

    while ($row = $result2->fetch_assoc()) {
        $categories[] = $row['category'];
        $sales[] = $row['total_sales'];
    }

    $categories_json = json_encode($categories);
    $sales_json = json_encode($sales);

//  THIRD CHART
    $sql3 = "
        SELECT mi.name, SUM(si.quantity) AS total_sold
        FROM sale_items si
        JOIN menu_items mi ON si.menu_item_id = mi.id
        JOIN sales s ON si.sale_id = s.id
        WHERE s.status = 'sale'
        GROUP BY mi.id
        ORDER BY total_sold DESC
        LIMIT 5
    ";
    $result3 = $conn->query($sql3);

    $item_names = [];
    $quantities = [];

    while ($row = $result3->fetch_assoc()) {
        $item_names[] = $row['name'];
        $quantities[] = $row['total_sold'];
    }

    $item_names_json = json_encode($item_names);
    $quantities_json = json_encode($quantities);

//  FOURTH CHART
    $sql4 = "
        SELECT payment_method, COUNT(*) AS total_transactions
        FROM sales
        WHERE status = 'sale'
        GROUP BY payment_method
    ";
    $result4 = $conn->query($sql4);

    $methods = [];
    $totals2 = [];

    while ($row = $result4->fetch_assoc()) {
        $methods[] = $row['payment_method'];
        $totals2[] = $row['total_transactions'];
    }

    $methods_json = json_encode($methods);
    $totals_json2 = json_encode($totals2);


//  FIFTH CHART

    $result5 = $conn->query($config['purchases']);

    $periods = [];
    $totals3 = [];

    while ($row = $result5->fetch_assoc()) {
        $periods[] = $row['period'];
        $totals3[] = $row['total_spent'];
    }

    $periods_json = json_encode($periods);
    $totals_json3 = json_encode($totals3);

    $label_title = $config['title'];



//  SIXTH CHART
    $sql6 = "
        SELECT order_type, COUNT(*) AS total_orders 
        FROM sales 
        WHERE status = 'sale'
        GROUP BY order_type
    ";

    $result6 = $conn->query($sql6);

    $types = [];
    $totals4 = [];

    while ($row = $result6->fetch_assoc()) {
        $types[] = $row['order_type'];
        $totals4[] = $row['total_orders'];
    }

    $types_json = json_encode($types);
    $totals_json4 = json_encode($totals4);

?>