<?php
session_start();
date_default_timezone_set('UTC');
include '../db_connection.php';

$message = "";

if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_pass_admin.php");
    exit();
}

if (isset($_POST['verify_otp'])) {
    $otp = trim($_POST['otp']);
    $user_id = (int)$_SESSION['reset_user_id'];
    $email = mysqli_real_escape_string($conn, $_SESSION['reset_email']);

    if (empty($otp)) {
        $message = "Please enter the OTP.";
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
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Verify OTP</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7ff;
        }
        .container {
            width: 400px;
            margin: 80px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
            margin-bottom: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
            text-align: center;
            font-size: 24px;
            letter-spacing: 5px;
        }
        button {
            width: 100%;
            padding: 12px;
            background: #5170ff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            opacity: 0.9;
        }
        .message {
            text-align: center;
            margin-bottom: 15px;
            color: red;
        }
        .back {
            margin-top: 20px;
            text-align: center;
        }
        .back a {
            text-decoration: none;
            color: #5170ff;
        }
        .info {
            text-align: center;
            margin-bottom: 20px;
            color: #555;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Verify OTP</h2>
    <div class="info">
        We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
    </div>

    <?php if (!empty($message)) : ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="otp" maxlength="6" placeholder="Enter OTP" autocomplete="off" required>
        <button type="submit" name="verify_otp">Verify OTP</button>
    </form>

    <div class="back">
        <a href="forgot_pass_admin.php">Request New OTP</a>
    </div>
</div>
</body>
</html>