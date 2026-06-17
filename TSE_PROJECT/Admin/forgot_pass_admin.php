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
            background: linear-gradient(135deg, #f4f4f4 0%, #f4f4f4 100%);
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

        .container{
            width:100%;
            max-width:450px;
            margin:auto;
            background:#fff;
            padding:40px;
            border-radius:20px;
            box-shadow:0 15px 40px rgba(0,0,0,0.15);
            animation:fadeIn 0.4s ease;
        }

        .container h2{
            text-align:center;
            color:#333;
            margin-bottom:10px;
            font-size:28px;
        }

        .subtitle{
            text-align:center;
            color:#777;
            margin-bottom:30px;
            font-size:14px;
        }

        label{
            display:block;
            margin-bottom:8px;
            font-weight:600;
            color:#444;
        }

        input{
            width:100%;
            padding:14px 16px;
            border:2px solid #e5e7eb;
            border-radius:12px;
            font-size:15px;
            transition:0.3s;
            outline:none;
            margin-bottom:20px;
        }

        input:focus{
            border-color:#5170ff;
            box-shadow:0 0 0 4px rgba(81,112,255,0.15);
        }

        button{
            width:100%;
            padding:14px;
            border:none;
            border-radius:12px;
            background:linear-gradient(135deg,#5170ff,#7b61ff);
            color:#fff;
            font-size:16px;
            font-weight:600;
            cursor:pointer;
            transition:0.3s;
        }

        button:hover{
            transform:translateY(-2px);
            box-shadow:0 10px 25px rgba(81,112,255,0.35);
        }

        button:active{
            transform:translateY(0);
        }

        .message{
            background:#fff3f3;
            color:#dc2626;
            border:1px solid #fecaca;
            padding:12px;
            border-radius:10px;
            text-align:center;
            margin-bottom:20px;
            font-size:14px;
        }

        .back{
            text-align:center;
            margin-top:20px;
        }

        .back a{
            color:#5170ff;
            text-decoration:none;
            font-weight:600;
            transition:0.3s;
        }

        .back a:hover{
            color:#3b57d1;
            text-decoration:underline;
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

        @media(max-width:500px){
            .container{
                margin:30px 15px;
                padding:30px 25px;
            }

            header{
                padding:15px 20px;
            }

            .logo img{
                height:50px;
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
