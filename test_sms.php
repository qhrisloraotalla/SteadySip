<?php
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();
$proc->inventoryAlerts($conn);
echo "Alerts Processed!";

?>