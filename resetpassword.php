<?php
// --- BACKEND SECTION ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax'])) {
    session_start();
    include "Procedures.php";
    $proc = new Procedures();
    $conn = $proc->getConnection();

    header('Content-Type: application/json');
    error_reporting(E_ALL);
    ini_set('display_errors', 1);

    $username = $_POST["username"] ?? '';
    $new_password = $_POST["password"] ?? '';

    if (!$username || !$new_password) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing fields']);
        exit;
    }

    // Fetch existing password first
    $check = $conn->prepare("SELECT password FROM users WHERE username = ? LIMIT 1");
    $check->bind_param("s", $username);
    $check->execute();
    $res = $check->get_result();

    if ($res->num_rows === 0) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }

    $row = $res->fetch_assoc();
    $old_password = $row['password'];

    // Prevent using the same password
    if ($old_password === $new_password) {
        echo json_encode(['error' => 'New password must NOT be the same as the old password.']);
        exit;
    }

    // Update the password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
    $stmt->bind_param("ss", $new_password, $username);

    if ($stmt->execute()) {
        // Delete OTP
        $del = $conn->prepare("
            DELETE FROM user_otps 
            WHERE user_id = (SELECT id FROM users WHERE username = ? LIMIT 1)
        ");
        $del->bind_param("s", $username);
        $del->execute();

        echo json_encode(['success' => true, 'message' => 'Password reset successful. You can now log in.']);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to reset password']);
    }
    exit;
}
?>

<!-- --- FRONTEND SECTION --- -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password</title>
  <link rel="stylesheet" href="resetpassword.css">
</head>
<body>
  <div class="box">
    <p class="text">Reset Password</p>
    <form id="resetForm">
      <input type="hidden" id="username" name="username" />
      <input type="password" id="password" name="password" placeholder="Enter new password" required />
      <button type="submit">Reset Password</button>
    </form>
  </div>

  <script>
    // Get username from URL (sent from verify OTP)
    const params = new URLSearchParams(window.location.search);
    document.getElementById('username').value = params.get('user') || '';

    document.getElementById('resetForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('ajax', '1');

      const res = await fetch('resetpassword.php', { method: 'POST', body: formData });
      const data = await res.json();

      if (data.success) {
        alert(data.message);
        window.location.href = 'login.php'; // redirect after success
      } else {
        alert(data.error || 'Something went wrong.');
      }
    });
  </script>
</body>
</html>
