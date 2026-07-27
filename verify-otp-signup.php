<?php
session_start();
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

header('Content-Type: application/json');

$input = json_decode(file_get_contents("php://input"), true);
$otp = $input['otp'] ?? '';

$stmt = $conn->prepare("SELECT otp_hash, expires_at FROM signup_otps ORDER BY id DESC LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row || new DateTime() > new DateTime($row['expires_at'])) {
  echo json_encode(["success" => false, "message" => "OTP expired."]);
  exit;
}

if (!password_verify($otp, $row['otp_hash'])) {
  echo json_encode(["success" => false, "message" => "Invalid OTP."]);
  exit;
}


$data = $_SESSION['signup_data'];
$username = $data['username'];
$password = $data['password']; 
$name = $data['name'];
$phone = $data['phone'];
$role = $data['role'];

$stmt = $conn->prepare("INSERT INTO users (username, password, role, name, phone) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sssss", $username, $password, $role, $name, $phone);

if ($stmt->execute()) {
  echo json_encode(["success" => true, "message" => "✅ Registration complete!"]);
} else {
  echo json_encode(["success" => false, "message" => "❌ " . $conn->error]);
}

unset($_SESSION['signup_otp']);
unset($_SESSION['signup_data']);
?>
