<?php
include "sidebar.php";

    // --- Fetch categories for dropdown
    $category_sql = "SELECT * FROM categories";
    $category_result = $conn->query($category_sql);
    $selected_category_id = $_POST['category_id'] ?? null;
    
    $selected_order_type = $_POST['type'] ?? null;
    $selected_method = $_POST['method'] ?? null;
    $selected_discount = $_POST['discount'] ?? null;

    $menu_item_ids = $_POST['menu_item_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    
    
    if (isset($_POST['reset'])) {
        // Clear filters
        $selected_name = null;
        $selected_category_id = null;
    } else {
        // Otherwise keep whatever user submitted
        $selected_name = $_POST['menu_name'] ?? null;
        $selected_category_id   = $_POST['category_id'] ?? null;
    }


    // --- Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $formType = $_POST['form_type'] ?? '';
        if ($formType === 'order_list') {

            $comment = ucwords(strtolower(trim($_POST['comments'] ?? '')));
            $menu_item_ids = $_POST['menu_item_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];

            // Validation (you already did this part)
            if (count($menu_item_ids) === 0) {
                echo "<script>alert('Please fill out all fields and add at least one menu item.');</script>";
            } else {
                $valid_menu_item_found = false;
                foreach ($menu_item_ids as $index => $menu_item_id) {
                    $menu_item_id = (int)$menu_item_id;
                    $qty = (float)$quantities[$index];
                    if ($menu_item_id > 0 && $qty > 0) {
                        $valid_menu_item_found = true;
                        break;
                    }
                }

                if (!$valid_menu_item_found) {
                    echo "<script>alert('Menu items must have quantity greater than 0.');</script>";
                    exit;
                }

                // Call your new function
                $proc->queryPendingOrder(
                    $conn,
                    $_SESSION['id'],
                    $_SESSION['name'],
                    $selected_order_type,
                    $selected_method,
                    $selected_discount,
                    $menu_item_ids,
                    $quantities,
                    $comment
                );
            }
        }

    }


    function categoryDropdown($category_result, $selected_category_id) {
        echo '<select class="dropdown_" name="category_id" id="category_id">';
        echo '<option value="" disabled selected hidden>Category</option>';

        if ($category_result->num_rows > 0) {
            while ($row = $category_result->fetch_assoc()) {
                $category_id = $row['id'];
                $category_name = $row['category'];
                $selected = ($category_id == $selected_category_id) ? 'selected' : '';
                echo "<option value='$category_id' $selected>$category_name</option>";
            }
        } else {
            echo '<option disabled>No Categories Found</option>';
        }

        echo '</select>';
    }

    function orderTypeDropdown($selected_order_type) {
        echo '<select class="dropdown_" name="type" id="type" required>';
        echo '<option value="" disabled selected hidden>Order Type</option>';

        $types = [
            'dine-in' => 'Dine-in',
            'take-out' => 'Take-out'
        ];

        foreach ($types as $value => $label) {
            $selected = ($selected_order_type === $value) ? 'selected' : '';
            echo "<option value=\"$value\" $selected>$label</option>";
        }

        echo '</select>';
    }
    
    function paymentMethodDropdown($selected_method) {
        echo '<select class="dropdown_" name="method" id="method" required>';
        echo '<option value="" disabled selected hidden>Payment Method</option>';

        $methods = [
            'cash' => 'Cash',
            'card' => 'Card',
            'ewallet' => 'E-wallet'
        ];

        foreach ($methods as $value => $label) {
            $selected = ($selected_method === $value) ? 'selected' : '';
            echo "<option value=\"$value\" $selected>$label</option>";
        }

        echo '</select>';
    }
    
    function discountTypeDropdown($selected_discount) {
        echo '<select class="dropdown_" name="discount" id="discount" required>';
        echo '<option value="" disabled selected hidden>Discount Type</option>';

        $discounts = [
            'none' => 'None',
            'PWD' => 'PWD',
            'senior' => 'Senior'
        ];

        foreach ($discounts as $value => $label) {
            $selected = ($selected_discount === $value) ? 'selected' : '';
            echo "<option value=\"$value\" $selected>$label</option>";
        }

        echo '</select>';
    }

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History</title>
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="order.css">

</head>
<body>
<p>MENU CATALOGUE   <a href="saleQueueTab.php">ORDER HISTORY</a></p>

<h2>Menu List</h2>

<div class="form-div">
    <form method="POST" action="">
        Item name: <input type="text" name="menu_name" placeholder="Item">
        <?php
        categoryDropdown($category_result, $selected_category_id);
        ?>

        <button type="submit">Filter Results</button>
        <button type="submit" name="reset">Reset Filters</button>

    </form>
</div>

    <?php

    $menu_result = $proc->getMenu($conn, $selected_category_id, $selected_name);

    $proc->showMenuList($menu_result, );

    ?>



<br>
<button data-popup="order_list" data-action="show">Order List</button>
<div id="order_list" class="popup">
    <h3>Order List</h3>
    <form action="" method="POST">
        <input type="hidden" name="form_type" value="order_list">
        <div id="ingredient-container">
            <div class="ingredient-row">
                    <select name="menu_item_id[]" class="ingredient-select" required>
                        <option value="" disabled selected hidden>Select Item</option>
                        <?php
                            $menu_sql = "SELECT id, name, price FROM menu_items";
                            $menu_result = $conn->query($menu_sql);
                            while ($row = $menu_result->fetch_assoc()) {
                                echo "<option value='{$row['id']}' data-price='{$row['price']}'>{$row['name']}</option>";
                            }

                            ?>
                    </select>
                    
                    <input type="number" name="quantity[]" step="1" min="0" placeholder="Quantity" required>
                    <span class="unit-label"></span>
                    
                    <button type="button" class="remove-btn">−</button>
                    <br>
            </div>
        </div>
        <br>
        <button type="button" id="add-dish">Add Item</button>
        <br>
        <!-- <label for="comments">Comments/Requests</label><br> -->
        <!-- <textarea id="comments" name="comments" rows="4" cols="40"></textarea><br> -->
            
            <?php
            echo "<br>";
            orderTypeDropdown($selected_order_type);
            paymentMethodDropdown($selected_method);
            discountTypeDropdown($selected_discount);
            echo "<br><br>";
            ?>
        <p><strong>Subtotal: ₱ <span id="order-subtotal">0.00</span></strong></p>
        <br>
        <button type="submit">Queue Order</button>
    </form>
<br><br>
</div>





<script>
    document.addEventListener("DOMContentLoaded", function () {
        const container = document.getElementById("ingredient-container");

        // Optional: dynamic "Add Ingredient" button logic
        document.getElementById("add-dish").addEventListener("click", function () {
            const newRow = container.querySelector(".ingredient-row").cloneNode(true);
            newRow.querySelector("select").selectedIndex = 0;
            newRow.querySelector("input").value = "";
            container.appendChild(newRow);
            calculateSubtotal();
        });

        // Remove ingredient row
        container.addEventListener("click", function (e) {
            if (e.target.classList.contains("remove-btn")) {
                e.stopPropagation();
                const rows = container.querySelectorAll(".ingredient-row");
                if (rows.length > 1) {
                    e.target.closest(".ingredient-row").remove();
                    calculateSubtotal();
                }

            }
        });

        // === LIVE SUBTOTAL CALC ===
        function calculateSubtotal() {
            let total = 0;

            document.querySelectorAll(".ingredient-row").forEach(row => {
                const select = row.querySelector("select");
                const price = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
                const qty = parseFloat(row.querySelector("input").value || 0);

                if (!isNaN(price) && !isNaN(qty)) {
                    total += price * qty;
                }
            });

            document.getElementById("order-subtotal").textContent = total.toFixed(2);
        }

        // Recalculate when dropdown or quantity is changed
        document.addEventListener("input", function (e) {
            if (e.target.matches(".ingredient-select") || e.target.matches("input[name='quantity[]']")) {
                calculateSubtotal();
            }
        });

    const discountType = document.getElementById('discount');
    const subtotalSpan = document.getElementById('order-subtotal');

    // Function to calculate subtotal
    function calculateSubtotal() {
        let rows = document.querySelectorAll(".ingredient-row");
        let subtotal = 0;

        rows.forEach(row => {
            let select = row.querySelector(".ingredient-select");
            let qtyInput = row.querySelector("input[name='quantity[]']");
            let price = parseFloat(select.selectedOptions[0]?.dataset.price || 0);
            let qty = parseFloat(qtyInput.value || 0);

            subtotal += price * qty;
        });

        // Apply discount here
        let discountTypeValue = discountType.value;
        let discountAmount = 0;

        if (discountTypeValue === "PWD" || discountTypeValue === "senior") {
            discountAmount = subtotal * 0.20;  // 20% discount
        }

        let discountedTotal = subtotal - discountAmount;

        subtotalSpan.textContent = discountedTotal.toFixed(2);
    }

    // Recalculate subtotal whenever discount type changes
    discountType.addEventListener("change", calculateSubtotal);

    // Recalculate whenever quantity or menu item changes
    document.addEventListener("input", function(e) {
        if (e.target.matches("input[name='quantity[]']")) {
            calculateSubtotal();
        }
    });

    document.addEventListener("change", function(e) {
        if (e.target.matches(".ingredient-select")) {
            calculateSubtotal();
        }
    });

    });
</script>
</body>
</html>
