<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification</title>
</head>
<body>

    <form action="" method="post">
        <h2>Account Verification</h2>
    <p>Enter Verify Code Below</p>

    <div class="otp-inputs">
      <input type="text" maxlength="1" oninput="moveNext(this, 'otp2')" id="otp1">
      <input type="text" maxlength="1" oninput="moveNext(this, 'otp3')" id="otp2">
      <input type="text" maxlength="1" oninput="moveNext(this, 'otp4')" id="otp3">
      <input type="text" maxlength="1" oninput="moveNext(this, 'otp5')" id="otp4">
      <input type="text" maxlength="1" oninput="moveNext(this, 'otp6')" id="otp5">
      <input type="text" maxlength="1" id="otp6">
    </div>

    <button>Verify Code</button>
    <br>
    <button>Resend Code</button>
    </form>
</body>
</html>
