<?php
session_start();
include '../db_connection.php';

$message = "";
$new_password = "";
$confirm_password = "";
$showSuccessPopup = false;
$successUserName = "";

function validatePassword($password) {
    $errors = [];

    // handle empty password
    if (empty($password)) {
        $errors[] = "Password cannot be empty";
        return $errors;
    }

    if(strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if(!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least 1 uppercase letter";
    }
    if(!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least 1 lowercase letter";
    }
    if(!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least 1 number";
    }
    if(!preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\\\/;]/', $password)) {
        $errors[] = "Password must contain at least 1 special symbol";
    }
    if(preg_match('/\s/', $password)) {
        $errors[] = "Password cannot contain spaces";
    }

    return $errors;
}

// ensure user came from OTP verification
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_pass.php");
    exit();
}

if (isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in both password fields.";
    }
    elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    }
    else {
        $validation_errors = validatePassword($new_password);

        if (!empty($validation_errors)) {
            $message = implode("<br>", $validation_errors);
        }
        else {
            $user_id = $_SESSION['reset_user_id'];
            
            $stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            $user_name = $user['name'];
            mysqli_stmt_close($stmt);

            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_password, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $successUserName = $user_name;
                $showSuccessPopup = true;
                
                // clear session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
            }
            else {
                $message = "Database error: Could not update password.";
            }
            
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password · Employee</title>
    <script src="https://kit.fontawesome.com/c2f7d169d6.js" crossorigin="anonymous"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f0f4fc;
        }

        /* ----- background ----- */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('../login_background.jpeg') center / cover no-repeat fixed;
            filter: blur(8px);
            z-index: -2;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            z-index: -1;
        }

        /* ----- header ----- */
        header {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(4px);
            padding: 12px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
            border-bottom: 1px solid rgba(255, 255, 255, 0.4);
            position: relative;
            z-index: 5;
        }

        .logo img {
            transform: translateX(20px);
            height: 60px;
            width: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .home a {
            text-decoration: none;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            transition: 0.2s;
            padding: 6px 14px;
            border-radius: 40px;
            background: rgba(255,255,255,0.3);
        }

        .home a:hover {
            color: #4f6ef7;
            background: rgba(79, 110, 247, 0.08);
        }

        .home i {
            font-size: 22px;
        }

        .home h2 {
            font-size: 18px;
            font-weight: 500;
            margin: 0;
        }

        /* ----- main container ----- */
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            position: relative;
            z-index: 3;
        }

        .login-wrapper {
            display: flex;
            flex-wrap: wrap;
            max-width: 1100px;
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(6px);
            border-radius: 36px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.30);
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* ----- form column ----- */
        .login-col {
            flex: 1 1 50%;
            padding: 48px 40px 40px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(2px);
        }

        /* Welcome section styling */
        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-section h2 {
            font-size: 32px;
            font-weight: 700;
            color: #0b1e3a;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .welcome-subtitle {
            color: #64748b;
            font-size: 15px;
            font-weight: 400;
            margin-top: 0;
        }

        /* Form group for better spacing */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #4f6ef7;
        }

        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 15px;
            transition: 0.25s;
            background: white;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-group input[type="password"]:focus,
        .form-group input[type="text"]:focus {
            outline: none;
            border-color: #4f6ef7;
            box-shadow: 0 0 0 4px rgba(79, 110, 247, 0.12);
        }

        .pass-field {
            position: relative;
            width: 100%;
        }

        .pass-field input {
            padding-right: 50px;
            margin-bottom: 0;
        }

        .toggle-password-wrapper {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            cursor: pointer;
            flex-shrink: 0;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        .toggle-password-wrapper i {
            font-size: 20px;
            color: #94a3b8;
            cursor: pointer;
            transition: color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 20px;
            height: 20px;
            line-height: 20px;
            pointer-events: none;
            margin: 0;
            padding: 0;
        }

        .toggle-password-wrapper:hover i {
            color: #4f6ef7;
        }

        .btn-reset {
            width: 100%;
            padding: 16px;
            background: #4f6ef7;
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 8px;
            box-shadow: 0 8px 18px -6px rgba(79, 110, 247, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-reset:hover:not(:disabled) {
            background: #3b56d9;
            transform: scale(1.01) translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(79, 110, 247, 0.5);
        }

        .btn-reset:disabled {
            background: #6c757d;
            cursor: not-allowed;
            opacity: 0.7;
            box-shadow: none;
            transform: none;
        }

        .btn-reset.active {
            background: #4f6ef7;
        }

        .bar {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1.5px solid rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: center;
        }

        .bar a {
            color: #4f6ef7;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .bar a:hover {
            color: #2d3f9e;
            text-decoration: underline;
        }

        .error-message {
            background: #fee9e7;
            color: #b91c1c;
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 24px;
            border-left: 5px solid #dc2626;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .error-message::before {
            content: "\f06a";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 18px;
        }

        .error-message-small {
            font-size: 13px;
            margin-top: 4px;
            margin-bottom: 12px;
            display: none;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 500;
        }

        .error-message-small.show {
            display: block;
        }

        /* ----- password requirements ----- */
        .password-requirements {
            background: #f8f9fa;
            border: 1.5px solid #e9ecef;
            border-radius: 16px;
            padding: 16px 20px;
            margin: 4px 0 8px 0;
        }

        .password-requirements h4 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #1e293b;
        }

        .password-requirements ul {
            list-style: none;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 16px;
        }

        .password-requirements li {
            font-size: 13px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 3px 0;
        }

        .password-requirements li i {
            font-size: 10px;
            color: #999;
            width: 14px;
            text-align: center;
        }

        .password-requirements li.valid i {
            color: #28a745;
        }

        .password-requirements li.valid span {
            color: #28a745;
        }

        /* ----- image column ----- */
        .image-col {
            flex: 1 1 50%;
            background: #d9e2ef;
            position: relative;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-col img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .image-col .img-placeholder {
            width: 100%;
            height: 100%;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(145deg, #4f6ef7, #7c8cf5);
            color: white;
            font-size: 22px;
            font-weight: 300;
            gap: 14px;
        }

        .image-col .overlay-text {
            position: absolute;
            bottom: 30px;
            left: 30px;
            color: white;
            text-shadow: 0 4px 20px rgba(0,0,0,0.4);
            background: rgba(0,0,0,0.15);
            backdrop-filter: blur(4px);
            padding: 14px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 18px;
            border: 1px solid rgba(255,255,255,0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ----- popup ----- */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            backdrop-filter: blur(4px);
        }

        .popup-overlay.hidden {
            display: none;
        }

        .popup-box {
            background: white;
            width: 460px;
            max-width: 92%;
            padding: 40px 35px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: popIn 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        @keyframes popIn {
            0% { transform: scale(0.7); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .popup-icon {
            font-size: 72px;
            margin-bottom: 15px;
        }

        .popup-title {
            color: #28a745;
            margin-bottom: 15px;
            font-size: 28px;
        }

        .popup-text {
            margin-top: 15px;
            font-size: 17px;
            line-height: 1.8;
            color: #333;
        }

        .popup-text strong {
            color: #4f6ef7;
            font-size: 20px;
        }

        .popup-btn {
            margin-top: 25px;
            padding: 14px 40px;
            border: none;
            background: #4f6ef7;
            color: white;
            border-radius: 40px;
            cursor: pointer;
            font-size: 17px;
            font-weight: 600;
            transition: background 0.3s, transform 0.1s;
        }

        .popup-btn:hover {
            background: #3b56d9;
            transform: scale(1.02);
        }

        .popup-btn:active {
            transform: scale(0.98);
        }

        /* ----- responsive ----- */
        @media (max-width: 720px) {
            .login-wrapper {
                flex-direction: column;
                border-radius: 28px;
            }

            .login-col {
                padding: 32px 24px;
                flex: 1 1 auto;
            }

            .image-col {
                min-height: 200px;
                flex: 1 1 200px;
            }

            .image-col .overlay-text {
                bottom: 16px;
                left: 16px;
                font-size: 15px;
                padding: 10px 18px;
            }

            header {
                padding: 12px 20px;
            }
            .logo img {
                transform: translateX(0);
                height: 48px;
            }
            .home h2 {
                font-size: 15px;
            }

            .welcome-section h2 {
                font-size: 28px;
            }

            .password-requirements ul {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 420px) {
            .login-col {
                padding: 24px 16px;
            }
            .welcome-section h2 {
                font-size: 24px;
            }
            .welcome-subtitle {
                font-size: 13px;
            }
            .popup-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

<header>
    <div class="logo">
        <img src="../logo.png" alt="Locker Tech Logo" onerror="this.style.display='none'">
    </div>
    <div class="home">
        <a href="../index.php"><i class="fas fa-home"></i><h2>HOME</h2></a>
    </div>
</header>

<section class="container">
    <div class="login-wrapper">

        <!-- LEFT COLUMN: Reset Password Form -->
        <div class="login-col">
            <div class="welcome-section">
                <h2>Reset Password</h2>
                <p class="welcome-subtitle">Create your new password</p>
            </div>

            <?php if (!empty($message)) : ?>
                <div class="error-message"><?php echo $message; ?></div>
            <?php endif; ?>

            <form method="POST" id="resetForm">
                <!-- New Password field -->
                <div class="form-group">
                    <label for="newPassword"><i class="fas fa-lock"></i> New Password</label>
                    <div class="pass-field">
                        <input type="password" 
                               id="newPassword" 
                               name="new_password" 
                               placeholder="Enter new password" 
                               value="<?php echo htmlspecialchars($new_password); ?>" 
                               required>
                        <span class="toggle-password-wrapper" data-target="newPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <div id="passwordMatchError" class="error-message-small"></div>

                <!-- Confirm Password field -->
                <div class="form-group">
                    <label for="confirmPassword"><i class="fas fa-check-circle"></i> Confirm Password</label>
                    <div class="pass-field">
                        <input type="password" 
                               id="confirmPassword" 
                               name="confirm_password" 
                               placeholder="Confirm new password" 
                               value="<?php echo htmlspecialchars($confirm_password); ?>" 
                               required>
                        <span class="toggle-password-wrapper" data-target="confirmPassword">
                            <i class="fas fa-eye"></i>
                        </span>
                    </div>
                </div>

                <!-- Password Requirements -->
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li id="req-length"><i class="fas fa-circle"></i><span>At least 8 characters</span></li>
                        <li id="req-upper"><i class="fas fa-circle"></i><span>At least 1 uppercase letter</span></li>
                        <li id="req-lower"><i class="fas fa-circle"></i><span>At least 1 lowercase letter</span></li>
                        <li id="req-number"><i class="fas fa-circle"></i><span>At least 1 number</span></li>
                        <li id="req-special"><i class="fas fa-circle"></i><span>At least 1 special symbol</span></li>
                        <li id="req-space"><i class="fas fa-circle"></i><span>No spaces</span></li>
                    </ul>
                </div>

                <button type="submit" name="reset_password" id="resetPasswordBtn" class="btn-reset" disabled>Update Password</button>
            </form>

            <div class="bar">
                <a href="login.php"><i class="fas fa-arrow-left"></i> Cancel &amp; Go to Login</a>
            </div>
        </div>

        <!-- RIGHT COLUMN: Image -->
        <div class="image-col">
            <img src="../login_background.jpeg" alt="Reset password visual"
                 onerror="this.style.display='none'; this.parentElement.querySelector('.img-placeholder').style.display='flex';">
            <div class="img-placeholder">
                <i class="fas fa-user-tie" style="font-size: 54px; opacity:0.7;"></i>
                <span style="background:rgba(255,255,255,0.1); padding:8px 22px; border-radius: 60px;">LockerTech</span>
            </div>
            <div class="overlay-text">
                <i class="fas fa-user"></i> Employee
            </div>
        </div>
    </div>
</section>

<!-- ========== SUCCESS POPUP ========== -->
<div class="popup-overlay <?php echo $showSuccessPopup ? '' : 'hidden'; ?>" id="successPopup">
    <div class="popup-box">
        <div class="popup-icon">🎉</div>
        <h2 class="popup-title">Password Updated</h2>
        <p class="popup-text">
            Congratulations<br>
            <strong><?php echo htmlspecialchars($successUserName); ?></strong>
            <br><br>
            Your password has been updated successfully.
        </p>
        <button class="popup-btn" id="popupOkBtn">OK</button>
    </div>
</div>

<script>
    (function() {
        // ---------- toggle password visibility ----------
        document.querySelectorAll('.toggle-password-wrapper').forEach(function(wrapper) {
            wrapper.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                const icon = this.querySelector('i');
                
                if (input && icon) {
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                    input.focus({ preventScroll: true });
                }
            });
        });

        // ---------- DOM refs ----------
        const newPassword = document.getElementById('newPassword');
        const confirmPassword = document.getElementById('confirmPassword');
        const resetPasswordBtn = document.getElementById('resetPasswordBtn');
        const passwordMatchError = document.getElementById('passwordMatchError');
        const resetForm = document.getElementById('resetForm');

        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const reqSpace = document.getElementById('req-space');

        // ---------- popup elements ----------
        const popupOverlay = document.getElementById('successPopup');
        const popupOkBtn = document.getElementById('popupOkBtn');

        // ---------- validation helpers ----------
        function checkPasswordRequirements(password) {
            let allValid = true;
            if (password.length >= 8) { reqLength.classList.add('valid'); } else { reqLength.classList.remove('valid'); allValid = false; }
            if (/[A-Z]/.test(password)) { reqUpper.classList.add('valid'); } else { reqUpper.classList.remove('valid'); allValid = false; }
            if (/[a-z]/.test(password)) { reqLower.classList.add('valid'); } else { reqLower.classList.remove('valid'); allValid = false; }
            if (/[0-9]/.test(password)) { reqNumber.classList.add('valid'); } else { reqNumber.classList.remove('valid'); allValid = false; }
            if (/[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\\\/;]/.test(password)) { reqSpecial.classList.add('valid'); } else { reqSpecial.classList.remove('valid'); allValid = false; }
            if (!/\s/.test(password)) { reqSpace.classList.add('valid'); } else { reqSpace.classList.remove('valid'); allValid = false; }
            return allValid;
        }

        function checkPasswordsMatch() {
            if (confirmPassword.value === '') {
                passwordMatchError.classList.remove('show');
                return false;
            }
            if (newPassword.value === confirmPassword.value) {
                passwordMatchError.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
                passwordMatchError.style.color = '#28a745';
                passwordMatchError.classList.add('show');
                return true;
            }
            passwordMatchError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
            passwordMatchError.style.color = '#dc3545';
            passwordMatchError.classList.add('show');
            return false;
        }

        function validateForm() {
            const password = newPassword.value || '';
            const validPassword = checkPasswordRequirements(password);
            const matchPassword = checkPasswordsMatch();
            if (validPassword && matchPassword && password.length > 0) {
                resetPasswordBtn.disabled = false;
                resetPasswordBtn.classList.add('active');
            } else {
                resetPasswordBtn.disabled = true;
                resetPasswordBtn.classList.remove('active');
            }
        }

        newPassword.addEventListener('input', validateForm);
        confirmPassword.addEventListener('input', validateForm);

        // ---------- initial validation ----------
        validateForm();

        // ---------- popup OK button redirect ----------
        popupOkBtn.addEventListener('click', function() {
            window.location.href = 'login.php';
        });

        // ---------- Auto-redirect if popup is shown ----------
        <?php if ($showSuccessPopup): ?>
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 5000);
        <?php endif; ?>

        // ---------- Handle image fallback ----------
        const img = document.querySelector('.image-col img');
        if (img) {
            img.addEventListener('error', function() {
                this.style.display = 'none';
                const placeholder = this.parentElement.querySelector('.img-placeholder');
                if (placeholder) {
                    placeholder.style.display = 'flex';
                }
            });
        }
    })();
</script>

</body>
</html>