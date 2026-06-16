<?php
session_start();
date_default_timezone_set('UTC');
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

$expires_timestamp = 0;
$is_expired = false;

$user_id = (int)$_SESSION['reset_user_id'];
$email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);

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
    $expires_timestamp = strtotime($expiryRow['expires_at'] . ' UTC') * 1000;
    
    // Check if already expired
    if (time() > strtotime($expiryRow['expires_at'] . ' UTC')) {
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
        $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        
        // Mark old OTPs as expired
        mysqli_query($conn, "UPDATE password_reset SET is_used = 'Expired' 
                             WHERE user_id = $user_id AND email = '$email' AND is_used = 'Active'");
        
        // Insert new OTP
        $insert = mysqli_query($conn, "INSERT INTO password_reset (user_id, email, token, expires_at, is_used)
                                       VALUES ('$user_id', '$email', '$otp', '$expires_at', 'Active')");
        
        if ($insert) {
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

                $mail->setFrom('leeching2565@gmail.com', 'Attendance System');
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
                
                // Update expiry timestamp for JavaScript
                $expires_timestamp = strtotime($expires_at . ' UTC') * 1000;
                $is_expired = false;
                
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

        // Query using UTC_TIMESTAMP() to compare with expires_at (stored in UTC)
        $sql = "SELECT reset_id, token, expires_at, is_used 
                FROM password_reset 
                WHERE user_id = $user_id 
                AND email = '$email'
                AND token = '$otp'
                AND is_used = 'Active'
                AND expires_at > UTC_TIMESTAMP()
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
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height:100vh;
            background:#f4f4f4;
            display:flex;
            flex-direction:column;
        }

        header{
            background:#fff;
            padding:15px 40px;
            display:flex;
            justify-content:space-between;
            align-items:center;
            box-shadow:0 2px 15px rgba(0,0,0,0.08);
            z-index:10;
        }

        .logo img{
            height:60px;
            width:auto;
        }

        .home a{
            text-decoration:none;
            color:#333;
            display:flex;
            align-items:center;
            gap:10px;
            font-weight:600;
            transition:0.3s;
        }

        .home a:hover{
            color:#5170ff;
        }

        .home i{
            font-size:22px;
        }

        .home h2{
            font-size:16px;
            margin:0;
        }

        .main-content{
            flex:1;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:30px;
        }

        .container{
            width:100%;
            max-width:600px;
            background:#fff;
            padding:40px;
            border-radius:20px;
            box-shadow:0 10px 30px rgba(0,0,0,0.12);
            text-align:center;
            animation:fadeIn .5s ease;
        }

        @keyframes fadeIn{
            from{
                opacity:0;
                transform:translateY(20px);
            }
            to{
                opacity:1;
                transform:translateY(0);
            }
        }

        h2{
            color:#333;
            font-size:30px;
            margin-bottom:10px;
        }

        .info{
            background:#f5f7ff;
            border-left:4px solid #667eea;
            padding:12px;
            border-radius:8px;
            margin-bottom:20px;
            color:#555;
            font-size:14px;
            line-height:1.5;
        }

        .info strong{
            color:#667eea;
        }

        .message{
            padding:12px;
            border-radius:8px;
            margin-bottom:20px;
            font-size:14px;
        }

        .message.error{
            background:#ffeaea;
            color:#d63031;
        }

        .message.success{
            background:#e8f5e9;
            color:#2e7d32;
        }

        input{
            width:100%;
            height:60px;
            border:2px solid #e5e7eb;
            border-radius:12px;
            outline:none;
            text-align:center;
            font-size:28px;
            font-weight:600;
            letter-spacing:10px;
            color:#333;
            transition:all 0.3s ease;
            margin-bottom:20px;
        }

        input:focus{
            border-color:#667eea;
            box-shadow:0 0 0 4px rgba(102,126,234,0.15);
        }

        input::placeholder{
            letter-spacing:3px;
            font-size:16px;
            color:#aaa;
        }

        .btn-verify{
            width:100%;
            height:55px;
            border:none;
            border-radius:12px;
            background:linear-gradient(135deg, #0037fe);
            color:white;
            font-size:16px;
            font-weight:600;
            cursor:pointer;
            transition:all 0.3s ease;
        }

        .btn-verify:hover:not(:disabled){
            transform:translateY(-2px);
            box-shadow:0 10px 20px rgba(102,126,234,0.3);
        }

        .btn-verify:active:not(:disabled){
            transform:translateY(0);
        }

        .btn-verify:disabled{
            opacity:0.6;
            cursor:not-allowed;
            background:linear-gradient(135deg,#999,#888);
        }

        .btn-new-otp{
            width:100%;
            height:55px;
            border:none;
            border-radius:12px;
            background:linear-gradient(135deg, #0037fe);
            color:white;
            font-size:16px;
            font-weight:600;
            cursor:pointer;
            transition:all 0.3s ease;
            margin-top:10px;
        }

        .btn-new-otp:hover{
            transform:translateY(-2px);
            box-shadow:0 10px 20px rgba(245,87,108,0.3);
        }

        .btn-new-otp:active{
            transform:translateY(0);
        }

        .back{
            margin-top:20px;
        }

        .back a{
            color:#667eea;
            text-decoration:none;
            font-weight:600;
            transition:0.3s;
        }

        .back a:hover{
            color:#764ba2;
            text-decoration:underline;
        }

        #countdown{
            margin-bottom:20px;
            padding:12px;
            border-radius:8px;
            font-weight:600;
            transition:all 0.3s ease;
        }

        #countdown.active{
            background:#fff3cd;
            color:#856404;
        }

        #countdown.expired{
            background:#f8d7da;
            color:#721c24;
        }

        .divider{
            display:flex;
            align-items:center;
            margin:20px 0;
            color:#999;
            font-size:14px;
        }

        .divider::before,
        .divider::after{
            content:"";
            flex:1;
            height:1px;
            background:#e5e7eb;
        }

        .divider::before{
            margin-right:15px;
        }

        .divider::after{
            margin-left:15px;
        }

        @media (max-width:480px){
            .container{
                padding:30px 20px;
            }

            h2{
                font-size:24px;
            }

            input{
                font-size:24px;
                letter-spacing:6px;
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
        <a href="../index.php">
            <i class="fa-solid fa-house"></i>
            <h2>HOME</h2>
        </a>
    </div>
</header>

<div class="main-content">
    <div class="container">
        <h2>Verify OTP</h2>

        <div class="info">
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
            <input
                type="text"
                name="otp"
                id="otpInput"
                maxlength="6"
                pattern="[0-9]{6}"
                inputmode="numeric"
                placeholder="000000"
                autocomplete="off"
                required
            >

            <button type="submit" name="verify_otp" id="verifyBtn" class="btn-verify">
                Verify OTP
            </button>
        </form>

        <div class="divider">OR</div>

        <form method="POST">
            <button type="submit" name="request_new_otp" class="btn-new-otp">
                <i class="fa-solid fa-envelope"></i> Request New OTP
            </button>
        </form>

        <div class="back">
            <a href="login_admin.php">Back to Login</a>
        </div>
    </div>
</div>
</body>

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
        verifyBtn.textContent = "OTP Expired";
        
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
    document.getElementById("verifyBtn").textContent = "OTP Expired";
    document.getElementById("otpInput").disabled = true;
}

updateCountdown();
setInterval(updateCountdown, 1000);

// Auto submit if OTP is entered completely
document.getElementById('otpInput').addEventListener('input', function(e) {
    if (this.value.length === 6 && !document.getElementById('verifyBtn').disabled) {
        this.form.submit();
    }
});
</script>
</html>