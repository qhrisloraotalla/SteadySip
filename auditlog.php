<?php
include "sidebar.php";

    $personnel_sql = "SELECT DISTINCT personnel FROM audit_log";  
    $date_sql = "SELECT DISTINCT DATE(created_at) AS created_date FROM audit_log ORDER BY created_date ASC";
    
    $name_result = $conn->query($personnel_sql);
    $date_result = $conn->query($date_sql);

    // Fetch filter values
    $selected_name   = $_POST['personnel'] ?? null;
    $selected_action = $_POST['action'] ?? null;
    $selected_date   = $_POST['created_date'] ?? null;

    if (isset($_POST['reset'])) {
        // Clear filters
        $selected_name = $selected_action = $selected_date = null;
    } else {
        // Otherwise keep whatever user submitted
        $selected_name   = $_POST['personnel'] ?? null;
        $selected_action = $_POST['action'] ?? null;
        $selected_date   = $_POST['created_date'] ?? null;
    }

    // Start the base query
    $query = "SELECT * FROM audit_log WHERE 1=1";
    $params = [];
    $types  = "";

    // Add filters dynamically
    if (!empty($selected_name)) {
        $query .= " AND personnel = ?";
        $params[] = $selected_name;
        $types .= "s";
    }

    if (!empty($selected_action)) {
        if ($selected_action === 'login_logout') {
            $query .= " AND (action = 'login' OR action = 'logout')";
        } else {
            $query .= " AND action = ?";
            $params[] = $selected_action;
            $types .= "s";
        }
    }

    if (!empty($selected_date)) {
        $query .= " AND DATE(created_at) = ?";
        $params[] = $selected_date;
        $types .= "s";
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $conn->prepare($query);

    // Bind parameters only if needed
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();   


    function actionDropdown($selected_action) {
        echo '<select name="action" id="action">';
        echo '<option value="" disabled selected hidden>Action</option>';

        $actions = [
            'login_logout' => 'Login/Logout',
            'sale' => 'Sale',
            'pending' => 'Pending',
            'purchase' => 'Purchase',
            'refund' => 'Refund',
            'void' => 'Void'
        ];

        foreach ($actions as $value => $label) {
            $selected = ($selected_action === $value) ? 'selected' : '';
            echo "<option value=\"$value\" $selected>$label</option>";
        }

        echo '</select>';
    }

    function nameDropdown($name_result, $selected_name) {
        echo '<select class="dropdown_" name="personnel" id="personnel">';
        echo '<option value="" disabled selected hidden>Personnel</option>';

        if ($name_result->num_rows > 0) {
            while ($row = $name_result->fetch_assoc()) {
                $person = $row['personnel'];
                $selected = ($person == $selected_name) ? 'selected' : '';
                echo "<option value='$person' $selected>$person</option>";
            }
        } else {
            echo "<option>No Personnel Found</option>";
        }
        echo '</select>';
    }

    function dateDropdown($date_result, $selected_date) {
        echo '<select class="dropdown_" name="created_date" id="created_date">';
        echo '<option value="" disabled selected hidden>Date</option>';

        if ($date_result->num_rows > 0) {
            while ($row = $date_result->fetch_assoc()) {
                $dateOnly = $row['created_date'];
                $selected = ($dateOnly == $selected_date) ? 'selected' : '';
                echo "<option value='$dateOnly' $selected>$dateOnly</option>";
            }
        } else {
            echo '<option disabled>No Dates Found</option>';
        }
        echo '</select>';
    }


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log</title>
    <link rel="icon" href="emg/4031logo.png" type="image/png">
    <link rel="stylesheet" href="sidebar.css" />
    <link rel="stylesheet" href="audit.css">
</head>
<body>
        <h1 style="text-align: center; margin-top: 10px; color: #333; font-family: Arial, sans-serif; font-weight: bold;">
        Audit Log
    </h1>
<div class="form-div">
    <form method="POST" action="">
        <?php
        echo 'Name: ';
        nameDropdown($name_result, $selected_name);
        actionDropdown($selected_action);
        dateDropdown($date_result, $selected_date);
        ?>
        <button type="submit">Filter Results</button>
        <button type="submit" name="reset">Reset Filters</button>

    </form>
</div>
<?php
echo '<br>';
$proc->auditList($result);
?>

</body>
</html>
