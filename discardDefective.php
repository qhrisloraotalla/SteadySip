<?php
include "sidebar.php";

    $selected_supply_name = $_POST['supply_name'] ?? null;
    $selected_supply_type_id = $_POST['supply_type_id'] ?? null;

    
    if (isset($_POST['reset'])) {
        $selected_supply_type_id = null;
        $selected_supply_name = null;
    }
    $inventory_result = $proc->getInventory($conn, $selected_supply_type_id, $selected_supply_name);

    
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discard Defective</title>
    <link rel="stylesheet" href="discard.css">
</head>
<body>

<!-- Define showCustomAlert EARLY -->
<script>
function showCustomAlert(message, type = 'success') {
    const alertBox = document.getElementById('customAlert');
    const alertMessage = document.getElementById('customAlertMessage');
    const okButton = document.getElementById('customAlertOk');
    const checkmark = alertBox.querySelector('.checkmark');
    const cross = alertBox.querySelector('.cross');

    alertMessage.textContent = message;

    if (type === 'success') {
        checkmark.style.display = 'block';
        cross.style.display = 'none';
        okButton.style.background = '#4CAF50';
    } else {
        checkmark.style.display = 'none';
        cross.style.display = 'block';
        okButton.style.background = '#F44336';
    }

    alertBox.classList.add('show');

    okButton.onclick = () => {
        alertBox.classList.remove('show');
    };
}
</script>

<!-- Modal HTML -->
<div id="customAlert" class="custom-alert">
    <div class="custom-alert-content">
        <div class="icon-container">
            <div class="checkmark"></div>
            <div class="cross">
                <span class="cross-line cross-line1"></span>
                <span class="cross-line cross-line2"></span>
            </div>
        </div>
        <p id="customAlertMessage">Message</p>
        <button id="customAlertOk">OK</button>
    </div>
</div>

<a href="checkinventory.php" style="text-decoration: none;">
    <button type="button">Back to Inventory</button>
</a>
<h3>Discard Defective Items</h3>
    <form id="defectFilterForm" method="POST">
        Item name: 
        <input type="text" name="supply_name" 
               value="<?= htmlspecialchars($_POST['supply_name'] ?? '') ?>" 
               placeholder="Enter item name">
        <button type="submit">Filter Results</button>
        <button type="submit" name="reset">Reset Filters</button>
    </form>

    <div id="defectResults">
        <?php $proc->discardDefectiveItems($conn, $inventory_result, $_SESSION['name'], $_SESSION['id']); ?>
    </div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // If PHP set a pending alert, show it now (and handle optional redirect)
    const pending = localStorage.getItem('pendingAlert');
    if (pending) {
        try {
            const obj = JSON.parse(pending);
            const msg = obj.msg || '';
            const type = obj.type || 'success';
            setTimeout(() => {
                if (typeof showCustomAlert === 'function') {
                    showCustomAlert(msg, type);
                } else {
                    alert(msg); // fallback
                }
                if (obj.redirect) {
                    const delay = parseInt(obj.delay) || 1200;
                    setTimeout(() => { window.location.href = obj.redirect; }, delay);
                }
            }, 50);
        } catch (e) {
            console.error('Invalid pendingAlert', e);
        }
        localStorage.removeItem('pendingAlert');
    }
});
</script>
    
</body>
</html>