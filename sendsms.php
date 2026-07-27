<?php
function sendSMS($number, $message) {

    $api_token = "1406cf33178c2fd93985e85ad826f9c1d5a9e60b";
    $iprog_url = "https://sms.iprogtech.com/api/v1/sms_messages";

    // Format request exactly like OTP
    $post_fields = http_build_query([
        'api_token'    => $api_token,
        'message'      => $message,
        'phone_number' => $number
    ]);

    $ch = curl_init($iprog_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    // Localhost fix (optional)
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ✅ Return true only if accepted by server
    return ($http_code >= 200 && $http_code < 300);
}
?>