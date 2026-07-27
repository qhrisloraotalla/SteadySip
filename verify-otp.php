<?php
session_start();
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = $_POST['username'] ?? '';
$otp_input = $_POST['otp'] ?? '';

if (!$username || !$otp_input) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing fields']);
    exit;
}

// Get user info
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

// Get latest unused OTP
$otpStmt = $conn->prepare("
    SELECT * FROM user_otps 
    WHERE user_id = ? AND used = 0 
    ORDER BY created_at DESC 
    LIMIT 1
");
$otpStmt->bind_param("i", $user['id']);
$otpStmt->execute();
$otpResult = $otpStmt->get_result();
$otpRecord = $otpResult->fetch_assoc();

if (!$otpRecord) {
    http_response_code(400);
    echo json_encode(['error' => 'No OTP found.']);
    exit;
}

// Check expiry
if (new DateTime() > new DateTime($otpRecord['expires_at'])) {
    http_response_code(400);
    echo json_encode(['error' => 'OTP expired.']);
    exit;
}

// ✅ Compare OTP (since it's hashed in DB)
if (password_verify($otp_input, $otpRecord['otp_hash'])) {
    // Mark as used
    $update = $conn->prepare("UPDATE user_otps SET used = 1 WHERE id = ?");
    $update->bind_param("i", $otpRecord['id']);
    $update->execute();

    echo json_encode(['success' => true, 'message' => 'OTP verified']);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid OTP']);
}
?>
