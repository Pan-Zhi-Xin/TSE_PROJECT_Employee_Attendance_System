<?php
// Check if user is logged in as admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../index.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
$show_back_button = ($current_page != 'dashboard_admin.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Attendance System</title>
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

        /* Inner container for centered content */
        .header-container {
            width: 80%;
            height: 100%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Left section - Back Button + Logo + System Name */
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

        /* Right section - Navigation Menu */
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
        }

        .user-auth {
            position: relative;
        }

        .user-icon {
            width: 35px;
            height: 35px;
            cursor: pointer;
            border-radius: 50%;
            background-color: #dc3545;
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
        }

        .user-menu.show {
            display: block;
        }

        .user-menu a {
            display: block;
            text-decoration: none;
            color: #333;
            font-size: 14px;
            padding: 8px 15px;
            transition: 0.3s;
        }

        .user-menu a:hover {
            background-color: #f0f0f0;
        }

        .user-menu .user-name {
            padding: 8px 15px;
            border-bottom: 1px solid #eee;
            font-weight: bold;
            color: #dc3545;
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

        /* Main content container - match header width */
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
                    <a href="dashboard_admin.php">
                        <i class="fas fa-arrow-left"></i> <span>Back to Dashboard</span>
                    </a>
                </div>
                <?php endif; ?>
                
                <a href="dashboard_admin.php" class="logo-link">
                    <img src="../logo.png" alt="Locker Tech Logo" onerror="this.src='https://via.placeholder.com/50x50?text=LT'">
                </a>
                <span><b>Admin Dashboard</b></span>
            </div>

            <!-- Right: Navigation Menu -->
            <div class="header-right">
                <nav>
                    <ul>
                        <li><a href="dashboard_admin.php">Dashboard</a></li>
                        <li><a href="employee_list.php">Employees</a></li>
                        <li><a href="update_absent_status.php">Absent Employee</a></li>
                        <li><a href="report.php">Report</a></li>
                    </ul>
                </nav>

                <div class="user-auth">
                    <div class="user-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="user-menu">
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