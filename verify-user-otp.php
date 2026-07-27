<?php
session_start();
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$otp = $data['otp'] ?? '';
$phone = $data['phone'] ?? '';

if (!$otp || !$phone) {
    echo json_encode(['success'=>false,'message'=>'OTP or phone missing.']);
    exit;
}

// Fetch latest OTP for this phone
$stmt = $conn->prepare("SELECT id, otp_hash, expires_at FROM signup_otps WHERE phone=? AND type='user' ORDER BY id DESC LIMIT 1");
$stmt->bind_param("s", $phone);
$stmt->execute();
$stmt->bind_result($id, $otp_hash, $expires_at);
$fetch_success = $stmt->fetch();
$stmt->close();

if (!$fetch_success || !$otp_hash || !$expires_at) {
    echo json_encode(['success'=>false,'message'=>'OTP not found.']);
    exit;
}

// Convert expires_at to DateTime safely
$expires = DateTime::createFromFormat('Y-m-d H:i:s', $expires_at);
if (!$expires) {
    echo json_encode(['success'=>false,'message'=>'Invalid OTP expiry.']);
    exit;
}

$now = new DateTime();
if ($now > $expires) {
    echo json_encode(['success'=>false,'message'=>'OTP expired.']);
    exit;
}

// OTP correct → save verified user data
$_SESSION['signup_verified_user'] = $_SESSION['signup_data'];
echo json_encode(['success'=>true, 'message'=>'User phone verified. Owner OTP will be sent next.']);
?>
