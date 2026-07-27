<?php
session_start();
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

$name = $_SESSION['name'] ?? 'User';

$stmt = $conn->prepare(
    "INSERT INTO audit_log (user_id, action, personnel, created_at) 
        VALUES (?, 'logout', ?, NOW())
    ");
$stmt->bind_param("is", $_SESSION['id'], $_SESSION['name']);
$stmt->execute();

$stmt->close();
$conn->close();

session_unset();
session_destroy();

header("refresh:3;url=loginpage.php");
?>