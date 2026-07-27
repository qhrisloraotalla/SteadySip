<?php
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

$menu_id = $_GET['menu_id'];

$query = $conn->prepare("
    SELECT 
        r.id AS recipe_id,
        r.quantity,
        s.id AS ingredient_id,
        s.name AS ingredient_name,
        s.unit
    FROM recipes r
    JOIN supplies s ON s.id = r.ingredient_id
    WHERE r.menu_item_id = ?
");
$query->bind_param("i", $menu_id);
$query->execute();
$result = $query->get_result();

$ingredients = [];
while ($row = $result->fetch_assoc()) {
    $ingredients[] = $row;
}

echo json_encode($ingredients);
