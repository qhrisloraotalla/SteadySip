<?php
session_start();
include "Procedures.php";
$proc = new Procedures();
$conn = $proc->getConnection();
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$phone = $_POST['phone'] ?? '';
$role = $_POST['role'] ?? '';

if (!$username || !$phone || !$role) {
  echo json_encode(['success'=>false,'message'=>'Incomplete details.']);
  exit;
}

// Generate OTP for user
$otp = random_int(100000, 999999);
$expires_at = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');
$otp_hash = password_hash($otp, PASSWORD_DEFAULT);

// Save OTP to DB
$stmt = $conn->prepare("INSERT INTO signup_otps (otp_hash, expires_at, phone, type) VALUES (?, ?, ?, 'user')");
$stmt->bind_param("sss",$otp_hash,$expires_at,$phone);
$stmt->execute();

// Save temp user data
$_SESSION['signup_data'] = $_POST;

// Send SMS to user (replace with your SMS API)
$message = "Your verification OTP: $otp (5 mins)";
$api_token = 'YOUR_API_TOKEN';
$iprog_url = 'https://sms.iprogtech.com/api/v1/sms_messages';

$post_fields = http_build_query([
    'api_token'=>$api_token,
    'message'=>$message,
    'phone_number'=>$phone
]);

$ch = curl_init($iprog_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_exec($ch);
curl_close($ch);

echo json_encode(['success'=>true]);

?>
