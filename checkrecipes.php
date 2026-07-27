<?php
session_start();

if (!isset($_SESSION['id'])) {
    header("Location: loginpage.php");
    exit();
}

include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

    if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_cost') {
        
        $id = intval($_GET['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['error' => 'Invalid ID']);
            exit;
        }
        
        // Get latest cost + bundle info
        $sql = "
        SELECT 
        sb.unit_cost,
        sb.bundle,
        sb.unit_per_bundle,
        s.is_bundled
        FROM supply_batches sb
        JOIN supplies s ON s.id = sb.supply_id
        WHERE sb.supply_id = ?
        ORDER BY sb.received_date DESC
        LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['error' => 'No batch found']);
            exit;
        }
        
        $row = $result->fetch_assoc();

        // Compute cost per unit
        if ($row['is_bundled']) {
            $total_units = $row['bundle'] * $row['unit_per_bundle'];
            $per_unit_cost = ($total_units > 0) ? ($row['unit_cost'] / $total_units) : 0;
        } else {
            $per_unit_cost = $row['unit_cost'];
        }
        
        echo json_encode([
            "per_unit_cost" => round($per_unit_cost, 2)
        ]);
        error_log("AJAX triggered with ID: " . $id);

        exit;
    }

    include "sidebar2.php";
    

// --- Fetch categories for dropdown
$category_sql = "SELECT * FROM categories";
$category_result = $conn->query($category_sql);
$selected_category_id = $_POST['category_id'] ?? null;

// --- Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = ucwords(strtolower(trim($_POST['recipeName'] ?? '')));
    $price = (float)($_POST['price'] ?? 0);
    $desc = ucwords(strtolower(trim($_POST['recipeDescription'] ?? '')));
    $selected_category_id = (int)($_POST['category_id'] ?? 0);

    $ingredient_ids = $_POST['ingredient_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    // --- Validate fields
    if (
        empty($name) ||
        $price <= 0 ||
        empty($desc) ||
        $selected_category_id <= 0 ||
        count($ingredient_ids) === 0
    ) {
        echo "<script>
            localStorage.setItem('pendingAlert', JSON.stringify({
            message: 'Please fill out all fields and add at least one ingredient.',
            type: 'error'
            }));
            window.location.href = 'checkinventory.php';
        </script>";
exit;

    } else {

        $valid_ingredient_found = false;

        foreach ($ingredient_ids as $index => $ingredient_id) {
            $ingredient_id = (int)$ingredient_id;
            $qty = (float)$quantities[$index];
            if ($ingredient_id > 0 && $qty > 0) {
                $valid_ingredient_found = true;
                break;
            }
        }

        if (!$valid_ingredient_found) {
            echo "<script>
                localStorage.setItem('pendingAlert', JSON.stringify({
                    message: 'Each ingredient must have a quantity greater than 0.',
                    type: 'error'
                }));
                window.location.href = 'checkinventory.php';
            </script>";
        exit;

        }
        
        $stmt = $conn->prepare("SELECT id FROM menu_items WHERE LOWER(name) = LOWER(?)");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $conn->rollback();
            $stmt->close();
            echo "<script>
                    localStorage.setItem('pendingAlert', JSON.stringify({
                        message: 'Item already exists in the menu!',
                        type: 'error'
                    }));
                    window.location.href = 'checkinventory.php';
                </script>";
            exit;

        }
        $stmt->close();

        $proc->queryNewRecipe($conn, $name, $selected_category_id, $price, $desc, $ingredient_ids, $quantities);

    }
}

function categoryDropdown($category_result, $selected_category_id) {
    echo '<select class="dropdown_" name="category_id" id="category_id" required>';
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

    echo '</select><br><br>';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Menu</title>
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="recipe.css" />    
</head>
<body>
    <div class="form-div">
        <form action="" method="POST">
                <h2>Add a Menu Item</h2>

                <label for="recipeName">Recipe Name</label><br>
                <input type="text" id="recipeName" name="recipeName" required>

                <?php
                categoryDropdown($category_result, $selected_category_id);
                ?>

                <label for="recipeDescription">Recipe Description</label><br>
                <textarea id="recipeDescription" name="recipeDescription" rows="2" cols="40"></textarea><br>

                <h3>Items Used</h3>
                <div id="ingredient-container">
                    <div class="ingredient-row">
                        <select name="ingredient_id[]" class="ingredient-select" required>
                            <option value="" disabled selected hidden>Select Item</option>
                            <?php
                            $ing_sql = "SELECT id, name, unit FROM supplies";
                            $ing_result = $conn->query($ing_sql);
                            while ($row = $ing_result->fetch_assoc()) {
                                echo "<option value='{$row['id']}' data-unit='{$row['unit']}'>{$row['name']}</option>";
                            }
                            ?>
                        </select>

                        <input type="number" name="quantity[]" class="qty-input" step="1" min="0" placeholder="Quantity" required>
                        <span class="unit-label"></span>
                        <button type="button" class="remove-btn">−</button>

                        <div class="ingredient-prices">
                            <div class="price-row">
                                <p>Cost per supply used:</p>
                                <span class="cost-per-unit">₱0.00</span>
                            </div>

                            <div class="price-row">
                                <p>Total cost per supply:</p>
                                <span class="cost-subtotal">₱0.00</span>
                            </div>
                        </div>

                    </div>

                </div>
                <button type="button" id="add-ingredient">+ Add Item</button>
                
                <br><br>
                
                <h4>Total Ingredient Cost: ₱<span id="totalCost">0.00</span></h4>
                <label for="price">Price</label><br>
                <input type="number" id="price" name="price" placeholder="₱"  min="0" required><br><br>

                <button type="submit">+ Add Menu Item</button>

        </form>
    </div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const container = document.getElementById("ingredient-container");
    const totalCostDisplay = document.getElementById("totalCost");

    /* ---------------------------------------------------------
        Helper: Recalculate total ingredient cost
    --------------------------------------------------------- */
    function updateTotals() {
        let total = 0;

        container.querySelectorAll(".ingredient-row").forEach(row => {
            let val = parseFloat(row.querySelector(".cost-subtotal").dataset.value || 0);
            total += val;
        });

        totalCostDisplay.textContent = total.toFixed(2);
    }

    /* ---------------------------------------------------------
        Helper: Fetch unit cost via AJAX
    --------------------------------------------------------- */
    function fetchUnitCost(ingredientId, row) {
        console.log("Fetching cost for ID:", ingredientId);

        fetch(window.location.pathname + "?ajax=get_cost&id=" + ingredientId)
            .then(res => res.json())
            .then(data => {
                let perUnit = parseFloat(data.per_unit_cost || 0);

                // Show cost per unit
                const costPU = row.querySelector(".cost-per-unit");
                costPU.textContent = "₱" + perUnit.toFixed(2);
                costPU.dataset.value = perUnit;

                // Recompute subtotal
                const qty = parseFloat(row.querySelector(".qty-input").value || 0);
                const subtotal = perUnit * qty;

                const subNode = row.querySelector(".cost-subtotal");
                subNode.textContent = "₱" + subtotal.toFixed(2);
                subNode.dataset.value = subtotal;

                updateTotals();
            });
    }

    /* ---------------------------------------------------------
        EVENT DELEGATION: Handle ingredient select + qty changes
    --------------------------------------------------------- */
    container.addEventListener("change", e => {
        const row = e.target.closest(".ingredient-row");
        if (!row) return;

        /* Ingredient selected */
        if (e.target.classList.contains("ingredient-select")) {
            const ingredientId = e.target.value;

            // Set unit label
            const unit = e.target.options[e.target.selectedIndex].dataset.unit || "";
            row.querySelector(".unit-label").textContent = unit;

            if (ingredientId) {
                fetchUnitCost(ingredientId, row);
            }
        }

        /* Quantity changed */
        if (e.target.classList.contains("qty-input")) {
            const ingredientId = row.querySelector(".ingredient-select").value;
            if (ingredientId) {
                fetchUnitCost(ingredientId, row);
            }
        }
    });

    /* ---------------------------------------------------------
        Add new ingredient row
    --------------------------------------------------------- */
    document.getElementById("add-ingredient").addEventListener("click", () => {
        const baseRow = container.querySelector(".ingredient-row");
        const newRow = baseRow.cloneNode(true);

        // Reset fields
        newRow.querySelector(".ingredient-select").selectedIndex = 0;
        newRow.querySelector(".qty-input").value = "";
        newRow.querySelector(".unit-label").textContent = "";

        // Reset cost displays
        newRow.querySelector(".cost-per-unit").textContent = "₱0.00";
        newRow.querySelector(".cost-per-unit").dataset.value = 0;

        newRow.querySelector(".cost-subtotal").textContent = "₱0.00";
        newRow.querySelector(".cost-subtotal").dataset.value = 0;

        container.appendChild(newRow);

        updateTotals();
    });

    /* ---------------------------------------------------------
        Remove ingredient row
    --------------------------------------------------------- */
    container.addEventListener("click", e => {
        if (e.target.classList.contains("remove-btn")) {
            const rows = container.querySelectorAll(".ingredient-row");
            if (rows.length > 1) {
                e.target.closest(".ingredient-row").remove();
                updateTotals();
            }
        }
    });
});
</script>
<!-- Custom Alert Modal -->
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