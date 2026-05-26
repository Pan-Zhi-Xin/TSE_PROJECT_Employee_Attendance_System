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
    <title>Update Absent Status - Admin Dashboard</title>
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
            max-width: 1300px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .card-header {
            background: #dc3545;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            font-size: 16px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .summary-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid #e0e0e0;
        }
        
        .summary-item {
            text-align: center;
            padding: 10px 25px;
            background: white;
            border-radius: 8px;
            min-width: 150px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .summary-item h3 {
            font-size: 32px;
            margin-bottom: 5px;
        }
        
        .summary-item p {
            color: #666;
            font-size: 13px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            font-size: 14px;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .btn-bulk {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        
        .btn-bulk:hover {
            background: #0069d9;
        }
        
        .btn-update {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-update:hover {
            background: #218838;
        }
        
        .bulk-actions {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            border: 1px solid #e0e0e0;
        }
        
        .select-all {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .select-all label {
            cursor: pointer;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .data-table th {
            background: #343a40;
            color: white;
            padding: 12px 10px;
            text-align: left;
            font-weight: 600;
        }
        
        .data-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .session-morning {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .session-afternoon {
            display: inline-block;
            background: #fd7e14;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            background: #dc3545;
            color: white;
            padding: 3px 10px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        select.status-select {
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            width: 110px;
            background: white;
        }
        
        textarea.reason-input {
            width: 100%;
            min-width: 180px;
            padding: 6px 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 12px;
            resize: vertical;
            font-family: inherit;
        }
        
        .text-muted {
            color: #999;
            font-size: 10px;
            margin-top: 3px;
        }
        
        .checkbox-col {
            text-align: center;
            width: 40px;
        }
        
        .checkbox-col input {
            cursor: pointer;
            width: 16px;
            height: 16px;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            📝 Update Absent Employee Status
        </div>
        <div class="card-body">
            <a href="dashboard_admin.php" class="btn-back">← Back to Dashboard</a>
            
            <?php if($success_message): ?>
                <div class="alert-success">
                    ✓ <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if($error_message): ?>
                <div class="alert-error">
                    ⚠ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="summary-box">
                <div class="summary-item">
                    <h3 style="color: #007bff;"><?php echo $morning_absent; ?></h3>
                    <p>🌅 Morning Session</p>
                </div>
                <div class="summary-item">
                    <h3 style="color: #fd7e14;"><?php echo $afternoon_absent; ?></h3>
                    <p>🌙 Afternoon Session</p>
                </div>
                <div class="summary-item">
                    <h3 style="color: #dc3545;"><?php echo count($absent_records); ?></h3>
                    <p>📊 Total Absent Records</p>
                </div>
            </div>
            
            <?php if(count($absent_records) > 0): ?>
            <form method="POST" action="" id="bulkForm">
                <div class="bulk-actions">
                    <div class="select-all">
                        <input type="checkbox" id="selectAllCheckbox">
                        <label for="selectAllCheckbox">✓ Select All Records</label>
                    </div>
                    <div>
                        <button type="submit" name="bulk_update" class="btn-bulk">📋 Update Selected Records</button>
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