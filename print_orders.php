<?php
require_once "sidebar.php";

$filter_type = $_GET['filter_type'] ?? 'day';
$chosen = $_GET['chosen'] ?? null;

$proc = new Procedures();

$result = $proc->getQueue($conn, $filter_type, $chosen);

$grand_total = 0; // initialize grand total
?>
<!DOCTYPE html>
<html>
<head>
    <title>Printable Order Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 100px;
            padding: 20px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        table, th, td {
            border: 1px solid black;
        }

        th {
            background: #ddd;
            padding: 8px;
            text-align: left;
        }

        td {
            padding: 6px;
            vertical-align: top;
        }

        button {
            margin-bottom: 20px;
        }

        @media print {
            button { display: none; }
            #sidebar, .sidebar { display: none; }
            body { margin: 0; padding: 0; }
        }
    </style>
</head>
<body>

<h2>Order History Report</h2>

<button onclick="window.print()">Print</button>

<table>
    <tr>
        <th>Sale ID</th>
        <th>Cashier</th>
        <th>Date</th>
        <th>Order Type</th>
        <th>Payment</th>
        <th>Items</th>
        <th>Total</th>
    </tr>

    <?php while($row = $result->fetch_assoc()): ?>
        <?php $grand_total += $row['total_amount']; ?>
        <tr>
            <td><?= $row['sale_id'] ?></td>
            <td><?= htmlspecialchars($row['cashier_name']) ?></td>
            <td><?= $row['order_date'] ?></td>
            <td><?= htmlspecialchars($row['order_type']) ?></td>
            <td><?= htmlspecialchars($row['payment_method']) ?></td>
            <td><?= $row['items_ordered'] ?></td>
            <td><?= number_format($row['total_amount'], 2) ?></td>
        </tr>
    <?php endwhile; ?>

    <!-- Grand total row -->
    <tr>
        <td colspan="6" style="text-align: right; font-weight: bold;">Total:</td>
        <td style="font-weight: bold;"><?= number_format($grand_total, 2) ?></td>
    </tr>
</table>

</body>
</html>
