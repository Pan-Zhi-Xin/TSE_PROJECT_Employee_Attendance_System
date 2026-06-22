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

// Handle AJAX requests for real-time validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_availability'])) {
    $type = $_POST['type'];
    $value = trim($_POST['value']);
    $exists = false;
    $is_valid_format = true;
    $error_message = '';

    if ($type === 'phone') {
        if (!preg_match("/^[0-9]{10,11}$/", $value)) {
            $is_valid_format = false;
            $error_message = "Phone must contain only numbers (10 or 11 digits)";
        } else {
            // Check in admins table
            $sql = "SELECT contact_number FROM admins WHERE contact_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            
            // If not found in admins, check in employees table (excluding current employee)
            if (!$exists) {
                $sql = "SELECT contact_number FROM employees WHERE contact_number = ? AND employee_id != ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $value, $_SESSION['employee_id']);
                $stmt->execute();
                $stmt->store_result();
                $exists = $stmt->num_rows > 0;
                $stmt->close();
            }
            
            if ($exists) {
                $error_message = "This contact number is already registered.";
            }
        }
    }

    echo json_encode(['exists' => $exists, 'valid_format' => $is_valid_format, 'message' => $error_message]);
    exit();
}

$user_id = $_SESSION['user_id'];
$employee_id = $_SESSION['employee_id'];
$success_message = '';
$error_message = '';

// Store validation errors for each field
$contact_error = '';
$address_error = '';

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

function validateContactNumber($contact) {
    if(empty($contact)) {
        return "Contact number is required.";
    }
    if(!preg_match("/^[0-9]{10,11}$/", $contact)) {
        return "Contact number must contain only numbers (10 or 11 digits). No spaces, hyphens, or + signs allowed.";
    }
    return "";
}

function validateContactExists($contact, $conn, $current_employee_id) {
    // Check in admins table
    $sql = "SELECT contact_number FROM admins WHERE contact_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $contact);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();
    
    // If not found in admins, check in employees table (excluding current employee)
    if (!$exists) {
        $sql = "SELECT contact_number FROM employees WHERE contact_number = ? AND employee_id != ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $contact, $current_employee_id);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
    }
    
    if($exists) {
        return "This contact number is already registered.";
    }
    return "";
}

function validateAddress($address) {
    if(empty($address)) {
        return "Address is required.";
    }
    if(strlen($address) < 10) {
        return "Please enter a complete address (at least 10 characters).";
    }
    if(strlen($address) > 255) {
        return "Address cannot exceed 255 characters.";
    }
    return "";
}

// Handle profile update
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    if(isset($_POST['update_profile'])) {
        $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
        $address = mysqli_real_escape_string($conn, trim($_POST['address']));
        
        // Validate fields
        $contact_error = validateContactNumber($contact_number);
        $contact_exists_error = validateContactExists($contact_number, $conn, $employee_id);
        $address_error = validateAddress($address);
        
        // Combine contact errors
        if(empty($contact_error) && !empty($contact_exists_error)) {
            $contact_error = $contact_exists_error;
        }
        
        // Check if any validation errors exist
        if(empty($contact_error) && empty($address_error)) {
            // Update employee table
            $update_employee = "UPDATE employees SET contact_number = '$contact_number', address = '$address' 
                                WHERE employee_id = '$employee_id'";
            if(mysqli_query($conn, $update_employee)) {
                $success_message = "Profile updated successfully!";
            } else {
                $error_message = "Error updating profile: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Please correct the errors below.";
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
        
        if(!in_array(strtolower($filetype), $allowed)) {
            $error_message = "Only JPG, JPEG, PNG, GIF files are allowed!";
        } elseif($filesize > 2 * 1024 * 1024) {
            $error_message = "File size too large! Maximum 2MB allowed.";
        } else {
            $current_pic_query = "SELECT profile_picture FROM users WHERE user_id = '$user_id'";
            $current_pic_result = mysqli_query($conn, $current_pic_query);
            $current_user = mysqli_fetch_assoc($current_pic_result);
            $old_picture = $current_user['profile_picture'] ?? '';
            
            $new_filename = "user_" . $user_id . "_" . time() . "." . $filetype;
            $upload_path = "../profile_picture/" . $new_filename;
            
            if(!file_exists("../profile_picture/")) {
                mkdir("../profile_picture/", 0777, true);
            }
            
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
    <title>My Profile - Employee</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../Admin/profile_both.css">
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
            
            <div class="code-section">
                <span>Employee Code:</span><br>
                <strong><?php echo $user['employee_code']; ?></strong>
            </div>
            
            <div class="code-section">
                <span>Employment Date:</span><br>
                <strong><?php echo date('d-m-Y', strtotime($user['employment_date'])); ?></strong>
            </div>
        </div>
        
        <!-- Profile Main Content -->
        <div class="profile-main">
            <!-- Personal Information -->
            <div class="section-title">Personal Information</div>
            <form method="POST" action="" id="profileForm">
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
                        <label>Contact Number <span class="required">*</span></label>
                        <input type="text" name="contact_number" id="contactNumber" 
                               value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : htmlspecialchars($user['contact_number']); ?>" 
                               placeholder="0123456789 (10 or 11 digits)" required>
                        <div id="contactError" class="error-message"><?php echo $contact_error; ?></div>
                    </div>
                    <div class="form-group">
                        <label>Address <span class="required">*</span></label>
                        <textarea name="address" id="address" rows="4" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        <div id="addressError" class="error-message"><?php echo $address_error; ?></div>
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
    // Flags to track if phone is available
    let isPhoneAvailable = true;
    
    function checkAvailability(type, value) {
        const errorElement = document.getElementById(`${type}Error`);
        
        if (!value.trim()) {
            if (type === 'phone') {
                isPhoneAvailable = true;
                errorElement.innerHTML = '';
            }
            return;
        }

        const formData = new FormData();
        formData.append('check_availability', 'true');
        formData.append('type', type);
        formData.append('value', value);

        fetch(window.location.href, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (!data.valid_format) {
                errorElement.innerHTML = data.message;
                if (type === 'phone') isPhoneAvailable = false;
            } else if (data.exists) {
                errorElement.innerHTML = data.message;
                if (type === 'phone') isPhoneAvailable = false;
            } else {
                errorElement.innerHTML = '';
                if (type === 'phone') isPhoneAvailable = true;
            }
        })
        .catch(error => {
            console.error('Error checking availability:', error);
            if (type === 'phone') isPhoneAvailable = false;
        });
    }

    function validateContact() {
        const value = document.getElementById('contactNumber').value;
        const errorDiv = document.getElementById('contactError');
        
        if (!value) {
            errorDiv.innerHTML = "Contact number is required.";
            isPhoneAvailable = false;
            return false;
        } else if (!/^[0-9]{10,11}$/.test(value)) {
            errorDiv.innerHTML = "Contact number must contain only numbers (10 or 11 digits). No spaces & special characters allowed.";
            isPhoneAvailable = false;
            return false;
        } else {
            errorDiv.innerHTML = "";
            isPhoneAvailable = true;
            checkAvailability('phone', value);
            return true;
        }
    }

    function validateAddress() {
        const value = document.getElementById('address').value;
        const errorDiv = document.getElementById('addressError');
        
        if (!value) {
            errorDiv.innerHTML = "Address is required.";
            return false;
        } else if (value.length < 10) {
            errorDiv.innerHTML = "Please enter a complete address (at least 10 characters).";
            return false;
        } else if (value.length > 255) {
            errorDiv.innerHTML = "Address cannot exceed 255 characters.";
            return false;
        } else {
            errorDiv.innerHTML = "";
            return true;
        }
    }

    // Debounce function for phone
    let phoneTimeout;
    
    document.getElementById('contactNumber').addEventListener('input', function() {
        clearTimeout(phoneTimeout);
        phoneTimeout = setTimeout(validateContact, 500);
    });
    
    document.getElementById('address').addEventListener('input', validateAddress);

    // Form submission validation for profile update
    document.getElementById('profileForm').addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessages = [];
        
        if (!validateContact()) {
            isValid = false;
            errorMessages.push("- Phone number is invalid or missing");
        }
        if (!validateAddress()) {
            isValid = false;
            errorMessages.push("- Address is invalid or missing");
        }
        
        // Check if phone already exists
        if (!isPhoneAvailable) {
            isValid = false;
            errorMessages.push("- Phone number already exists. Please use a different number.");
        }
        
        if (!isValid) {
            e.preventDefault();
            alert("Please correct the following errors:\n" + errorMessages.join("\n"));
        }
    });
    
    // Profile Picture Upload Validation
    document.getElementById('profilePicInput').addEventListener('change', function(e) {
        const file = this.files[0];
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        const maxSize = 2 * 1024 * 1024; // 2MB
        
        if(file) {
            if(!allowedTypes.includes(file.type)) {
                alert('Invalid file type! Please upload JPG, JPEG, PNG, or GIF files only.');
                this.value = '';
                return;
            }
            
            if(file.size > maxSize) {
                alert('File too large! Maximum size is 2MB.');
                this.value = '';
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('profileImage').src = e.target.result;
            };
            reader.readAsDataURL(file);
            
            const form = document.getElementById('profilePicForm');
            form.submit();
        }
    });
    
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(function(icon) {
        icon.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            
            if (input) {
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
                input.focus();
            }
        });
    });
    
    // Password validation
    const currentPassword = document.getElementById('currentPassword');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const changePasswordBtn = document.getElementById('changePasswordBtn');
    const passwordMatchError = document.getElementById('passwordMatchError');
    
    const reqLength = document.getElementById('req-length');
    const reqUpper = document.getElementById('req-upper');
    const reqLower = document.getElementById('req-lower');
    const reqNumber = document.getElementById('req-number');
    const reqSpecial = document.getElementById('req-special');
    const reqSpace = document.getElementById('req-space');
    
    function checkPasswordRequirements(password) {
        let allValid = true;
        
        if(password.length >= 8) {
            reqLength.classList.add('valid');
        } else {
            reqLength.classList.remove('valid');
            allValid = false;
        }
        
        if(/[A-Z]/.test(password)) {
            reqUpper.classList.add('valid');
        } else {
            reqUpper.classList.remove('valid');
            allValid = false;
        }
        
        if(/[a-z]/.test(password)) {
            reqLower.classList.add('valid');
        } else {
            reqLower.classList.remove('valid');
            allValid = false;
        }
        
        if(/[0-9]/.test(password)) {
            reqNumber.classList.add('valid');
        } else {
            reqNumber.classList.remove('valid');
            allValid = false;
        }
        
        if(/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            reqSpecial.classList.add('valid');
        } else {
            reqSpecial.classList.remove('valid');
            allValid = false;
        }
        
        if(!/\s/.test(password)) {
            reqSpace.classList.add('valid');
        } else {
            reqSpace.classList.remove('valid');
            allValid = false;
        }
        
        return allValid;
    }
    
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
    
    function checkCurrentPassword() {
        return currentPassword.value.trim() !== '';
    }
    
    function validateAllFields() {
        const hasCurrentPassword = checkCurrentPassword();
        const isPasswordValid = checkPasswordRequirements(newPassword.value);
        const doPasswordsMatch = checkPasswordsMatch();
        const hasNewPassword = newPassword.value.trim() !== '';
        
        if(hasCurrentPassword && hasNewPassword && isPasswordValid && doPasswordsMatch) {
            changePasswordBtn.disabled = false;
            changePasswordBtn.classList.add('active');
        } else {
            changePasswordBtn.disabled = true;
            changePasswordBtn.classList.remove('active');
        }
    }
    
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
    
    validateAllFields();
    
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