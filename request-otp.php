<?php
session_start(); 
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
if (!$username) {
  http_response_code(400);
  echo json_encode(['error' => 'Username is required']);
  exit;
}

// Find user
$stmt = $conn->prepare("SELECT id, phone FROM users WHERE username = ? LIMIT 1");
if (!$stmt) {
  echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
  exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
  http_response_code(404);
  echo json_encode(['error' => 'User not found']);
  exit;
}

// Generate OTP (6-digit) + expiry
$otp = random_int(100000, 999999);
$expires_at = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

// Save OTP record
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);
$insert = $conn->prepare("INSERT INTO user_otps (user_id, phone, otp_hash, expires_at) VALUES (?, ?, ?, ?)");
$insert->bind_param("isss", $user['id'], $user['phone'], $otp_hash, $expires_at);
$insert->execute();

// Send via IPROG SMS
$api_token = '1406cf33178c2fd93985e85ad826f9c1d5a9e60b';
$iprog_url = 'https://sms.iprogtech.com/api/v1/sms_messages';
$message = "Your password reset code is $otp. It expires in 5 minutes.";

$post_fields = http_build_query([
  'api_token' => $api_token,
  'message' => $message,
  'phone_number' => $user['phone']
]);

$ch = curl_init($iprog_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Check response
if ($http_code >= 200 && $http_code < 300) {
  echo json_encode(['success' => true, 'message' => 'OTP sent to your registered phone.']);
} else {
  http_response_code(500);
  echo json_encode(['error' => 'Failed to send SMS', 'response' => $response]);
}
?>
