<?php
session_start(); 
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];

    // Validate phone format (+639xxxxxxxxx or 09xxxxxxxxx)
    if (!preg_match('/^(?:\+639|09)\d{9}$/', $phone)) {
        die("Invalid phone number format. Use +639XXXXXXXXX or 09XXXXXXXXX");
    }

    // Prepare SQL statement
    $stmt = $conn->prepare("INSERT INTO users (username, password, role, name, phone) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $username, $password, $role, $name, $phone);

    // Execute and check success
    if ($stmt->execute()) {
        echo "✅ Registration successful! You can now log in.";
    } else {
        if ($conn->errno == 1062) {
            echo "⚠️ Username or phone number already exists.";
        } else {
            echo "❌ Error: " . $conn->error;
        }
    }

    $stmt->close();
}

$conn->close();
?>
