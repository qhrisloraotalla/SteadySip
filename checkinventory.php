<?php
include "sidebar.php";

$proc->inventoryNotifs($conn);


    $supply_sql = "SELECT id, name, is_bundled, unit, current_stock FROM supplies ORDER BY name ASC ";  
    $supply_result = $conn->query($supply_sql);
    
    $supply_type_sql = "SELECT id, type FROM supply_types";  
    $supply_type_result = $conn->query($supply_type_sql);
    
    $supply_type_sql2 = "SELECT id, type FROM supply_types";  
    $supply_type_result2 = $conn->query($supply_type_sql2);

    $selected_unit = $_POST['unit'] ?? null;
    $selected_supply_id = $_POST['supply_id'] ?? null;
    $selected_supply_type_id = $_POST['supply_type_id'] ?? null;
    
    $selected_supply_name = $_POST['supply_name'] ?? null;
    
    if (isset($_POST['reset'])) {
        $selected_supply_type_id = null;
        $selected_supply_name = null;
    }

    $inventory_result = $proc->getInventory($conn, $selected_supply_type_id, $selected_supply_name);

    $expired_batches = $proc->getExpiredBatches($conn);

    

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $formType = $_POST['form_type'] ?? '';

        if ($formType === 'restock') {
            $selected_supply_id = (int)($_POST['supply_id'] ?? 0);
            $quantity = (float)($_POST['quantity'] ?? 0);
            $bundle = (int)($_POST['bundle'] ?? 0);
            $unit_per_bundle = (int)($_POST['unit_per_bundle'] ?? 0);
            $unit_cost = (float)($_POST['unit_cost'] ?? 0);
            $expiration_date = trim($_POST['expiration_date'] ?? '');

            $checkStmt = $conn->prepare("SELECT is_bundled FROM supplies WHERE id = ?");
            $checkStmt->bind_param("i", $selected_supply_id);
            $checkStmt->execute();
            $checkStmt->bind_result($is_bundled);
            $checkStmt->fetch();
            $checkStmt->close();

            $subtotal = $is_bundled
                ? ($bundle * $unit_per_bundle * $unit_cost)
                : ($quantity * $unit_cost);

                if ($is_bundled) {
                    $subtotal = $bundle * $unit_cost;
                    $quantity = $bundle * $unit_per_bundle;
                }

            if ($unit_cost <= 0 || $subtotal <= 0 || empty($expiration_date)) {
                $msg = json_encode('Please fill out all fields before submitting.');
                echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'error'}));</script>";
            } else {
                try {
                    $success = $proc->queryRestock(
                        $conn,
                        $selected_supply_id,
                        $quantity,
                        $unit_cost,
                        $subtotal,
                        $expiration_date,
                        $bundle,
                        $unit_per_bundle,
                        $_SESSION['id'],
                        $_SESSION['name']
                    );
                    if ($success) {
                        $msg = json_encode('Item restocked successfully!');
                        echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'success', redirect: 'checkinventory.php', delay: 1200}));</script>";
                    }
                } catch (Exception $e) {
                    $msg = json_encode('Error: ' . $e->getMessage());
                    echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'error'}));</script>";
                }
            }
        }

        elseif ($formType === 'add_new') {
            $name = trim($_POST['name'] ?? '');
            $name = ucfirst(strtolower($name));
            $selected_unit = $_POST['unit'] ?? '';
            $selected_supply_type_id = $_POST['supply_type_id'] ?? null;

            $is_bundled = (isset($_POST['is_bundled']) && $_POST['is_bundled'] === 'yes') ? 1 : 0;

            $bundle = (int)($_POST['bundle'] ?? 0);
            $unit_per_bundle = (int)($_POST['unit_per_bundle'] ?? 0);
            $quantity = (float)($_POST['quantity'] ?? 0);
            $restock_lvl = (float)($_POST['restock_lvl'] ?? 0);
            $unit_cost = (float)($_POST['unit_cost'] ?? 0);
            $expiration_date = trim($_POST['transaction_date'] ?? '');

            if ($is_bundled) {
                $quantity = $bundle * $unit_per_bundle;
                $subtotal = $bundle * $unit_cost;
            } else {
                $bundle = null;
                $unit_per_bundle = null;
                $subtotal = $quantity * $unit_cost;
            }

            if (
                empty($name) ||
                $quantity <= 0 ||
                $restock_lvl <= 0 ||
                $unit_cost <= 0 ||
                $subtotal <= 0 ||
                empty($expiration_date)
            ) {
                $msg = json_encode('Please fill out all required fields before submitting.');
                echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'error'}));</script>";
            } else {
            

                try {
                    $result = $proc->queryNewItem(
                        $conn,
                        $name,
                        $selected_unit,
                        $quantity,
                        $restock_lvl,
                        $unit_cost,
                        $subtotal,
                        $expiration_date,
                        $selected_supply_type_id,
                        $bundle,
                        $unit_per_bundle, 
                        $is_bundled,       
                        $_SESSION['id'],
                        $_SESSION['name']
                    );

                    if ($result === true) {
                        $msg = json_encode('Item added successfully!');
                        echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'success', redirect: 'checkinventory.php', delay: 1200}));</script>";
                    } elseif ($result === 'exists') {
                        $msg = json_encode('Item already exists in inventory!');
                        echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'error', redirect: 'checkinventory.php', delay: 1200}));</script>";
                    }
                } catch (Exception $e) {
                    $msg = json_encode('Error: ' . $e->getMessage());
                    echo "<script>localStorage.setItem('pendingAlert', JSON.stringify({msg: $msg, type: 'error'}));</script>";
                }
            }
        }


    }


    function typeDropdown($supply_type_result, $selected_supply_type_id) {
        echo '<select class="dropdown_" name="supply_type_id" id="supply_type_id" >';
        echo '<option value="" disabled selected hidden>Category</option>';

        if ($supply_type_result->num_rows > 0) {
            while ($row = $supply_type_result->fetch_assoc()) {
                $type_id = $row['id'];
                $type_name = $row['type'];
                $selected = ($type_id == $selected_supply_type_id) ? 'selected' : '';
                echo "<option value='$type_id' $selected>$type_name</option>";
            }
        } else {
            echo '<option disabled>No Categories Found</option>';
        }

        echo '</select>';
    }



    function supplyDropdown($result, $selected_id = null) {
        echo '<label for="supply_id">Select Supply:</label>';
        echo '<select name="supply_id" id="supply_id" required>';
        echo '<option value="">-- Select an Item --</option>';

        while ($row = $result->fetch_assoc()) {
            $selected = ($selected_id == $row['id']) ? 'selected' : '';

            $unit = htmlspecialchars($row['unit']);
            $isBundled = (int)$row['is_bundled'];
            $currentStock = htmlspecialchars($row['current_stock'] ?? 0);
            $reorderLevel = htmlspecialchars($row['reorder_level'] ?? 0);

            echo "
                <option 
                    value='{$row['id']}'
                    data-unit='{$unit}'
                    data-is-bundled='{$isBundled}'
                    data-stock='{$currentStock}'
                    data-reorder='{$reorderLevel}'
                    {$selected}>
                    {$row['name']}
                </option>
            ";
        }

        echo '</select>';
    }


    function unitDropdown($selected_unit) {
        echo '<select class="dropdown_" name="unit" id="unit" required>';
        echo '<option value="" disabled selected hidden>Unit of Measurement</option>';

        $types = [
            'g' => 'grams',
            'mL' => 'milliliters',
            'pcs' => 'pieces',
            'can' => 'cans'
        ];

        foreach ($types as $value => $label) {
            $selected = ($selected_unit === $value) ? 'selected' : '';
            echo "<option value=\"$value\" $selected>$label</option>";
        }

        echo '</select>';
    }

function setPendingAlert($msg, $type = 'success', $redirect = null, $delay = 1200) {
    $payload = ['msg' => $msg, 'type' => $type];
    if ($redirect) { $payload['redirect'] = $redirect; $payload['delay'] = $delay; }
    echo "<script>localStorage.setItem('pendingAlert', " . json_encode($payload) . ");</script>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory</title>
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="inventory.css" />
    
</head>
<body>
    <h1 style="text-align: center; margin-top: 10px; color: #333; font-family: Arial, sans-serif; font-weight: bold;">      
        Manage Inventory
    </h1>
    <div class="form-div">
        <form method="POST" action="">
            <div class="inputs">
            Item name: <input type="text" name="supply_name" placeholder="Item">
            <?php
                typeDropdown($supply_type_result, $selected_supply_type_id);
            ?>
            <button type="submit">Filter Results</button>
            <button type="submit" name="reset">Reset Filters</button>
            </div>
        </form>
    </div>

    <?php
        echo '<br>';
        $proc->showInventoryList($inventory_result);
    ?>

    <br>
    <button data-popup="restock" data-action="show">Restock an Item</button>
    <div id="restock" class="popup">
        <form method="POST" action="">
                <input type="hidden" name="form_type" value="restock">
                
                <p id="restock_current_stock" style="font-weight: bold; color: #333; margin-top: 5px;"></p>
                <br>
                <?php supplyDropdown($supply_result, $selected_supply_id); ?>
                <br>

                <div id="restock_bundle_fields" style="display: none; flex-direction: column; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label for="restock_bundle">Number of Units:</label>
                        <input type="number" id="restock_bundle" name="bundle" step="1" min="1" style="width: 100%;">
                    </div>

                    <div style="display: flex; align-items: center; gap: 5px;">
                        <label for="restock_unit_per_bundle">Amount per Bundle:</label>
                        <input type="number" id="restock_unit_per_bundle" name="unit_per_bundle" step="1" min="1" style="width: 100%;">
                        <span id="restock_unit_display_bundled" style="font-weight: bold;">pcs</span>
                    </div>

                    <p id="restock_total_units_display" style="font-weight: bold; margin-top: 5px;"></p>
                </div>

            <div id="restock_single_fields" style="display: flex; align-items: center; gap: 5px;">
                <label for="restock_quantity">Quantity:</label>
                <input type="number" id="restock_quantity" name="quantity" step="1" min="1" style="width: 90%;" required>
                <span id="restock_unit_display_single" style="font-weight: bold;">pcs</span>
            </div>



                <br>
                <label>Unit Cost (₱):</label>
                <input type="number" id="restock_unit_cost" name="unit_cost" step="1"  min="1" required>

                <p id="restock_subtotal_display" style="font-weight: bold; margin-top: 5px;">Subtotal: ₱0.00</p>

                <br>
                <label>Expiry Date:</label>
                <input type="date" id="restock_expiration_date" name="expiration_date" required>

                <br><br>
                <button type="submit">Restock Item</button>
        </form>

    </div>

    
    <button data-popup="add_new" data-action="show">Add New Item</button>
    <div id="add_new" class="popup">
        <form method="POST" action="">
            <input type="hidden" name="form_type" value="add_new">
            
            <label for="name">Item Name:</label>
            <input type="text" name="name" id="name" required>
            
            <?php typeDropdown($supply_type_result2, $selected_supply_type_id); ?>
            <br>
            
            <label>Is this item consumed partially?</label><br>
            <input type="radio" name="is_bundled" value="yes" id="bundled_yes" required> <label for="bundled_yes">Yes</label>
            <input type="radio" name="is_bundled" value="no" id="bundled_no" required checked> <label for="bundled_no">No</label>
            <br>
            
            <div id="bundle_fields" style="display: none;">
                <label>Number of Units:</label>
                <input type="number" id="bundle" name="bundle" step="1" min="1">
                <br>
                
                <label>Amount per Unit:</label>
                <input type="number" id="unit_per_bundle" name="unit_per_bundle" step="1" min="1">
            </div>
            <br>
            <div id="total_units_display" style="font-style: italic;"></div>
            
                <div id="single_fields">
                    <label>Quantity:</label>
                    <input type="number" id="quantity" name="quantity" step="1" min="1">
                </div>
                <?php unitDropdown($selected_unit); ?>
                
                <br>
                <label>Item Cost (₱):</label>
                <input type="number" id="unit_cost" name="unit_cost" step="1" min="1" required>
                <br>
                
                <span id="subtotal_display" style="font-weight: bold;">Subtotal: ₱0.00</span>
                
                
                <br>
                <label>Restock Level:</label>
                <input type="number" name="restock_lvl" step="1" min="1" required>
                <br>

                
                <br>
                <label>Expiry Date:</label>
                <input type="date" name="transaction_date" required>
                <br>

                <button type="submit">Add To Inventory</button>
        </form>
    </div>
    
    
    
    <button data-popup="discard" data-action="show">Discard Expired Supplies</button>
    <div id="discard" class="popup">
        <?php
            $proc->showExpiredBatches($conn, $expired_batches, $_SESSION['name'], $_SESSION['id']);
        ?>
    </div>

    <a href="discardDefective.php" style="text-decoration: none;">
        <button type="button">Discard Defective Items</button>
    </a>

<script>
document.addEventListener('DOMContentLoaded', () => {

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
                    alert(msg);
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

     const discardForm = document.getElementById("discardForm");
     if (discardForm) {
         discardForm.addEventListener("submit", function(e) {
             const inputs = discardForm.querySelectorAll("input[name^='discard_quantities']");
             let hasValue = false;
             let invalid = false;
             let messages = [];
 
             inputs.forEach(input => {
                 const val = parseFloat(input.value) || 0;
                 const max = parseFloat(input.max) || 0;
 
                 if (val > 0) hasValue = true;
                 if (val > max) {
                     invalid = true;
                     const batchId = input.name.match(/\d+/)[0];
                     messages.push(`Discard quantity for batch ${batchId} exceeds available stock (${max}).`);
                 }
             });
 
             if (!hasValue) {
                 e.preventDefault();
                 showCustomAlert("Please enter a discard quantity for at least one batch before submitting.", "error");
                 return;
             }
 
             if (invalid) {
                 e.preventDefault();
                 showCustomAlert(messages.join("\n"), "error");
                 return;
             }
         });
     }

        const bundledYes = document.getElementById('bundled_yes');
        const bundledNo = document.getElementById('bundled_no');
        const bundleFields = document.getElementById('bundle_fields');
        const singleFields = document.getElementById('single_fields');
        const bundleInput = document.getElementById('bundle');
        const unitPerBundleInput = document.getElementById('unit_per_bundle');
        const quantityInput = document.getElementById('quantity');
        const unitCostInput = document.getElementById('unit_cost');
        const subtotalDisplay = document.getElementById('subtotal_display');
        const totalUnitsDisplay = document.getElementById('total_units_display');

        function toggleAddNewFields() {
            if (!bundledYes || !bundledNo) return;

            if (bundledYes.checked) {
                bundleFields.style.display = 'block';
                singleFields.style.display = 'none';

                bundleInput.required = true;
                bundleInput.disabled = false;
                unitPerBundleInput.required = true;
                unitPerBundleInput.disabled = false;

                quantityInput.required = false;
                quantityInput.disabled = true;
                quantityInput.value = '';
            } else {
                bundleFields.style.display = 'none';
                singleFields.style.display = 'block';

                bundleInput.required = false;
                bundleInput.disabled = true;
                bundleInput.value = '';
                unitPerBundleInput.required = false;
                unitPerBundleInput.disabled = true;
                unitPerBundleInput.value = '';

                quantityInput.required = true;
                quantityInput.disabled = false;
            }

            updateAddNewSubtotal();
        }

        function updateAddNewSubtotal() {
            if (!unitCostInput) return;
            const unitCost = parseFloat(unitCostInput.value) || 0;
            const quantity = parseFloat(quantityInput?.value) || 0;
            let subtotal = 0;

            if (bundledNo?.checked) {
                totalUnitsDisplay.textContent = '';
                subtotal = quantity * unitCost;
            } else if (bundledYes?.checked) {
                const bundles = parseFloat(bundleInput?.value) || 0;
                const unitsPerBundle = parseFloat(unitPerBundleInput?.value) || 0;
                const totalUnits = bundles * unitsPerBundle;
                totalUnitsDisplay.textContent = `Total Units: ${totalUnits}`;
                subtotal = bundles * unitCost;
            }

            subtotalDisplay.textContent = `Subtotal: ₱${subtotal.toFixed(2)}`;
        }

        if (bundledYes && bundledNo) {
            [bundledYes, bundledNo].forEach(radio => {
                radio.addEventListener('change', () => {
                    toggleAddNewFields();
                    updateAddNewSubtotal();
                });
            });

            [bundleInput, unitPerBundleInput, quantityInput, unitCostInput].forEach(input => {
                if (input) input.addEventListener('input', updateAddNewSubtotal);
            });

            toggleAddNewFields();
        }

        // === Restock Form Logic ===
        const restockSupplySelect = document.getElementById('supply_id');
        const restockBundleFields = document.getElementById('restock_bundle_fields');
        const restockSingleFields = document.getElementById('restock_single_fields');
        const restockBundleInput = document.getElementById('restock_bundle');
        const restockUnitPerBundleInput = document.getElementById('restock_unit_per_bundle');
        const restockQuantityInput = document.getElementById('restock_quantity');
        const restockUnitCostInput = document.getElementById('restock_unit_cost');
        const restockSubtotalDisplay = document.getElementById('restock_subtotal_display');
        const restockTotalUnitsDisplay = document.getElementById('restock_total_units_display');
        const restockUnitDisplayBundled = document.getElementById('restock_unit_display_bundled');
        const restockUnitDisplaySingle = document.getElementById('restock_unit_display_single');

        function updateRestockUnitDisplay() {
            const selected = restockSupplySelect.options[restockSupplySelect.selectedIndex];
            const unit = selected ? selected.dataset.unit || '' : '';
            restockUnitDisplayBundled.textContent = unit;
            restockUnitDisplaySingle.textContent = unit;
        }

        function toggleRestockFields() {
            const selected = restockSupplySelect.options[restockSupplySelect.selectedIndex];
            const isBundled = selected && selected.dataset.isBundled === '1';

            if (isBundled) {
                restockBundleFields.style.display = 'block';
                restockSingleFields.style.display = 'none';
                restockBundleInput.required = true;
                restockUnitPerBundleInput.required = true;
                restockQuantityInput.required = false;
                restockQuantityInput.value = '';
            } else {
                restockBundleFields.style.display = 'none';
                restockSingleFields.style.display = 'block';
                restockBundleInput.required = false;
                restockUnitPerBundleInput.required = false;
                restockBundleInput.value = '';
                restockUnitPerBundleInput.value = '';
            }

            updateRestockSubtotal();
        }

        function updateRestockSubtotal() {
            const unitCost = parseFloat(restockUnitCostInput.value) || 0;
            let subtotal = 0;

            if (restockBundleFields.style.display === 'block') {
                const bundles = parseFloat(restockBundleInput.value) || 0;
                const unitsPerBundle = parseFloat(restockUnitPerBundleInput.value) || 0;
                const totalUnits = bundles * unitsPerBundle;
                subtotal = bundles * unitCost;
                restockTotalUnitsDisplay.textContent = totalUnits > 0 ? `Total Units: ${totalUnits}` : '';
            } else {
                const quantity = parseFloat(restockQuantityInput.value) || 0;
                subtotal = quantity * unitCost;
                restockTotalUnitsDisplay.textContent = '';
            }

            restockSubtotalDisplay.textContent = subtotal > 0 ? `Subtotal: ₱${subtotal.toFixed(2)}` : '';
        }

        function updateCurrentStockDisplay() {
            const selected = restockSupplySelect.options[restockSupplySelect.selectedIndex];
            const stock = selected ? selected.dataset.stock : null;
            const unit = selected ? selected.dataset.unit : '';
            const reorder = selected ? parseFloat(selected.dataset.reorder) || 0 : 0;
            const display = document.getElementById('restock_current_stock');

            if (!stock) {
                display.textContent = "";
                return;
            }

            let stockText = `Current Stock: ${stock} ${unit}`;
            if (parseFloat(stock) <= reorder && reorder > 0) {
                stockText += " ⚠️ (Low Stock)";
                display.style.color = "red";
            } else {
                display.style.color = "#333";
            }

            display.textContent = stockText;
        }

        restockSupplySelect.addEventListener('change', () => {
            toggleRestockFields();
            updateRestockUnitDisplay();
            updateCurrentStockDisplay();
        });

        [restockBundleInput, restockUnitPerBundleInput, restockQuantityInput, restockUnitCostInput]
            .forEach(el => el.addEventListener('input', updateRestockSubtotal));

        toggleRestockFields();
        updateRestockUnitDisplay();
        updateRestockSubtotal();
        updateCurrentStockDisplay();

        const popupButtons = document.querySelectorAll("[data-popup][data-action='show']");
        const popups = document.querySelectorAll(".popup");

        popupButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const popupId = btn.getAttribute('data-popup');
                localStorage.setItem('openPopup', popupId);
                document.getElementById(popupId).classList.add('show');
            });
        });

        popups.forEach(popup => {
            const closeBtn = popup.querySelector("[data-action='close']");
            if (closeBtn) {
                closeBtn.addEventListener('click', () => {
                    popup.classList.remove('show');
                    localStorage.removeItem('openPopup');
                });
            }
        });


        localStorage.removeItem('openPopup');
        const openPopup = localStorage.getItem('openPopup');
        if (openPopup) {
            const popup = document.getElementById(openPopup);
            if (popup) popup.classList.add('show');
        }

    });

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

        document.addEventListener("DOMContentLoaded", () => {
        const pending = localStorage.getItem("pendingAlert");

        if (pending) {
            const data = JSON.parse(pending);
            
            showCustomAlert(data.msg, data.type);

            if (data.redirect) {
                setTimeout(() => {
                    window.location.href = data.redirect;
                }, data.delay || 1200);
            }

            localStorage.removeItem("pendingAlert");
        }
    });

    function showCustomAlert(message, type) {
        const modal = document.getElementById("customAlert");
        const msgBox = document.getElementById("customAlertMessage");

        const check = document.querySelector(".checkmark");
        const cross = document.querySelector(".cross");

        msgBox.textContent = message;

        if (type === "success") {
            check.style.display = "block";
            cross.style.display = "none";
        } else {
            check.style.display = "none";
            cross.style.display = "block";
        }

        modal.style.display = "flex";

        document.getElementById("customAlertOk").onclick = () => {
            modal.style.display = "none";
        };
    }
    }


</script>

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



</body>
</html>
