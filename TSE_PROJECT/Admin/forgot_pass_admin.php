<?php
session_start();
date_default_timezone_set('UTC'); // Critical for UTC consistency

include '../db_connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

$message = "";
$email = "";

if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email.";
    } else {
        $email = mysqli_real_escape_string($conn, $email);

        // Check if admin exists
        $sql = "SELECT u.user_id, u.name, u.email
                FROM users u
                INNER JOIN admins a ON u.user_id = a.user_id
                WHERE u.email = '$email' AND u.role = 'admin'";

        $result = mysqli_query($conn, $sql);

        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            $user_id = $user['user_id'];
            $name = $user['name'];

            // Generate 6-digit OTP (stored in token field)
            $otp = rand(100000, 999999);
            $expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));

            // Insert into password_reset table
            $insert = mysqli_query($conn, "INSERT INTO password_reset (user_id, email, token, expires_at, is_used)
                                           VALUES ('$user_id', '$email', '$otp', '$expires_at', 'Active')");

            if (!$insert) {
                $message = "Database error: Could not save reset request.";
            } else {
                // Send email with OTP
                try {
                    $mail = new PHPMailer(true);
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username = 'panzhixin7256@gmail.com';
                    $mail->Password = 'hfhy trka fwrs grzt';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS; 
                    $mail->Port       = 587;

                    $mail->setFrom('leeching2565@gmail.com', 'Attendance System');
                    $mail->addAddress($email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset OTP';
                    $mail->Body    = "
                        <h2>Password Reset Request</h2>
                        <p>Hello <b>$name</b>,</p>
                        <p>Your OTP code is:</p>
                        <h1 style='color:#5170ff;'>$otp</h1>
                        <p>This OTP will expire in 5 minutes.</p>
                        <p>Please do not share this OTP with anyone.</p>
                    ";

                    $mail->send();

                    // Store data in session and redirect
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user_id;
                    header("Location: verify_otp_admin.php");
                    exit();
                } catch (Exception $e) {
                    $message = "Failed to send OTP. Mail Error: " . $mail->ErrorInfo;
                }
            }
        } else {
            $message = "No admin account found with this email.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
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
    </style>
</head>
<body>
<div class="container">
    <h2>Forgot Password</h2>

    <?php if (!empty($message)) : ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>Email Address</label>
        <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
        <button type="submit" name="send_otp">Send OTP</button>
    </form>

    <div class="back">
        <a href="login_admin.php">Back to Login</a>
    </div>
</div>
</body>
</html>