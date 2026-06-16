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

// Handle AJAX requests for real-time validation
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['check_availability'])) {
    $type = $_POST['type'];
    $value = trim($_POST['value']);
    $exists = false;
    $is_valid_format = true;
    $error_message = '';

    if ($type === 'email') {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $is_valid_format = false;
            $error_message = "Invalid email format";
        } else {
            $sql = "SELECT email FROM users WHERE email = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            
            // Error message for duplicate email
            if ($exists) {
                $error_message = "This email address is already registered.";
            }
        }
    } elseif ($type === 'phone') {
        // Remove special characters for checking
        if (!preg_match("/^[0-9]{10,11}$/", $value)) {
            $is_valid_format = false;
            $error_message = "Phone must contain only numbers (10 or 11 digits)";
        } else {
            $sql = "SELECT contact_number FROM employees WHERE contact_number = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $value);
            $stmt->execute();
            $stmt->store_result();
            $exists = $stmt->num_rows > 0;
            $stmt->close();
            
            // Error message for duplicate phone
            if ($exists) {
                $error_message = "This contact number is already registered.";
            }
        }
    }

    echo json_encode(['exists' => $exists, 'valid_format' => $is_valid_format, 'message' => $error_message]);
    exit();
}

$error = '';
$success = '';
$generated_password = '';
$auto_employee_code = generateEmployeeCode($conn);

// Store validation errors for each field
$name_error = '';
$email_error = '';
$contact_error = '';
$department_error = '';
$position_error = '';
$date_error = '';
$address_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['check_availability'])) {
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $employee_code = mysqli_real_escape_string($conn, $_POST['employee_code']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, trim($_POST['position']));
    $employment_date = mysqli_real_escape_string($conn, $_POST['employment_date']);
    $contact_number = mysqli_real_escape_string($conn, trim($_POST['contact_number']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    
    // Validation functions
    function validateName($name) {
        if(empty($name)) {
            return "Full name is required.";
        }
        if(!preg_match("/^[a-zA-Z\s]+$/", $name)) {
            return "Full name can only contain letters and spaces.";
        }
        if(strlen($name) < 3) {
            return "Full name must be at least 3 characters long.";
        }
        if(strlen($name) > 50) {
            return "Full name cannot exceed 50 characters.";
        }
        return "";
    }
    
    function validateEmailExists($email, $conn) {
        $sql = "SELECT email FROM users WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if($exists) {
            return "This email address is already registered.";
        }
        return "";
    }
    
    function validateContactExists($contact, $conn) {
        $sql = "SELECT contact_number FROM employees WHERE contact_number = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $contact);
        $stmt->execute();
        $stmt->store_result();
        $exists = $stmt->num_rows > 0;
        $stmt->close();
        if($exists) {
            return "This contact number is already registered.";
        }
        return "";
    }
    
    function validateContactNumber($contact) {
        if(empty($contact)) {
            return "Contact number is required.";
        }
        // only digits, no special characters (e.g., 0112223333)
        if(!preg_match("/^[0-9]{10,11}$/", $contact)) {
            return "Contact number must contain only numbers (10 or 11 digits). No spaces, hyphens, or + signs allowed.";
        }
        return "";
    }
    
    function validateDepartment($department) {
        if(empty($department)) {
            return "Please select a department.";
        }
        return "";
    }
    
    function validatePosition($position) {
        if(empty($position)) {
            return "Position is required.";
        }
        // Allow letters, spaces, hyphens, forward slash, and numbers
        if(!preg_match("/^[a-zA-Z0-9\s\-\/]+$/", $position)) {
            return "Position can only contain letters, numbers, spaces, hyphens, and forward slash (/).";
        }
        if(strlen($position) < 2) {
            return "Position must be at least 2 characters long.";
        }
        if(strlen($position) > 50) {
            return "Position cannot exceed 50 characters.";
        }
        return "";
    }
    
    function validateEmploymentDate($date) {
        if(empty($date)) {
            return "Employment date is required.";
        }
        $today = date('Y-m-d');
        if($date > $today) {
            return "Employment date cannot be in the future.";
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
    
    // Validate all fields
    $name_error = validateName($name);
    $email_error = validateEmailExists($email, $conn);
    $contact_error = validateContactNumber($contact_number);
    $contact_exists_error = validateContactExists($contact_number, $conn);
    $department_error = validateDepartment($department);
    $position_error = validatePosition($position);
    $date_error = validateEmploymentDate($employment_date);
    $address_error = validateAddress($address);
    
    // Combine contact errors
    if(empty($contact_error) && !empty($contact_exists_error)) {
        $contact_error = $contact_exists_error;
    }
    
    // Check email format
    if(empty($email_error)) {
        if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email_error = "Please enter a valid email address (e.g., email@domain.com).";
        }
    }
    
    // Check if any validation errors exist
    if(empty($name_error) && empty($email_error) && empty($contact_error) && 
       empty($department_error) && empty($position_error) && empty($date_error) && 
       empty($address_error)) {
        
        // Generate random password
        $generated_password = generateRandomPassword(8);
        
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
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = 'panzhixin7256@gmail.com';
                    $mail->Password = 'hfhy trka fwrs grzt';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;
                    $mail->CharSet = 'UTF-8';
                    $mail->setFrom('panzhixin7256@gmail.com', 'Locker Tech Attendance System');
                    $mail->addAddress($email, $name);
                    
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
                                <p style='color: #856404; margin: 0;'><strong>Important:</strong> Please login and change your password immediately.</p>
                            </div>
                            <div style='text-align: center; margin-top: 20px;'>
                                <a href='http://localhost/TSE_PROJECT/user/login.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Login to Your Account</a>
                            </div>
                            <hr style='margin: 20px 0;'>
                            <p style='color: #999; font-size: 12px; text-align: center;'>This is an automated message. Please do not reply.</p>
                        </div>
                    ";
                    
                    $mail->send();
                    
                    // Show success message and redirect
                    echo "<script>
                        alert('Employee added successfully! A welcome email with password has been sent to {$email}');
                        window.location.href = 'employee_list.php';
                    </script>";
                    exit();
                } catch (Exception $e) {
                    echo "<script>
                        alert('Employee added successfully but failed to send email.\\nError: " . addslashes($mail->ErrorInfo) . "');
                        window.location.href = 'employee_list.php';
                    </script>";
                    exit();
                }
            } else {
                $error = "Error adding employee: " . mysqli_error($conn);
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
            <a href="employee_list.php" class="btn-back">← Back</a>
            <h5>Add New Employee</h5>
            <button type="button" class="btn-clear" onclick="resetForm()">Clear</button>
        </div>
        <div class="card-body">
            <?php if($error): ?>
                <div class="alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" id="employeeForm">
                <div class="form-layout">
                    <div class="form-left">
                        <div class="form-group">
                            <label>Full Name <span class="required">*</span></label>
                            <input type="text" name="name" id="fullName" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                                   placeholder="Enter full name (letters and spaces only)" required>
                            <div id="nameError" class="error-message"><?php echo $name_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Employee Code <span class="required">*</span></label>
                            <input type="text" name="employee_code" value="<?php echo $auto_employee_code; ?>" readonly>
                        </div>
                        
                        <div class="form-group">
                            <label>Contact Number <span class="required">*</span></label>
                            <input type="text" name="contact_number" id="contactNumber" 
                                   value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" 
                                   placeholder="0123456789 (10 or 11 digits)" required>
                            <div id="contactError" class="error-message"><?php echo $contact_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" id="email" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                                   placeholder="email@domain.com" required>
                            <div id="emailError" class="error-message"><?php echo $email_error; ?></div>
                        </div>
                    </div>
                    
                    <div class="form-right">
                        <div class="form-group">
                            <label>Department <span class="required">*</span></label>
                            <select name="department" id="department" required>
                                <option value="" disabled <?php echo (!isset($_POST['department']) || empty($_POST['department'])) ? 'selected' : ''; ?>>--- Select Department ---</option>
                                <option value="IT Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                <option value="HR Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'HR Department') ? 'selected' : ''; ?>>HR Department</option>
                                <option value="Finance Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Finance Department') ? 'selected' : ''; ?>>Finance Department</option>
                                <option value="Marketing Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Marketing Department') ? 'selected' : ''; ?>>Marketing Department</option>
                                <option value="Sales Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Sales Department') ? 'selected' : ''; ?>>Sales Department</option>
                                <option value="Operations Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Operations Department') ? 'selected' : ''; ?>>Operations Department</option>
                                <option value="Customer Service" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="Administration" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                            <div id="departmentError" class="error-message"><?php echo $department_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <input type="text" name="position" id="position" 
                                value="<?php echo isset($_POST['position']) ? htmlspecialchars($_POST['position']) : ''; ?>" 
                                placeholder="e.g., Software Engineer III or UI/UX Designer" required>
                            <div id="positionError" class="error-message"><?php echo $position_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Employment Date <span class="required">*</span></label>
                            <input type="date" name="employment_date" id="employmentDate" 
                                   value="<?php echo isset($_POST['employment_date']) ? $_POST['employment_date'] : $today_date; ?>" 
                                   max="<?php echo $today_date; ?>" required>
                            <div id="dateError" class="error-message"><?php echo $date_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Address <span class="required">*</span></label>
                            <textarea name="address" id="address" rows="2" 
                                      placeholder="Enter complete address (min 10 characters)" required><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            <div id="addressError" class="error-message"><?php echo $address_error; ?></div>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn-submit">Add Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const todayDate = '<?php echo $today_date; ?>';
    
    // Flags to track if email and phone are available
    let isEmailAvailable = true;
    let isPhoneAvailable = true;
    
    function checkAvailability(type, value) {
        const errorElement = document.getElementById(`${type}Error`);
        
        if (!value.trim()) {
            if (type === 'email') {
                isEmailAvailable = true;
                errorElement.innerHTML = '';
            } else if (type === 'phone') {
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
                if (type === 'email') isEmailAvailable = false;
                if (type === 'phone') isPhoneAvailable = false;
            } else if (data.exists) {
                errorElement.innerHTML = `${type === 'email' ? 'Email' : 'Phone number'} already exists. Please use a different one.`;
                if (type === 'email') isEmailAvailable = false;
                if (type === 'phone') isPhoneAvailable = false;
            } else {
                errorElement.innerHTML = '';
                if (type === 'email') isEmailAvailable = true;
                if (type === 'phone') isPhoneAvailable = true;
            }
        })
        .catch(error => {
            console.error('Error checking availability:', error);
            if (type === 'email') isEmailAvailable = false;
            if (type === 'phone') isPhoneAvailable = false;
        });
    }

    function validateFullName() {
        const value = document.getElementById('fullName').value;
        const errorDiv = document.getElementById('nameError');
        
        if (!value) {
            errorDiv.innerHTML = "Full name is required.";
            return false;
        } else if (!/^[a-zA-Z\s]+$/.test(value)) {
            errorDiv.innerHTML = "Full name can only contain letters and spaces.";
            return false;
        } else if (value.length < 3) {
            errorDiv.innerHTML = "Full name must be at least 3 characters.";
            return false;
        } else if (value.length > 50) {
            errorDiv.innerHTML = "Full name cannot exceed 50 characters.";
            return false;
        } else {
            errorDiv.innerHTML = "";
            return true;
        }
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

    function validateEmailField() {
        const value = document.getElementById('email').value;
        const errorDiv = document.getElementById('emailError');
        
        if (!value) {
            errorDiv.innerHTML = "Email address is required.";
            isEmailAvailable = false;
            return false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            errorDiv.innerHTML = "Please enter a valid email address (e.g., email@domain.com).";
            isEmailAvailable = false;
            return false;
        } else {
            errorDiv.innerHTML = "";
            isEmailAvailable = true;
            checkAvailability('email', value);
            return true;
        }
    }

    function validateDepartment() {
        const value = document.getElementById('department').value;
        const errorDiv = document.getElementById('departmentError');
        
        if (!value) {
            errorDiv.innerHTML = "Please select a department.";
            return false;
        } else {
            errorDiv.innerHTML = "";
            return true;
        }
    }

    function validatePosition() {
        const value = document.getElementById('position').value;
        const errorDiv = document.getElementById('positionError');
        
        if (!value) {
            errorDiv.innerHTML = "Position is required.";
            return false;
        } else if (!/^[a-zA-Z0-9\s\-\/]+$/.test(value)) {
            errorDiv.innerHTML = "Position can only contain letters, numbers, spaces, -, and /.";
            return false;
        } else if (value.length < 2) {
            errorDiv.innerHTML = "Position must be at least 2 characters.";
            return false;
        } else if (value.length > 50) {
            errorDiv.innerHTML = "Position cannot exceed 50 characters.";
            return false;
        } else {
            errorDiv.innerHTML = "";
            return true;
        }
    }

    function validateEmploymentDate() {
        const value = document.getElementById('employmentDate').value;
        const errorDiv = document.getElementById('dateError');
        
        if (!value) {
            errorDiv.innerHTML = "Employment date is required.";
            return false;
        } else if (value > todayDate) {
            errorDiv.innerHTML = "Employment date cannot be in the future.";
            return false;
        } else {
            errorDiv.innerHTML = "";
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

    // Debounce function for email and phone
    let emailTimeout;
    let phoneTimeout;
    
    document.getElementById('fullName').addEventListener('input', validateFullName);
    document.getElementById('contactNumber').addEventListener('input', function() {
        clearTimeout(phoneTimeout);
        phoneTimeout = setTimeout(validateContact, 500);
    });
    document.getElementById('email').addEventListener('input', function() {
        clearTimeout(emailTimeout);
        emailTimeout = setTimeout(validateEmailField, 500);
    });
    document.getElementById('department').addEventListener('change', validateDepartment);
    document.getElementById('position').addEventListener('input', validatePosition);
    document.getElementById('employmentDate').addEventListener('change', validateEmploymentDate);
    document.getElementById('address').addEventListener('input', validateAddress);

    // Form submission validation
    document.getElementById('employeeForm').addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessages = [];
        
        if (!validateFullName()) {
            isValid = false;
            errorMessages.push("- Full name is invalid or missing");
        }
        if (!validateContact()) {
            isValid = false;
            errorMessages.push("- Phone number is invalid or missing");
        }
        if (!validateEmailField()) {
            isValid = false;
            errorMessages.push("- Email is invalid or missing");
        }
        if (!validateDepartment()) {
            isValid = false;
            errorMessages.push("- Department not selected");
        }
        if (!validatePosition()) {
            isValid = false;
            errorMessages.push("- Position is invalid or missing");
        }
        if (!validateEmploymentDate()) {
            isValid = false;
            errorMessages.push("- Employment date is invalid");
        }
        if (!validateAddress()) {
            isValid = false;
            errorMessages.push("- Address is invalid or missing");
        }
        
        // Check if email or phone already exists
        if (!isEmailAvailable) {
            isValid = false;
            errorMessages.push("- Email already exists. Please use a different email.");
        }
        
        if (!isPhoneAvailable) {
            isValid = false;
            errorMessages.push("- Phone number already exists. Please use a different number.");
        }
        
        if (!isValid) {
            e.preventDefault();
            alert("Please correct the following errors:\n" + errorMessages.join("\n"));
        }
    });

    function resetForm() {
        document.getElementById('employeeForm').reset();
        const errorDivs = document.querySelectorAll('.error-message');
        errorDivs.forEach(div => div.innerHTML = '');
        isEmailAvailable = true;
        isPhoneAvailable = true;
        document.getElementById('department').value = '';
        document.getElementById('employmentDate').value = todayDate;
    }
</script>
</body>
</html>