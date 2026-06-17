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

// 1. Get employee_id from user_id
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

// NEW: Check if it's before midnight (00:00 to 23:59:59)
// The form should be accessible until 11:59:59 PM
$isBeforeMidnight = ($currentTimestamp < strtotime('23:59:59'));

// 2. Check today's attendance records from database
$todayRecords = [];
$hasAnyRecord = false;
$isPresent = false;
$abnormalRecords = [];

// Define which statuses require a reason (only late, early_leave, late_early)
// Note: 'early_leave' in code should match 'left_early' in database
$reasonRequiredStatuses = ['late', 'left_early', 'late_early'];

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
    
    // Collect records that need a reason (late, left_early, late_early only)
    // EXCLUDING: absent, half_day, holiday
    if (in_array($row['status'], $reasonRequiredStatuses)) {
        $abnormalRecords[] = $row;
    }
}
$stmt->close();

// 3. Determine what to display
$hasAbnormalRecords = (count($abnormalRecords) > 0);

// CHECK if any abnormal record already has a reason (notes not empty)
$hasSubmittedReasons = false;
foreach ($abnormalRecords as $record) {
    if (!empty($record['notes'])) {
        $hasSubmittedReasons = true;
        break;
    }
}

// 4. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $reasons = $_POST['reason'] ?? [];
    $allUpdated = true;
    $updatedCount = 0;

    // Only process records that require a reason
    foreach ($reasons as $record_id => $reason) {
        $reason = trim($reason);
        if ($reason === '') {
            $allUpdated = false;
            $message = "Please provide reasons for all required records.";
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
            if (in_array($row['status'], $reasonRequiredStatuses)) {
                $abnormalRecords[] = $row;
            }
        }
        $stmt->close();
        $hasAbnormalRecords = (count($abnormalRecords) > 0);
        
        // Re-check if any abnormal record has a reason
        $hasSubmittedReasons = false;
        foreach ($abnormalRecords as $record) {
            if (!empty($record['notes'])) {
                $hasSubmittedReasons = true;
                break;
            }
        }
    } elseif ($allUpdated && $updatedCount == 0) {
        $message = "No changes were made.";
    }
}

// Status labels for display (matching database values)
$statusLabels = [
    'late'        => 'Late',
    'left_early'  => 'Early Leave',
    'late_early'  => 'Late & Early Leave',
    'absent'      => 'Absent',
    'half_day'    => 'Half Day',
    'holiday'     => 'Holiday',
    'present'     => 'Present'
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
.status-left_early {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.status-late_early {
    background: #fce4ec;
    color: #880e4f;
    border-color: #e91e63;
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
.info-box.left_early {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.info-box.late_early {
    background: #fce4ec;
    color: #880e4f;
    border-color: #e91e63;
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
.badge-left_early { background: #dbeafe; color: #1e40af; }
.badge-late_early { background: #fce4ec; color: #880e4f; }
.badge-absent { background: #fee2e2; color: #991b1b; }
.badge-half_day { background: #fef3c7; color: #92400e; }
.badge-holiday { background: #d1fae5; color: #065f46; }
.badge-present { background: #d1fae5; color: #065f46; }
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
.submitted-reason-box {
    padding: 12px 16px;
    background: #e8f5e9;
    border-radius: 10px;
    border-left: 4px solid #4caf50;
    margin-top: 10px;
}
.submitted-reason-box .label {
    font-weight: 600;
    color: #2e7d32;
    font-size: 13px;
    display: block;
    margin-bottom: 4px;
}
.submitted-reason-box .reason-text {
    color: #1b5e20;
    font-size: 15px;
}
.reason-required-badge {
    display: inline-block;
    background: #ff9800;
    color: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
.reason-submitted-badge {
    display: inline-block;
    background: #4caf50;
    color: white;
    padding: 2px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
}
</style>

<div class="main-container">
    <div class="reason-container">
        <div class="reason-card">
            <h2 class="reason-title">📋 Attendance Reason Submission</h2>

            <?php if ($message !== "" && $hasAbnormalRecords): ?>
                <div class="message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- Working Hours Info -->
            <div class="working-hours-info">
                Working Hours: 9:00 AM - 6:00 PM (Current time: <?php echo date('h:i A'); ?>)
                <br><small>Reasons can be submitted until 11:59 PM today</small>
            </div>

            <!-- Date Display (Read-only) -->
            <div class="form-group">
                <label>Date</label>
                <input type="text" class="form-control" value="<?php echo $displayDate; ?>" readonly>
            </div>

            <?php
            
            // CASE 1: Check if it's past midnight (00:00 of next day)
            $isEarlyMorning = ($currentTimestamp >= strtotime('00:00:00') && $currentTimestamp < strtotime('09:00:00'));
            
            if ($isEarlyMorning) {
                ?>
                <div class="info-box empty">
                    No attendance records found for today.
                    <br><small>(Attendance records are only available from 9:00 AM to 11:59 PM)</small>
                </div>
                <?php
            }
            // CASE 2: Within the day (9:00 AM to 11:59 PM)
            else {
                // Check if there are any records that need a reason (late, left_early, late_early)
                if ($hasAbnormalRecords) {
                    // Check if ALL abnormal records already have a reason submitted
                    $allHaveReasons = true;
                    foreach ($abnormalRecords as $record) {
                        if (empty($record['notes'])) {
                            $allHaveReasons = false;
                            break;
                        }
                    }
                    
                    if ($allHaveReasons) {
                        // ALL records have reasons - show only the submitted reasons
                        ?>
                        <div class="info-box success" style="margin-bottom:20px;">
                            All reasons have been submitted successfully.
                        </div>
                        
                        <!-- Show summary of all sessions -->
                        <div class="record-summary">
                            <?php foreach ($todayRecords as $record): 
                                $session = $record['session'];
                                $status = $record['status'];
                                $statusDisplay = ucfirst(str_replace('_', ' ', $status));
                                $needsReason = in_array($status, ['late', 'left_early', 'late_early']);
                                $icon = ($status == 'present' || $status == 'holiday') ? '✅' : ($needsReason ? '📝' : 'ℹ️');
                            ?>
                                <div class="record-summary-item">
                                    <?php echo $icon; ?> <strong><?php echo ucfirst($session); ?>:</strong> 
                                    <?php echo $statusDisplay; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php foreach ($abnormalRecords as $record): 
                            $session = $record['session'];
                            $status = $record['status'];
                            $existingNote = $record['notes'] ?? '';
                        ?>
                            <div class="record-item">
                                <div class="record-header">
                                    <span>
                                        <?php echo $sessionLabels[$session] ?? ucfirst($session); ?>
                                        <span class="session-time">(<?php echo $sessionTimes[$session] ?? ''; ?>)</span>
                                    </span>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <?php echo $statusLabels[$status] ?? $status; ?>
                                        <span class="reason-submitted-badge">✓ Reason Submitted</span>
                                    </span>
                                </div>
                                
                                <div class="submitted-reason-box">
                                    <span class="label">📝 Reason Provided:</span>
                                    <span class="reason-text"><?php echo htmlspecialchars($existingNote); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <?php
                    } else {
                        // Some records don't have reasons yet - show form
                        ?>
                        <div class="info-box" style="background:#fff3cd; color:#856404; border-color:#ffc107; margin-bottom:20px;">
                            You have <strong><?php echo count($abnormalRecords); ?></strong> attendance record(s) that require a reason.
                            Please provide a reason for each.
                        </div>
                        
                        <!-- Show summary of all sessions -->
                        <div class="record-summary">
                            <?php foreach ($todayRecords as $record): 
                                $session = $record['session'];
                                $status = $record['status'];
                                $statusDisplay = ucfirst(str_replace('_', ' ', $status));
                                $needsReason = in_array($status, ['late', 'left_early', 'late_early']);
                                $hasReason = !empty($record['notes']);
                                $icon = ($status == 'present' || $status == 'holiday') ? '✅' : ($needsReason ? ($hasReason ? '📝' : '⚠️') : 'ℹ️');
                            ?>
                                <div class="record-summary-item">
                                    <?php echo $icon; ?> <strong><?php echo ucfirst($session); ?>:</strong> 
                                    <?php echo $statusDisplay; ?>
                                    <?php if ($needsReason && !$hasReason): ?>
                                        <span style="color:#dc3545; font-size:11px;">(reason required)</span>
                                    <?php elseif ($needsReason && $hasReason): ?>
                                        <span style="color:#4caf50; font-size:11px;">(reason submitted)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <form method="POST" style="margin-top: 20px;">
                            <?php foreach ($abnormalRecords as $record): 
                                $recordId = $record['record_id'];
                                $session = $record['session'];
                                $status = $record['status'];
                                $existingNote = $record['notes'] ?? '';
                                
                                // Skip showing textarea if already has a reason
                                if (!empty($existingNote)) {
                                    // Show the submitted reason instead
                                    ?>
                                    <div class="record-item">
                                        <div class="record-header">
                                            <span>
                                                <?php echo $sessionLabels[$session] ?? ucfirst($session); ?>
                                                <span class="session-time">(<?php echo $sessionTimes[$session] ?? ''; ?>)</span>
                                            </span>
                                            <span class="badge badge-<?php echo $status; ?>">
                                                <?php echo $statusLabels[$status] ?? $status; ?>
                                                <span class="reason-submitted-badge">✓ Submitted</span>
                                            </span>
                                        </div>
                                        <div class="submitted-reason-box">
                                            <span class="label">📝 Reason Provided:</span>
                                            <span class="reason-text"><?php echo htmlspecialchars($existingNote); ?></span>
                                        </div>
                                    </div>
                                    <?php
                                    continue;
                                }
                                
                                // Display appropriate info box based on status
                                $statusClass = '';
                                $statusMessage = '';
                                if ($status == 'late') {
                                    $statusClass = 'late';
                                    $statusMessage = 'You are marked as <strong>Late</strong> for this session. Please provide a reason.';
                                } elseif ($status == 'left_early') {
                                    $statusClass = 'left_early';
                                    $statusMessage = 'You are marked as <strong>Early Leave</strong> for this session. Please provide a reason.';
                                } elseif ($status == 'late_early') {
                                    $statusClass = 'late_early';
                                    $statusMessage = 'You are marked as <strong>Late & Early Leave</strong> for this session. Please provide a reason.';
                                }
                            ?>
                                <div class="record-item">
                                    <div class="record-header">
                                        <span>
                                            <?php echo $sessionLabels[$session] ?? ucfirst($session); ?>
                                            <span class="session-time">(<?php echo $sessionTimes[$session] ?? ''; ?>)</span>
                                        </span>
                                        <span class="badge badge-<?php echo $status; ?>">
                                            <?php echo $statusLabels[$status] ?? $status; ?>
                                            <span class="reason-required-badge">⚠️ Reason Required</span>
                                        </span>
                                    </div>
                                    <div class="info-box <?php echo $statusClass; ?>" style="margin-bottom: 15px;">
                                        <?php echo $statusMessage; ?>
                                    </div>
                                    
                                    <textarea 
                                        name="reason[<?php echo $recordId; ?>]" 
                                        class="form-control" 
                                        placeholder="Please explain why you were <?php echo strtolower($statusLabels[$status] ?? $status); ?> for the <?php echo $session; ?> session..."
                                        rows="3"
                                        required
                                    ></textarea>
                                </div>
                            <?php endforeach; ?>
                            <button type="submit" name="submit" class="submit-btn">
                                Submit Reason<?php echo count(array_filter($abnormalRecords, function($r) { return empty($r['notes']); })) > 1 ? 's' : ''; ?>
                            </button>
                        </form>
                        <?php
                    }
                }
                // CASE 2b: No records that need a reason (all present, absent, holiday, half_day, or no records)
                else {
                    // Check if there are ANY records today
                    if ($hasAnyRecord) {

                        // Check whether ALL sessions are present
                        $allPresent = true;

                        foreach ($todayRecords as $record) {
                            if ($record['status'] !== 'present') {
                                $allPresent = false;
                                break;
                            }
                        }

                        if ($allPresent && count($todayRecords) >= 2) {
                            ?>
                            <div class="info-box success">
                                You are marked <strong>Present</strong> for all sessions today.
                                <br><small>No reason is required.</small>
                            </div>
                            <?php
  
                        } else {
                            // Records exist but none are 'present' - they might be absent, half_day, or holiday
                            ?>
                            <div class="info-box" style="background:#e3f2fd; color:#0d47a1; border-color:#1e88e5;">
                                <?php 
                                $statuses = array_unique(array_column($todayRecords, 'status'));
                                $statusList = array_map(function($s) use ($statusLabels) {
                                    return $statusLabels[$s] ?? $s;
                                }, $statuses);
                                ?>
                                Your attendance today: <strong><?php echo implode(', ', $statusList); ?></strong>.
                                <?php if (in_array('absent', $statuses) || in_array('half_day', $statuses)): ?>
                                    <br><span style="font-size:13px; font-weight:normal;">Note: Absent and Half Day statuses do not require a reason in this form.</span>
                                <?php endif; ?>
                            </div>
                            <?php
                            
                            // Show summary of all records
                            ?>
                            <div class="record-summary">
                                <?php foreach ($todayRecords as $record): 
                                    $session = $record['session'];
                                    $status = $record['status'];
                                    $statusDisplay = ucfirst(str_replace('_', ' ', $status));
                                    $icon = ($status == 'present' || $status == 'holiday') ? '✅' : 'ℹ️';
                                ?>
                                    <div class="record-summary-item">
                                        <?php echo $icon; ?> <strong><?php echo ucfirst($session); ?>:</strong> 
                                        <?php echo $statusDisplay; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php
                        }
                    } else {
                        ?>
                        <div class="info-box empty">
                            No attendance records found for today.
                            <br><small>If you have completed your attendance, the records will appear here.</small>
                        </div>
                        <?php
                    }
                }
            }
            ?>
            
        </div>
    </div>
</div>

</body>
</html>