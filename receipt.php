<?php
include "sidebar.php";

// Make sure $sale_id is set after queryPendingOrder
$sale_id = $_GET['sale_id'] ?? 0;
$cash = floatval($_GET['cash'] ?? 0);

if (!$sale_id) {
    echo "No sale specified.";
    exit;
}

// Fetch sale info
$sale = $proc->getSale($conn, $sale_id);
if (!$sale) {
    echo "Receipt not found.";
    exit;
}

// Fetch sale items
$items = $proc->getSaleItems($conn, $sale_id);

$total = $sale['total_amount'];
$discount = $sale['discount_amount'] ?? 0;
$change = $cash - $total;

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Receipt - <?= htmlspecialchars($sale_id) ?></title>
    <style>
        body { font-family: Arial; font-size: 14px; }
        .center { text-align: center; }
        .divider { border-top: 1px solid #000; margin: 5px 0; }
        .two-col { display: flex; justify-content: space-between; }
        ul { list-style: none; padding: 0; margin: 0; }
        li { margin-bottom: 3px; }
    </style>
</head>
<body>
    <h3 class="center">STEADYSIP CORPORATION</h3>
    <div class="divider"></div>

    <h3 class="center">SALES INVOICE</h3>
    <div class="two-col"><span>S.I. No.:</span><span><?= htmlspecialchars($sale_id) ?></span></div>
    <div class="two-col"><span>Date/Time:</span><span><?= date("Y-m-d H:i:s", strtotime($sale['order_date'])) ?></span></div>
    <div class="divider"></div>

    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <?= htmlspecialchars($item['name']) ?> ×<?= $item['quantity'] ?> = ₱<?= number_format($item['subtotal'], 2) ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="divider"></div>
    <div class="two-col"><span>Subtotal:</span><span>₱<?= number_format($total + $discount, 2) ?></span></div>
    <div class="two-col"><span>Discount:</span><span>₱<?= number_format($discount, 2) ?></span></div>
    <div class="two-col"><span>Total:</span><span>₱<?= number_format($total, 2) ?></span></div>
    <div class="two-col"><span>Cash:</span><span>₱<?= number_format($cash, 2) ?></span></div>
    <div class="two-col"><span>Change:</span><span>₱<?= number_format($change, 2) ?></span></div>

    <div class="divider"></div>
    <p class="center">Thank You!</p>

    <script>
        // Automatically trigger print when page loads
        window.onload = function() {
            window.print();
        };
    </script>
</body>
</html>
