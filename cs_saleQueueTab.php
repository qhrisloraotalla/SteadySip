<?php
include "cs_sidebar.php";


$filter_type = $_GET['filter_type'] ?? 'day';

$chosen = $_GET['chosen_day'] 
       ?? $_GET['chosen_week'] 
       ?? $_GET['chosen_month'] 
       ?? null;

// Load filtered results
$result = $proc->getQueue($conn, $filter_type, $chosen);

$new_status = $_POST['status'] ?? 'sale';



if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_status'])) {
    $sale_id = (int)$_POST['sale_id'];
    $new_status = $_POST['status'];
    $sale_id = $_POST['sale_id'];

    $proc->queryFinishOrder(
        $conn,
        $_SESSION['id'],
        $_SESSION['name'],
        $new_status,
        $sale_id
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="orders.css">    
</head>
<body>

<h1 style="text-align: center; margin-top: 10px; color: #333; font-family: Arial, sans-serif; font-weight: bold;">      
        Order History
    </h1>

<form method="GET" style="margin-bottom: 20px;">
    <label>Filter by: </label>

    <select name="filter_type" onchange="this.form.submit()">
        <option value="day"   <?= ($filter_type == 'day') ? 'selected' : '' ?>>Day</option>
        <option value="week"  <?= ($filter_type == 'week') ? 'selected' : '' ?>>Week</option>
        <option value="month" <?= ($filter_type == 'month') ? 'selected' : '' ?>>Month</option>
    </select>

    <!-- Day Picker -->
    <?php if($filter_type == 'day'): ?>
        <input type="date" name="chosen_day"
               value="<?= $_GET['chosen_day'] ?? '' ?>"
               onchange="this.form.submit()">
    <?php endif; ?>

    <!-- Week Picker -->
    <?php if($filter_type == 'week'): ?>
        <input type="week" name="chosen_week"
               value="<?= $_GET['chosen_week'] ?? '' ?>"
               onchange="this.form.submit()">
    <?php endif; ?>

    <!-- Month Picker -->
    <?php if($filter_type == 'month'): ?>
        <input type="month" name="chosen_month"
               value="<?= $_GET['chosen_month'] ?? '' ?>"
               onchange="this.form.submit()">
    <?php endif; ?>


    <button type="button" onclick="window.location='print_orders.php?filter_type=<?= $filter_type ?>&chosen=<?= $chosen ?>'">

    Print </button>
</form>

<?php

$result = $proc->getQueue($conn, $filter_type, $chosen);

$proc->showOrderQueue($result);
?>
<!-- CONFIRMATION POPUP -->
<div id="confirm-popup" class="popup">
    <div class="popup-content">
        <h3>Confirm Update?</h3>

        <div class="btn-row">
            <button id="confirm-yes">Yes</button>
            <button id="confirm-no">No</button>
        </div>
    </div>
</div>



<script>
document.addEventListener("DOMContentLoaded", () => {

    const confirmPopup = document.getElementById("confirm-popup");
    const yesBtn = document.getElementById("confirm-yes");
    const noBtn = document.getElementById("confirm-no");

    let activeForm = null; // store the form that triggered the popup

    // When "Update1" is clicked
    document.querySelectorAll(".confirm-btn").forEach(btn => {
        btn.addEventListener("click", () => {
            activeForm = btn.closest(".update-form"); // store the correct form
            confirmPopup.style.display = "flex"; // show popup
        });
    });

    // YES → submit the original form
    yesBtn.addEventListener("click", () => {
        if (activeForm) {
            activeForm.submit();
        }
        confirmPopup.style.display = "none";
    });

    // NO → close popup only
    noBtn.addEventListener("click", () => {
        confirmPopup.style.display = "none";
        activeForm = null;
    });

    // Click outside closes popup
    confirmPopup.addEventListener("click", e => {
        if (e.target === confirmPopup) {
            confirmPopup.style.display = "none";
            activeForm = null;
        }
    });
});

</script>

</body>
</html>
