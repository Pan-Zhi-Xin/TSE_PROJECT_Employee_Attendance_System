<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include '../db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$message = "";
$message_type = "";

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_pass_admin.php");
    exit();
}

$user_id = (int)$_SESSION['reset_user_id'];
$email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);

// Auto-expire ALL expired OTPs
mysqli_query($conn, "
    UPDATE password_reset 
    SET is_used = 'Expired' 
    WHERE is_used = 'Active' 
    AND expires_at < NOW()
");

// Auto-expire specific user's expired OTPs
mysqli_query($conn, "
    UPDATE password_reset 
    SET is_used = 'Expired' 
    WHERE user_id = $user_id 
    AND email = '$email' 
    AND is_used = 'Active' 
    AND expires_at < NOW()
");

$expires_timestamp = 0;
$is_expired = false;

$expiryQuery = mysqli_query($conn, "
    SELECT expires_at
    FROM password_reset
    WHERE user_id = $user_id
    AND email = '$email'
    AND is_used = 'Active'
    ORDER BY reset_id DESC
    LIMIT 1
");

if ($expiryQuery && mysqli_num_rows($expiryQuery) > 0) {
    $expiryRow = mysqli_fetch_assoc($expiryQuery);
    // Convert to timestamp 
    $expires_timestamp = strtotime($expiryRow['expires_at']) * 1000;
    
    // Check if already expired
    if (time() > strtotime($expiryRow['expires_at'])) {
        $is_expired = true;
    }
}

// Handle Request New OTP
if (isset($_POST['request_new_otp'])) {
    $user_id = (int)$_SESSION['reset_user_id'];
    $email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);
    
    // Get user details
    $sql = "SELECT u.name, u.email
            FROM users u
            WHERE u.user_id = $user_id AND u.role = 'admin'";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $user = mysqli_fetch_assoc($result);
        $name = $user['name'];
        
        // Generate new 6-digit OTP
        $otp = rand(100000, 999999);
        
        // Mark old OTPs as expired
        mysqli_query($conn, "UPDATE password_reset SET is_used = 'Expired' 
                             WHERE user_id = $user_id AND email = '$email' AND is_used = 'Active'");
        
        // Insert new OTP with local time
        $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        $insert = mysqli_query($conn, "INSERT INTO password_reset (user_id, email, token, expires_at, is_used)
                                       VALUES ('$user_id', '$email', '$otp', '$expires_at', 'Active')");
        
        if ($insert) {
            // Get the expires_at value for JavaScript
            $expiryQuery = mysqli_query($conn, "
                SELECT expires_at
                FROM password_reset
                WHERE user_id = $user_id
                AND email = '$email'
                AND is_used = 'Active'
                ORDER BY reset_id DESC
                LIMIT 1
            ");
            
            if ($expiryQuery && mysqli_num_rows($expiryQuery) > 0) {
                $expiryRow = mysqli_fetch_assoc($expiryQuery);
                $expires_timestamp = strtotime($expiryRow['expires_at']) * 1000;
                $is_expired = false;
            }

            // Send email with new OTP
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'panzhixin7256@gmail.com';
                $mail->Password   = 'hfhy trka fwrs grzt';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('panzhixin7256@gmail.com', 'Locker Tech Attendance System');
                $mail->addAddress($email, $name);
                $mail->isHTML(true);
                $mail->Subject = 'New Password Reset OTP';
                $mail->Body    = "
                    <h2>New Password Reset Request</h2>
                    <p>Hello <b>$name</b>,</p>
                    <p>Your new OTP code is:</p>
                    <h1 style='color:#5170ff;'>$otp</h1>
                    <p>This OTP will expire in 5 minutes.</p>
                    <p>Please do not share this OTP with anyone.</p>
                ";

                $mail->send();
                
                $message = "New OTP has been sent to your email!";
                $message_type = "success";
                
            } catch (Exception $e) {
                $message = "Failed to send new OTP. Please try again.";
                $message_type = "error";
            }
        } else {
            $message = "Failed to generate new OTP. Please try again.";
            $message_type = "error";
        }
    }
}

// Handle Verify OTP
if (isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $user_id = (int)$_SESSION['reset_user_id'];
    $email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);

    if (empty($otp)) {
        $message = "Please enter the OTP.";
        $message_type = "error";
    } else {
        $otp = mysqli_real_escape_string($conn, $otp);

        // OTP verification using local time
        $sql = "SELECT reset_id, token, expires_at, is_used 
                FROM password_reset 
                WHERE user_id = $user_id 
                AND email = '$email'
                AND token = '$otp'
                AND is_used = 'Active'
                AND expires_at > NOW()
                ORDER BY reset_id DESC LIMIT 1";

        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            $row = mysqli_fetch_assoc($result);
            $reset_id = $row['reset_id'];

            // Mark as used
            mysqli_query($conn, "UPDATE password_reset SET is_used = 'Used' WHERE reset_id = $reset_id");

            // Redirect to reset password page
            header("Location: reset_pass_admin.php");
            exit();
        } else {
            $message = "Invalid or expired OTP. Please request a new one.";
            $message_type = "error";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP</title>
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
            background: url('../login_admin_background.png') center / cover no-repeat fixed;
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

        .info-box {
            background: #f5f7ff;
            border-left: 4px solid #4f6ef7;
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            color: #1e293b;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-box strong {
            color: #4f6ef7;
        }

        /* Message styling */
        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .message.error {
            background: #fee9e7;
            color: #b91c1c;
            border-left: 5px solid #dc2626;
        }

        .message.success {
            background: #e6f7ed;
            color: #0b6e4f;
            border-left: 5px solid #10b981;
        }

        .message.error::before {
            content: "\f06a";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 18px;
        }

        .message.success::before {
            content: "\f058";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            font-size: 18px;
        }

        /* Countdown styling */
        #countdown {
            padding: 14px 16px;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            text-align: center;
            font-size: 15px;
        }

        #countdown.active {
            background: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }

        #countdown.expired {
            background: #fee9e7;
            color: #b91c1c;
            border-left: 5px solid #dc2626;
        }

        /* Form group */
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

        .otp-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 10px;
            text-align: center;
            transition: 0.25s;
            background: white;
            font-family: inherit;
            box-sizing: border-box;
            color: #0b1e3a;
        }

        .otp-input:focus {
            outline: none;
            border-color: #4f6ef7;
            box-shadow: 0 0 0 4px rgba(79, 110, 247, 0.12);
        }

        .otp-input:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
            opacity: 0.6;
        }

        .otp-input::placeholder {
            letter-spacing: 3px;
            font-size: 16px;
            font-weight: 400;
            color: #94a3b8;
        }

        .btn-verify {
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

        .btn-verify:hover:not(:disabled) {
            background: #3b56d9;
            transform: scale(1.01) translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(79, 110, 247, 0.5);
        }

        .btn-verify:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #94a3b8;
            box-shadow: none;
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 24px 0 20px 0;
            color: #94a3b8;
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: #e2e8f0;
        }

        .divider::before {
            margin-right: 15px;
        }

        .divider::after {
            margin-left: 15px;
        }

        .btn-new-otp {
            width: 100%;
            padding: 16px;
            background: #f1f5f9;
            border: 2px solid #e2e8f0;
            border-radius: 40px;
            color: #1e293b;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-new-otp:hover {
            background: #e2e8f0;
            border-color: #4f6ef7;
            color: #4f6ef7;
            transform: scale(1.01);
        }

        .btn-new-otp i {
            color: #4f6ef7;
        }

        .bar {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1.5px solid rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: center;
        }

        .back a {
            color: #4f6ef7;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back a:hover {
            color: #2d3f9e;
            text-decoration: underline;
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

        /* Responsive */
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
            .otp-input {
                font-size: 24px;
                letter-spacing: 6px;
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
            <!-- LEFT COLUMN: Verify OTP Form -->
            <div class="login-col">
                <div class="welcome-section">
                    <h2>Verify OTP</h2>
                    <p class="welcome-subtitle">Enter the verification code sent to your email</p>
                </div>

                <div class="info-box">
                    <i class="fas fa-envelope" style="color:#4f6ef7; margin-right:8px;"></i>
                    We've sent a 6-digit OTP to 
                    <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
                </div>

                <div id="countdown" class="active">
                    OTP expires in 05:00
                </div>

                <?php if (!empty($message)) : ?>
                    <div class="message <?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="otpInput"><i class="fas fa-shield-alt"></i> Enter OTP Code</label>
                        <input
                            type="text"
                            name="otp"
                            id="otpInput"
                            class="otp-input"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            inputmode="numeric"
                            placeholder="000000"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <button type="submit" name="verify_otp" id="verifyBtn" class="btn-verify">
                        <i class="fas fa-check-circle"></i> Verify OTP
                    </button>
                </form>

                <div class="divider">OR</div>

                <form method="POST">
                    <button type="submit" name="request_new_otp" class="btn-new-otp">
                        <i class="fas fa-envelope"></i> Request New OTP
                    </button>
                </form>

                <div class="bar">
                    <div class="back">
                        <a href="login_admin.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Image -->
            <div class="image-col">
                <img src="../login_admin_background.png" alt="Admin login visual" 
                     onerror="this.style.display='none'; this.parentElement.querySelector('.img-placeholder').style.display='flex';">
                <div class="img-placeholder">
                    <i class="fas fa-building" style="font-size: 54px; opacity:0.7;"></i>
                    <span style="background:rgba(255,255,255,0.1); padding:8px 22px; border-radius: 60px;">LockerTech</span>
                </div>
                <div class="overlay-text">
                    <i class="fas fa-user-shield"></i> Admin
                </div>
            </div>
        </div>
    </section>

    <script>
        const expireTime = <?php echo $expires_timestamp; ?>;
        let isExpired = <?php echo $is_expired ? 'true' : 'false'; ?>;

        function updateCountdown() {
            if (!expireTime) {
                document.getElementById("countdown").innerHTML = "OTP expiration unavailable";
                return;
            }

            const now = new Date().getTime();
            const distance = expireTime - now;

            if (distance <= 0 || isExpired) {
                document.getElementById("countdown").innerHTML = "OTP Expired! Please request a new one.";
                document.getElementById("countdown").className = "expired";
                
                // Disable verify button
                const verifyBtn = document.getElementById("verifyBtn");
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = '<i class="fas fa-times-circle"></i> OTP Expired';
                
                // Disable OTP input
                document.getElementById("otpInput").disabled = true;
                
                return;
            }

            const minutes = Math.floor(distance / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);

            document.getElementById("countdown").innerHTML = 
                "OTP expires in " +
                String(minutes).padStart(2, '0') +
                ":" +
                String(seconds).padStart(2, '0');
            
            document.getElementById("countdown").className = "active";
        }

        // Initial check
        if (isExpired) {
            document.getElementById("countdown").innerHTML = "OTP Expired! Please request a new one.";
            document.getElementById("countdown").className = "expired";
            document.getElementById("verifyBtn").disabled = true;
            document.getElementById("verifyBtn").innerHTML = '<i class="fas fa-times-circle"></i> OTP Expired';
            document.getElementById("otpInput").disabled = true;
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);

        // Handle image fallback
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
    </script>
</body>
</html>