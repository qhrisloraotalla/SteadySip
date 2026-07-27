<?php
include "sidebar.php";
    // ---- FETCH MENU ITEMS + CATEGORY ----
        $query = "
            SELECT 
                mi.id AS menu_id,
                mi.name AS menu_name,
                mi.price,
                mi.description,
                mi.is_active,
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

    // --- Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menu_item_id'])) {

        $menu_id = intval($_POST['menu_item_id']);
        $ingredient_ids = $_POST['ingredient_id'] ?? [];
        $quantities = $_POST['quantity'] ?? [];

        // Sanity check
        if (count($ingredient_ids) !== count($quantities)) {
            die("Invalid form submission");
        }

        // 1. Get all existing recipe IDs for this menu item
        $existing = [];
        $res = $conn->query("SELECT id, ingredient_id FROM recipes WHERE menu_item_id = $menu_id");
        while ($row = $res->fetch_assoc()) {
            $existing[$row['ingredient_id']] = $row['id'];
        }

        // 2. Loop through submitted ingredients
        $processed = []; // to keep track of ingredients we processed

        foreach ($ingredient_ids as $index => $ingredient_id) {
            $ingredient_id = intval($ingredient_id);
            $quantity = floatval($quantities[$index]);

            if (isset($existing[$ingredient_id])) {
                // Update existing ingredient
                $recipe_id = $existing[$ingredient_id];
                $stmt = $conn->prepare("UPDATE recipes SET quantity = ? WHERE id = ?");
                $stmt->bind_param("di", $quantity, $recipe_id);
                $stmt->execute();
            } else {
                // Insert new ingredient
                $stmt = $conn->prepare("INSERT INTO recipes (menu_item_id, ingredient_id, quantity) VALUES (?, ?, ?)");
                $stmt->bind_param("iid", $menu_id, $ingredient_id, $quantity);
                $stmt->execute();
            }

            $processed[] = $ingredient_id;
        }

        // 3. Delete ingredients that were removed
        $to_delete = array_diff(array_keys($existing), $processed);
        if (!empty($to_delete)) {
            $ids = implode(",", $to_delete);
            $conn->query("DELETE FROM recipes WHERE menu_item_id = $menu_id AND ingredient_id IN ($ids)");
        }

        // Redirect or show success
        echo "<script>alert('Ingredients updated successfully!'); window.location.href='menuItems.php';</script>";
        exit;
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

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Menu Items</title>
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="menuItems.css">
</head>

<body>

<h1 style="text-align: center; margin-top: 10px; color: #333; font-family: Arial, sans-serif; font-weight: bold;">      
        Menu Items
    </h1>

<div class="pos-wrapper">

    <!-- LEFT SIDE: MENU TILES -->
    <div style="width:105%;">
        <div class="menu-controls">
            <input type="text" id="search" placeholder="Search menu...">

            <div class="tabs">
                <button class="tab active" data-category="all">All</button>
                <button class="tab" data-category="dish">Dish</button>
                <button class="tab" data-category="beverage">Beverage</button>
                <button class="tab" data-category="dessert">Dessert</button>
            </div>

        </div>
        <a href="checkrecipes.php" style="text-decoration: none;">
            <button type="button">Add a New Menu Item</button>
        </a>

            <br>
            <br>

        <div class="menu-grid">
            <?php foreach ($menuItems as $item): 
            ?>

            <div class="menu-tile" data-popup="edit" data-action="show"
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
            </div>

            <?php endforeach; ?>
        </div>
        <br>

    <div id="edit" class="popup popup-content">
        <div class="popup-content">
            <h3 id="popup-title">Edit Ingredients</h3>

            <form id="ingredients-form" method="POST">
                <input type="hidden" name="menu_item_id" id="menu_item_id">

                <table id="ingredient-table">
                    <thead>
                        <tr>
                            <th>Ingredient</th>
                            <th>Quantity</th>
                            <th>Unit</th>  
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="ingredient-list">
                        <!-- ingredients will load here -->
                    </tbody>
                </table>
                <br>
                <button type="button" id="add-ingredient-btn">+ Add Ingredient</button>
                <button type="submit">Save Changes</button>
            </form>
            <button type="button" id="delete-menu-item-btn" style="background:red;color:white;margin-top:10px;">
                Delete Menu Item
            </button>
        </div>
    </div>





<script>
    document.addEventListener("DOMContentLoaded", function () {
        /* ============================
        SEARCH + CATEGORY FILTERING
        ============================ */
        const searchInput = document.getElementById("search");
        const tabs = document.querySelectorAll(".tab");
        const tiles = document.querySelectorAll(".menu-tile");

        let activeCategory = "all";

        // Search filter
        searchInput.addEventListener("input", () => {
            const term = searchInput.value.toLowerCase();

            tiles.forEach(tile => {
                const name = tile.dataset.name.toLowerCase();
                const category = tile.dataset.category.toLowerCase();

                const matchText = name.includes(term);
                const matchCategory = activeCategory === "all" || category === activeCategory;

                tile.style.display = (matchText && matchCategory) ? "block" : "none";
            });
        });

        // Category tab filter
        tabs.forEach(tab => {
            tab.addEventListener("click", () => {

                // Set active tab
                tabs.forEach(t => t.classList.remove("active"));
                tab.classList.add("active");

                activeCategory = tab.dataset.category.toLowerCase();
                const term = searchInput.value.toLowerCase();

                tiles.forEach(tile => {
                    const name = tile.dataset.name.toLowerCase();
                    const category = tile.dataset.category.toLowerCase();

                    const matchText = name.includes(term);
                    const matchCategory = (activeCategory === "all" || category === activeCategory);

                    tile.style.display = (matchText && matchCategory) ? "block" : "none";
                });
            });
        });

        /* ============================
        POPUP OPEN / CLOSE LOGIC
        ============================ */
        const popupButtons = document.querySelectorAll("[data-popup][data-action='show']");
        const popups = document.querySelectorAll(".popup");

        // Open popup
        popupButtons.forEach(btn => {
            btn.addEventListener("click", (e) => {
                    e.stopPropagation(); // <-- FIX HERE
                const popupId = btn.getAttribute("data-popup");
                localStorage.setItem("openPopup", popupId);
                document.getElementById(popupId).classList.add("show");
            });
        });

        // Close popup using close button
        popups.forEach(popup => {
            const closeBtn = popup.querySelector("[data-action='close']");
            if (closeBtn) {
                closeBtn.addEventListener("click", () => {
                    popup.classList.remove("show");
                    localStorage.removeItem("openPopup");
                });
            }
        });

        // Load previously open popup
        localStorage.removeItem("openPopup");
        const openPopup = localStorage.getItem("openPopup");
        if (openPopup) {
            const popup = document.getElementById(openPopup);
            if (popup) popup.classList.add("show");
        }

        // Stop clicks inside the popup from closing it
        document.querySelectorAll(".popup-content").forEach(content => {
            content.addEventListener("click", function (e) {
                e.stopPropagation();
            });
        });

        // Close popup when clicking outside
        document.addEventListener("click", function (e) {

            document.querySelectorAll(".popup.show").forEach(popup => {
                const content = popup.querySelector(".popup-content");
                const clickedOutside = !content.contains(e.target);
                const isTriggerButton = e.target.matches("[data-popup][data-action='show']");

                if (clickedOutside && !isTriggerButton) {
                    popup.classList.remove("show");
                    localStorage.removeItem("openPopup");
                }
            });

        });

        popupButtons.forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.stopPropagation();

                const popupId = btn.getAttribute("data-popup");
                const menuId = btn.dataset.id;
                const menuName = btn.dataset.name;

                document.getElementById("menu_item_id").value = menuId;
                document.getElementById("popup-title").innerText = "Edit Ingredients: " + menuName;

                // Load ingredients via AJAX
                fetch("get_ingredients.php?menu_id=" + menuId)
                    .then(res => res.json())
                    .then(data => renderIngredientList(data));

                document.getElementById(popupId).classList.add("show");
            });
        });

        document.getElementById("delete-menu-item-btn").addEventListener("click", function() {
            const menuItemId = document.getElementById("menu_item_id").value;

            if (!menuItemId) {
                alert("Error: No menu item selected.");
                return;
            }

            if (!confirm("Are you sure you want to delete this menu item?")) {
                return;
            }

            fetch("deleteMenuItem.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "menu_item_id=" + encodeURIComponent(menuItemId)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Menu item deleted successfully!");
                    location.reload(); // or hide popup & refresh list
                } else {
                    alert("Error: " + data.message);
                }
            })
            .catch(err => console.error(err));
        });


        function setupUnitAutoUpdate() {
            document.querySelectorAll(".ingredient-select").forEach(select => {
                select.addEventListener("change", function() {
                    const unit = this.selectedOptions[0].dataset.unit || "";
                    this.closest("tr").querySelector(".unit-cell").textContent = unit;
                    updateInvalidIngredients();
                });
            });
        }

        function renderIngredientList(items) {
            const container = document.getElementById("ingredient-list");
            container.innerHTML = "";

            items.forEach(item => {
                const row = document.createElement("tr");
                row.dataset.recipeId = item.recipe_id;

                row.innerHTML = `
                    <td>
                        <select name="ingredient_id[]" class="ingredient-select">
                            <option value="${item.ingredient_id}" selected>${item.ingredient_name}</option>
                            <?php
                            $supplies = $conn->query("SELECT id, name, unit FROM supplies ORDER BY name");
                            while ($s = $supplies->fetch_assoc()) {
                                echo "<option value='{$s['id']}' data-unit='{$s['unit']}'>{$s['name']}</option>";
                            }
                            ?>
                        </select>
                    </td>

                    <td>
                        <input type="number" name="quantity[]" step="1" value="${item.quantity}" min="1">
                    </td>

                    <td class="unit-cell">${item.unit}</td> <!-- SHOW UNIT HERE -->

                    <td>
                        <button type="button" class="delete-ingredient">Delete</button>
                    </td>
                `;

                container.appendChild(row);
            });

            attachDeleteEvents();
            setupUnitAutoUpdate();
            updateInvalidIngredients();
        }


        function attachDeleteEvents() {
            document.querySelectorAll(".delete-ingredient").forEach(btn => {
                btn.onclick = () => {
                    btn.closest("tr").remove();
                };
            });
        }


        function attachDeleteEvents() {
            document.querySelectorAll(".delete-ingredient").forEach(btn => {
                btn.onclick = () => {
                    btn.parentElement.remove();
                };
            });
        }

        function updateInvalidIngredients() {
            const selected = new Set();
            
            document.querySelectorAll('.ingredient-select').forEach(sel => {
                if (sel.value) selected.add(sel.value);
            });

            document.querySelectorAll('.ingredient-select').forEach(sel => {
                Array.from(sel.options).forEach(opt => {
                    if (!opt.value) return;
                    opt.disabled = selected.has(opt.value) && sel.value !== opt.value;
                });
            });
        }


        document.getElementById("add-ingredient-btn").addEventListener("click", () => {
            const container = document.getElementById("ingredient-list");

            const row = document.createElement("tr");

            row.innerHTML = `
                <td>
                    <select name="ingredient_id[]" class="ingredient-select">
                        <option disabled selected>Select ingredient</option>
                        <?php
                        $supplies = $conn->query("SELECT id, name, unit FROM supplies ORDER BY name");
                        while ($s = $supplies->fetch_assoc()) {
                            echo "<option value='{$s['id']}' data-unit='{$s['unit']}'>{$s['name']}</option>";
                        }
                        ?>
                    </select>
                </td>

                <td>
                    <input type="number" name="quantity[]" step="1" min="1">
                </td>

                <td class="unit-cell"></td>
                
                <td>
                    <button type="button" class="delete-ingredient">Delete</button>
                </td>
            `;

            container.appendChild(row);

            attachDeleteEvents();
            setupUnitAutoUpdate();
            updateInvalidIngredients();
        });


        function attachDeleteEvents() {
            // Select all delete buttons inside the ingredient table
            document.querySelectorAll(".delete-ingredient").forEach(btn => {
                btn.onclick = () => {
                    // Remove the row instantly
                    btn.closest("tr").remove();
                };
            });
        }





    });
</script>

</body>
</html>
