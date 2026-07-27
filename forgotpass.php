<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password</title>
  <link rel="stylesheet" href="forgotpass.css">
</head>
<body>
  
</body>
</html>
<!-- Step 1: Request OTP -->
<form id="reqOtp">
  <a href="loginpage.php" id="backLink">&lt; back to login</a>
  <p class="text">Please enter your username.</p>
  <input name="username" id="username" placeholder="Enter your username" required />
  <button type="submit">Send OTP</button>
</form>

<!-- Step 2: Verify OTP -->
<form id="verifyOtp" style="display:none;">
  <p class="text">Enter Verify Code Below.</p>
  <div class="otp-boxes">
    <input type="text" maxlength="1" class="otp-input">
    <input type="text" maxlength="1" class="otp-input">
    <input type="text" maxlength="1" class="otp-input">
    <input type="text" maxlength="1" class="otp-input">
    <input type="text" maxlength="1" class="otp-input">
    <input type="text" maxlength="1" class="otp-input">
  </div>

  <input type="hidden" id="otp" /> 
  <input type="hidden" id="vusername" />
  <button type="submit">Verify OTP</button>
  <button type="button" id="resendOtp">Resend OTP</button>
</form>

<script>
document.addEventListener('DOMContentLoaded', () => {

  // Request OTP
  document.getElementById('reqOtp').addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('username').value.trim();
    if (!username) {
      alert("Please enter your username.");
      return;
    }

    try {
      const res = await fetch('request-otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({username})
      });

      const text = await res.text();
      console.log("Raw response:", text);

      let j;
      try {
        j = JSON.parse(text);
      } catch {
        alert("Server error:\n" + text);
        return;
      }

      if (j.success) {
        alert('OTP sent to your registered phone number.');
        document.getElementById('reqOtp').style.display = 'none';
        document.getElementById('vusername').value = username;
        document.getElementById('verifyOtp').style.display = 'block';
      } else {
        alert(j.error || 'Something went wrong.');
      }
    } catch (err) {
      alert("Network error: " + err.message);
    }
  });

  // OTP input auto-jump
  const otpInputs = document.querySelectorAll('.otp-input');
  const hiddenOtp = document.getElementById('otp');

  otpInputs.forEach((input, index) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/[^0-9]/g, '');
      if (input.value && index < otpInputs.length - 1) {
        otpInputs[index + 1].focus();
      }
      hiddenOtp.value = Array.from(otpInputs).map(i => i.value).join('');
    });

    input.addEventListener('keydown', e => {
      if (e.key === "Backspace" && !input.value && index > 0) {
        otpInputs[index - 1].focus();
      }
    });
  });

  // Verify OTP
  document.getElementById('verifyOtp').addEventListener('submit', async e => {
    e.preventDefault();
    const username = document.getElementById('vusername').value.trim();
    const otp = hiddenOtp.value.trim();

    if (!otp || otp.length !== otpInputs.length) {
      alert("Please enter the complete OTP.");
      return;
    }

    try {
      const res = await fetch('verify-otp.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({username, otp})
      });

      const j = await res.json();
      if (j.success) {
        alert('OTP verified! Redirecting to reset password...');
        location.href = 'resetpassword.php?user=' + encodeURIComponent(username);
      } else {
        alert(j.error || 'Invalid OTP.');
      }
    } catch (err) {
      alert("Server error: " + err.message);
    }
  });

});

// Resend OTP
document.getElementById('resendOtp').addEventListener('click', async () => {
  const username = document.getElementById('vusername').value.trim();
  if (!username) return alert("No username found.");

  try {
    const res = await fetch('request-otp.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({ username })
    });
    const text = await res.text();
    let j;
    try { j = JSON.parse(text); } 
    catch { alert("Server error:\n" + text); return; }

    if (j.success) {
      alert('OTP resent to your registered phone number.');
      // Clear previous OTP inputs
      document.querySelectorAll('.otp-input').forEach(i => i.value = '');
      document.getElementById('otp').value = '';
      document.querySelector('.otp-input').focus();
    } else {
      alert(j.error || 'Failed to resend OTP.');
    }
  } catch (err) {
    alert("Network error: " + err.message);
  }
});

document.getElementById("backToLogin").addEventListener("click", () => {
  window.location.href = "loginpage.php";
});
</script>

