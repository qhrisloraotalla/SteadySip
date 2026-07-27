<?php
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

if (!isset($_POST['menu_item_id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing menu_item_id']);
    exit;
}

$menu_item_id = intval($_POST['menu_item_id']);

$stmt = $conn->prepare("UPDATE menu_items SET is_active = 0 WHERE id = ?");
$stmt->bind_param("i", $menu_item_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
