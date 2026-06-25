<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login_admin.php");
    exit();
}

$today = date('Y-m-d');
$success_message = '';
$error_message = '';

// Handle individual status update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $record_id = mysqli_real_escape_string($conn, $_POST['record_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $reason = isset($_POST['reason']) && trim($_POST['reason']) !== '' ? mysqli_real_escape_string($conn, trim($_POST['reason'])) : null;
    
    if($reason === null) {
        $update_query = "UPDATE attendance_records 
                         SET status = '$new_status', notes = NULL 
                         WHERE record_id = '$record_id'";
    } else {
        $update_query = "UPDATE attendance_records 
                         SET status = '$new_status', notes = '$reason' 
                         WHERE record_id = '$record_id'";
    }
    
    if(mysqli_query($conn, $update_query)) {
        $success_message = "Status updated to " . ucfirst($new_status) . "!";
    } else {
        $error_message = "Update failed: " . mysqli_error($conn);
    }
}

// Handle bulk update
if($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['bulk_update'])) {
    $updates = $_POST['updates'] ?? [];
    $updated_count = 0;
    
    foreach($updates as $record_id => $data) {
        if(!isset($data['selected']) || $data['selected'] != 1) continue;
        
        $new_status = mysqli_real_escape_string($conn, $data['status']);
        $reason = isset($data['reason']) && trim($data['reason']) !== '' ? mysqli_real_escape_string($conn, trim($data['reason'])) : null;
        
        if($reason === null) {
            $update_query = "UPDATE attendance_records 
                            SET status = '$new_status', notes = NULL 
                            WHERE record_id = '$record_id'";
        } else {
            $update_query = "UPDATE attendance_records 
                            SET status = '$new_status', notes = '$reason' 
                            WHERE record_id = '$record_id'";
        }
        
        if(mysqli_query($conn, $update_query)) {
            $updated_count++;
        }
    }
    
    if($updated_count > 0) {
        $success_message = "$updated_count record(s) updated successfully!";
    } else {
        $error_message = "No records were selected for update.";
    }
}

// Display success messages(bulk update)
if(!empty($success_message) && isset($_POST['bulk_update'])) {
    echo "<script>alert('" . addslashes($success_message) . "'); window.location.href = 'update_absent_status.php';</script>";
    exit();
}

if(!empty($error_message) && isset($_POST['bulk_update'])) {
    echo "<script>alert('" . addslashes($error_message) . "'); window.location.href = 'update_absent_status.php';</script>";
    exit();
}

// Display success messages(individual update)
if(!empty($success_message) && isset($_POST['update_status'])) {
    echo "<script>alert('" . addslashes($success_message) . "'); window.location.href = 'update_absent_status.php';</script>";
    exit();
}

if(!empty($error_message) && isset($_POST['update_status'])) {
    echo "<script>alert('" . addslashes($error_message) . "'); window.location.href = 'update_absent_status.php';</script>";
    exit();
}

// Get all absent employees for today where notes is NULL
$absent_query = "SELECT a.*, u.name, e.employee_code, e.department, e.position 
                 FROM attendance_records a
                 JOIN employees e ON a.employee_id = e.employee_id
                 JOIN users u ON e.user_id = u.user_id
                 WHERE a.record_date = '$today' 
                 AND a.status = 'absent' 
                 AND a.notes IS NULL
                 ORDER BY a.session, u.name";
$absent_result = mysqli_query($conn, $absent_query);

// Get summary counts
$morning_absent = 0;
$afternoon_absent = 0;
$absent_records = [];

while($row = mysqli_fetch_assoc($absent_result)) {
    $absent_records[] = $row;
    if($row['session'] == 'morning') {
        $morning_absent++;
    } else {
        $afternoon_absent++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Absent Employee Status</title>
    <link rel="stylesheet" href="update_absent_status.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            Update Absent Employee Status
        </div>
        <div class="card-body">            
            <div class="summary-box">
                <div class="summary-item">
                    <h3 style="color: #007bff;"><?php echo $morning_absent; ?></h3>
                    <p>Morning Session</p>
                </div>
                <div class="summary-item">
                    <h3 style="color: #fd7e14;"><?php echo $afternoon_absent; ?></h3>
                    <p>Afternoon Session</p>
                </div>
                <div class="summary-item-total">
                    <h3 style="color: #dc3545;"><?php echo count($absent_records); ?></h3>
                    <p>Total Absent</p>
                </div>
            </div>
            
            <?php if(count($absent_records) > 0): ?>
            <form method="POST" action="" id="bulkForm">
                <div class="bulk-actions">
                    <button type="submit" name="bulk_update" class="btn-update">Update Selected</button>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="checkbox-col">
                                    <input type="checkbox" id="selectAllCheckbox" class="select-all-checkbox" title="Select All">
                                </th>
                                <th>Emp Code</th>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Position</th>
                                <th>Session</th>
                                <th>Current Status</th>
                                <th>New Status</th>
                                <th>Reason / Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($absent_records as $record): 
                                $session_class = ($record['session'] == 'morning') ? 'session-morning' : 'session-afternoon';
                                $session_name = ($record['session'] == 'morning') ? 'Morning (9-12)' : 'Afternoon (1-6)';
                            ?>
                            <tr>
                                <td class="checkbox-col">
                                    <input type="checkbox" name="updates[<?php echo $record['record_id']; ?>][selected]" class="record-checkbox" value="1">
                                </td>
                                <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($record['name']); ?></td>
                                <td><?php echo htmlspecialchars($record['department']); ?></td>
                                <td><?php echo htmlspecialchars($record['position']); ?></td>
                                <td><span class="<?php echo $session_class; ?>"><?php echo $session_name; ?></span></td>
                                <td><span class="status-badge">Absent</span></td>
                                <td>
                                    <select name="updates[<?php echo $record['record_id']; ?>][status]" class="status-select">
                                        <option value="half_day">Half Day</option>
                                        <option value="holiday">Holiday</option>
                                        <option value="absent" selected>Absent</option>
                                    </select>
                                </td>
                                <td>
                                    <textarea name="updates[<?php echo $record['record_id']; ?>][reason]" class="reason-input" rows="2" placeholder="Optional: Enter reason..."></textarea>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>
            <?php else: ?>
                <div class="no-data">
                    <div style="font-size: 48px; margin-bottom: 15px;">✅</div>
                    No absent employees found for today!<br>
                    <small style="color: #999;">All employees have checked in or been marked.</small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    const headerCheckbox = document.getElementById('selectAllCheckbox');
    const recordCheckboxes = document.querySelectorAll('.record-checkbox');

    function updateSelectAll() {
        if (!headerCheckbox) return;
        const allChecked = Array.from(recordCheckboxes).length > 0 && 
                          Array.from(recordCheckboxes).every(cb => cb.checked);
        headerCheckbox.checked = allChecked;
    }

    function selectAll(checked) {
        recordCheckboxes.forEach(cb => {
            cb.checked = checked;
        });
        updateSelectAll();
    }

    if (headerCheckbox) {
        headerCheckbox.addEventListener('change', function() {
            selectAll(this.checked);
        });
    }

    recordCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectAll);
    });
    
    // Add checkboxes animation 
    document.querySelectorAll('.record-checkbox, .select-all-checkbox').forEach(checkbox => {
        checkbox.addEventListener('click', function(e) {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 100);
        });
    });
</script>
</body>
</html>