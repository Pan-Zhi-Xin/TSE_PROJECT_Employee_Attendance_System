<?php
session_start();
include '../db_connection.php';

$message = "";
$new_password = "";
$confirm_password = "";

// Ensure user came from OTP verification
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: forgot_pass_admin.php");
    exit();
}

if (isset($_POST['reset_password'])) {
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($new_password) || empty($confirm_password)) {
        $message = "Please fill in both password fields.";
    } elseif ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
    } elseif (strlen($new_password) < 3) {
        $message = "Password must be at least 3 characters.";
    } else {
        $user_id = $_SESSION['reset_user_id'];
        
        // In a real application, you should hash the password using password_hash()
        // For this system, we store plain text (as per existing schema)
        $new_password_escaped = mysqli_real_escape_string($conn, $new_password);
        
        $sql = "UPDATE users SET password = '$new_password_escaped' WHERE user_id = '$user_id'";
        
        if (mysqli_query($conn, $sql)) {
            // Clear reset session variables
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            
            // Redirect to login with success message
            $_SESSION['reset_success'] = "Password has been reset successfully. Please login.";
            header("Location: login_admin.php");
            exit();
        } else {
            $message = "Database error: Could not update password.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
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
    <h2>Reset Password</h2>
    
    <?php if (!empty($message)) : ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <form method="POST">
        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Enter new password" required>
        
        <label>Confirm Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password" required>
        
        <button type="submit" name="reset_password">Update Password</button>
    </form>

    <div class="back">
        <a href="login_admin.php">Cancel & Go to Login</a>
    </div>
</div>
</body>
</html>