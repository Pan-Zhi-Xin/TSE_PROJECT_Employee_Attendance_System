<?php
session_start();
include 'db_connection.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Attendance System - Locker Tech</title>
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
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            margin: 0;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('company_background.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            opacity: 0.6; 
            z-index: -1;
        }
        
        /* Main Card - Two Columns */
        .main-card {
            display: flex;
            max-width: 700px;
            width: 100%;
            height: 350px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            position: relative;
            z-index: 1;
        }
        
        /* Left Panel - Light Blue Background */
        .left-panel {
            flex: 1;
            background: #e7edf0;
            padding: 40px 25px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
        }

        .logo-area {
            text-align: left;
            margin-left: 20px;
        }

        .logo-area img {
            height: 65px;
            width: auto;
            margin-bottom: 20px;
        }

        .logo-area h1 {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a5f;
            line-height: 1.3;
            margin-bottom: 10px;
            text-align: left;
        }

        .company-name {
            color: #1e3a5f;
            font-size: 13px;
            margin-top: 10px;
            font-weight: 500;
            text-align: left;
        }
        
        /* Right Panel - White Background */
        .right-panel {
            flex: 1;
            padding: 40px 25px;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .select-role{
            margin-top: 25px;
            text-align: center;
        }
        
        .instruction {
            color: #555;
            font-size: 14px;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #7BA7C9;
            text-align: center;
        }
        
        /* Button Styles */
        .btn {
            display: block;
            width: 100%;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .btn:last-child {
            margin-bottom: 0;
        }
        
        /* Admin Button */
        .btn-admin {
            background: #5170ff;
            color: white;
        }
        
        .btn-admin:hover {
            background: #357abd;
            transform: translateY(-2px);
        }
        
        /* Employee Button */
        .btn-employee {
            background: #5170ff;
            color: white;
        }
        
        .btn-employee:hover {
            background: #357abd;
            transform: translateY(-2px);
        }
        
        .btn-content {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        
        .btn-text h4 {
            font-size: 15px;
            margin-bottom: 2px;
        }
        
        .btn-text p {
            font-size: 11px;
            opacity: 0.8;
        }
        
        /* Alert */
        .alert {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: center;
        }
        
        /* Footer at bottom of right panel */
        .footer {
            margin-top: auto;
            padding-top: 20px;
            text-align: center;
        }
        
        .footer p {
            color: #999;
            font-size: 11px;
        }

    </style>
</head>
<body>
    <div class="main-card">
        <!-- Left Panel - Logo and Title -->
        <div class="left-panel">
            <div class="logo-area">
                <img src="logo.png" alt="Locker Tech Logo" onerror="this.style.display='none'">
                <h1>Employee Attendance<br>System</h1>
                <div class="company-name">
                    Locker Tech
                </div>
            </div>
        </div>
        
        <!-- Right Panel - Selection Options -->
        <div class="right-panel">
            <?php if(isset($_SESSION['error'])): ?>
                <div class="alert">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <div class="select-role">
                <div class="instruction">
                    Please select your role
                </div>
                
                <!-- Admin Button -->
                <a href="admin/login_admin.php" class="btn btn-admin">
                    <div class="btn-content">
                        <div class="btn-text">
                            <h4>I am an Admin</h4>
                        </div>
                    </div>
                </a>
                
                <!-- Employee Button -->
                <a href="user/login.php" class="btn btn-employee">
                    <div class="btn-content">
                        <div class="btn-text">
                            <h4>I am an Employee</h4>
                        </div>
                    </div>
                </a>
            </div>
            
            <div class="footer">
                <p>© Locker Tech Corporation | All Rights Reserved</p>
            </div>
        </div>
    </div>
</body>
</html>