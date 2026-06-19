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
$messageType = "";

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
$currentTimestamp = strtotime($currentTime);

// Define which statuses require a reason
$reasonRequiredStatuses = ['late', 'left_early', 'late_early'];

// 2. Check today's attendance records from database
$todayRecords = [];
$abnormalRecords = [];
$hasAnyRecord = false; 

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
    
    // Collect records that need a reason
    if (in_array($row['status'], $reasonRequiredStatuses)) {
        $abnormalRecords[] = $row;
    }
}
$stmt->close();

$hasAbnormalRecords = (count($abnormalRecords) > 0);

// 3. Handle form submission 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $reasons = $_POST['reason'] ?? [];
    
    // Log what was submitted
    error_log("=== FORM SUBMISSION ===");
    error_log("Reasons submitted: " . print_r($reasons, true));
    error_log("Employee ID: " . $employee_id);
    
    $allUpdated = true;
    $updatedCount = 0;
    $failedUpdates = [];
    
    // Process each submitted reason
    foreach ($reasons as $record_id => $reason) {
        $reason = trim($reason);
        
        // Log each update attempt
        error_log("Processing record_id: " . $record_id . " | Reason: " . $reason);
        
        if ($reason === '') {
            $allUpdated = false;
            $message = "Please provide reasons for all required records.";
            $messageType = "error";
            break;
        }
        
        // Verify the record belongs to this employee and session
        $verifySql = "
            SELECT record_id, session, status, notes 
            FROM attendance_records 
            WHERE record_id = ? 
            AND employee_id = ?
            AND record_date = ?
        ";
        $verifyStmt = $conn->prepare($verifySql);
        $verifyStmt->bind_param("sis", $record_id, $employee_id, $today);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        
        if ($verifyResult->num_rows === 0) {
            error_log("ERROR: Record not found - record_id: " . $record_id);
            $failedUpdates[] = "Record not found: " . $record_id;
            $allUpdated = false;
            $verifyStmt->close();
            continue;
        }
        
        $recordData = $verifyResult->fetch_assoc();
        error_log("Found record - Session: " . $recordData['session'] . " | Current notes: " . $recordData['notes']);
        $verifyStmt->close();
        
        // Perform the update
        $updateSql = "
            UPDATE attendance_records
            SET notes = ?
            WHERE record_id = ?
            AND employee_id = ?
            AND record_date = ?
        ";
        $updateStmt = $conn->prepare($updateSql);
        // record_id is VARCHAR(50), so use 's' not 'i'
        $updateStmt->bind_param("ssss", $reason, $record_id, $employee_id, $today);
        
        if ($updateStmt->execute()) {
            if ($updateStmt->affected_rows > 0) {
                $updatedCount++;
                error_log("SUCCESS: Updated record_id: " . $record_id . " with reason: " . $reason);
            } else {
                error_log("WARNING: No rows affected for record_id: " . $record_id);
            }
        } else {
            error_log("ERROR: Update failed - " . $updateStmt->error);
            $failedUpdates[] = "Update failed: " . $record_id;
            $allUpdated = false;
        }
        $updateStmt->close();
    }
    
    // Refresh data after updates
    if ($allUpdated && $updatedCount > 0) {
        $message = "Reasons submitted successfully! (" . $updatedCount . " record(s) updated)";
        $messageType = "success";
        
        // REFRESH ALL DATA
        $refreshSql = "
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
        $refreshStmt = $conn->prepare($refreshSql);
        $refreshStmt->bind_param("is", $employee_id, $today);
        $refreshStmt->execute();
        $refreshResult = $refreshStmt->get_result();
        
        $todayRecords = [];
        $abnormalRecords = [];
        $hasAnyRecord = false;  
        
        while ($row = $refreshResult->fetch_assoc()) {
            $todayRecords[] = $row;
            $hasAnyRecord = true;  
            if (in_array($row['status'], $reasonRequiredStatuses)) {
                $abnormalRecords[] = $row;
            }
        }
        $refreshStmt->close();
        $hasAbnormalRecords = (count($abnormalRecords) > 0);
        
        // Log the refreshed data
        error_log("=== REFRESHED DATA ===");
        error_log("Today Records: " . print_r($todayRecords, true));
        error_log("Abnormal Records: " . print_r($abnormalRecords, true));
        
    } elseif ($allUpdated && $updatedCount == 0) {
        $message = "No changes were made.";
        $messageType = "info";
    } elseif (!empty($failedUpdates)) {
        $message = "Some updates failed: " . implode(", ", $failedUpdates);
        $messageType = "error";
    }
}

// Status labels for display
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

$sessionTimes = [
    'morning' => '9:00 AM - 12:00 PM',
    'afternoon' => '1:00 PM - 6:00 PM'
];

// Display current abnormal records
error_log("=== CURRENT STATE ===");
error_log("Abnormal Records Count: " . count($abnormalRecords));
error_log("Has Any Record: " . ($hasAnyRecord ? 'Yes' : 'No'));
foreach ($abnormalRecords as $record) {
    error_log("Record: " . $record['record_id'] . " | Session: " . $record['session'] . " | Status: " . $record['status'] . " | Notes: " . ($record['notes'] ?? 'NULL'));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reason · LockerTech</title>
    <script src="https://kit.fontawesome.com/c2f7d169d6.js" crossorigin="anonymous"></script>
    <style>
        /* --- Reset and Base --- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Roboto, system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f0f4fc;
        }

        /* --- Background with blur --- */
        body::before {
            content: "";
            position: fixed;
            inset: 0;
            background: url('../reason_background.jpg') center / cover no-repeat fixed;
            filter: blur(8px);
            z-index: -2;
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.25);
            z-index: -1;
        }

        /* --- Main container (full-width, centered) --- */
        .container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 30px 20px;
            position: relative;
            z-index: 3;
            width: 100%;
            margin-top: 40px;
        }

        /* --- Card wrapper: form left, image right --- */
        .login-wrapper {
            display: flex;
            flex-wrap: wrap;
            max-width: 1100px;
            width: 100%;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(6px);
            border-radius: 36px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.30);
            transition: 0.3s;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        /* --- Left column (form) --- */
        .login-col {
            flex: 1 1 50%;
            padding: 48px 40px 40px 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(2px);
        }

        /* --- Right column (image) --- */
        .image-col {
            flex: 1 1 50%;
            background: #d9e2ef;
            position: relative;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .image-col img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .image-col .img-placeholder {
            width: 100%;
            height: 100%;
            display: none;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            background: linear-gradient(145deg, #4f6ef7, #7c8cf5);
            color: white;
            font-size: 22px;
            font-weight: 300;
            gap: 14px;
        }

        .image-col .overlay-text {
            position: absolute;
            bottom: 30px;
            left: 30px;
            color: white;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            background: rgba(0, 0, 0, 0.15);
            backdrop-filter: blur(4px);
            padding: 14px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 18px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .reason-container {
            max-width: 100%;
            margin: 0;
            width: 100%;
        }

        .reason-card {
            background: transparent;
            border-radius: 0;
            padding: 0;
            box-shadow: none;
            width: 100%;
        }

        .reason-title {
            font-size: 32px;
            font-weight: 700;
            color: #0b1e3a;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
            text-align: left;
        }

        .reason-title::after {
            display: block;
            font-size: 15px;
            font-weight: 400;
            color: #64748b;
            margin-top: 2px;
            letter-spacing: 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 14px;
            letter-spacing: 0.3px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #4f6ef7;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            font-size: 15px;
            transition: 0.25s;
            background: white;
            font-family: inherit;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #4f6ef7;
            box-shadow: 0 0 0 4px rgba(79, 110, 247, 0.12);
        }

        textarea.form-control {
            min-height: 90px;
            resize: vertical;
            padding: 14px 16px;
            border-radius: 16px;
        }

        /* Buttons match login */
        .submit-btn {
            width: 100%;
            padding: 16px;
            background: #4f6ef7;
            border: none;
            border-radius: 40px;
            color: white;
            font-weight: 700;
            font-size: 17px;
            letter-spacing: 0.5px;
            cursor: pointer;
            transition: 0.2s;
            margin-top: 8px;
            box-shadow: 0 8px 18px -6px rgba(79, 110, 247, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background: #3b56d9;
            transform: scale(1.01) translateY(-2px);
            box-shadow: 0 12px 24px -8px rgba(79, 110, 247, 0.5);
        }

        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Messages match login error style */
        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            border-left: 5px solid transparent;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border-color: #10b981;
        }

        .message.error {
            background: #fee9e7;
            color: #b91c1c;
            border-color: #dc2626;
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
            padding: 16px 18px;
            border-radius: 16px;
            font-weight: 500;
            border: 1.5px solid #e2e8f0;
            background: #f8fafc;
            color: #1e293b;
            margin-bottom: 16px;
            font-size: 15px;
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

        /* Record items */
        .record-item {
            border: 1.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px 20px;
            margin-bottom: 16px;
            background: white;
            transition: 0.2s;
        }

        .record-item:hover {
            border-color: #cbd5e1;
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
            gap: 8px;
        }

        .record-header span {
            font-weight: 600;
            font-size: 15px;
            color: #0b1e3a;
        }

        /* Badges */
        .badge {
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 13px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-late { background: #fef3c7; color: #92400e; }
        .badge-left_early { background: #dbeafe; color: #1e40af; }
        .badge-late_early { background: #fce4ec; color: #880e4f; }
        .badge-absent { background: #fee2e2; color: #991b1b; }
        .badge-half_day { background: #fef3c7; color: #92400e; }
        .badge-holiday { background: #d1fae5; color: #065f46; }
        .badge-present { background: #d1fae5; color: #065f46; }

        .reason-required-badge {
            display: inline-block;
            background: #ff9800;
            color: white;
            padding: 2px 12px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
        }

        .reason-submitted-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #1b5e20; 
            padding: 2px 12px;
            border-radius: 40px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 6px;
        }

        .working-hours-info {
            text-align: center;
            padding: 12px 16px;
            background: #e8f4fd;
            border-radius: 16px;
            margin-bottom: 20px;
            color: #1e40af;
            font-size: 14px;
            border: 1px solid #bfdbfe;
        }

        .working-hours-info small {
            display: block;
            font-weight: 400;
            color: #64748b;
            margin-top: 2px;
        }

        .record-summary {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 8px;
            padding: 14px 16px;
            background: #f8fafc;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }

        .record-summary-item {
            font-size: 14px;
            color: #1e293b;
            background: white;
            padding: 4px 14px;
            border-radius: 40px;
            border: 1px solid #e2e8f0;
        }

        .record-summary-item strong {
            color: #0b1e3a;
        }

        /* Submitted reason box */
        .submitted-reason-box {
            padding: 12px 16px;
            background: #e8f5e9;
            border-radius: 12px;
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

        /* Session time */
        .session-time {
            font-size: 12px;
            color: #64748b;
            font-weight: normal;
        }

        /* Status icon */
        .status-icon {
            font-size: 18px;
            margin-right: 5px;
        }

        /* --- Responsive --- */
        @media (max-width: 720px) {
            .login-wrapper {
                flex-direction: column;
                border-radius: 28px;
            }

            .login-col {
                padding: 32px 24px;
                flex: 1 1 auto;
            }

            .image-col {
                min-height: 200px;
                flex: 1 1 200px;
            }

            .image-col .overlay-text {
                bottom: 16px;
                left: 16px;
                font-size: 15px;
                padding: 10px 18px;
            }

            header {
                padding: 12px 20px;
            }
            .logo img {
                transform: translateX(0);
                height: 48px;
            }
            .home h2 {
                font-size: 15px;
            }

            .reason-title {
                font-size: 28px;
            }
            .reason-title::after {
                font-size: 14px;
            }
        }

        @media (max-width: 420px) {
            .login-col {
                padding: 24px 16px;
            }
            .reason-title {
                font-size: 24px;
            }
            .reason-title::after {
                font-size: 13px;
            }
            .record-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .record-summary {
                flex-direction: column;
                gap: 6px;
            }
        }
    </style>
</head>
<body>

<section class="container">
        <div class="login-wrapper">

            <!-- LEFT COLUMN: Reason Form -->
            <div class="login-col">
                <div class="frame">
                    <div class="section">
                        <h2 class="reason-title">📋 Attendance Reason</h2>
                        <p class="subtitle">Provide reasons for your attendance</p>
                    </div>

                    <?php if ($message !== "" && $hasAbnormalRecords): ?>
                        <div class="message <?php echo $messageType; ?>">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Working Hours Info -->
                    <div class="working-hours-info">
                        Working Hours: 9:00 AM - 6:00 PM (Current time: <?php echo date('h:i A'); ?>)
                        <br><small>Reasons can be submitted until 11:59 PM today</small>
                    </div>

                    <!-- Date Display-->
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="text" id="date" class="form-control" value="<?php echo $displayDate; ?>" readonly>
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
                                ?>                              
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
                                            </span>
                                            <span class="badge badge-<?php echo $status; ?>">
                                                <?php echo $statusLabels[$status] ?? $status; ?>
                                                <span class="reason-submitted-badge">Reason Submitted</span>
                                            </span>
                                        </div>
                                        
                                        <div class="submitted-reason-box">
                                            <span class="label">Reason Provided:</span>
                                            <span class="reason-text"><?php echo htmlspecialchars($existingNote); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <?php
                            } else {
                                // Some records don't have reasons yet - show form
                                ?>
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
                                                    </span>
                                                    <span class="badge badge-<?php echo $status; ?>">
                                                        <?php echo $statusLabels[$status] ?? $status; ?>
                                                        <span class="reason-submitted-badge">Submitted</span>
                                                    </span>
                                                </div>
                                                <div class="submitted-reason-box">
                                                    <span class="label">Reason Provided:</span>
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
                                            ><?php echo htmlspecialchars($existingNote); ?></textarea>
                                        </div>
                                    <?php endforeach; ?>
                                    <button type="submit" name="submit" class="submit-btn">
                                        <i class="fas fa-paper-plane"></i> Submit Reason<?php echo count(array_filter($abnormalRecords, function($r) { return empty($r['notes']); })) > 1 ? 's' : ''; ?>
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

            <!-- RIGHT COLUMN: Image -->
            <div class="image-col">
                <img src="../reason.png" alt="Attendance reason visual"
                     onerror="this.style.display='none'; this.parentElement.querySelector('.img-placeholder').style.display='flex';">
                <div class="img-placeholder">
                    <i class="fas fa-clipboard-list" style="font-size: 54px; opacity:0.7;"></i>
                    <span style="background:rgba(255,255,255,0.1); padding:8px 22px; border-radius: 60px;">LockerTech</span>
                </div>
            </div>
        </div>
    </section>

    <script>
        (function() {
            // Handle image fallback
            const img = document.querySelector('.image-col img');
            if (img) {
                img.addEventListener('error', function() {
                    this.style.display = 'none';
                    const placeholder = this.parentElement.querySelector('.img-placeholder');
                    if (placeholder) {
                        placeholder.style.display = 'flex';
                    }
                });
            }
        })();
    </script>
</body>
</html>