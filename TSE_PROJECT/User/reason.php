<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

include("../db_connection.php");
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// ============================================================
// 1. Get employee_id from user_id
// ============================================================
$employeeSql = "SELECT employee_id FROM employees WHERE user_id = ?";
$employeeStmt = $conn->prepare($employeeSql);
$employeeStmt->bind_param("i", $user_id);
$employeeStmt->execute();
$employeeResult = $employeeStmt->get_result();

if ($employeeResult->num_rows == 0) {
    die("Employee record not found for this user.");
}

$employeeRow = $employeeResult->fetch_assoc();
$employee_id = $employeeRow['employee_id'];
$employeeStmt->close();

$today = date("Y-m-d");
$displayDate = date("d/m/Y");

// Get current time
$currentTime = date("H:i:s");
$workingHourStart = '09:00:00';
$workingHourEnd = '18:00:00';

// Check if current time is within working hours
$currentTimestamp = strtotime($currentTime);
$startTimestamp = strtotime($workingHourStart);
$endTimestamp = strtotime($workingHourEnd);

$isWorkingHour = ($currentTimestamp >= $startTimestamp && $currentTimestamp <= $endTimestamp);

// ============================================================
// 2. Check today's attendance records from database
// ============================================================
$todayRecords = [];
$hasAnyRecord = false;
$isPresent = false;
$abnormalRecords = [];

$sql = "
    SELECT 
        record_id,
        session,
        status,
        notes,
        check_in_time,
        check_out_time
    FROM attendance_records
    WHERE employee_id = ?
      AND record_date = ?
    ORDER BY FIELD(session, 'morning', 'afternoon')
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $todayRecords[] = $row;
    $hasAnyRecord = true;
    
    // Check if any record has status 'present'
    if ($row['status'] == 'present') {
        $isPresent = true;
    }
    
    // Collect abnormal records (late, absent, early_leave)
    if (in_array($row['status'], ['late', 'absent', 'early_leave'])) {
        $abnormalRecords[] = $row;
    }
}
$stmt->close();

// ============================================================
// 3. Determine what to display
// ============================================================
$outsideWorkingHours = !$isWorkingHour;
$hasAbnormalRecords = (count($abnormalRecords) > 0);

// Debug info (you can remove this after testing)
// echo "<!-- Debug: user_id=$user_id, employee_id=$employee_id, today=$today, records=" . count($todayRecords) . ", isPresent=" . ($isPresent ? 'true' : 'false') . ", abnormal=" . count($abnormalRecords) . " -->";

// ============================================================
// 4. Handle form submission
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $reasons = $_POST['reason'] ?? [];
    $allUpdated = true;
    $updatedCount = 0;

    foreach ($reasons as $record_id => $reason) {
        $reason = trim($reason);
        if ($reason === '') {
            $allUpdated = false;
            $message = "Please provide reasons for all abnormal records.";
            break;
        }

        $updateSql = "
            UPDATE attendance_records
            SET notes = ?
            WHERE record_id = ?
            AND employee_id = ?
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sii", $reason, $record_id, $employee_id);
        if ($updateStmt->execute()) {
            $updatedCount++;
        }
        $updateStmt->close();
    }

    if ($allUpdated && $updatedCount > 0) {
        $message = "All reasons submitted successfully (" . $updatedCount . " record" . ($updatedCount > 1 ? "s" : "") . " updated).";
        
        // Refresh abnormal records after update
        $abnormalRecords = [];
        $isPresent = false;
        $hasAnyRecord = false;
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $employee_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $hasAnyRecord = true;
            if ($row['status'] == 'present') {
                $isPresent = true;
            }
            if (in_array($row['status'], ['late', 'absent', 'early_leave'])) {
                $abnormalRecords[] = $row;
            }
        }
        $stmt->close();
        $hasAbnormalRecords = (count($abnormalRecords) > 0);
    } elseif ($allUpdated && $updatedCount == 0) {
        $message = "No changes were made.";
    }
}

$statusLabels = [
    'late'        => 'Late',
    'absent'      => 'Absent',
    'early_leave' => 'Early Leave'
];
$sessionLabels = [
    'morning'   => 'Morning (9:00 AM - 12:00 PM)',
    'afternoon' => 'Afternoon (1:00 PM - 6:00 PM)'
];

// Session time ranges for display
$sessionTimes = [
    'morning' => '9:00 AM - 12:00 PM',
    'afternoon' => '1:00 PM - 6:00 PM'
];
?>

<style>
.reason-container {
    max-width: 700px;
    margin: 80px auto 40px auto;
}
.reason-card {
    background: #fff;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.reason-title {
    text-align: center;
    font-weight: 700;
    margin-bottom: 30px;
    color: #333;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #444;
}
.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #dcdfe6;
    border-radius: 10px;
    transition: all 0.3s ease;
}
.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.15);
    outline: none;
}
textarea.form-control {
    min-height: 90px;
    resize: vertical;
}
.status-box {
    padding: 14px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
}
.status-late {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.status-absent {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}
.status-early_leave {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.submit-btn {
    width: 100%;
    border: none;
    border-radius: 12px;
    padding: 14px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: 0.3s;
}
.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102,126,234,0.3);
}
.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.message {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 600;
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}
.message.error {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}
.message.info {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.message.warning {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.info-box {
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #10b981;
}
.info-box.success {
    background: #d1fae5;
    color: #065f46;
    border-color: #10b981;
}
.info-box.empty {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.info-box.absent {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}
.info-box.late {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.info-box.early {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.record-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fafafa;
}
.record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.record-header span {
    font-weight: 600;
    font-size: 15px;
}
.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}
.badge-late { background: #fef3c7; color: #92400e; }
.badge-absent { background: #fee2e2; color: #991b1b; }
.badge-early_leave { background: #dbeafe; color: #1e40af; }
.working-hours-info {
    text-align: center;
    padding: 10px;
    background: #e8f4fd;
    border-radius: 10px;
    margin-bottom: 20px;
    color: #1e40af;
    font-size: 14px;
}
.session-time {
    font-size: 12px;
    color: #666;
    font-weight: normal;
}
.record-summary {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    margin-top: 8px;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 8px;
}
.record-summary-item {
    font-size: 13px;
    color: #555;
}
.record-summary-item strong {
    color: #333;
}
.status-icon {
    font-size: 18px;
    margin-right: 5px;
}
</style>

<div class="main-container">
    <div class="reason-container">
        <div class="reason-card">
            <h2 class="reason-title">📋 Attendance Reason Submission</h2>

            <?php if ($message !== ""): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <!-- Working Hours Info -->
            <div class="working-hours-info">
                Working Hours: 9:00 AM - 6:00 PM (Current time: <?php echo date('h:i A'); ?>)
            </div>

            <!-- Date Display (Read-only) -->
            <div class="form-group">
                <label>Date</label>
                <input type="text" class="form-control" value="<?php echo $displayDate; ?>" readonly>
            </div>

            <?php
            // ============================================================
            // DISPLAY LOGIC
            // ============================================================
            
            // CASE 1: Outside working hours (before 9:00 or after 18:00)
            if ($outsideWorkingHours) {
                ?>
                <div class="info-box empty">
                    No attendance records found for today.
                    <br><small>(Attendance can only be viewed during working hours: 9:00 AM - 6:00 PM)</small>
                </div>
                <?php
            }
            // CASE 2: Within working hours
            else {
                // CASE 2a: Employee is marked Present (has at least one 'present' record)
                if ($isPresent && !$hasAbnormalRecords) {
                    ?>
                    <div class="info-box success">
                        You are marked <strong>Present</strong> for all sessions today. No reason is required.
                    </div>
                    <?php
                }
                // CASE 2b: Employee has both present and abnormal records (mixed)
                elseif ($isPresent && $hasAbnormalRecords) {
                    ?>
                    <div class="info-box" style="background:#fff3cd; color:#856404; border-color:#ffc107; margin-bottom:20px;">
                        ⚠️ You have <strong><?php echo count($abnormalRecords); ?></strong> abnormal attendance record(s) today.
                        Please provide a reason for each abnormal session.
                    </div>
                    
                    <!-- Show summary of all sessions -->
                    <div class="record-summary">
                        <?php foreach ($todayRecords as $record): 
                            $session = $record['session'];
                            $status = $record['status'];
                            $statusDisplay = ucfirst(str_replace('_', ' ', $status));
                            $icon = ($status == 'present') ? '✅' : '⚠️';
                        ?>
                            <div class="record-summary-item">
                                <?php echo $icon; ?> <strong><?php echo ucfirst($session); ?>:</strong> 
                                <?php echo $statusDisplay; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <form method="POST" style="margin-top: 20px;">
                        <?php foreach ($abnormalRecords as $record): 
                            $recordId = $record['record_id'];
                            $session = $record['session'];
                            $status = $record['status'];
                            $existingNote = $record['notes'] ?? '';
                            
                            // Display appropriate info box based on status
                            $statusClass = '';
                            $statusIcon = '';
                            $statusMessage = '';
                            if ($status == 'absent') {
                                $statusClass = 'absent';
                                $statusMessage = 'You are marked as <strong>Absent</strong> for this session. Please provide a reason.';
                            } elseif ($status == 'late') {
                                $statusClass = 'late';
                                $statusMessage = 'You are marked as <strong>Late</strong> for this session. Please provide a reason.';
                            } elseif ($status == 'early_leave') {
                                $statusClass = 'early';
                                $statusMessage = 'You are marked as <strong>Early Leave</strong> for this session. Please provide a reason.';
                            }
                        ?>
                            <div class="record-item">
                                <div class="record-header">
                                    <span>
                                        <?php echo $sessionLabels[$session] ?? ucfirst($session); ?>
                                        <span class="session-time">(<?php echo $sessionTimes[$session] ?? ''; ?>)</span>
                                    </span>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <?php echo $statusIcon; ?> <?php echo $statusLabels[$status] ?? $status; ?>
                                    </span>
                                </div>
                                <div class="info-box <?php echo $statusClass; ?>" style="margin-bottom: 15px;">
                                    <?php echo $statusMessage; ?>
                                </div>
                                
                                <?php if (!empty($existingNote)): ?>
                                    <div style="margin-bottom: 10px; padding: 8px 12px; background: #e8f5e9; border-radius: 8px; color: #2e7d32; font-size: 13px;">
                                        Previously submitted: "<?php echo htmlspecialchars($existingNote); ?>"
                                    </div>
                                <?php endif; ?>
                                
                                <textarea 
                                    name="reason[<?php echo $recordId; ?>]" 
                                    class="form-control" 
                                    placeholder="Please explain why you were <?php echo strtolower($statusLabels[$status] ?? $status); ?> for the <?php echo $session; ?> session..."
                                    rows="3"
                                ><?php echo htmlspecialchars($existingNote); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="submit" class="submit-btn">
                            Submit Reason<?php echo count($abnormalRecords) > 1 ? 's' : ''; ?>
                        </button>
                    </form>
                    <?php
                }
                // CASE 2c: Only abnormal records (no present records)
                elseif ($hasAbnormalRecords && !$isPresent) {
                    ?>
                    <div class="info-box" style="background:#fef3c7; color:#92400e; border-color:#f59e0b; margin-bottom:20px;">
                        ⚠️ You have <strong><?php echo count($abnormalRecords); ?></strong> abnormal attendance record(s) today. 
                        Please provide a reason for each.
                    </div>
                    
                    <form method="POST">
                        <?php foreach ($abnormalRecords as $record): 
                            $recordId = $record['record_id'];
                            $session = $record['session'];
                            $status = $record['status'];
                            $existingNote = $record['notes'] ?? '';
                            
                            // Display appropriate info box based on status
                            $statusClass = '';
                            $statusIcon = '';
                            $statusMessage = '';
                            if ($status == 'absent') {
                                $statusClass = 'absent';
                                $statusMessage = 'You are marked as <strong>Absent</strong> for this session. Please provide a reason.';
                            } elseif ($status == 'late') {
                                $statusClass = 'late';
                                $statusMessage = 'You are marked as <strong>Late</strong> for this session. Please provide a reason.';
                            } elseif ($status == 'early_leave') {
                                $statusClass = 'early';
                                $statusMessage = 'You are marked as <strong>Early Leave</strong> for this session. Please provide a reason.';
                            }
                        ?>
                            <div class="record-item">
                                <div class="record-header">
                                    <span>
                                        <?php echo $sessionLabels[$session] ?? ucfirst($session); ?>
                                        <span class="session-time">(<?php echo $sessionTimes[$session] ?? ''; ?>)</span>
                                    </span>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <?php echo $statusIcon; ?> <?php echo $statusLabels[$status] ?? $status; ?>
                                    </span>
                                </div>
                                <div class="info-box <?php echo $statusClass; ?>" style="margin-bottom: 15px;">
                                    <?php echo $statusMessage; ?>
                                </div>
                                
                                <?php if (!empty($existingNote)): ?>
                                    <div style="margin-bottom: 10px; padding: 8px 12px; background: #e8f5e9; border-radius: 8px; color: #2e7d32; font-size: 13px;">
                                        Previously submitted: "<?php echo htmlspecialchars($existingNote); ?>"
                                    </div>
                                <?php endif; ?>
                                
                                <textarea 
                                    name="reason[<?php echo $recordId; ?>]" 
                                    class="form-control" 
                                    placeholder="Please explain why you were <?php echo strtolower($statusLabels[$status] ?? $status); ?> for the <?php echo $session; ?> session..."
                                    rows="3"
                                ><?php echo htmlspecialchars($existingNote); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="submit" class="submit-btn">
                            Submit Reason<?php echo count($abnormalRecords) > 1 ? 's' : ''; ?>
                        </button>
                    </form>
                    <?php
                }
                // CASE 2d: Within working hours but NO records at all
                else {
                    ?>
                    <div class="info-box empty">
                        No attendance records found for today.
                    </div>
                    <?php
                }
            }
            ?>
            
        </div>
    </div>
</div>

</body>
</html>