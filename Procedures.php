<?php
include "connection.php";
include "sendsms.php";

class Procedures
{
    function getAllUserPhones($conn) {
        $phones = [];
        $query = "SELECT phone FROM users WHERE phone IS NOT NULL AND phone != ''";
        $result = $conn->query($query);

        while ($row = $result->fetch_assoc()) {
            $phones[] = $row['phone'];
        }

        return $phones;
    }

    //for testing
    // function getAllUserPhones($conn) 
    // {
    //     $phones = [];
    //     $query = "SELECT phone FROM users WHERE phone IS NOT NULL AND phone != '' AND username = 'tantan'";
    //     $result = $conn->query($query);

    //     while ($row = $result->fetch_assoc()) {
    //         $phones[] = $row['phone'];
    //     }
    //     return $phones;
    // }


    private $conn;

    public function __construct()
    {
        $database = new Database();
        $this->conn = $database->conn;
    }

    public function getConnection()
    {
        return $this->conn;
    }

    function updateSuppliesCurrentStock($conn) {
        $sql = "
            SELECT 
                s.id AS supply_id,
                COALESCE(SUM(b.quantity), 0) AS total_stock
            FROM supplies s
            LEFT JOIN supply_batches b 
                ON b.supply_id = s.id
                AND (b.expiration_date IS NULL OR b.expiration_date >= CURDATE())
                AND b.discarded_at IS NULL
            GROUP BY s.id
        ";

        $result = $conn->query($sql);

        if (!$result) {
            echo "Query error: " . $conn->error;
            return false;
        }

        $updateStmt = $conn->prepare("
            UPDATE supplies
            SET current_stock = ?
            WHERE id = ?
        ");

        while ($row = $result->fetch_assoc()) {
            $updateStmt->bind_param("ii", $row['total_stock'], $row['supply_id']);
            $updateStmt->execute();
        }

        return true;
    }




    function inventoryNotifs($conn) {
        {

            if (isset($_SESSION['inventory_notif_shown']) && $_SESSION['inventory_notif_shown'] === true) {
                return;
            }

            $alerts = [
                'expired' => [],
                'soon_expire' => [],
                'low' => []
            ];

            $expiredItems = [];
            $soonExpiringItems = [];
            $lowItems = [];

            $expiredQuery = "
                    SELECT 
                        s.id,
                        s.name,
                        s.unit,
                        SUM(CASE WHEN b.expiration_date < NOW() THEN b.quantity ELSE 0 END) AS expired_stock,
                        MIN(b.expiration_date) AS nearest_expiration
                    FROM supplies s
                    JOIN supply_batches b ON s.id = b.supply_id
                    AND expiration_date <= CURDATE()
                    AND b.quantity != 0
                    GROUP BY s.id, s.name, s.unit
            ";

            if ($expiredResult = $conn->query($expiredQuery)) {
                while ($row = $expiredResult->fetch_assoc()) {
                    $expiredItems[] = $row;
                    $alerts['expired'][] = $row;
                }
            }

            $soonExpireQuery = "
                SELECT 
                    s.id,
                    s.name,
                    s.unit,
                    SUM(b.quantity) AS soon_expiring_stock,
                    MIN(b.expiration_date) AS nearest_expiration
                FROM supplies s
                JOIN supply_batches b ON s.id = b.supply_id
                WHERE b.expiration_date > CURDATE()
                AND b.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                AND b.quantity != 0
                GROUP BY s.id, s.name, s.unit
            ";

            if ($soonExpireResult = $conn->query($soonExpireQuery)) {
                while ($row = $soonExpireResult->fetch_assoc()) {
                    $soonExpiringItems[] = $row;
                    $alerts['soon_expire'][] = $row;
                }
            }


            $lowStockQuery = "
                SELECT 
                    s.name, 
                    s.unit, 
                    s.current_stock, 
                    s.reorder_level
                FROM supplies s
                WHERE s.current_stock <= s.reorder_level
                AND s.current_stock != 0
            ";

            if ($lowResult = $conn->query($lowStockQuery)) {
                while ($row = $lowResult->fetch_assoc()) {
                    $lowItems[] = $row;
                    $alerts['low'][] = $row;
                }
            }

            if (!empty($expiredItems) || !empty($soonExpiringItems) || !empty($lowItems)) {

                    echo '<div class="alert alert-dismissible fade show floating-alert combined-alert" role="alert">';
                    echo '<h5><strong>⚠️Inventory⚠️</strong></h5>';

                    if (!empty($alerts['expired'])) {
                        echo '<span class="text-danger fw-bold">Expired Items:</span><br>';
                        foreach ($alerts['expired'] as $item) {
                            echo htmlspecialchars($item['expired_stock']) .
                                htmlspecialchars($item['unit']) . " " .
                                htmlspecialchars($item['name']) . " — expired on " . 
                                htmlspecialchars($item['nearest_expiration']) . "<br>";
                        }
                        echo "<hr>";
                    }

                    if (!empty($alerts['soon_expire'])) {
                        echo '<span class="text-danger fw-bold">Soon Expiring Items:</span><br>';
                        foreach ($alerts['soon_expire'] as $item) {
                            echo htmlspecialchars($item['soon_expiring_stock']) .
                                htmlspecialchars($item['unit']) . " " .
                                htmlspecialchars($item['name']) . " — soon expiring on " . 
                                htmlspecialchars($item['nearest_expiration']) . "<br>";
                        }
                        echo "<hr>";
                    }

                    if (!empty($alerts['low'])) {
                        echo '<span class="text-warning fw-bold">Low Stock:</span><br>';
                        foreach ($alerts['low'] as $item) {
                            echo htmlspecialchars($item['name']) . " — " . 
                                htmlspecialchars($item['current_stock']) . " " . 
                                htmlspecialchars($item['unit']) . 
                                " left (Reorder at " . htmlspecialchars($item['reorder_level']) . " " . 
                                htmlspecialchars($item['unit']) . ")<br>";
                        }
                    }

                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                    
                    $_SESSION['inventory_notif_shown'] = true;
            }

            return $alerts;
        }
    }


    function inventoryAlerts($conn) {
        {
            $userPhones = $this->getAllUserPhones($conn); 

            $alerts = [
                'expired' => [],
                'soon_expire' => [],
                'low' => []
            ];

            $expiredItems = [];
            $soonExpiringItems = [];
            $lowItems = [];

            $expiredQuery = "
                    SELECT 
                        s.id,
                        s.name,
                        s.unit,
                        SUM(CASE WHEN b.expiration_date < NOW() THEN b.quantity ELSE 0 END) AS expired_stock,
                        MIN(b.expiration_date) AS nearest_expiration
                    FROM supplies s
                    JOIN supply_batches b ON s.id = b.supply_id
                    AND expiration_date <= CURDATE()
                    AND b.quantity != 0
                    GROUP BY s.id, s.name, s.unit
            ";

            if ($expiredResult = $conn->query($expiredQuery)) {
                while ($row = $expiredResult->fetch_assoc()) {
                    $expiredItems[] = $row;
                    $alerts['expired'][] = $row;
                }
            }

            $soonExpireQuery = "
                SELECT 
                    s.id,
                    s.name,
                    s.unit,
                    SUM(b.quantity) AS soon_expiring_stock,
                    MIN(b.expiration_date) AS nearest_expiration
                FROM supplies s
                JOIN supply_batches b ON s.id = b.supply_id
                WHERE b.expiration_date > CURDATE()
                AND b.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
                AND b.quantity != 0
                GROUP BY s.id, s.name, s.unit
            ";

            if ($soonExpireResult = $conn->query($soonExpireQuery)) {
                while ($row = $soonExpireResult->fetch_assoc()) {
                    $soonExpiringItems[] = $row;
                    $alerts['soon_expire'][] = $row;
                }
            }


            $lowStockQuery = "
                SELECT 
                    s.name, 
                    s.unit, 
                    s.current_stock, 
                    s.reorder_level
                FROM supplies s
                WHERE s.current_stock <= s.reorder_level
                AND s.current_stock != 0
            ";

            if ($lowResult = $conn->query($lowStockQuery)) {
                while ($row = $lowResult->fetch_assoc()) {
                    $lowItems[] = $row;
                    $alerts['low'][] = $row;
                }
            }

            if (!empty($expiredItems) || !empty($soonExpiringItems) || !empty($lowItems)) {
                $msgParts = [];

                if (!empty($expiredItems)) {
                    $msgParts[] = "* EXPIRED STOCK ALERT *";
                    foreach ($expiredItems as $item) {
                        $msgParts[] = "- {$item['name']} ({$item['expired_stock']} {$item['unit']})";
                    }
                    $msgParts[] = "";
                }

                if (!empty($soonExpiringItems)) {
                    $msgParts[] = "* SOON EXPIRING STOCK ALERT *";
                    foreach ($soonExpiringItems as $item) {
                        $msgParts[] = "- {$item['name']} ({$item['soon_expiring_stock']} {$item['unit']}) on {$item['nearest_expiration']}";
                    }
                    $msgParts[] = "";
                }

                if (!empty($lowItems)) {
                    $msgParts[] = "* LOW STOCK ALERT *";
                    foreach ($lowItems as $item) {
                        $msgParts[] = "- {$item['name']} ({$item['current_stock']} {$item['unit']})";
                    }
                }


                $finalMsg = implode("\n", $msgParts);

                $userPhones = array_unique($userPhones);

                foreach ($userPhones as $phone) {
                    sendSMS($phone, $finalMsg);
                }


                if (!empty($expiredItems)) {
                    $logStmt = $conn->prepare("INSERT INTO alert_logs (alert_type, item_name, sent_at) VALUES ('expired', ?, NOW())");
                    foreach ($expiredItems as $item) {
                        $logStmt->bind_param("s", $item['name']);
                        $logStmt->execute();
                    }
                }

                if (!empty($soonExpiringItems)) {
                    $logStmt = $conn->prepare("INSERT INTO alert_logs (alert_type, item_name, sent_at) VALUES ('soon_expiring', ?, NOW())");
                    foreach ($soonExpiringItems as $item) {
                        $logStmt->bind_param("s", $item['name']);
                        $logStmt->execute();
                    }
                }

                if (!empty($lowItems)) {
                    $logStmt = $conn->prepare("INSERT INTO alert_logs (alert_type, item_name, sent_at) VALUES ('low', ?, NOW())");
                    foreach ($lowItems as $item) {
                        $logStmt->bind_param("s", $item['name']);
                        $logStmt->execute();
                    }
                }
            }

            return $alerts;
        }
    }


    function updateExpiredBatches($conn)
    {
        $sql = "
            UPDATE supply_batches
            SET discarded_at = CURDATE()
            WHERE expiration_date IS NOT NULL
            AND expiration_date <= CURDATE()
        ";

        if (!$conn->query($sql)) {
            error_log('Error updating expired batches: ' . $conn->error);
        }
    }
    

    function queryRestock($conn, $supply_id, $quantity, $unit_cost, $subtotal, $expiration_date, $bundle, $unit_per_bundle, $user_id, $user_name)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            
            $stmt = $conn->prepare("
                INSERT INTO purchases (user_id, purchase_date, total_cost)
                VALUES (?, NOW(), ?)
            ");
            $stmt->bind_param("id", $user_id, $subtotal);
            $stmt->execute();
            $purchase_id = $conn->insert_id;
            $stmt->close();

            
            $stmt = $conn->prepare("
                INSERT INTO purchase_items (purchase_id, ingredient_id, quantity, unit_cost, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiddd", $purchase_id, $supply_id, $quantity, $unit_cost, $subtotal);
            $stmt->execute();
            $purchase_item_id = $conn->insert_id;
            $stmt->close();

            
            $stmt = $conn->prepare("
                INSERT INTO supply_batches (supply_id, purchase_item_id, bundle, unit_per_bundle, quantity, unit_cost, expiration_date, received_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iiiidds", $supply_id, $purchase_item_id, $bundle, $unit_per_bundle, $quantity, $unit_cost, $expiration_date);
            $stmt->execute();
            $stmt->close();

            
            $update = $conn->prepare("UPDATE supplies SET current_stock = current_stock + ? WHERE id = ?");
            $update->bind_param("di", $quantity, $supply_id);
            $update->execute();
            $update->close();

            
            $stmt = $conn->prepare("
                INSERT INTO audit_log (user_id, action, personnel, created_at)
                VALUES (?, 'purchase', ?, NOW())
            ");
            $stmt->bind_param("is", $user_id, $user_name);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return true;

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    function queryNewItem($conn, $name, $unit, $quantity, $restock_lvl, $unit_cost, $subtotal, $expiration_date, $type_id, $bundle, $unit_per_bundle, $is_bundled, $user_id, $user_name)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            if (is_null($bundle)) $bundle = 0;
            if (is_null($unit_per_bundle)) $unit_per_bundle = 0;
            
            $stmt = $conn->prepare("SELECT id FROM supplies WHERE LOWER(name) = LOWER(?)");
            $stmt->bind_param("s", $name);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $stmt->close();
                $conn->rollback();
                return "exists";
            }
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO supplies (name, unit, current_stock, reorder_level, last_updated, type_id, is_bundled)
                VALUES (?, ?, ?, ?, NOW(), ?, ?)
            ");
            $stmt->bind_param("ssddii", $name, $unit, $quantity, $restock_lvl, $type_id, $is_bundled);
            $stmt->execute();
            $supply_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO purchases (user_id, purchase_date, total_cost)
                VALUES (?, NOW(), ?)
            ");
            $stmt->bind_param("id", $user_id, $subtotal);
            $stmt->execute();
            $purchase_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO purchase_items (purchase_id, ingredient_id, quantity, unit_cost, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("iiddd", $purchase_id, $supply_id, $quantity, $unit_cost, $subtotal);
            $stmt->execute();
            $purchase_item_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO supply_batches (supply_id, purchase_item_id, bundle, unit_per_bundle,  quantity, unit_cost, expiration_date, received_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("iiiidds", $supply_id, $purchase_item_id, $bundle, $unit_per_bundle, $quantity, $unit_cost, $expiration_date);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO audit_log (user_id, action, personnel, created_at)
                VALUES (?, 'purchase', ?, NOW())
            ");
            $stmt->bind_param("is", $user_id, $user_name);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return true;

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
    }

    function queryNewRecipe($conn, $name, $selected_category_id, $price, $desc, $ingredient_ids, $quantities)
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO menu_items (name, category_id, price, description)
                                    VALUES (?, ?, ?, ?)");
            $stmt->bind_param("sids", $name, $selected_category_id, $price, $desc);
            $stmt->execute();
            $menu_item_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO recipes (menu_item_id, ingredient_id, quantity)
                                    VALUES (?, ?, ?)");
            foreach ($ingredient_ids as $index => $ingredient_id) {
                $ingredient_id = (int)$ingredient_id;
                $qty = (float)$quantities[$index];

                if ($ingredient_id > 0 && $qty > 0) {
                    $stmt->bind_param("iid", $menu_item_id, $ingredient_id, $qty);
                    $stmt->execute();
                }
            }
            $stmt->close();

            $conn->commit();
            echo "<script>alert('Menu item and recipe added successfully!');</script>";
            return true;

        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>alert('Error: " . addslashes($e->getMessage()) . "');</script>";
        }
    }

    function queryPendingOrder($conn, $user_id, $user_name, $selected_order_type, $selected_method, $selected_discount, $menu_item_ids, $quantities, $comment) 
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            $subtotal = 0.0;

            foreach ($menu_item_ids as $index => $menu_item_id) {
                $menu_item_id = (int)$menu_item_id;
                $qty = (float)($quantities[$index] ?? 0);

                if ($menu_item_id <= 0 || $qty <= 0) continue;

                $stmt = $conn->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmt->bind_param("i", $menu_item_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();

                if ($res->num_rows === 0) {
                    throw new Exception("Menu item (ID {$menu_item_id}) not found.");
                }

                $row = $res->fetch_assoc();
                $price = (float)$row['price'];
                $subtotal += $price * $qty;
            }

            if ($subtotal <= 0) {
                throw new Exception("No valid menu items/quantities provided.");
            }

            $discount_amount = 0.0;
            if ($selected_discount === 'PWD' || $selected_discount === 'senior') {
                $discount_amount = $subtotal * 0.20;
            }

            $total = round($subtotal - $discount_amount, 2);

            $needed_ingredients = [];

            foreach ($menu_item_ids as $index => $menu_item_id) {
                $menu_item_id = (int)$menu_item_id;
                $qty = (float)($quantities[$index] ?? 0);

                if ($menu_item_id <= 0 || $qty <= 0) continue;

                $stmt = $conn->prepare("
                    SELECT r.ingredient_id, r.quantity AS recipe_qty, s.current_stock, s.name AS ingredient_name
                    FROM recipes r
                    JOIN supplies s ON r.ingredient_id = s.id
                    WHERE r.menu_item_id = ?
                ");
                $stmt->bind_param("i", $menu_item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();

                while ($row = $result->fetch_assoc()) {
                    $ingredient_id = (int)$row['ingredient_id'];
                    $required = $row['recipe_qty'] * $qty;
                    $current_stock = $row['current_stock'];
                    $ingredient_name = $row['ingredient_name'];

                    if (!isset($needed_ingredients[$ingredient_id])) {
                        $needed_ingredients[$ingredient_id] = [
                            'name' => $ingredient_name,
                            'required' => 0,
                            'available' => $current_stock
                        ];
                    }

                    $needed_ingredients[$ingredient_id]['required'] += $required;
                }
            }

            foreach ($needed_ingredients as $ingredient_id => &$data) {
                $stmt = $conn->prepare("
                    SELECT SUM(quantity) AS total_valid_stock
                    FROM supply_batches
                    WHERE supply_id = ? 
                    AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                ");
                $stmt->bind_param("i", $ingredient_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $stmt->close();

                $row = $res->fetch_assoc();
                $valid_stock = (float)($row['total_valid_stock'] ?? 0);

                $data['available'] = $valid_stock;

                if ($data['required'] > $valid_stock) {
                    throw new Exception("Not enough *valid* (non-expired) stock for ingredient: {$data['name']} — needed {$data['required']}, available {$valid_stock}");
                }
            }
            unset($data);


            foreach ($needed_ingredients as $ingredient_id => $data) {
                $needed = $data['required'];

                $stmt = $conn->prepare("
                    SELECT id, quantity 
                    FROM supply_batches 
                    WHERE supply_id = ? 
                    AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                    ORDER BY received_date ASC
                ");
                $stmt->bind_param("i", $ingredient_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stmt->close();

                while ($batch = $result->fetch_assoc()) {
                    if ($needed <= 0) break;

                    $batch_id = $batch['id'];
                    $batch_qty = (float)$batch['quantity'];
                    $deduct = min($batch_qty, $needed);
                    $needed -= $deduct;

                    $stmtUpdate = $conn->prepare("UPDATE supply_batches SET quantity = quantity - ? WHERE id = ?");
                    $stmtUpdate->bind_param("di", $deduct, $batch_id);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                }

                if ($needed > 0) {
                    throw new Exception("Unexpected shortage while deducting {$data['name']} (short by {$needed}).");
                }

                $stmtSum = $conn->prepare("
                    UPDATE supplies s
                    SET s.current_stock = COALESCE((
                        SELECT SUM(sb.quantity)
                        FROM supply_batches sb
                        WHERE sb.supply_id = s.id 
                        AND (expiration_date IS NULL OR expiration_date >= CURDATE())
                    ), 0)
                    WHERE s.id = ?
                ");
                $stmtSum->bind_param("i", $ingredient_id);
                $stmtSum->execute();
                $stmtSum->close();
            }

            $stmt = $conn->prepare("
                INSERT INTO sales 
                    (user_id, order_date, total_amount, order_type, payment_method, status, discount_type, subtotal_amount, discount_amount)
                VALUES (?, NOW(), ?, ?, ?, 'sale', ?, ?, ?)
            ");
            $stmt->bind_param(
                "idsssdd",
                $user_id,
                $total,
                $selected_order_type,
                $selected_method,
                $selected_discount,
                $subtotal,
                $discount_amount
            );
            $stmt->execute();
            $sale_id = $conn->insert_id;
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO sale_items (sale_id, menu_item_id, quantity, unit_price, subtotal)
                VALUES (?, ?, ?, ?, ?)
            ");

            foreach ($menu_item_ids as $index => $menu_item_id) {
                $menu_item_id = (int)$menu_item_id;
                $qty = (float)($quantities[$index] ?? 0);

                if ($menu_item_id <= 0 || $qty <= 0) continue;

                $stmtPrice = $conn->prepare("SELECT price FROM menu_items WHERE id = ?");
                $stmtPrice->bind_param("i", $menu_item_id);
                $stmtPrice->execute();
                $res2 = $stmtPrice->get_result();
                $stmtPrice->close();

                $row2 = $res2->fetch_assoc();
                $price = (float)$row2['price'];
                $item_subtotal = round($price * $qty, 2);

                $stmt->bind_param("iiidd", $sale_id, $menu_item_id, $qty, $price, $item_subtotal);
                $stmt->execute();
            }
            $stmt->close();

            $stmt = $conn->prepare("
                INSERT INTO audit_log (user_id, action, personnel, created_at)
                VALUES (?, 'sale', ?, NOW())
            ");
            $stmt->bind_param("is", $user_id, $user_name);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            return true;

        } catch (Exception $e) {
            $conn->rollback();
            return false;
        }
    }

    function queryFinishOrder($conn, $user_id, $user_name, $status, $sale_id) 
    {
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        $conn->begin_transaction();

        try {
            if ($status === 'void') {
                // Get items in the sale
                $stmt = $conn->prepare("
                    SELECT menu_item_id, quantity 
                    FROM sale_items 
                    WHERE sale_id = ?
                ");
                $stmt->bind_param("i", $sale_id);
                $stmt->execute();
                $sale_items = $stmt->get_result();
                $stmt->close();

                while ($item = $sale_items->fetch_assoc()) {
                    $menu_item_id = (int)$item['menu_item_id'];
                    $menu_qty     = (float)$item['quantity'];

                    // Get recipe ingredients for each menu item
                    $stmt = $conn->prepare("
                        SELECT ingredient_id, quantity 
                        FROM recipes
                        WHERE menu_item_id = ?
                    ");
                    $stmt->bind_param("i", $menu_item_id);
                    $stmt->execute();
                    $ingredients = $stmt->get_result();
                    $stmt->close();

                    while ($ing = $ingredients->fetch_assoc()) {
                        $ingredient_id = $ing['ingredient_id'];
                        $restore_qty   = $ing['quantity'] * $menu_qty;

                        // Restore ingredient quantities to batches
                        $stmt = $conn->prepare("
                            SELECT id, quantity
                            FROM supply_batches
                            WHERE supply_id = ?
                            ORDER BY expiration_date DESC, id DESC
                        ");
                        $stmt->bind_param("i", $ingredient_id);
                        $stmt->execute();
                        $batches = $stmt->get_result();
                        $stmt->close();

                        foreach ($batches as $batch) {
                            if ($restore_qty <= 0) break;

                            $batch_id  = $batch['id'];
                            $batch_qty = $batch['quantity'];

                            $new_qty = $batch_qty + $restore_qty;

                            $update = $conn->prepare("
                                UPDATE supply_batches 
                                SET quantity = ?
                                WHERE id = ?
                            ");
                            $update->bind_param("di", $new_qty, $batch_id);
                            $update->execute();
                            $update->close();

                            $restore_qty = 0; // Fully restored
                        }
                    }
                }
            }

            // Update sale status
            $stmt = $conn->prepare("
                UPDATE sales
                SET status = ?
                WHERE id = ?
            ");
            $stmt->bind_param("si", $status, $sale_id);
            $stmt->execute();
            $stmt->close();

            // Log action
            $stmt = $conn->prepare("
                INSERT INTO audit_log (user_id, action, personnel, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $user_id, $status, $user_name);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

            // Feedback for JS alert
            if ($status === 'void') {
                echo "<script>showCustomAlert('Order voided. Ingredients restored to inventory.', 'success');</script>";
            }

            return true;

        } catch (Exception $e) {
            $conn->rollback();
            echo "<script>showCustomAlert(" . json_encode('Error: ' . $e->getMessage()) . ", 'error');</script>";
            return false;
        }
    }


    function getQueue($conn, $filter_type = 'day', $chosen = null)
    {
        $dateCondition = "1";

        if ($filter_type === 'day' && $chosen) {
            $dateCondition = "DATE(s.order_date) = '$chosen'";
        }

        if ($filter_type === 'week' && $chosen) {
            list($year, $week) = explode("-W", $chosen);
            $dateCondition = "YEARWEEK(s.order_date, 1) = YEARWEEK('$year-01-01' + INTERVAL ($week-1) WEEK, 1)";
        }

        if ($filter_type === 'month' && $chosen) {
            list($year, $month) = explode("-", $chosen);
            $dateCondition = "YEAR(s.order_date) = '$year' AND MONTH(s.order_date) = '$month'";
        }

        $query = "
            SELECT 
                s.id AS sale_id,
                u.name AS cashier_name,
                s.order_date,
                s.order_type,
                s.payment_method,
                s.status,
                s.subtotal_amount,
                s.discount_amount,
                s.total_amount,
                GROUP_CONCAT(CONCAT(mi.name, ' - ', si.quantity) SEPARATOR '<br>') AS items_ordered
            FROM sales s
            JOIN users u ON s.user_id = u.id
            JOIN sale_items si ON s.id = si.sale_id
            JOIN menu_items mi ON si.menu_item_id = mi.id
            WHERE s.status = 'sale'
            AND $dateCondition
            GROUP BY s.id
            ORDER BY s.order_date DESC
        ";

        return $conn->query($query);
    }
    function getInventory($conn, $selected_type = null, $search_name = null)
    {
        $query = "
            SELECT 
                sb.id AS batch_id,
                s.id,
                s.name,
                s.unit,
                st.type,
                sb.unit_cost,
                sb.bundle,
                sb.unit_per_bundle,
                CONCAT(sb.unit_per_bundle, ' ', s.unit) AS unit_per_bundle_with_unit,
                sb.expiration_date,
                sb.received_date,
                sb.quantity,
                CONCAT(sb.quantity, ' ', s.unit) AS quantity_with_unit,
                pi.subtotal,
                CONCAT(s.current_stock, ' ', s.unit) AS current_stock,
                CONCAT(s.reorder_level, ' ', s.unit) AS reorder_level,
                CASE 
                    WHEN s.current_stock IS NULL THEN 'Out of Stock'
                    WHEN s.current_stock <= s.reorder_level THEN 'Low Stock'
                    ELSE 'OK'
                END AS status
            FROM supply_batches sb
            LEFT JOIN supplies s ON sb.supply_id = s.id
            LEFT JOIN purchase_items pi ON sb.purchase_item_id = pi.id
            LEFT JOIN supply_types st ON s.type_id = st.id
            WHERE 1=1
              AND sb.quantity <> 0
              AND (sb.expiration_date IS NULL OR sb.expiration_date >= DATE(CURDATE()))
        ";

        $params = [];
        $types  = "";

        if (!empty($selected_type)) {
            $query .= " AND s.type_id = ?";
            $params[] = $selected_type;
            $types .= "s";
        }

        if (!empty($search_name)) {
            $query .= " AND s.name LIKE ?";
            $params[] = "%" . $search_name . "%";
            $types .= "s";
        }

        $query .= " ORDER BY s.name ASC";

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }


    function getExpiredBatches($conn)
    {
        $sql = "
            SELECT 
                sb.id AS batch_id,
                s.name AS supply_name,
                sb.quantity,
                sb.bundle,
                sb.unit_per_bundle,
                s.unit,
                sb.expiration_date
            FROM supply_batches sb
            JOIN supplies s ON sb.supply_id = s.id
            WHERE sb.expiration_date IS NOT NULL
            AND sb.expiration_date <= CURDATE()
            AND quantity <> 0
        ";
        return $conn->query($sql);
    }

    function getMenu($conn, $selected_category_id = null, $search_name = null ) 
    {
        $query = "
            SELECT 
                m.id,
                m.name,
                m.description,
                m.price,
                c.category
            FROM menu_items m
            LEFT JOIN categories c ON m.category_id = c.id
            WHERE 1=1
        ";

        $params = [];
        $types  = "";



        if (!empty($selected_category_id)) {
            $query .= " AND m.category_id = ?";
            $params[] = $selected_category_id;
            $types .= "s";
        }

        if (!empty($search_name)) {
            $query .= " AND m.name LIKE ?";
            $params[] = "%" . $search_name . "%";
            $types .= "s";
        }

        $query .= "

            ORDER BY m.name ASC
        ";

        $stmt = $conn->prepare($query);

        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        return $stmt->get_result();
    }

    function typeDropdown($supply_type_result, $selected_supply_type_id) 
    {
        echo '<select class="dropdown_" name="supply_type_id" id="supply_type_id" required>';
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


    public function auditList($result)
    {
        if ($result->num_rows > 0) {
            echo '
            <div class="list-box" style="
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ccc;
                border-radius: 8px;
            ">
            ';

            echo '<table class="table" style="width:100%; border-collapse:collapse;">';
            echo '<thead style="position: sticky; top: 0; background: #f9f9f9;">';
            echo '<tr>';
            echo '<th>Personnel</th>';
            echo '<th>Action Committed</th>';
            echo '<th>Date and Time</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['personnel']) . '</td>';
                echo '<td>' . htmlspecialchars($row['action']) . '</td>';
                echo '<td>' . htmlspecialchars($row['created_at']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';

        } else {
            echo '<p>There is nothing recorded.</p>';
        }
    }

    public function showInventoryList($result) 
    {
        if ($result->num_rows > 0) {
            echo '
            <div class="list-box" style="
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ccc;
                border-radius: 8px;
            ">
            ';

            echo '<table class="table" style="width:100%; border-collapse:collapse;">';
            echo '<thead style="position: sticky; top: 0; background: #f9f9f9;">';
            echo '<tr>';
            echo '<th>Batch ID</th>';
            echo '<th>Item</th>';
            echo '<th>Category</th>';
            echo '<th>Bundle</th>';
            echo '<th>Unit per Bundle</th>';
            echo '<th>Quantity</th>';
            echo '<th>Unit Price</th>';
            echo '<th>Batch Price</th>';
            echo '<th>Expiration Date</th>';
            echo '<th>Date Received</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['batch_id']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['type']) . '</td>';
                echo '<td>' . ((empty($row['bundle']) || $row['bundle'] == 0) ? '--' : htmlspecialchars($row['bundle'])) . '</td>';
                echo '<td>' . ((empty($row['unit_per_bundle_with_unit']) || $row['unit_per_bundle_with_unit'] == 0) ? '--' : htmlspecialchars($row['unit_per_bundle_with_unit'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['quantity_with_unit']) . '</td>';
                echo '<td>' . htmlspecialchars($row['unit_cost']) . '</td>';
                echo '<td>' . htmlspecialchars($row['subtotal']) . '</td>';
                echo '<td>' . htmlspecialchars($row['expiration_date']) . '</td>';
                echo '<td>' . htmlspecialchars($row['received_date']) . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        } else {
            echo '<p>There is nothing recorded.</p>';
        }
    }

    public function discardDefectiveItems($conn, $result, $user_name, $user_id)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discard_partial'])) {
            if (!empty($_POST['discard_quantities'])) {
                $discard_quantities = $_POST['discard_quantities'];
                $batch_ids = array_keys($discard_quantities);

                $conn->begin_transaction();

                try {
                    $insert_stmt = $conn->prepare("
                        INSERT INTO discarded_batches 
                            (supply_id, batch_id, bundle, unit_per_bundle, quantity, unit_cost, expiration_date, discarded_reason, discarded_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'Defective', ?)
                    ");

                    $update_stmt = $conn->prepare("
                        UPDATE supply_batches 
                        SET quantity = quantity - ?
                        WHERE id = ? AND quantity >= ?
                    ");

                    foreach ($batch_ids as $batch_id) {
                        $discard_qty = floatval($discard_quantities[$batch_id]);
                        if ($discard_qty <= 0) continue;

                        $check = $conn->prepare("
                            SELECT supply_id, quantity, bundle, unit_per_bundle, unit_cost, expiration_date 
                            FROM supply_batches 
                            WHERE id = ?
                        ");
                        $check->bind_param("i", $batch_id);
                        $check->execute();
                        $res = $check->get_result()->fetch_assoc();
                        $check->close();

                        if (!$res) continue;

                        $supply_id = $res['supply_id'];
                        $available_qty = $res['quantity'];
                        $bundle = $res['bundle'];
                        $unit_per_bundle = $res['unit_per_bundle'];
                        $unit_cost = $res['unit_cost'];
                        $expiration_date = $res['expiration_date'];

                        if ($discard_qty > $available_qty) {
                            throw new Exception("Discard quantity exceeds available stock for batch ID $batch_id.");
                        }

                        $insert_stmt->bind_param(
                            "iiiddsss",
                            $supply_id,
                            $batch_id,
                            $bundle,
                            $unit_per_bundle,
                            $discard_qty,
                            $unit_cost,
                            $expiration_date,
                            $user_name
                        );
                        $insert_stmt->execute();

                        $update_stmt->bind_param("dii", $discard_qty, $batch_id, $discard_qty);
                        $update_stmt->execute();
                    }

                    $insert_stmt->close();
                    $update_stmt->close();

                    $update_stock_sql = "
                        UPDATE supplies s
                        LEFT JOIN (
                            SELECT supply_id, COALESCE(SUM(quantity), 0) AS total_qty
                            FROM supply_batches
                            WHERE expiration_date IS NULL OR expiration_date >= NOW()
                            GROUP BY supply_id
                        ) sb ON sb.supply_id = s.id
                        SET s.current_stock = sb.total_qty
                    ";

                    $conn->query($update_stock_sql);

                    $stmt = $conn->prepare("
                        INSERT INTO audit_log (user_id, action, personnel, created_at)
                        VALUES (?, 'discard', ?, NOW())
                    ");
                    $stmt->bind_param("is", $user_id, $user_name);
                    $stmt->execute();
                    $stmt->close();
                    $conn->commit();
                    $this->setPendingAlert('Defective items discarded successfully!', 'success');

                } catch (Exception $e) {
                    $conn->rollback();
                    $this->setPendingAlert('Error discarding defective items: ' . $e->getMessage(), 'error');
                }

            } else {
                $this->setPendingAlert('Please enter a discard quantity for at least one batch before submitting.', 'error');
            }
        }

        echo '
        
        <form method="POST" action="" id="discardForm">
        <div class="scroll-table">
        <div class="list-box" style="
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #ccc;
            border-radius: 8px;
        ">
            <table class="table" style="width:100%; border-collapse:collapse;">
            <thead style="position: sticky; top: 0; background: #f9f9f9;">
                    <tr>
                        <th>Batch ID</th>
                        <th>Supply Name</th>
                        <th>Quantity</th>
                        <th>Bundle Info</th>
                        <th>Date Received</th>
                        <th>Discard Quantity</th>
                    </tr>
        ';

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {

                if (!empty($row['bundle']) && !empty($row['unit_per_bundle'])) {
                    $fullBundles = floor($row['quantity'] / $row['unit_per_bundle']);
                    $remainingUnits = fmod($row['quantity'], $row['unit_per_bundle']);
                    if ($remainingUnits > 0 && $fullBundles > 0)
                        $bundleInfo = "{$fullBundles} unit(s) + {$remainingUnits} {$row['unit']}";
                    elseif ($remainingUnits > 0)
                        $bundleInfo = "{$remainingUnits} {$row['unit']}";
                    else
                        $bundleInfo = "{$fullBundles} full unit(s)";
                } else {
                    $bundleInfo = "Non-bundled ({$row['quantity']} {$row['unit']})";
                }

                echo "
                <tr>
                    <td>" . htmlspecialchars($row['batch_id']) . "</td>
                    <td>" . htmlspecialchars($row['name']) . "</td>
                    <td>" . htmlspecialchars($row['quantity']) . " " . htmlspecialchars($row['unit']) . "</td>
                    <td>" . htmlspecialchars($bundleInfo) . "</td>
                    <td>" . htmlspecialchars($row['received_date']) . "</td>
                    <td>
                    <input type='number' name='discard_quantities[{$row['batch_id']}]' 
                    min='0' max='{$row['quantity']}' step='1' placeholder='0'>
                    </td>
                </tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No batches available.</td></tr>";
        }

        echo '
            </table>
            </div>
            </div>
            <br>
            <button type="submit" name="discard_partial">Discard Selected Quantities</button>
        </form>
        ';

        echo '
        <script>
        document.getElementById("discardForm").addEventListener("submit", function(e) {
            const inputs = document.querySelectorAll("input[name^=\'discard_quantities\']");
            let hasValue = false;
            let invalid = false;
            let messages = [];

            inputs.forEach(input => {
                const val = parseFloat(input.value) || 0;
                const max = parseFloat(input.max) || 0;

                if (val > 0) hasValue = true;
                if (val > max) {
                    invalid = true;
                    messages.push("Discard quantity for batch " + input.name.match(/\\d+/)[0] + " exceeds available stock (" + max + ").");
                }
            });

            if (!hasValue) {
                e.preventDefault();
                showCustomAlert("Please enter a discard quantity for at least one batch before submitting.", "error");
                return false;
            }

            if (invalid) {
                e.preventDefault();
                showCustomAlert(messages.join(" | "), "error");
                return false;
            }
        });
        </script>';
    }

    public function showExpiredBatches($conn, $result, $user_name, $user_id)
    {
        // Handle discard submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discard'])) {
            if (!empty($_POST['batch_ids'])) {
                $batch_ids = $_POST['batch_ids'];

                $conn->begin_transaction();
                try {
                    // Prepare statements for inserting into discarded_batches and deleting from supply_batches
                    $selectStmt = $conn->prepare("
                        SELECT 
                            id AS batch_id,
                            supply_id,
                            bundle,
                            unit_per_bundle,
                            quantity,
                            unit_cost,
                            expiration_date
                        FROM supply_batches
                        WHERE id = ?
                    ");

                    $insertStmt = $conn->prepare("
                        INSERT INTO discarded_batches (
                            supply_id, batch_id, bundle, unit_per_bundle, quantity, unit_cost, expiration_date, discarded_reason, discarded_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'Expired', ?)
                    ");

                    $updateStmt = $conn->prepare("
                        UPDATE supply_batches
                        SET quantity = 0,
                            discarded_at = NOW()
                        WHERE id = ?
                    ");

                    foreach ($batch_ids as $id) {
                        // Fetch batch details
                        $selectStmt->bind_param("i", $id);
                        $selectStmt->execute();
                        $batch = $selectStmt->get_result()->fetch_assoc();

                        if ($batch) {
                            // Insert into discarded_batches
                            $insertStmt->bind_param(
                                "iiidddss",
                                $batch['supply_id'],
                                $batch['batch_id'],
                                $batch['bundle'],
                                $batch['unit_per_bundle'],
                                $batch['quantity'],
                                $batch['unit_cost'],
                                $batch['expiration_date'],
                                $user_name
                            );
                            $insertStmt->execute();

                            // Mark batch as discarded by zeroing quantity
                            $updateStmt->bind_param("i", $id);
                            $updateStmt->execute();
                        }
                    }

                    // Close prepared statements
                    $selectStmt->close();
                    $insertStmt->close();
                    // $deleteStmt->close();

                    // Update total stock for all supplies based on expiration and discarded batches
                    $update_stock_sql = "
                        UPDATE supplies s
                        LEFT JOIN (
                            SELECT supply_id, COALESCE(SUM(quantity), 0) AS total_qty
                            FROM supply_batches
                            WHERE (expiration_date IS NULL OR expiration_date >= NOW())
                            AND quantity > 0
                            GROUP BY supply_id
                        ) sb ON sb.supply_id = s.id
                        SET s.current_stock = COALESCE(sb.total_qty, 0)
                    ";
                    $conn->query($update_stock_sql);

                    // Audit log
                    $stmt = $conn->prepare("
                        INSERT INTO audit_log (user_id, action, personnel, created_at)
                        VALUES (?, 'discard', ?, NOW())
                    ");
                    $stmt->bind_param("is", $user_id, $user_name);
                    $stmt->execute();
                    $stmt->close();

                    $conn->commit();
                    echo "<script>window.location.href='checkinventory.php'</script>";

                } catch (Exception $e) {
                    $conn->rollback();
                    echo "<script>alert('Error discarding batches: " . addslashes($e->getMessage()) . "');</script>";
                }
            } else {
                echo "<script>alert('Please select at least one batch to discard.');</script>";
            }
        }

        // Display table
        echo '
        <h2>Expired Supply Batches</h2>
        <form method="POST" action="">
            <table border="1" cellpadding="8">
                <tr>
                    <th>Select</th>
                    <th>Supply Name</th>
                    <th>Batch Quantity</th>
                    <th>Bundle Info</th>
                    <th>Expiration Date</th>
                </tr>';

        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {

                // --- Compute the bundle info dynamically ---
                if (!empty($row['bundle']) && !empty($row['unit_per_bundle'])) {
                    $fullBundles = floor($row['quantity'] / $row['unit_per_bundle']);
                    $remainingUnits = fmod($row['quantity'], $row['unit_per_bundle']);

                    if ($remainingUnits > 0 && $fullBundles > 0) {
                        $bundleInfo = "{$fullBundles} bundle(s) + {$remainingUnits} {$row['unit']}";
                    } elseif ($remainingUnits > 0) {
                        $bundleInfo = "{$remainingUnits} {$row['unit']}";
                    } else {
                        $bundleInfo = "{$fullBundles} full unit(s)";
                    }
                } else {
                    $bundleInfo = "Non-bundled ({$row['quantity']} {$row['unit']})";
                }

                // --- Output the row ---
                echo "<tr>
                        <td><input type='checkbox' name='batch_ids[]' value='{$row['batch_id']}'></td>
                        <td>" . htmlspecialchars($row['supply_name']) . "</td>
                        <td>" . htmlspecialchars($row['quantity']) . "</td>
                        <td>" . htmlspecialchars($bundleInfo) . "</td>
                        <td>{$row['expiration_date']}</td>
                    </tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No expired batches found.</td></tr>";
        }


        echo '
            </table>
            <br>
            <button type="submit" name="discard">Discard Selected Batches</button>
        </form>';
    }

    public function showMenuList($result) 
    {
        if ($result->num_rows > 0) {
            echo '
            <div class="list-box" style="
                max-height: 400px;
                overflow-y: auto;
                border: 1px solid #ccc;
                border-radius: 8px;
            ">
            ';

            echo '<table class="table" style="width:100%; border-collapse:collapse;">';
            echo '<thead style="position: sticky; top: 0; background: #f9f9f9;">';
            echo '<tr>';
            echo '<th>Name</th>';
            echo '<th>Category</th>';
            echo '<th>Desription</th>';
            echo '<th>Price</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';
            while ($row = $result->fetch_assoc()) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['category']) . '</td>';
                echo '<td>' . htmlspecialchars($row['description']) . '</td>';
                echo '<td>' . htmlspecialchars($row['price']) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p>There is nothing recorded.</p>';
        }
    }

public function showOrderQueue($result)
{
    if ($result->num_rows > 0) {
        echo '<div class="list-box" style="max-height:400px; overflow-y:auto; border:1px solid #ccc; border-radius:8px;">';
        echo '<table class="table" style="width:100%; border-collapse:collapse;">';
        echo '<thead><tr>
                <th>Order Number</th>
                <th>Items Ordered</th>
                <th>Order Type</th>
                <th>Payment Method</th>
                <th>Date</th>
                <th>Subtotal Amount</th>
                <th>Discount Amount</th>
                <th>Total Amount</th>
                <th>Action</th>
              </tr></thead><tbody>';

        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo "<td>{$row['sale_id']}</td>";
            echo "<td>{$row['items_ordered']}</td>";
            echo "<td>{$row['order_type']}</td>";
            echo "<td>{$row['payment_method']}</td>";
            echo "<td>{$row['order_date']}</td>";
            echo "<td>{$row['subtotal_amount']}</td>";
            echo "<td>{$row['discount_amount']}</td>";
            echo "<td>{$row['total_amount']}</td>";

            // Void button form
            echo '<td>';
            echo '<form method="POST" action="" class="update-form">';
            echo "<input type='hidden' name='sale_id' value='{$row['sale_id']}'>";
            echo "<input type='hidden' name='status' value='void'>"; // Always void
            echo "<input type='hidden' name='update_status' value='1'>"; 
            echo '<button type="button" class="confirm-btn">Void</button>';
            echo '</form>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table></div>';
    } else {
        echo '<p>There is nothing recorded.</p>';
    }
}


    // Get sale info by sale ID
function getSale($conn, $sale_id) {
    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Get sale items
function getSaleItems($conn, $sale_id) {
    $stmt = $conn->prepare("
        SELECT si.*, mi.name, mi.price 
        FROM sale_items si 
        JOIN menu_items mi ON si.menu_item_id = mi.id
        WHERE si.sale_id = ?
    ");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}


    public function setPendingAlert($msg, $type = 'success', $redirect = null, $delay = 1200) {
        $payload = ['msg' => $msg, 'type' => $type];
        if ($redirect) { $payload['redirect'] = $redirect; $payload['delay'] = $delay; }
        echo "<script>localStorage.setItem('pendingAlert', " . json_encode($payload) . ");</script>";
    }

}
