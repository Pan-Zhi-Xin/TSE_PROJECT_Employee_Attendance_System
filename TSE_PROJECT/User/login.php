<?php
session_start();
include '../db_connection.php'; 

$error = '';
$email = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);

    if (empty($email) || empty($password)) {
        $error = "Both email and password are required.";
    } else {
        // Escape special characters to prevent SQL injection
        $email = mysqli_real_escape_string($conn, $email);
        
        // Check if user exists in users table with role employee
        $sql = "SELECT u.user_id, u.name, u.email, u.password, u.role, u.status, 
                       e.employee_id, e.employee_code, e.department, e.position 
                FROM users u 
                JOIN employees e ON u.user_id = e.user_id 
                WHERE u.email = '$email' AND u.role = 'employee'";
        
        $result = mysqli_query($conn, $sql);
        
        if ($result && mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            
            if($user["status"] != 'Active') {
                $error = "Your account has been Inactive, please contact the administrator for more information.";
            }
            else if ($password === $user["password"]) {
                $_SESSION["user_id"] = $user["user_id"];
                $_SESSION["username"] = $user["name"];
                $_SESSION["user_name"] = $user["name"];
                $_SESSION["email"] = $user["email"];
                $_SESSION["role"] = $user["role"];
                $_SESSION["employee_id"] = $user["employee_id"];
                $_SESSION["employee_code"] = $user["employee_code"];
                $_SESSION["department"] = $user["department"];
                $_SESSION["position"] = $user["position"];
                
                // Store in session that we're redirecting
                $_SESSION['show_loading'] = true;
                header("Location: dashboard.php");
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "No account found with this email.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Employee Login - Attendance System</title>
    <script src="https://kit.fontawesome.com/c2f7d169d6.js" crossorigin="anonymous"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    height: 100vh;
}

body::before {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: url('../login_background.jpeg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    filter: blur(8px);
    z-index: -2;
}

body::after {
    content: "";
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.3);
    z-index: -1;
}

/* Make sure container also doesn't scroll */
.container {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    overflow: hidden;
}

        header {
            background: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .logo img {
            transform: translateX(20px);
            height: 60px;
            weidth: auto;
        }

        .home a {
            text-decoration: none;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: color 0.3s;
        }

        .home a:hover {
            color: #667eea;
        }

        .home i {
            font-size: 24px;
        }

        .home h2 {
            font-size: 18px;
            margin: 0;
        }

        .container {
            display: flex;
            min-height: calc(100vh - 90px);
            align-items: center;
            justify-content: center;
            padding: 40px;
        }

        .left-side {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .left-side img {
            max-width: 90%;
            height: auto;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .right-side {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .right-side-inner {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .frame h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
            font-size: 28px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            border: 1px solid #f5c6cb;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            margin-bottom: 20px;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
        }

        .wrapper {
            position: relative;
        }

        .pass-field {
            position: relative;
            width: 100%;
        }

        .pass-field input {
            width: 100%;
            padding: 12px;
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            margin-bottom: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .pass-field input:focus {
            outline: none;
            border-color: #667eea;
        }

        #show-password {
            position: absolute;
            right: 12px;
            top: 12px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
            z-index: 10;
            background: transparent;
            font-size: 18px;
        }

        #show-password:hover {
            color: #667eea;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #5170ff;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            margin-top: 10px;
        }

        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .bar {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .forgot_pass{
            text-align: center;
            margin-bottom: 15px;
        }

        .forgot_pass a {
            color: red;
            text-decoration: underline;
            font-size: 14px;
            font-weight: 500;
        }

        .forgot_pass a:hover {
            text-decoration: underline;
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
        <div class="left-side">
            <img src="../image/Picture.png" alt="Attendance System" onerror="this.style.display='none'">
        </div>
        <div class="right-side">
            <div class="right-side-inner">
                <div class="frame">
                    <h2>Employee Login</h2>
                    <?php if (!empty($error)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    <form method="post" action="">
                        <label>Email:</label>
                        <input type="email" placeholder="example@gmail.com" name="email" value="<?php echo htmlspecialchars($email); ?>" required><br>
    
                        <label>Password:</label>
                        <div class="wrapper">
                            <div class="pass-field">
                                <input type="password" placeholder="Enter your password" name="password" id="password" required>
                                <i class="fa-solid fa-eye" id="show-password"></i>
                            </div>
                        </div>

                        <button type="submit">Login</button>
                    </form>
                </div>
        
                <section class="bar">
                    <div class="forgot_pass">
                        <a href="forgot_pass.php">Forgot password</a>
                    </div>
                </section>
            </div>
        </div>
    </section>

    <script>
    const passwordInput = document.getElementById('password');
    const togglePassword = document.getElementById('show-password');
    
    if (togglePassword) {
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
            
            passwordInput.focus();
        });
    }
    
    <?php if (!empty($error) && strpos($error, 'Inactive') !== false): ?>
        alert("<?php echo htmlspecialchars($error); ?>");
    <?php endif; ?>
</script>
</body>
</html>