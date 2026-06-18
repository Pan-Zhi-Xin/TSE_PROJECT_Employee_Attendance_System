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
    <link rel="stylesheet" href="header_admin.css">
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
                        <a href="profile_admin.php"><i class="fas fa-user-circle"></i> My Profile</a>
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