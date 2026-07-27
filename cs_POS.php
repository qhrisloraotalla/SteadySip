<?php
include "cs_sidebar.php";


        $category_sql = "SELECT * FROM categories";
        $category_result = $conn->query($category_sql);
        $selected_category_id = $_POST['category_id'] ?? null;
        
        $selected_order_type = $_POST['type'] ?? null;
        $selected_method = $_POST['method'] ?? null;
        $selected_discount = $_POST['discount'] ?? null;

        $menu_item_ids = $_POST['menu_item_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'order_list')  {

            $comment = ucwords(strtolower(trim($_POST['comments'] ?? '')));
            $menu_item_ids = $_POST['menu_item_id'] ?? [];
            $quantities = $_POST['quantity'] ?? [];
            $cash = $_POST['cash'] ?? 0;

            $proc->queryPendingOrder(
                $conn,
                $_SESSION['id'],
                $_SESSION['name'],
                $_POST['type'] ?? '',
                $_POST['method'] ?? '',
                $_POST['discount'] ?? 0,
                $menu_item_ids,
                $quantities,
                $comment
            );

            // If AJAX request, respond normally
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                echo "success";
                exit;
            }

            // **Redirect after POST** to prevent duplicate submission on refresh
            header("Location: " . $_SERVER['PHP_SELF'] . "?payment_done=1");
            exit;
        }


            $payment_done = true;


        $query = "
            SELECT 
                mi.id AS menu_id,
                mi.name AS menu_name,
                mi.price,
                mi.description,
                c.category AS category_name
            FROM menu_items mi
            LEFT JOIN categories c ON c.id = mi.category_id
            WHERE mi.is_active = 1
            ORDER BY mi.name ASC
        ";

        $result = $conn->query($query);
        $menuItems = [];

        while ($row = $result->fetch_assoc()) {
            $menuItems[] = $row;
        }

        $recipesByMenuId = [];

        foreach ($menuItems as $item) {
            $menu_id = $item['menu_id'];
            $sql = "
                SELECT 
                    r.ingredient_id, 
                    s.unit,
                    r.quantity AS quantity_needed, 
                    s.current_stock,
                    s.name AS ingredient_name,
                    s.current_stock
                FROM recipes r
                JOIN supplies s ON s.id = r.ingredient_id
                WHERE r.menu_item_id = ?
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $menu_id);
            $stmt->execute();
            $result = $stmt->get_result();

            $ingredients = [];
            while ($row = $result->fetch_assoc()) {
                $ingredients[] = [
                    'id' => (int)$row['ingredient_id'],
                    'name' => $row['ingredient_name'],
                    'unit' => $row['unit'],
                    'quantity_needed' => (float)$row['quantity_needed'],
                    'stock' => (float)$row['current_stock']
                ];
            }

            $recipesByMenuId[$menu_id] = $ingredients;
        }

    $category_sql = "SELECT * FROM categories";
    $category_result = $conn->query($category_sql);


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

    function getAvailableServings($conn, $menu_id) {
        $sql = "
            SELECT r.ingredient_id, r.quantity, s.current_stock
            FROM recipes r
            JOIN supplies s ON s.id = r.ingredient_id
            WHERE r.menu_item_id = ?
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $menu_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $available = PHP_INT_MAX;

        while ($row = $result->fetch_assoc()) {
            $stock = (float)$row['current_stock'];
            $needed = (float)$row['quantity'];
            if ($needed > 0) {
                $dishAvailability = floor($stock / $needed);
                $available = min($available, $dishAvailability);
            }
        }

    return $available === PHP_INT_MAX ? 0 : $available;
}




?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="POS2.css">
</head>

<body>

<h1 style="text-align: center; margin-top: 10px; color: #333; font-family: Arial, sans-serif; font-weight: bold;">      
        Point of Sale
    </h1>

<div class="pos-wrapper">

    <div style="width:70%;">
        <div class="menu-controls">
            <input type="text" id="search" placeholder="Search menu...">

            <div class="tabs">
                <button class="tab active" data-category="all">All</button>
                <button class="tab" data-category="dish">Dish</button>
                <button class="tab" data-category="beverage">Beverage</button>
                <button class="tab" data-category="dessert">Dessert</button>
            </div>
        </div>

        <div class="menu-grid">
            <?php foreach ($menuItems as $item): 
                $available = getAvailableServings($conn, $item['menu_id']);
            ?>
                <div class="menu-tile"
                    data-id="<?= $item['menu_id'] ?>"
                    data-name="<?= strtolower($item['menu_name']) ?>"
                    data-category="<?= $item['category_name'] ?>"
                    data-price="<?= $item['price'] ?>"
                    data-available="<?= $available ?>">

                    <h3><?= htmlspecialchars($item['menu_name']) ?></h3>
                    <p class="price">₱<?= number_format($item['price'], 2) ?></p>

                    <?php if (!empty($item['description'])): ?>
                        <p class="description"><?= htmlspecialchars($item['description']) ?></p>
                    <?php endif; ?>

                    <p class="available">Available: <?= $available ?></p>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="pos-panel">

        <h3>Order Summary</h3>

        <form method="POST">
            <input type="hidden" name="form_type" value="order_list">
            <input type="hidden" id="payment_success" name="payment_success" value="0">

            <div id="dish-container">

            </div>
            <div class="below">
                <?php
                    orderTypeDropdown($selected_order_type);
                    paymentMethodDropdown($selected_method);
                    discountTypeDropdown($selected_discount);
                ?>

                <p><strong>Subtotal: ₱ <span id="order-subtotal">0.00</span></strong></p>
                <p><strong>Discount: ₱ <span id="order-discount">0.00</span></strong></p>
                <hr>
                <p><strong>Total: ₱ <span id="order-total">0.00</span></strong></p>


            </div>
            <button id="complete-order-btn" type="button">Complete Order</button>


            <div id="payment-popup" class="popup" style="display:none;">
                <div class="popup-content">
                    <h3>Payment</h3>
                    <p><strong>Date/Time:</strong> <span id="payment-datetime"></span></p>
                    <p><strong>Transaction ID:</strong> <span id="transaction-id"></span></p>
                    <p><strong>Order Type:</strong> <span id="order-type">Dine-in</span></p>

                    <h4>Items Ordered:</h4>
                    <ul id="payment-items"></ul>

                    <p>Subtotal: ₱<span id="payment-subtotal"></span></p>
                    <p>Discount: ₱<span id="payment-discount"></span></p>
                    <p>Total: ₱<span id="payment-total"></span></p>

                    <label for="cash-input">Cash:</label>
                    <input type="number" id="cash-input" min="0" step="1">

                    <p>Change: ₱<span id="cash-change">0.00</span></p>

                <div class="popup-buttons">
                    <button id="pay-btn" type="submit">Pay</button>
                    <button id="cancel-payment-btn" type="button">Cancel</button>
                    <button id="print-receipt-btn" type="button" style="display:none; margin-left: 10px;">Print Receipt</button>
                </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const recipes = <?= json_encode($recipesByMenuId); ?>;
    console.log("Recipes:", recipes);
</script>

<script>
document.addEventListener("DOMContentLoaded", () => {

    const completeBtn = document.getElementById("complete-order-btn");
    const paymentPopup = document.getElementById("payment-popup");
    const cancelPaymentBtn = document.getElementById("cancel-payment-btn");
    const paymentDatetime = document.getElementById("payment-datetime");
    const transactionId = document.getElementById("transaction-id");
    const paymentItems = document.getElementById("payment-items");
    const paymentSubtotal = document.getElementById("payment-subtotal");
    const paymentDiscount = document.getElementById("payment-discount");
    const paymentTotal = document.getElementById("payment-total");
    const cashInput = document.getElementById("cash-input");
    const cashChange = document.getElementById("cash-change");
    const orderContainer = document.getElementById("dish-container");
    const discountType = document.getElementById("discount");
    const subtotalSpan = document.getElementById("order-subtotal");
    const tiles = document.querySelectorAll(".menu-tile");
    const searchInput = document.getElementById("search");
    const tabs = document.querySelectorAll(".tab");
    const payBtn = document.getElementById("pay-btn");
    const form = document.querySelector("form");

    let activeCategory = "all";
    let reservedIngredients = {};

    // ====================== Helper Functions ======================
    function updateCompleteButton() {
        completeBtn.disabled = orderContainer.querySelectorAll(".dish-row").length === 0;
    }

    function calculateTotals() {
        let subtotal = 0;
        orderContainer.querySelectorAll(".dish-row").forEach(row => {
            const qty = parseInt(row.querySelector(".qty").textContent) || 1;
            const price = parseFloat(row.dataset.price) || 0;
            subtotal += qty * price;
        });

        subtotalSpan.textContent = subtotal.toFixed(2);

        let discountAmount = 0;
        if (discountType.value === "PWD" || discountType.value === "senior") {
            discountAmount = subtotal * 0.2;
        }

        document.getElementById("order-discount").textContent = discountAmount.toFixed(2);
        document.getElementById("order-total").textContent = (subtotal - discountAmount).toFixed(2);
    }

    function computeAvailable(menuId) {
        const ingredients = recipes[menuId] || [];
        let available = Infinity;

        ingredients.forEach(ing => {
            const reserved = reservedIngredients[ing.id] || 0;
            const stockLeft = ing.stock - reserved;

            if (ing.quantity_needed <= 0) return;

            const possibleServings = Math.floor(stockLeft / ing.quantity_needed);
            available = Math.min(available, possibleServings);
        });

        return available <= 0 ? 0 : available;
    }

    function refreshTileAvailability() {
        tiles.forEach(tile => {
            const menuId = tile.dataset.id;
            const available = computeAvailable(menuId);
            tile.querySelector(".available").textContent = `Available: ${available}`;
            tile.style.opacity = available === 0 ? 0.5 : 1;
        });
    }

    function addOrderRow(id, name, price) {
        let row = orderContainer.querySelector(`.dish-row[data-menu-id='${id}']`);
        if (row) {
            row.scrollIntoView({ behavior: "smooth" });
            return;
        }

        const maxAvailable = computeAvailable(id);
        if (maxAvailable <= 0) {
            const missingList = recipes[id]
                .map(ing => {
                    const reserved = reservedIngredients[ing.id] || 0;
                    const stockLeft = ing.stock - reserved;
                    if (stockLeft < ing.quantity_needed) {
                        return `• ${ing.name} is short by ${ing.quantity_needed - stockLeft} ${ing.unit}`;
                    }
                    return null;
                })
                .filter(x => x !== null)
                .join("\n");

            alert(`This dish cannot be added due to insufficient ingredients:\n\n${missingList}`);
            return;
        }

        row = document.createElement("div");
        row.classList.add("dish-row");
        row.dataset.menuId = id;
        row.dataset.price = parseFloat(price);

        row.innerHTML = `
            <input type="hidden" name="menu_item_id[]" value="${id}">
            <input type="hidden" name="quantity[]" class="qty-input" value="1">
            <div class="dish-row-content">
                <span class="dish-name"><strong>${name}</strong></span>
                <div class="qty-controller">
                    <button type="button" class="minus-btn">−</button>
                    <span class="qty">1</span>
                    <button type="button" class="plus-btn">+</button>
                </div>
                <button type="button" class="remove-btn">×</button>
            </div>
        `;

        orderContainer.appendChild(row);

        recipes[id].forEach(ing => {
            reservedIngredients[ing.id] = (reservedIngredients[ing.id] || 0) + ing.quantity_needed;
        });

        calculateTotals();
        updateCompleteButton();
        refreshTileAvailability();
    }

    // ====================== Event Listeners ======================
    tiles.forEach(tile => {
        tile.addEventListener("click", () => {
            addOrderRow(tile.dataset.id, tile.querySelector("h3").textContent, tile.dataset.price);
        });
    });

    orderContainer.addEventListener("click", e => {
        const row = e.target.closest(".dish-row");
        if (!row) return;

        const qtySpan = row.querySelector(".qty");
        const qtyInput = row.querySelector(".qty-input");
        const menuId = row.dataset.menuId;
        const ingredients = recipes[menuId];
        let qty = parseInt(qtySpan.textContent);

        if (e.target.classList.contains("plus-btn")) {
            let maxAvailable = Infinity;
            ingredients.forEach(ing => {
                const reserved = reservedIngredients[ing.id] || 0;
                const currentRowReserved = qty * ing.quantity_needed;
                const stockLeft = ing.stock - (reserved - currentRowReserved);
                maxAvailable = Math.min(maxAvailable, Math.floor(stockLeft / ing.quantity_needed));
            });
            if (qty < maxAvailable) {
                qty++;
                qtySpan.textContent = qty;
                qtyInput.value = qty;
                ingredients.forEach(ing => { reservedIngredients[ing.id] += ing.quantity_needed; });
                calculateTotals();
                refreshTileAvailability();
            } else alert("Cannot add more, stock limit reached.");
        } else if (e.target.classList.contains("minus-btn")) {
            if (qty > 1) {
                qty--;
                qtySpan.textContent = qty;
                qtyInput.value = qty;
                ingredients.forEach(ing => { reservedIngredients[ing.id] -= ing.quantity_needed; });
                calculateTotals();
                refreshTileAvailability();
            }
        } else if (e.target.classList.contains("remove-btn")) {
            ingredients.forEach(ing => { reservedIngredients[ing.id] -= ing.quantity_needed * qty; });
            row.remove();
            calculateTotals();
            updateCompleteButton();
            refreshTileAvailability();
        }
    });

    discountType.addEventListener("change", calculateTotals);

    searchInput.addEventListener("input", () => {
        const term = searchInput.value.toLowerCase();
        tiles.forEach(tile => {
            const name = tile.dataset.name.toLowerCase();
            const category = tile.dataset.category.toLowerCase();
            tile.style.display = (name.includes(term) && (activeCategory === "all" || category === activeCategory)) ? "block" : "none";
        });
    });

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            tabs.forEach(t => t.classList.remove("active"));
            tab.classList.add("active");
            activeCategory = tab.dataset.category.toLowerCase();
            const term = searchInput.value.toLowerCase();
            tiles.forEach(tile => {
                const name = tile.dataset.name.toLowerCase();
                const category = tile.dataset.category.toLowerCase();
                tile.style.display = (name.includes(term) && (activeCategory === "all" || category === activeCategory)) ? "block" : "none";
            });
        });
    });

    completeBtn.addEventListener("click", () => {
        const orderItems = [];
        document.querySelectorAll(".dish-row").forEach(row => {
            orderItems.push({ menu_id: row.dataset.menuId, qty: parseInt(row.querySelector(".qty").textContent) });
        });

        if (orderItems.length === 0) { alert("Please add at least one menu item before paying!"); return; }

        const form = completeBtn.closest("form");
        if (!form.checkValidity()) { form.reportValidity(); return; }

        const now = new Date();
        paymentDatetime.textContent = now.toLocaleString('en-PH', { hour12: false });

        const saleId = Math.floor(Math.random() * 9000) + 1000;
        transactionId.textContent = `POS${now.getFullYear()}${(now.getMonth()+1).toString().padStart(2,'0')}${now.getDate().toString().padStart(2,'0')}${now.getHours().toString().padStart(2,'0')}${now.getMinutes().toString().padStart(2,'0')}${now.getSeconds().toString().padStart(2,'0')}${saleId}`;

        paymentItems.innerHTML = '';
        orderItems.forEach(row => {
            const menuRow = document.querySelector(`.dish-row[data-menu-id='${row.menu_id}']`);
            const name = menuRow.querySelector(".dish-name").textContent;
            const qty = row.qty;
            const price = parseFloat(menuRow.dataset.price);
            const li = document.createElement("li");
            li.textContent = `${name} ×${qty} = ₱${(qty*price).toFixed(2)}`;
            paymentItems.appendChild(li);
        });

        paymentSubtotal.textContent = document.getElementById("order-subtotal").textContent;
        paymentDiscount.textContent = document.getElementById("order-discount").textContent;
        paymentTotal.textContent = document.getElementById("order-total").textContent;

        cashInput.value = '';
        cashChange.textContent = '0.00';

        paymentPopup.style.display = 'flex';
    });

    cancelPaymentBtn.addEventListener("click", () => { paymentPopup.style.display = 'none'; });

    cashInput.addEventListener("input", () => {
        const total = parseFloat(paymentTotal.textContent) || 0;
        const cash = parseFloat(cashInput.value) || 0;
        cashChange.textContent = (cash - total >= 0 ? cash - total : 0).toFixed(2);
    });

// ====================== PAY BUTTON / RECEIPT ======================
payBtn.addEventListener("click", () => {
    
    const cash = parseFloat(cashInput.value) || 0;
    const total = parseFloat(paymentTotal.textContent) || 0;

    // BLOCK payment if insufficient
    if (cash < total) {
        e.preventDefault(); 
        alert("Cash is insufficient.");
        return false;
    }
    // OPEN WINDOW IMMEDIATELY to avoid pop-up blockers
    const printWindow = window.open("", "", "width=400,height=600");
    if (!printWindow) {
        alert("Pop-up blocked! Please allow pop-ups for this site.");
        return;
    }

    const form = document.querySelector("form");
    const formData = new FormData(form);
    formData.append("cash", cash);
    formData.append("form_type", "order_list");

    fetch(form.action || window.location.href, { method: "POST", body: formData })
        .then(res => res.text())
        .then(data => {
            // Build receipt content dynamically
            const itemsHTML = Array.from(document.querySelectorAll("#payment-items li"))
                .map(li => `<li>${li.textContent}</li>`).join("");

            const receiptHTML = `
                <div id="receipt-section">

                    <h3>STEADYSIP CORPORATION</h3>
                    <div class="center">BUSINESS NAME:</div>
                    <div class="center">STEADYSIP CORPORATION</div>
                    <div class="center">VILLA LOURDES</div>
                    <div class="center">MATAAS NA LUPA, LIPA CITY</div>
                    <div class="center">BATANGAS</div>

                    <div class="divider"></div>

                    <h3>SALES INVOICE</h3>

                    <div class="two-col">
                        <span>S.I. No.:</span>
                        <span id="r-transaction-id">${transactionId.textContent}</span>
                    </div>

                    <div class="two-col">
                        <span>Date/Time:</span>
                        <span id="r-datetime">${paymentDatetime.textContent}</span>
                    </div>

                    <div class="divider"></div>

                    <div class="center">----- ITEMS -----</div>

                    <ul id="r-items">${itemsHTML}</ul>

                    <div class="divider"></div>

                    <div class="two-col">
                        <span>Subtotal:</span>
                        <span>₱<span id="r-subtotal">${paymentSubtotal.textContent}</span></span>
                    </div>

                    <div class="two-col">
                        <span>Discount:</span>
                        <span>₱<span id="r-discount">${paymentDiscount.textContent}</span></span>
                    </div>

                    <div class="two-col">
                        <span>Total:</span>
                        <span>₱<span id="r-total">${paymentTotal.textContent}</span></span>
                    </div>

                    <div class="divider"></div>

                    <div class="two-col">
                        <span>Cash:</span>
                        <span>₱<span id="r-cash">${cash.toFixed(2)}</span></span>
                    </div>

                    <div class="two-col">
                        <span>Change:</span>
                        <span>₱<span id="r-change">${cashChange.textContent}</span></span>
                    </div>

                    <div class="divider"></div>

                    <p class="center">Thank You For Coming!</p>

                    <div class="divider"></div>

                    <h3 class="center">CUSTOMER INFO</h3>

                    <div>Customer Name: ______________</div>
                    <div>Address: _____________________</div>
                    <div>TIN: ________________________</div>
                    <div>BUSINESS STYLE: ___________</div>

                    <div class="divider"></div>

                    <div class="center label">POS Provider:</div>
                    <div class="center">SteadySip</div>
                    <div class="center">System Marketing Inc.</div>
                    <div class="center">Villa Lourdes, Mataas na Lupa</div>
                    <div class="center">Lipa City</div>
                    <div class="center">Batangas</div>

                    <div class="center label">Date Issued: <span id="r-issued"></span></div>
                    <div class="center label">Valid Until: <span id="r-valid-until"></span></div>

                    <div class="center label">PTU NUMBER:</div>
                    <div class="center">FP042024-059-0440369-00000</div>
                    <div class="center">Date Issued: <span id="r-ptu-issued"></span></div>

                </div>
                `;

            printWindow.document.write("<html><head><title>Receipt</title></head><body>");
            printWindow.document.write(receiptHTML);
            printWindow.document.write("</body></html>");
            printWindow.document.close();
            printWindow.print();

            // Reset order
            orderContainer.innerHTML = "";
            reservedIngredients = {};
            calculateTotals();
            updateCompleteButton();
            refreshTileAvailability();
            paymentPopup.style.display = "none";
        })
        .catch(err => {
            console.error(err);
            alert("Something went wrong while processing payment.");
        });
});

cashInput.addEventListener("input", () => {
    const total = parseFloat(paymentTotal.textContent) || 0;
    const cash = parseFloat(cashInput.value) || 0;
    payBtn.disabled = !(cash >= total);
});

paymentPopup.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        e.preventDefault(); // stops form submission
    }
});


    // ====================== INIT ======================
    updateCompleteButton();
    refreshTileAvailability();

    // Receipt date setup
    function setReceiptDates() {
        const now = new Date();
        const format = d => d.toLocaleDateString("en-PH", { month: "long", day: "numeric", year: "numeric" });
        document.getElementById("r-issued").textContent = format(now);
        document.getElementById("r-ptu-issued").textContent = format(now);
        const valid = new Date(now); valid.setFullYear(valid.getFullYear() + 1);
        document.getElementById("r-valid-until").textContent = format(valid);
    }
    setReceiptDates();

});
</script>




</body>
</html>
