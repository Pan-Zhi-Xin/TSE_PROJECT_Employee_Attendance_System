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

$today = date('Y-m-d');
$employee_id = $_SESSION['employee_id'];
$work_start_time_morning = '09:00:00';
$work_start_time_afternoon = '13:00:00';
$work_end_time_morning = '12:00:00';
$work_end_time_afternoon = '18:00:00';
$current_time = date('H:i:s');

// ========== AUTO-CREATE ABSENT RECORDS FOR THIS EMPLOYEE ==========
function autoCreateAbsentForEmployee($conn, $employee_id, $today_date, $current_time, $morning_end, $afternoon_end) {
    $morning_created = 0;
    $afternoon_created = 0;
    
    $create_morning_absent = ($current_time > $morning_end);
    $create_afternoon_absent = ($current_time > $afternoon_end);
    
    if (!$create_morning_absent && !$create_afternoon_absent) {
        return ['morning' => 0, 'afternoon' => 0];
    }
    
    if ($create_morning_absent) {
        $check_morning = "SELECT * FROM attendance_records 
                          WHERE employee_id = '$employee_id' AND record_date = '$today_date' AND session = 'morning'";
        $morning_result = mysqli_query($conn, $check_morning);
        if(mysqli_num_rows($morning_result) == 0) {
            $record_id = 'MNG' . date('Ymd') . str_pad($employee_id, 3, '0', STR_PAD_LEFT);
            $insert_query = "INSERT INTO attendance_records (record_id, employee_id, record_date, session, status) 
                             VALUES ('$record_id', '$employee_id', '$today_date', 'morning', 'absent')";
            if(mysqli_query($conn, $insert_query)) $morning_created++;
        }
    }
    
    if ($create_afternoon_absent) {
        $check_afternoon = "SELECT * FROM attendance_records 
                            WHERE employee_id = '$employee_id' AND record_date = '$today_date' AND session = 'afternoon'";
        $afternoon_result = mysqli_query($conn, $check_afternoon);
        if(mysqli_num_rows($afternoon_result) == 0) {
            $record_id = 'AFT' . date('Ymd') . str_pad($employee_id, 3, '0', STR_PAD_LEFT);
            $insert_query = "INSERT INTO attendance_records (record_id, employee_id, record_date, session, status) 
                             VALUES ('$record_id', '$employee_id', '$today_date', 'afternoon', 'absent')";
            if(mysqli_query($conn, $insert_query)) $afternoon_created++;
        }
    }
    
    return ['morning' => $morning_created, 'afternoon' => $afternoon_created];
}

// Run auto-absent creation for this specific employee
$created = autoCreateAbsentForEmployee($conn, $employee_id, $today, $current_time, $work_end_time_morning, $work_end_time_afternoon);

// Helper functions
function calculateWorkingHours($check_in_time, $check_out_time) {
    if($check_in_time && $check_out_time) {
        return round((strtotime($check_out_time) - strtotime($check_in_time)) / 3600, 2);
    }
    return 0;
}

function calculateLateMinutes($check_in_time, $session) {
    global $work_start_time_morning, $work_start_time_afternoon;
    if(!$check_in_time) return 0;
    $work_start = ($session == 'morning') ? $work_start_time_morning : $work_start_time_afternoon;
    $check_in_only = date('H:i:s', strtotime($check_in_time));
    if($check_in_only > $work_start) {
        return round((strtotime($check_in_only) - strtotime($work_start)) / 60);
    }
    return 0;
}

function getSessionStatus($check_in_time, $check_out_time, $session) {
    global $work_start_time_morning, $work_start_time_afternoon, $work_end_time_morning, $work_end_time_afternoon;
    
    if(!$check_in_time) return 'absent';
    
    $work_start = ($session == 'morning') ? $work_start_time_morning : $work_start_time_afternoon;
    $work_end = ($session == 'morning') ? $work_end_time_morning : $work_end_time_afternoon;
    
    $check_in_only = date('H:i:s', strtotime($check_in_time));
    $is_late = ($check_in_only > $work_start);
    
    if($check_out_time) {
        $check_out_only = date('H:i:s', strtotime($check_out_time));
        $is_early = ($check_out_only < $work_end);
        
        if($is_late && $is_early) {
            return 'late_early';
        }
        if($is_late) {
            return 'late';
        }
        if($is_early) {
            return 'left_early';
        }
        return 'present';
    }
    
    return $is_late ? 'late' : 'present';
}

// Get today's attendance records (both sessions) - read session from database
$query = "SELECT * FROM attendance_records 
          WHERE employee_id = '$employee_id' AND record_date = '$today' 
          ORDER BY session ASC";
$result = mysqli_query($conn, $query);
$today_records = [];

while($row = mysqli_fetch_assoc($result)) {
    // Use the session column from database instead of guessing
    $session_name = $row['session']; // 'morning' or 'afternoon'
    $row['session'] = $session_name;
    $row['calculated_working_hours'] = calculateWorkingHours($row['check_in_time'], $row['check_out_time']);
    $row['calculated_late_minutes'] = calculateLateMinutes($row['check_in_time'], $session_name);
    $row['calculated_status'] = getSessionStatus($row['check_in_time'], $row['check_out_time'], $session_name);
    $today_records[] = $row;
}

$has_open_session = false;
foreach($today_records as $record) {
    if($record['check_in_time'] && !$record['check_out_time']) {
        $has_open_session = true;
        break;
    }
}

$can_check_in = count($today_records) < 2;
$can_check_out = $has_open_session;

// Get monthly summary
$current_month = date('m');
$current_year = date('Y');
$start_date = "$current_year-$current_month-01";
$end_date = date('Y-m-t');

$summary_query = "SELECT a.* FROM attendance_records a
                  WHERE a.employee_id = '$employee_id' 
                  AND a.record_date BETWEEN '$start_date' AND '$end_date'";
$summary_result = mysqli_query($conn, $summary_query);

$summary = [
    'total_days' => 0,
    'morning_present' => 0, 'morning_late' => 0, 'morning_early' => 0, 'morning_late_early' => 0,
    'afternoon_present' => 0, 'afternoon_late' => 0, 'afternoon_early' => 0, 'afternoon_late_early' => 0,
    'total_hours' => 0, 'total_late_minutes' => 0
];

$unique_dates = [];
while($row = mysqli_fetch_assoc($summary_result)) {
    $date = $row['record_date'];
    if(!in_array($date, $unique_dates)) {
        $unique_dates[] = $date;
    }
    
    // Use session column from database
    $session = $row['session']; // 'morning' or 'afternoon'
    $status = getSessionStatus($row['check_in_time'], $row['check_out_time'], $session);
    
    if($session == 'morning') {
        if($status == 'present') $summary['morning_present']++;
        elseif($status == 'late') $summary['morning_late']++;
        elseif($status == 'early_leave') $summary['morning_early']++;
        elseif($status == 'late_early') $summary['morning_late_early']++;
    } else {
        if($status == 'present') $summary['afternoon_present']++;
        elseif($status == 'late') $summary['afternoon_late']++;
        elseif($status == 'early_leave') $summary['afternoon_early']++;
        elseif($status == 'late_early') $summary['afternoon_late_early']++;
    }
    
    $summary['total_hours'] += calculateWorkingHours($row['check_in_time'], $row['check_out_time']);
    $summary['total_late_minutes'] += calculateLateMinutes($row['check_in_time'], $session);
}
$summary['total_days'] = count($unique_dates);

// Get message from session
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($_SESSION['error']) ? $_SESSION['error'] : '';
$checkin_time = isset($_SESSION['checkin_time']) ? $_SESSION['checkin_time'] : '';

unset($_SESSION['success']);
unset($_SESSION['error']);
unset($_SESSION['checkin_time']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Attendance System</title>
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
        
        .message-popup {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 10000;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            min-width: 320px;
            text-align: center;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translate(-50%, -60%); }
            to { opacity: 1; transform: translate(-50%, -50%); }
        }
        
        .popup-success {
            border-top: 5px solid #28a745;
        }
        
        .popup-error {
            border-top: 5px solid #dc3545;
        }
        
        .popup-content {
            padding: 25px;
        }
        
        .popup-content i {
            font-size: 50px;
            margin-bottom: 10px;
        }
        
        .popup-success i {
            color: #28a745;
        }
        
        .popup-error i {
            color: #dc3545;
        }
        
        .popup-content h4 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .popup-content p {
            color: #666;
            margin-bottom: 15px;
        }
        
        .popup-content button {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 25px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .close-btn {
            position: absolute;
            top: 10px;
            right: 15px;
            font-size: 20px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(3px);
            z-index: 20000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-box {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 420px;
            overflow: hidden;
            animation: slideUp 0.3s ease;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .modal-header {
            background: #dc3545;
            padding: 20px;
        }
        
        .modal-header h3 {
            color: white;
            margin: 0;
            text-align: center;
            font-weight: bold;
        }
        
        .modal-body {
            padding: 25px;
            text-align: center;
        }
        
        .info-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
            text-align: left;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .info-value {
            color: #007bff;
            font-weight: bold;
        }
        
        .warning-text {
            color: #dc3545;
            font-size: 13px;
            margin-top: 10px;
        }
        
        .modal-footer {
            padding: 0 25px 25px 25px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .btn-confirm {
            flex: 1;
            padding: 12px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .btn-cancel {
            flex: 1;
            padding: 12px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .time-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .time-card h3 {
            color: #666;
            font-size: 16px;
            margin-bottom: 10px;
        }
        
        .time-card h1 {
            color: #007bff;
            font-size: 48px;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .time-card p {
            color: #999;
            font-size: 14px;
        }
        
        .status-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .status-card h4 {
            text-align: center;
            margin-bottom: 15px;
            color: #333;
        }
        
        .current-status {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: bold;
        }
        
        .status-checked-in {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .status-checked-out {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .status-row {
            display: flex;
            justify-content: space-around;
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        
        .session-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .session-title {
            font-weight: bold;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .session-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #333;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        
        .late-alert {
            margin-top: 10px;
            padding: 10px;
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            color: #856404;
            text-align: center;
            font-size: 12px;
        }
        
        .button-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .btn {
            padding: 50px 20px;
            font-size: 18px;
            font-weight: bold;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.12);
        }
        
        .btn-checkin {
            border-top: 4px solid #28a745;
        }
        
        .btn-checkin i {
            color: #28a745;
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }
        
        .btn-checkout {
            border-top: 4px solid #dc3545;
        }
        
        .btn-checkout i {
            color: #dc3545;
            font-size: 48px;
            display: block;
            margin-bottom: 15px;
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        @media (max-width: 768px) {
            .main-container {
                width: 95%;
                margin-top: 80px;
            }
            .button-grid {
                grid-template-columns: 1fr;
            }
            .time-card h1 {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>

<div class="main-container">
    <!-- Modal Overlay -->
    <div id="modalOverlay" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h3>Confirm Check Out</h3>
            </div>
            <div class="modal-body">
                <div class="info-box" id="checkoutInfo"></div>
                <div class="warning-text">Make sure you have completed all your tasks</div>
            </div>
            <div class="modal-footer">
                <button class="btn-confirm" onclick="confirmCheckout()">Yes, Check Out</button>
                <button class="btn-cancel" onclick="closeModal()">Cancel</button>
            </div>
        </div>
    </div>
    
    <!-- Floating Message Popup -->
    <?php if($success_message || $error_message): ?>
    <div class="message-popup" id="messagePopup">
        <div class="popup-content <?php echo $success_message ? 'popup-success' : 'popup-error'; ?>">
            <span class="close-btn" onclick="document.getElementById('messagePopup').style.display='none'">&times;</span>
            <i class="fas <?php echo $success_message ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <h4><?php echo $success_message ? 'Success!' : 'Error!'; ?></h4>
            <p><?php echo $success_message ? $success_message : $error_message; ?></p>
            <?php if($checkin_time): ?>
                <p><strong>Time: <?php echo $checkin_time; ?></strong></p>
            <?php endif; ?>
            <button onclick="document.getElementById('messagePopup').style.display='none'">OK</button>
        </div>
    </div>
    <script>
        setTimeout(function() {
            var popup = document.getElementById('messagePopup');
            if(popup) popup.style.display = 'none';
        }, 4000);
    </script>
    <?php endif; ?>
    
    <!-- Time Card -->
    <div class="time-card">
        <h3><i class="fas fa-clock"></i> Current Time</h3>
        <h1 id="currentTime">--:--:--</h1>
        <p id="currentDate"></p>
    </div>
    
    <!-- Status Card -->
    <div class="status-card">
        <h4>Today's Status</h4>
        
        <div class="current-status <?php echo $has_open_session ? 'status-checked-in' : 'status-checked-out'; ?>">
            <i class="fas <?php echo $has_open_session ? 'fa-sign-in-alt' : 'fa-sign-out-alt'; ?>"></i>
            Currently: <strong><?php echo $has_open_session ? 'CHECKED IN' : 'CHECKED OUT'; ?></strong>
        </div>
        
        <?php if(empty($today_records)): ?>
            <div class="status-row">
                <div style="text-align: center; width: 100%; color: #999;">
                    <i class="fas fa-clock"></i> No activity yet today
                </div>
            </div>
        <?php else: ?>
            <?php foreach($today_records as $record): 
                $session_label = ($record['session'] == 'morning') ? 'Morning Session (9:00 - 12:00)' : 'Afternoon Session (13:00 - 18:00)';
                // Format check-in time, show '-' if NULL
                $check_in_display = $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '-';
                // Format check-out time, show '-' if NULL
                $check_out_display = $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '-';
                // Working hours display
                $working_hours_display = $record['calculated_working_hours'] > 0 ? number_format($record['calculated_working_hours'], 2) . ' hrs' : '-';
                // Late minutes display
                $late_minutes_display = $record['calculated_late_minutes'] > 0 ? $record['calculated_late_minutes'] . ' minutes' : '-';
            ?>
            <div class="session-card">
                <div class="session-title"><?php echo $session_label; ?></div>
                <div class="session-detail">
                    <span>Check In:</span>
                    <span><?php echo $check_in_display; ?></span>
                </div>
                <div class="session-detail">
                    <span>Check Out:</span>
                    <span><?php echo $check_out_display; ?></span>
                </div>
                <?php if($record['calculated_working_hours'] > 0): ?>
                <div class="session-detail">
                    <span>Working Hours:</span>
                    <span><?php echo $working_hours_display; ?></span>
                </div>
                <?php endif; ?>
                <?php if($record['calculated_late_minutes'] > 0): ?>
                <div class="late-alert">
                    <i class="fas fa-exclamation-triangle"></i> Late by <?php echo $late_minutes_display; ?>
                </div>
                <?php endif; ?>
                <?php
                $status_text = '';
                $status_class = '';
                if($record['calculated_status'] == 'present') {
                    $status_text = 'Present';
                    $status_class = 'badge-success';
                } elseif($record['calculated_status'] == 'late') {
                    $status_text = 'Late';
                    $status_class = 'badge-warning';
                } elseif($record['calculated_status'] == 'early_leave') {
                    $status_text = 'Left Early';
                    $status_class = 'badge-warning';
                } elseif($record['calculated_status'] == 'late_early') {
                    $status_text = 'Late + Left Early';
                    $status_class = 'badge-warning';
                } else {
                    $status_text = 'Absent';
                    $status_class = 'badge-danger';
                }
                ?>
                <div class="session-detail">
                    <span>Status:</span>
                    <span class="<?php echo $status_class; ?>" style="padding: 4px 10px; border-radius: 20px; font-size: 11px; display: inline-block;"><?php echo $status_text; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Action Buttons -->
    <div class="button-grid">
        <button class="btn btn-checkin" onclick="location.href='check_in.php'" <?php echo !$can_check_in ? 'disabled' : ''; ?>>
            <i class="fas fa-sign-in-alt"></i>
            Check In
            <?php if(!$can_check_in): ?>
                <br><small style="font-size: 12px;">Both sessions completed</small>
            <?php endif; ?>
        </button>
        
        <button class="btn btn-checkout" id="checkoutBtn" <?php echo !$can_check_out ? 'disabled' : ''; ?>>
            <i class="fas fa-sign-out-alt"></i>
            Check Out
            <?php if(!$can_check_out): ?>
                <br><small style="font-size: 12px;">No open session</small>
            <?php endif; ?>
        </button>
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('currentTime').innerHTML = `${hours}:${minutes}:${seconds}`;
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').innerHTML = now.toLocaleDateString('en-MY', options);
    }
    
    setInterval(updateDateTime, 1000);
    updateDateTime();
    
    const hasOpenSession = <?php echo json_encode($has_open_session); ?>;
    
    const modalOverlay = document.getElementById('modalOverlay');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const checkoutInfo = document.getElementById('checkoutInfo');
    
    if (checkoutBtn) {
        checkoutBtn.onclick = function() {
            if (hasOpenSession) {
                const now = new Date();
                const currentTime = now.toLocaleTimeString('en-MY', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                
                checkoutInfo.innerHTML = `
                    <div class="info-row">
                        <span class="info-label">Current Time:</span>
                        <span class="info-value">${currentTime}</span>
                    </div>
                `;
                modalOverlay.style.display = 'flex';
            }
        }
    }
    
    function closeModal() {
        modalOverlay.style.display = 'none';
    }
    
    function confirmCheckout() {
        window.location.href = 'check_out.php';
    }
    
    modalOverlay.onclick = function(event) {
        if (event.target === modalOverlay) {
            modalOverlay.style.display = 'none';
        }
    }
</script>
</body>
</html>