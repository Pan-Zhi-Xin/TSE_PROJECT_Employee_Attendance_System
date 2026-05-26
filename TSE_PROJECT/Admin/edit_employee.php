<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

$error = '';
$success = '';

// Get employee ID from URL
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($employee_id == 0) {
    header("Location: employee_list.php");
    exit();
}

// Fetch employee data
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE e.employee_id = '$employee_id'";
$result = mysqli_query($conn, $query);
$employee = mysqli_fetch_assoc($result);

if(!$employee) {
    header("Location: employee_list.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    
    // Update employee table (only department and position)
    $update_employee = "UPDATE employees 
                        SET department = '$department', position = '$position' 
                        WHERE employee_id = '$employee_id'";
    
    if(mysqli_query($conn, $update_employee)) {
        $success = "Employee updated successfully!";
        // Refresh employee data
        $query = "SELECT u.*, e.* FROM users u 
                  JOIN employees e ON u.user_id = e.user_id 
                  WHERE e.employee_id = '$employee_id'";
        $result = mysqli_query($conn, $query);
        $employee = mysqli_fetch_assoc($result);
    } else {
        $error = "Error updating employee: " . mysqli_error($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Centered Main Container */
        .main-container {
            max-width: 900px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: #dc3545;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h5 {
            margin: 0;
            font-size: 18px;
        }
        
        .btn-back {
            background: white;
            color: #dc3545;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-back:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Profile Section */
        .profile-section {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        
        .profile-picture {
            text-align: center;
        }
        
        .profile-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #dc3545;
        }
        
        .profile-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid #dc3545;
        }
        
        .profile-placeholder i {
            font-size: 45px;
            color: #999;
        }
        
        /* Form Styles */
        .form-layout {
            display: flex;
            gap: 30px;
        }
        
        .form-left {
            flex: 1;
        }
        
        .form-right {
            flex: 1;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #dc3545;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #dc3545;
        }
        
        .form-group input[readonly] {
            background: #e9ecef;
            cursor: not-allowed;
        }
        
        .readonly-field {
            background: #e9ecef;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            color: #666;
            font-size: 14px;
        }
        
        /* Alert Messages - Simple inline */
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        /* Button Styles */
        .btn-submit {
            background: #dc3545;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            width: 100%;
            justify-content: center;
        }
        
        .btn-submit:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <a href="employee_list.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Employee List
            </a>
            <h5><i class="fas fa-user-edit"></i> Edit Employee</h5>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if($success): ?>
                <div class="alert-success">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <!-- Profile Picture - Centered -->
                <div class="profile-section">
                    <div class="profile-picture">
                        <?php
                        $profile_pic_path = "../profile_picture/" . ($employee['profile_picture'] ?? '');
                        $has_profile = !empty($employee['profile_picture']) && file_exists($profile_pic_path);
                        ?>
                        <?php if($has_profile): ?>
                            <img src="<?php echo $profile_pic_path; ?>" alt="Profile" class="profile-img">
                        <?php else: ?>
                            <div class="profile-placeholder">
                                <i class="fas fa-user"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-layout">
                    <!-- Left Column -->
                    <div class="form-left">
                        <div class="form-group">
                            <label>Full Name</label>
                            <div class="readonly-field"><?php echo htmlspecialchars($employee['name']); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Employee Code</label>
                            <div class="readonly-field"><?php echo htmlspecialchars($employee['employee_code']); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Number</label>
                            <div class="readonly-field"><?php echo htmlspecialchars($employee['contact_number'] ?? '-'); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address</label>
                            <div class="readonly-field"><?php echo htmlspecialchars($employee['email']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="form-right">
                        <div class="form-group">
                            <label>Department <span class="required">*</span></label>
                            <select name="department" required>
                                <option value="">--- Select Department ---</option>
                                <option value="IT Department" <?php echo ($employee['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                <option value="HR Department" <?php echo ($employee['department'] == 'HR Department') ? 'selected' : ''; ?>>HR Department</option>
                                <option value="Finance Department" <?php echo ($employee['department'] == 'Finance Department') ? 'selected' : ''; ?>>Finance Department</option>
                                <option value="Marketing Department" <?php echo ($employee['department'] == 'Marketing Department') ? 'selected' : ''; ?>>Marketing Department</option>
                                <option value="Sales Department" <?php echo ($employee['department'] == 'Sales Department') ? 'selected' : ''; ?>>Sales Department</option>
                                <option value="Operations Department" <?php echo ($employee['department'] == 'Operations Department') ? 'selected' : ''; ?>>Operations Department</option>
                                <option value="Customer Service" <?php echo ($employee['department'] == 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="Administration" <?php echo ($employee['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <input type="text" name="position" value="<?php echo htmlspecialchars($employee['position']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Employment Date</label>
                            <div class="readonly-field"><?php echo date('d-m-Y', strtotime($employee['employment_date'])); ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Address</label>
                            <div class="readonly-field"><?php echo htmlspecialchars($employee['address'] ?? '-'); ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>