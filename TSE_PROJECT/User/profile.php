<?php
session_start();
include '../db_connection.php';
include 'header.php';

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$employee_id = $_SESSION['employee_id'];
$success_message = '';
$error_message = '';

// Password validation function
function validatePassword($password) {
    $errors = [];
    
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
    if(!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "Password must contain at least 1 special symbol";
    }
    if(preg_match('/\s/', $password)) {
        $errors[] = "Password cannot contain spaces";
    }
    
    return $errors;
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $contact_number = mysqli_real_escape_string($conn, $_POST['contact_number']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        
        // Update employee table only (name and email not editable)
        $update_employee = "UPDATE employees SET contact_number = '$contact_number', address = '$address' 
                            WHERE employee_id = '$employee_id'";
        if(mysqli_query($conn, $update_employee)) {
            $success_message = "Profile updated successfully!";
        } else {
            $error_message = "Error updating profile: " . mysqli_error($conn);
        }
    }
    
    // Handle password change with validation
    if(isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Get current password from database
        $pass_query = "SELECT password FROM users WHERE user_id = '$user_id'";
        $pass_result = mysqli_query($conn, $pass_query);
        $user_data = mysqli_fetch_assoc($pass_result);
        
        if($current_password != $user_data['password']) {
            $error_message = "Current password is incorrect!";
        } else {
            // Validate new password
            $validation_errors = validatePassword($new_password);
            
            if(!empty($validation_errors)) {
                $error_message = implode("<br>", $validation_errors);
            } elseif($new_password != $confirm_password) {
                $error_message = "New password and confirm password do not match!";
            } else {
                $update_pass = "UPDATE users SET password = '$new_password' WHERE user_id = '$user_id'";
                if(mysqli_query($conn, $update_pass)) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Error changing password: " . mysqli_error($conn);
                }
            }
        }
    }
    
    // Handle profile picture upload
    if(isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['profile_picture']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        $filesize = $_FILES['profile_picture']['size'];
        
        // Validate file type
        if(!in_array(strtolower($filetype), $allowed)) {
            $error_message = "Only JPG, JPEG, PNG, GIF files are allowed!";
        }
        // Validate file size (max 2MB)
        elseif($filesize > 2 * 1024 * 1024) {
            $error_message = "File size too large! Maximum 2MB allowed.";
        }
        else {
            // Get current profile picture before updating
            $current_pic_query = "SELECT profile_picture FROM users WHERE user_id = '$user_id'";
            $current_pic_result = mysqli_query($conn, $current_pic_query);
            $current_user = mysqli_fetch_assoc($current_pic_result);
            $old_picture = $current_user['profile_picture'] ?? '';
            
            $new_filename = "user_" . $user_id . "_" . time() . "." . $filetype;
            $upload_path = "../profile_picture/" . $new_filename;
            
            // Create directory if not exists
            if(!file_exists("../profile_picture/")) {
                mkdir("../profile_picture/", 0777, true);
            }
            
            // Delete old profile picture if exists
            if(!empty($old_picture) && file_exists("../profile_picture/" . $old_picture)) {
                unlink("../profile_picture/" . $old_picture);
            }
            
            if(move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                $update_pic = "UPDATE users SET profile_picture = '$new_filename' WHERE user_id = '$user_id'";
                if(mysqli_query($conn, $update_pic)) {
                    $_SESSION['profile_picture'] = $new_filename;
                    $success_message = "Profile picture updated successfully!";
                } else {
                    $error_message = "Error updating database!";
                }
            } else {
                $error_message = "Error uploading file!";
            }
        }
    }
}

// Get user data again after potential update
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.user_id = '$user_id'";
$result = mysqli_query($conn, $query);
$user = mysqli_fetch_assoc($result);

// Set profile picture path
$profile_pic_filename = isset($user['profile_picture']) && !empty($user['profile_picture']) ? $user['profile_picture'] : '';
$profile_pic = "../profile_picture/" . $profile_pic_filename;

if(empty($profile_pic_filename) || !file_exists($profile_pic)) {
    $profile_pic = "https://ui-avatars.com/api/?background=007bff&color=fff&size=150&name=" . urlencode($user['name']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Attendance System</title>
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
        
        .main-container {
            width: 85%;
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        /* Profile Container */
        .profile-container {
            display: grid;
            grid-template-columns: 350px 1fr;
            gap: 25px;
        }
        
        /* Profile Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            height: fit-content;
        }
        
        .profile-avatar {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #007bff;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Hidden file input */
        #profilePicInput {
            display: none;
        }
        
        .upload-btn {
            background: #007bff;
            color: white;
            padding: 8px 15px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            transition: all 0.3s;
            border: none;
        }
        
        .upload-btn:hover {
            background: #0056b3;
        }
        
        /* File info text */
        .file-info {
            font-size: 11px;
            color: #999;
            margin-top: 8px;
        }
        
        .employee-code {
            background: #e9ecef;
            padding: 10px;
            border-radius: 8px;
            margin: 15px 0;
            font-size: 14px;
        }
        
        .employee-code span {
            color: #666;
        }
        
        .employee-code strong {
            color: #007bff;
        }
        
        /* Profile Main Content */
        .profile-main {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: bold;
            color: #333;
            padding-bottom: 10px;
            border-bottom: 2px solid #007bff;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #555;
        }
        
        .form-group input,
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
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .form-group input[readonly] {
            background: #e9ecef;
            cursor: not-allowed;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        /* Password Field with Eye Icon */
        .pass-wrapper {
            position: relative;
        }
        
        .pass-wrapper input {
            width: 100%;
            padding: 10px 12px;
            padding-right: 40px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .pass-wrapper input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
            font-size: 16px;
        }
        
        .toggle-password:hover {
            color: #007bff;
        }
        
        /* Error message for password match */
        .error-message-small {
            color: #dc3545;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }
        
        .error-message-small.show {
            display: block;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background: #0056b3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-secondary.active {
            background: #007bff;
        }
        
        .btn-secondary.active:hover {
            background: #0056b3;
        }
        
        .btn-secondary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
        
        .row-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        /* Password fields */
        .password-fields {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        /* Password Requirements */
        .password-requirements {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            border: 1px solid #e9ecef;
        }
        
        .password-requirements h4 {
            font-size: 14px;
            color: #333;
            margin-bottom: 10px;
        }
        
        .password-requirements ul {
            list-style: none;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .password-requirements li {
            font-size: 12px;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-requirements li i {
            font-size: 8px;
            color: #999;
        }
        
        .password-requirements li.valid i {
            color: #28a745;
        }
        
        .password-requirements li.valid span {
            color: #28a745;
        }
        
        hr {
            margin: 25px 0;
            border: none;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>

<div class="main-container">
    <?php if($success_message): ?>
        <div class="alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if($error_message): ?>
        <div class="alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <div class="profile-container">
        <!-- Profile Sidebar -->
        <div class="profile-sidebar">
            <div class="profile-avatar">
                <img src="<?php echo $profile_pic; ?>" alt="Profile Picture" id="profileImage">
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="profilePicForm">
                <label class="upload-btn">
                    <i class="fas fa-camera"></i> Choose Photo
                    <input type="file" name="profile_picture" id="profilePicInput" accept="image/jpeg,image/png,image/gif,image/jpg">
                </label>
                <div class="file-info">
                    <i class="fas fa-info-circle"></i> JPG, PNG, GIF only. Max 2MB
                </div>
            </form>
            
            <div class="employee-code">
                <span>Employee Code:</span><br>
                <strong><?php echo $user['employee_code']; ?></strong>
            </div>
            
            <div class="employee-code">
                <span>Employment Date:</span><br>
                <strong><?php echo date('d-m-Y', strtotime($user['employment_date'])); ?></strong>
            </div>
        </div>
        
        <!-- Profile Main Content -->
        <div class="profile-main">
            <!-- Personal Information -->
            <div class="section-title">Personal Information</div>
            <form method="POST" action="">
                <div class="row-2">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['name']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                </div>
                
                <div class="row-2">
                    <div class="form-group">
                        <label>Department</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['department']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['position']); ?>" readonly>
                    </div>
                </div>
                
                <div class="row-2">
                    <div class="form-group">
                        <label>Contact Number</label>
                        <input type="text" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address" rows="4"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
            </form>
            
            <hr>
            
            <!-- Change Password -->
            <div class="section-title">Change Password</div>
            <form method="POST" action="" id="passwordForm">
                <div class="password-fields">
                    <div class="form-group">
                        <label>Current Password</label>
                        <div class="pass-wrapper">
                            <input type="password" name="current_password" id="currentPassword" placeholder="Enter current password" required>
                            <i class="fas fa-eye toggle-password" data-target="currentPassword"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <div class="pass-wrapper">
                            <input type="password" name="new_password" id="newPassword" placeholder="Enter new password" required>
                            <i class="fas fa-eye toggle-password" data-target="newPassword"></i>
                        </div>
                        <div id="passwordMatchError" class="error-message-small"></div>
                    </div>
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <div class="pass-wrapper">
                            <input type="password" name="confirm_password" id="confirmPassword" placeholder="Confirm new password" required>
                            <i class="fas fa-eye toggle-password" data-target="confirmPassword"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Password Requirements -->
                <div class="password-requirements">
                    <h4>Password Requirements:</h4>
                    <ul>
                        <li id="req-length"><i class="fas fa-circle"></i><span>At least 8 characters</span></li>
                        <li id="req-upper"><i class="fas fa-circle"></i><span>At least 1 uppercase letter</span></li>
                        <li id="req-lower"><i class="fas fa-circle"></i><span>At least 1 lowercase letter</span></li>
                        <li id="req-number"><i class="fas fa-circle"></i><span>At least 1 number</span></li>
                        <li id="req-special"><i class="fas fa-circle"></i><span>At least 1 special symbol</span></li>
                        <li id="req-space"><i class="fas fa-circle"></i><span>No spaces</span></li>
                    </ul>
                </div>
                
                <button type="submit" name="change_password" id="changePasswordBtn" class="btn-secondary" style="margin-top: 10px;" disabled>Change Password</button>
            </form>
        </div>
    </div>
</div>

<script>
    // Profile Picture Upload Validation
    document.getElementById('profilePicInput').addEventListener('change', function(e) {
        const file = this.files[0];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if(file) {
            // Validate file type
            if(!allowedTypes.includes(file.type)) {
                alert('Invalid file type! Please upload JPG, JPEG, PNG, or GIF files only.');
                this.value = '';
                return;
            }
            
            // Validate file size
            if(file.size > maxSize) {
                alert('File too large! Maximum size is 2MB.');
                this.value = '';
                return;
            }
            
            // Preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profileImage').src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            // Auto submit form
            const form = document.getElementById('profilePicForm');
            form.submit();
        }
    });
    
    // Toggle password visibility for all password fields
    document.querySelectorAll('.toggle-password').forEach(function(icon) {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                
                // Toggle eye icon
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
                
                // Keep focus on input
                input.focus();
            }
        });
    });
    
    // Get all required elements
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const passwordMatchError = document.getElementById('passwordMatchError');
    
    // Requirement elements
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const reqSpace = document.getElementById('req-space');
    
    // Function to validate password requirements
    function checkPasswordRequirements(password) {
        let allValid = true;
        
        // Check length
        if(password.length >= 8) {
            reqLength.classList.add('valid');
        } else {
            reqLength.classList.remove('valid');
            allValid = false;
        }
        
        // Check uppercase
        if(/[A-Z]/.test(password)) {
            reqUpper.classList.add('valid');
        } else {
            reqUpper.classList.remove('valid');
            allValid = false;
        }
        
        // Check lowercase
        if(/[a-z]/.test(password)) {
            reqLower.classList.add('valid');
        } else {
            reqLower.classList.remove('valid');
            allValid = false;
        }
        
        // Check number
        if(/[0-9]/.test(password)) {
            reqNumber.classList.add('valid');
        } else {
            reqNumber.classList.remove('valid');
            allValid = false;
        }
        
        // Check special character
        if(/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            reqSpecial.classList.add('valid');
        } else {
            reqSpecial.classList.remove('valid');
            allValid = false;
        }
        
        // Check no spaces
        if(!/\s/.test(password)) {
            reqSpace.classList.add('valid');
        } else {
            reqSpace.classList.remove('valid');
            allValid = false;
        }
        
        return allValid;
    }
    
    // Function to check if passwords match
    function checkPasswordsMatch() {
        const newPass = newPassword.value;
        const confirmPass = confirmPassword.value;
        
        if(confirmPass === '') {
            passwordMatchError.classList.remove('show');
            return true;
        }
        
        if(newPass === confirmPass) {
            passwordMatchError.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match!';
            passwordMatchError.classList.add('show');
            passwordMatchError.style.color = '#28a745';
            return true;
        } else {
            passwordMatchError.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match!';
            passwordMatchError.classList.add('show');
            passwordMatchError.style.color = '#dc3545';
            return false;
        }
    }
    
    // Function to check if current password is entered
    function checkCurrentPassword() {
        return currentPassword.value.trim() !== '';
    }
    
    // Main validation function
    function validateAllFields() {
        const hasCurrentPassword = checkCurrentPassword();
        const isPasswordValid = checkPasswordRequirements(newPassword.value);
        const doPasswordsMatch = checkPasswordsMatch();
        const hasNewPassword = newPassword.value.trim() !== '';
        
        // Enable button only if all conditions are met
        if(hasCurrentPassword && hasNewPassword && isPasswordValid && doPasswordsMatch) {
            changePasswordBtn.disabled = false;
            changePasswordBtn.classList.add('active');
        } else {
            changePasswordBtn.disabled = true;
            changePasswordBtn.classList.remove('active');
        }
    }
    
    // Real-time validation events
    currentPassword.addEventListener('input', validateAllFields);
    newPassword.addEventListener('input', function() {
        checkPasswordRequirements(this.value);
        checkPasswordsMatch();
        validateAllFields();
    });
    confirmPassword.addEventListener('input', function() {
        checkPasswordsMatch();
        validateAllFields();
    });
    
    // Initial validation
    validateAllFields();
    
    // Form submission validation
    document.getElementById('passwordForm').addEventListener('submit', function(e) {
        const password = newPassword.value;
        const errors = [];
        
        if(password.length < 8) errors.push("Password must be at least 8 characters");
        if(!/[A-Z]/.test(password)) errors.push("Password must contain at least 1 uppercase letter");
        if(!/[a-z]/.test(password)) errors.push("Password must contain at least 1 lowercase letter");
        if(!/[0-9]/.test(password)) errors.push("Password must contain at least 1 number");
        if(!/[!@#$%^&*(),.?":{}|<>]/.test(password)) errors.push("Password must contain at least 1 special symbol");
        if(/\s/.test(password)) errors.push("Password cannot contain spaces");
        
        if(errors.length > 0) {
            e.preventDefault();
            alert("Password Requirements:\n" + errors.join("\n"));
        }
    });
</script>

</body>
</html>