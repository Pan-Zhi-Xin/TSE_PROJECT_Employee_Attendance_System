<?php
// Check if user is logged in as employee
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$show_back_button = ($current_page != 'dashboard.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        html {
            scroll-padding-top: 80px;
        }

        body {
            margin: 0;
            padding-top: 80px;
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        header {
            height: 120px;
            box-sizing: border-box;
            background-color: white;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .header-container {
            width: 80%;
            height: 100%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .back-button a {
            color: #333;
            text-decoration: none;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f0f0f0;
            padding: 8px 15px;
            border-radius: 25px;
            transition: all 0.3s;
        }

        .back-button a:hover {
            background: #e0e0e0;
            color: #667eea;
        }

        .logo-link {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo-link img {
            height: 50px;
            width: auto;
        }

        .header-left span {
            font-weight: bold;
            font-size: 18px;
            color: #333;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        nav ul {
            list-style: none;
            display: flex;
            gap: 25px;
            margin: 0;
            padding: 0;
        }

        nav ul li {
            display: inline;
        }

        nav ul li a {
            text-decoration: none;
            color: #333;
            font-size: 16px;
            font-weight: 500;
            padding: 5px 10px;
            transition: all 0.3s;
            position: relative;
        }

        nav ul li a:hover {
            color: #667eea;
        }

        nav ul li a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: #667eea;
            transition: width 0.3s ease;
        }

        nav ul li a:hover::after {
            width: 100%;
        }

        .user-auth {
            position: relative;
        }

        .user-icon {
            width: 35px;
            height: 35px;
            cursor: pointer;
            border-radius: 50%;
            background-color: black;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
        }

        .user-icon:hover {
            transform: scale(1.05);
        }

        .user-menu {
            display: none;
            position: absolute;
            right: 0;
            top: 45px;
            background-color: white;
            box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
            border-radius: 8px;
            width: 160px;
            text-align: center;
            padding: 10px 0;
            z-index: 1001;
            animation: slideDown 0.3s ease;
        }

        .user-menu.show {
            display: block;
        }

        .user-menu .user-name {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            color: #667eea;
            font-size: 14px;
        }

        .user-menu a {
            display: block;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            padding: 8px 15px;
            transition: 0.3s;
            text-align: left;
        }

        .user-menu a:hover {
            background-color: #f0f0f0;
            color: #667eea;
        }

        .user-menu a i {
            margin-right: 10px;
            width: 18px;
        }

        .dashboard-card {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            background: white;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .welcome-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .main-container {
            width: 80%;
            margin: 0 auto;
            max-width: none;
        }
    </style>
</head>
<body>
    <header>
        <div class="header-container">
            <!-- Left: Back Button + Logo + System Name -->
            <div class="header-left">
                <?php if($show_back_button): ?>
                <div class="back-button">
                    <a href="javascript:history.back()">
                        <i class="fas fa-arrow-left"></i> <span>Back</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <a href="dashboard.php" class="logo-link">
                    <img src="../logo.png" alt="Locker Tech Logo" onerror="this.src='https://via.placeholder.com/50x50?text=LT'">
                </a>
                <span><b>Employee Attendance System</b></span>
            </div>

            <!-- Right: Navigation Menu -->
            <div class="header-right">
                <nav>
                    <ul>
                        <li><a href="dashboard.php">Home</a></li>
                        <li><a href="reason.php">Reason</a></li>
                        <li><a href="attendance.php">Attendance</a></li>
                    </ul>
                </nav>

                <div class="user-auth">
                    <div class="user-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="user-menu">
                        <div class="user-name">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['user_name']; ?>
                        </div>
                        <a href="profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <script>
        // Toggle user menu on click
        document.addEventListener('DOMContentLoaded', function() {
            const userIcon = document.querySelector('.user-icon');
            const userMenu = document.querySelector('.user-menu');
            
            if (userIcon && userMenu) {
                // Toggle menu when clicking on user icon
                userIcon.addEventListener('click', function(e) {
                    e.stopPropagation();
                    userMenu.classList.toggle('show');
                });
                
                // Close menu when clicking outside
                document.addEventListener('click', function(event) {
                    if (!userIcon.contains(event.target) && !userMenu.contains(event.target)) {
                        userMenu.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>