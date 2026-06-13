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

$department_error = '';
$position_error = '';

// Validation functions
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
    if(!preg_match("/^[a-zA-Z\s\-]+$/", $position)) {
        return "Position can only contain letters, spaces, and hyphens.";
    }
    if(strlen($position) < 2) {
        return "Position must be at least 2 characters long.";
    }
    if(strlen($position) > 50) {
        return "Position cannot exceed 50 characters.";
    }
    return "";
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, trim($_POST['position']));
    
    // Validate fields
    $department_error = validateDepartment($department);
    $position_error = validatePosition($position);
    
    // Check if any validation errors exist
    if(empty($department_error) && empty($position_error)) {
        $update_employee = "UPDATE employees 
                            SET department = '$department', position = '$position' 
                            WHERE employee_id = '$employee_id'";
        
        if(mysqli_query($conn, $update_employee)) {
            $query = "SELECT u.*, e.* FROM users u 
                      JOIN employees e ON u.user_id = e.user_id 
                      WHERE e.employee_id = '$employee_id'";
            $result = mysqli_query($conn, $query);
            $employee = mysqli_fetch_assoc($result);
            
            // Show JavaScript alert and redirect
            echo "<script>
                    alert('Employee updated successfully!');
                    window.location.href = 'employee_list.php';
                  </script>";
            exit();
        } else {
            $error = "Error updating employee: " . mysqli_error($conn);
        }
    } else {
        $error = "Please correct the errors below.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee</title>
    <link rel="stylesheet" href="edit_employee.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <a href="employee_list.php" class="btn-back">← Back</a>
            <h5>Edit Employee</h5>
            <div style="width: 70px;"></div>
        </div>
        <div class="card-body">
            <?php if($error && empty($department_error) && empty($position_error)): ?>
                <div class="alert-danger">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="editEmployeeForm">
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
                            <select name="department" id="department" 
                                    class="<?php echo $department_error ? 'error-field' : ''; ?>">
                                <option value="" disabled <?php echo (empty($employee['department'])) ? 'selected' : ''; ?>>--- Select Department ---</option>
                                <option value="IT Department" <?php echo ($employee['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                <option value="HR Department" <?php echo ($employee['department'] == 'HR Department') ? 'selected' : ''; ?>>HR Department</option>
                                <option value="Finance Department" <?php echo ($employee['department'] == 'Finance Department') ? 'selected' : ''; ?>>Finance Department</option>
                                <option value="Marketing Department" <?php echo ($employee['department'] == 'Marketing Department') ? 'selected' : ''; ?>>Marketing Department</option>
                                <option value="Sales Department" <?php echo ($employee['department'] == 'Sales Department') ? 'selected' : ''; ?>>Sales Department</option>
                                <option value="Operations Department" <?php echo ($employee['department'] == 'Operations Department') ? 'selected' : ''; ?>>Operations Department</option>
                                <option value="Customer Service" <?php echo ($employee['department'] == 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
                                <option value="Administration" <?php echo ($employee['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                            </select>
                            <div id="departmentError" class="error-message"><?php echo $department_error; ?></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Position <span class="required">*</span></label>
                            <input type="text" name="position" id="position" 
                                   value="<?php echo htmlspecialchars($employee['position']); ?>" 
                                   placeholder="e.g., Software Engineer"
                                   class="<?php echo $position_error ? 'error-field' : ''; ?>">
                            <div id="positionError" class="error-message"><?php echo $position_error; ?></div>
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
                        Update Employee
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    let isDepartmentValid = <?php echo empty($department_error) ? 'true' : 'false'; ?>;
    let isPositionValid = <?php echo empty($position_error) ? 'true' : 'false'; ?>;
    
    function validateDepartment() {
        const value = document.getElementById('department').value;
        const errorDiv = document.getElementById('departmentError');
        
        if (!value) {
            errorDiv.innerHTML = "Please select a department.";
            document.getElementById('department').classList.add('error-field');
            isDepartmentValid = false;
            return false;
        } else {
            errorDiv.innerHTML = "";
            document.getElementById('department').classList.remove('error-field');
            isDepartmentValid = true;
            return true;
        }
    }
    
    function validatePosition() {
        const value = document.getElementById('position').value.trim();
        const errorDiv = document.getElementById('positionError');
        
        if (!value) {
            errorDiv.innerHTML = "Position is required.";
            document.getElementById('position').classList.add('error-field');
            isPositionValid = false;
            return false;
        } else if (!/^[a-zA-Z\s\-]+$/.test(value)) {
            errorDiv.innerHTML = "Position can only contain letters, spaces, and hyphens.";
            document.getElementById('position').classList.add('error-field');
            isPositionValid = false;
            return false;
        } else if (value.length < 2) {
            errorDiv.innerHTML = "Position must be at least 2 characters.";
            document.getElementById('position').classList.add('error-field');
            isPositionValid = false;
            return false;
        } else if (value.length > 50) {
            errorDiv.innerHTML = "Position cannot exceed 50 characters.";
            document.getElementById('position').classList.add('error-field');
            isPositionValid = false;
            return false;
        } else {
            errorDiv.innerHTML = "";
            document.getElementById('position').classList.remove('error-field');
            isPositionValid = true;
            return true;
        }
    }
    
    // Real-time validation
    document.getElementById('department').addEventListener('change', validateDepartment);
    document.getElementById('department').addEventListener('blur', validateDepartment);
    
    document.getElementById('position').addEventListener('input', validatePosition);
    document.getElementById('position').addEventListener('blur', validatePosition);
    
    // Form submission validation
    document.getElementById('editEmployeeForm').addEventListener('submit', function(e) {
        let isValid = true;
        let errorMessages = [];
        
        if (!validateDepartment()) {
            isValid = false;
            errorMessages.push("- Department not selected");
        }
        if (!validatePosition()) {
            isValid = false;
            errorMessages.push("- Position is invalid or missing");
        }
        
        if (!isValid) {
            e.preventDefault();
            alert("Please correct the following errors:\n" + errorMessages.join("\n"));
        }
    });
</script>
</body>
</html>