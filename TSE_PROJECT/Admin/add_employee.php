<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

// Include PHPMailer
require '../PHPMailer/src/Exception.php';
require '../PHPMailer/src/PHPMailer.php';
require '../PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Function to generate random password
function generateRandomPassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Function to generate employee code
function generateEmployeeCode($conn) {
    $prefix = 'EMP';
    $query = "SELECT employee_code FROM employees ORDER BY employee_id DESC LIMIT 1";
    $result = mysqli_query($conn, $query);
    if($result && mysqli_num_rows($result) > 0) {
        $last_code = mysqli_fetch_assoc($result)['employee_code'];
        $last_number = intval(substr($last_code, 3));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    return $prefix . str_pad($new_number, 3, '0', STR_PAD_LEFT);
}

$error = '';
$success = '';
$generated_password = '';
$auto_employee_code = generateEmployeeCode($conn);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $employee_code = mysqli_real_escape_string($conn, $_POST['employee_code']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $employment_date = mysqli_real_escape_string($conn, $_POST['employment_date']);
    $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    
    // Generate random password
    $generated_password = generateRandomPassword(8);
    
    // Check if email already exists
    $check_email = "SELECT email FROM users WHERE email = '$email'";
    $email_result = mysqli_query($conn, $check_email);
    if(mysqli_num_rows($email_result) > 0) {
        $error = "Email already exists!";
    } else {
        // Insert into users table
        $insert_user = "INSERT INTO users (email, password, name, role, status) 
                        VALUES ('$email', '$generated_password', '$name', 'employee', 'Active')";
        
        if(mysqli_query($conn, $insert_user)) {
            $user_id = mysqli_insert_id($conn);
            
            // Insert into employees table
            $insert_employee = "INSERT INTO employees (user_id, employee_code, department, position, employment_date, contact_number, address) 
                                VALUES ('$user_id', '$employee_code', '$department', '$position', '$employment_date', '$contact_number', '$address')";
            
            if(mysqli_query($conn, $insert_employee)) {
                // Send email with password
                $mail = new PHPMailer(true);
                
                try {
                    // SMTP Configuration
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'panzhixin7256@gmail.com';
                    $mail->Password = 'hfhy trka fwrs grzt';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    
                    // Recipients
                    $mail->setFrom('panzhixin7256@gmail.com', 'Locker Tech Attendance System');
                    $mail->addAddress($email, $name);
                    
                    // Email content
                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome to Locker Tech Attendance System';
                    $mail->Body = "
                        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px;'>
                            <div style='text-align: center; margin-bottom: 20px;'>
                                <h2 style='color: #dc3545;'>Welcome to Locker Tech!</h2>
                                <p style='font-size: 16px;'>Your employee account has been created successfully.</p>
                            </div>
                            
                            <div style='background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                                <h3 style='color: #333; margin-bottom: 15px;'>Account Details:</h3>
                                <p><strong>Employee Code:</strong> {$employee_code}</p>
                                <p><strong>Name:</strong> {$name}</p>
                                <p><strong>Email:</strong> {$email}</p>
                                <p><strong>Department:</strong> {$department}</p>
                                <p><strong>Position:</strong> {$position}</p>
                                <p><strong>Temporary Password:</strong> <span style='background: #ffc107; padding: 3px 8px; border-radius: 5px; font-weight: bold;'>{$generated_password}</span></p>
                            </div>
                            
                            <div style='background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;'>
                                <p style='color: #856404; margin: 0;'><strong>Important:</strong> Please login and change your password immediately for security reasons.</p>
                            </div>
                            
                            <div style='text-align: center; margin-top: 20px;'>
                                <a href='http://localhost/TSE_PROJECT/user/login.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Your Account</a>
                            </div>
                            
                            <hr style='margin: 20px 0;'>
                            <p style='color: #999; font-size: 12px; text-align: center;'>This is an automated message. Please do not reply.</p>
                        </div>
                    ";
                    
                    $mail->send();
                    $success = "Employee added successfully! A welcome email with password has been sent to {$email}";
                    
                    // Generate new employee code for next entry
                    $auto_employee_code = generateEmployeeCode($conn);
                    // Clear form
                    $_POST = array();
                } catch (Exception $e) {
                    $success = "Employee added successfully but failed to send email. Error: " . $mail->ErrorInfo;
                }
            } else {
                $error = "Error adding employee: " . mysqli_error($conn);
                // Delete the user if employee insertion fails
                mysqli_query($conn, "DELETE FROM users WHERE user_id = '$user_id'");
            }
        } else {
            $error = "Error adding user: " . mysqli_error($conn);
        }
    }
}

$today_date = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Employee</title>
    <link rel="stylesheet" href="add_employee.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <a href="employee_list.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Employee List
            </a>
            <h5>Add New Employee</h5>
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
                <div class="form-layout">
                    <!-- Left Column -->
                    <div class="form-left">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Employee Code <span class="required">*</span></label>
                            <input type="text" name="employee_code" value="<?php echo $auto_employee_code; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Number <span class="required">*</span></label>
                            <input type="text" name="contact_number" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" placeholder="+60123456789" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" placeholder="email@domain.com" required>
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="form-right">
                        <div class="form-group">
                            <label>Department <span class="required">*</span></label>
                            <select name="department" required>
                                <option value="" diabled>--- Select Department ---</option>
                                <option value="IT Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                <option value="HR Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'HR Department') ? 'selected' : ''; ?>>HR Department</option>
                                <option value="Finance Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Finance Department') ? 'selected' : ''; ?>>Finance Department</option>
                                <option value="Marketing Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Marketing Department') ? 'selected' : ''; ?>>Marketing Department</option>
                                <option value="Sales Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Sales Department') ? 'selected' : ''; ?>>Sales Department</option>
                                <option value="Operations Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Operations Department') ? 'selected' : ''; ?>>Operations Department</option>
                                <option value="Customer Service" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="Administration" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <input type="text" name="position" value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Employment Date <span class="required">*</span></label>
                            <input type="date" name="employment_date" value="<?php echo $today_date; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Address <span class="required">*</span></label>
                            <textarea name="address" rows="2" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> Add Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>