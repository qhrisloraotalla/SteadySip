<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="favicon.png">
    <title>Login</title>
    <!-- <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;700;900&display=swap" rel="stylesheet"> -->
    <link rel="stylesheet" href="login.css">
</head>
<body>
    <div class="container">
        
        <div class="form-container">
            
        <form action="login.php" method="post">
            <img src="emg/4031logo.png" alt="logo" class="logo">
            <h2> <span> Welcome Back! </span> Please login to your account.</h2>

            <?php if (isset($_GET['error'])) { ?>
                <p class="error"><?php echo $_GET['error']; ?></p>
            <?php } ?>

            <input type="text" name="username" placeholder="Username" required>

            <div class="password-container">
                <input type="password" id="password" name="password" placeholder="Password" required>
                <img src="emg/eye-icon.png" alt="Toggle Password Visibility" class="toggle-password" onclick="togglePasswordVisibility()">
            </div>

            <div class="forgotpass_container">
                <a href="forgotpass.php">Forgot Password?</a>
            </div>

            <div class="login-container">
                <button type="submit">SIGN IN</button>
            </div>

            <!-- ✨ Move Sign-Up here -->
            <div class="signin-container">
                <a href="signup.html">
                    <button type="button">SIGN UP</button>
                </a>
            </div>

            <p class="power">POWERED BY</p>
            <p class="system">SteadySip Integrated POS-Inventory System</p>
        </form>
    </div>    


    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.src = 'emg/eye-slash-icon.png';
            } else {
                passwordInput.type = 'password';
                toggleIcon.src = 'emg/eye-icon.png';
            }
        }
    </script>
</body>
</html>
