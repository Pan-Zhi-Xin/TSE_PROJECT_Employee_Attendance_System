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
    $reason = isset($_POST['reason']) ? mysqli_real_escape_string($conn, $_POST['reason']) : '';
    
    $update_query = "UPDATE attendance_records 
                     SET status = '$new_status', notes = '$reason' 
                     WHERE record_id = '$record_id'";
    
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
        $reason = isset($data['reason']) ? mysqli_real_escape_string($conn, $data['reason']) : '';
        
        $update_query = "UPDATE attendance_records 
                         SET status = '$new_status', notes = '$reason' 
                         WHERE record_id = '$record_id'";
        
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

// Get all absent employees for today (both sessions)
$absent_query = "SELECT a.*, u.name, e.employee_code, e.department, e.position 
                 FROM attendance_records a
                 JOIN employees e ON a.employee_id = e.employee_id
                 JOIN users u ON e.user_id = u.user_id
                 WHERE a.record_date = '$today' 
                 AND (a.status = 'absent' OR a.status = '')
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
    <title>Update Absent Status</title>
    <link rel="stylesheet" href="update_absent_status.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            Update Absent Employee Status
        </div>
        <div class="card-body">            
            <?php if($success_message): ?>
                <div class="alert-success">
                    ✓ <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert-error">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="summary-box">
                <div class="summary-item">
                    <h3 style="color: #007bff;"><?php echo $morning_absent; ?></h3>
                    <p>Morning Session</p>
                </div>
                <div class="summary-item">
                    <h3 style="color: #fd7e14;"><?php echo $afternoon_absent; ?></h3>
                    <p>Afternoon Session</p>
                </div>
                <div class="summary-item">
                    <h3 style="color: #dc3545;"><?php echo count($absent_records); ?></h3>
                    <p>Total Absent</p>
                </div>
            </div>
            
            <?php if(count($absent_records) > 0): ?>
            <form method="POST" action="" id="bulkForm">
                <div class="bulk-actions">
                    <div class="select-all">
                        <input type="checkbox" id="selectAllCheckbox">
                        <label for="selectAllCheckbox">Select All Records</label>
                    </div>
                    <div>
                        <button type="submit" name="bulk_update" class="btn-update">Update</button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="checkbox-col"></th>
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
                                <td>
                                    <input type="hidden" name="record_id" value="<?php echo $record['record_id']; ?>">
                                    <input type="hidden" name="session" value="<?php echo $record['session']; ?>">
                                    <input type="hidden" name="new_status" id="new_status_<?php echo $record['record_id']; ?>">
                                    <input type="hidden" name="reason" id="reason_<?php echo $record['record_id']; ?>">
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
        const allChecked = Array.from(recordCheckboxes).every(cb => cb.checked);
        if(headerCheckbox) headerCheckbox.checked = allChecked;
    }
    
    function selectAll(checked) {
        recordCheckboxes.forEach(cb => {
            cb.checked = checked;
        });
        updateSelectAll();
    }
    
    if(headerCheckbox) {
        headerCheckbox.addEventListener('change', function() {
            selectAll(this.checked);
        });
    }
    
    recordCheckboxes.forEach(cb => {
        cb.addEventListener('change', updateSelectAll);
    });
    
    function prepareIndividualUpdate(btn) {
        const row = btn.closest('tr');
        const statusSelect = row.querySelector('.status-select');
        const reasonTextarea = row.querySelector('.reason-input');
        const recordId = row.querySelector('input[name="record_id"]').value;
        
        const newStatusInput = document.getElementById('new_status_' + recordId);
        const reasonInput = document.getElementById('reason_' + recordId);
        
        if(newStatusInput) newStatusInput.value = statusSelect.value;
        if(reasonInput) reasonInput.value = reasonTextarea.value;
        
        const checkbox = row.querySelector('.record-checkbox');
        if(checkbox) checkbox.checked = true;
        
        document.querySelectorAll('.record-checkbox').forEach(cb => {
            if(cb.closest('tr') !== row) {
                cb.checked = false;
            }
        });
        
        updateSelectAll();
        
        // Submit the form
        document.getElementById('bulkForm').submit();
    }
</script>
</body>
</html>