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
$email = ""; // Initialize $email variable

if (isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $message = "Please enter your email.";
    } else {
        $email = mysqli_real_escape_string($conn, $email);

        // Check if employee exists
        $sql = "SELECT u.user_id, u.name, u.email
                FROM users u
                INNER JOIN employees e ON u.user_id = e.user_id
                WHERE u.email = '$email' AND u.role = 'employee'";

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

                    $mail->setFrom('panzhixin7256@gmail.com', 'Locker Tech Attendance System');
                    $mail->addAddress($email, $name);
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset OTP';
                    $mail->Body    = "
                        <h2>Password Reset Request</h2>
                        <p>Hello <b>$name</b>,</p>
                        <p>Your OTP code is:</p>
                        <h1 style='color:#4f6ef7;'>$otp</h1>
                        <p>This OTP will expire in 5 minutes.</p>
                        <p>Please do not share this OTP with anyone.</p>
                    ";

                    $mail->send();

                    // Store data in session and redirect
                    $_SESSION['reset_email'] = $email;
                    $_SESSION['reset_user_id'] = $user_id;
                    header("Location: verify_otp.php");
                    exit();
                } catch (Exception $e) {
                    $message = "Failed to send OTP. Mail Error: " . $mail->ErrorInfo;
                }
            }
        } else {
            $message = "No employee account found with this email.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password · Employee</title>
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

        /* ----- background with blur ----- */
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
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
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
            background: rgba(255, 255, 255, 0.3);
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

        /* ----- main container (split layout) ----- */
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

        /* ----- left column: form ----- */
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

        .form-group input[type="email"] {
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

        .form-group input[type="email"]:focus {
            outline: none;
            border-color: #4f6ef7;
            box-shadow: 0 0 0 4px rgba(79, 110, 247, 0.12);
        }

        .btn-send {
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

        .btn-send:hover {
            background: #3b56d9;
            transform: scale(1.01) translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(79, 110, 247, 0.5);
        }

        .bar {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1.5px solid rgba(0, 0, 0, 0.04);
            display: flex;
            justify-content: center;
        }

        .back-link a {
            color: #4f6ef7;
            text-decoration: none;
            font-weight: 600;
            font-size: 15px;
            transition: 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .back-link a:hover {
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

        /* ----- right column: image  ----- */
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
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(4px);
            padding: 14px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 18px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ----- responsiveness ----- */
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
        }
    </style>
</head>
<body>
    <header>
        <div class="logo">
            <img src="../logo.png" alt="Locker Tech Logo" onerror="this.style.display='none'">
        </div>
        <div class="home">
            <a href="../index.php"><i class="fa-solid fa-house"></i><h2>HOME</h2></a>
        </div>
    </header>

    <section class="container">
        <div class="login-wrapper">

            <!-- LEFT COLUMN: Form  -->
            <div class="login-col">
                <div class="welcome-section">
                    <h2>Forgot Password</h2>
                    <p class="welcome-subtitle">We'll send you a one‑time code</p>
                </div>

                <?php if (!empty($message)) : ?>
                    <div class="error-message"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" placeholder="employee@lockertech.com" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                    </div>

                    <button type="submit" name="send_otp" class="btn-send">Send OTP</button>
                </form>

                <div class="bar">
                    <div class="back-link">
                        <a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Image -->
            <div class="image-col">
                <img src="../login_background.jpeg" alt="Employee login visual"
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

    <script>
        (function() {
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
        })();
    </script>
</body>
</html>