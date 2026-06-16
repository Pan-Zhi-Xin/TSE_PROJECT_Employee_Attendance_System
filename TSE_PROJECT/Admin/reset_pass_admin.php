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

    // Handle empty password
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
    // Updated special character regex to be more inclusive
    if(!preg_match('/[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\\\/;]/', $password)) {
        $errors[] = "Password must contain at least 1 special symbol";
    }
    if(preg_match('/\s/', $password)) {
        $errors[] = "Password cannot contain spaces";
    }

    return $errors;
}

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
            
            // Use prepared statement for security
            $stmt = mysqli_prepare($conn, "SELECT name FROM users WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $user = mysqli_fetch_assoc($result);
            $user_name = $user['name'];
            mysqli_stmt_close($stmt);

            // Use prepared statement for update as well
            $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE user_id = ?");
            mysqli_stmt_bind_param($stmt, "si", $new_password, $user_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $successUserName = $user_name;
                $showSuccessPopup = true;
                
                // Clear session variables
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                mysqli_stmt_close($stmt);
                
                // Display success popup
                ?>
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Password Updated</title>
                    <style>
                        .popup-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0,0,0,0.5);
                            display: flex;
                            justify-content: center;
                            align-items: center;
                            z-index: 9999;
                        }
                        .popup-box {
                            background: white;
                            width: 420px;
                            padding: 30px;
                            border-radius: 15px;
                            text-align: center;
                            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                            animation: popIn 0.3s ease-out;
                        }
                        @keyframes popIn {
                            0% { transform: scale(0.8); opacity: 0; }
                            100% { transform: scale(1); opacity: 1; }
                        }
                        .popup-icon {
                            font-size: 60px;
                            margin-bottom: 15px;
                        }
                        .popup-title {
                            color: #28a745;
                            margin-bottom: 15px;
                        }
                        .popup-text {
                            margin-top: 15px;
                            font-size: 16px;
                            line-height: 1.6;
                        }
                        .popup-btn {
                            margin-top: 20px;
                            padding: 12px 30px;
                            border: none;
                            background: #5170ff;
                            color: white;
                            border-radius: 8px;
                            cursor: pointer;
                            font-size: 16px;
                            transition: background 0.3s;
                        }
                        .popup-btn:hover {
                            background: #4060e0;
                        }
                    </style>
                </head>
                <body>
                    <div class="popup-overlay" id="successPopup">
                        <div class="popup-box">
                            <div class="popup-icon">🎉</div>
                            <h2 class="popup-title">Password Updated</h2>
                            <p class="popup-text">
                                Congratulations<br>
                                <strong><?php echo htmlspecialchars($successUserName); ?></strong>
                                <br><br>
                                Your password has been updated successfully.
                            </p>
                            <button class="popup-btn" onclick="redirectToLogin()">OK</button>
                        </div>
                    </div>
                    <script>
                        function redirectToLogin() {
                            window.location.href = 'login_admin.php';
                        }
                        // Auto-redirect after 5 seconds
                        setTimeout(redirectToLogin, 5000);
                    </script>
                </body>
                </html>
                <?php
                exit();
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
    <title>Reset Password</title>
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
            padding: 10px;
            background: #ffe6e6;
            border-radius: 5px;
        }
        .message.success {
            color: #28a745;
            background: #e6ffe6;
        }
        .back {
            margin-top: 20px;
            text-align: center;
        }
        .back a {
            text-decoration: none;
            color: #5170ff;
        }
        .pass-wrapper{
            position:relative;
        }

        .pass-wrapper input{
            padding-right:45px;
        }

        .toggle-password{
            position:absolute;
            right:15px;
            top:50%;
            transform:translateY(-50%);
            cursor:pointer;
            color:#999;
        }

        .toggle-password:hover{
            color:#5170ff;
        }

        .error-message-small{
            font-size:13px;
            margin-top:-10px;
            margin-bottom:15px;
            display:none;
            padding: 8px;
            border-radius: 4px;
        }

        .error-message-small.show{
            display:block;
        }

        .password-requirements{
            background:#f8f9fa;
            border:1px solid #e9ecef;
            border-radius:10px;
            padding:15px;
            margin-bottom:20px;
        }

        .password-requirements h4{
            font-size:14px;
            margin-bottom:10px;
        }

        .password-requirements ul{
            list-style:none;
            display:grid;
            grid-template-columns:1fr 1fr;
            gap:8px;
        }

        .password-requirements li{
            font-size:12px;
            color:#666;
            display:flex;
            align-items:center;
            gap:8px;
        }

        .password-requirements li i{
            font-size:8px;
            color:#999;
        }

        .password-requirements li.valid i{
            color:#28a745;
        }

        .password-requirements li.valid span{
            color:#28a745;
        }

        button:disabled{
            background:#6c757d;
            cursor:not-allowed;
        }

        button.active{
            background:#5170ff;
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
        <h2>Reset Password</h2>
        
        <?php if (!empty($message)) : ?>
            <div class="message <?php echo strpos($message, 'success') !== false ? 'success' : ''; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="resetForm">
            <label>New Password</label>
            <div class="pass-wrapper">
                <input type="password"
                    name="new_password"
                    id="newPassword"
                    placeholder="Enter new password"
                    required>
                <i class="fas fa-eye toggle-password"
                data-target="newPassword"></i>
            </div>

            <div id="passwordMatchError"
                class="error-message-small"></div>
            
            <label>Confirm Password</label>
            <div class="pass-wrapper">
                <input type="password"
                    name="confirm_password"
                    id="confirmPassword"
                    placeholder="Confirm new password"
                    required>
                <i class="fas fa-eye toggle-password"
                data-target="confirmPassword"></i>
            </div>

            <div class="password-requirements">
            <h4>Password Requirements:</h4>

            <ul>
                <li id="req-length">
                    <i class="fas fa-circle"></i>
                    <span>At least 8 characters</span>
                </li>

                <li id="req-upper">
                    <i class="fas fa-circle"></i>
                    <span>At least 1 uppercase letter</span>
                </li>

                <li id="req-lower">
                    <i class="fas fa-circle"></i>
                    <span>At least 1 lowercase letter</span>
                </li>

                <li id="req-number">
                    <i class="fas fa-circle"></i>
                    <span>At least 1 number</span>
                </li>

                <li id="req-special">
                    <i class="fas fa-circle"></i>
                    <span>At least 1 special symbol</span>
                </li>

                <li id="req-space">
                    <i class="fas fa-circle"></i>
                    <span>No spaces</span>
                </li>
            </ul>
        </div>

            <button type="submit"
                    name="reset_password"
                    id="resetPasswordBtn"
                    disabled>
                Update Password
            </button>
        </form>

        <div class="back">
            <a href="login_admin.php">Cancel & Go to Login</a>
        </div>
    </div>
</body>

<script>
document.querySelectorAll('.toggle-password').forEach(function(icon){
    icon.addEventListener('click',function(){
        const targetId=this.getAttribute('data-target');
        const input=document.getElementById(targetId);

        if(input.type==='password'){
            input.type='text';
            this.classList.remove('fa-eye');
            this.classList.add('fa-eye-slash');
        }
        else{
            input.type='password';
            this.classList.remove('fa-eye-slash');
            this.classList.add('fa-eye');
        }
    });
});

const newPassword=document.getElementById('newPassword');
const confirmPassword=document.getElementById('confirmPassword');
const resetPasswordBtn=document.getElementById('resetPasswordBtn');
const passwordMatchError=document.getElementById('passwordMatchError');

const reqLength=document.getElementById('req-length');
const reqUpper=document.getElementById('req-upper');
const reqLower=document.getElementById('req-lower');
const reqNumber=document.getElementById('req-number');
const reqSpecial=document.getElementById('req-special');
const reqSpace=document.getElementById('req-space');

function checkPasswordRequirements(password){

    let allValid=true;

    if(password.length>=8){
        reqLength.classList.add('valid');
    }else{
        reqLength.classList.remove('valid');
        allValid=false;
    }

    if(/[A-Z]/.test(password)){
        reqUpper.classList.add('valid');
    }else{
        reqUpper.classList.remove('valid');
        allValid=false;
    }

    if(/[a-z]/.test(password)){
        reqLower.classList.add('valid');
    }else{
        reqLower.classList.remove('valid');
        allValid=false;
    }

    if(/[0-9]/.test(password)){
        reqNumber.classList.add('valid');
    }else{
        reqNumber.classList.remove('valid');
        allValid=false;
    }

    if(/[!@#$%^&*(),.?":{}|<>_\-+=~`\[\]\\\\\/;]/.test(password)){
        reqSpecial.classList.add('valid');
    }else{
        reqSpecial.classList.remove('valid');
        allValid=false;
    }

    if(!/\s/.test(password)){
        reqSpace.classList.add('valid');
    }else{
        reqSpace.classList.remove('valid');
        allValid=false;
    }

    return allValid;
}

function checkPasswordsMatch(){

    if(confirmPassword.value===''){
        passwordMatchError.classList.remove('show');
        return false;
    }

    if(newPassword.value===confirmPassword.value){
        passwordMatchError.innerHTML=
        '<i class="fas fa-check-circle"></i> Passwords match!';
        passwordMatchError.style.color='#28a745';
        passwordMatchError.classList.add('show');
        return true;
    }

    passwordMatchError.innerHTML=
    '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
    passwordMatchError.style.color='#dc3545';
    passwordMatchError.classList.add('show');
    return false;
}

function validateForm(){

    const password = newPassword.value || '';
    const validPassword = checkPasswordRequirements(password);
    const matchPassword = checkPasswordsMatch();

    if(validPassword && matchPassword && password.length > 0){
        resetPasswordBtn.disabled=false;
        resetPasswordBtn.classList.add('active');
    }
    else{
        resetPasswordBtn.disabled=true;
        resetPasswordBtn.classList.remove('active');
    }
}

newPassword.addEventListener('input',validateForm);
confirmPassword.addEventListener('input',validateForm);

// Initial validation
validateForm();

// Prevent form submission if passwords don't match
document.getElementById('resetForm').addEventListener('submit', function(e) {
    if (newPassword.value !== confirmPassword.value) {
        e.preventDefault();
        passwordMatchError.innerHTML = 
        '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
        passwordMatchError.style.color = '#dc3545';
        passwordMatchError.classList.add('show');
    }
});
</script>
</html>