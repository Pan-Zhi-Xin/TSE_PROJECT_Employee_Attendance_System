<?php
session_start();
include '../db_connection.php';

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['employee_id'];
$today = date('Y-m-d');
$current_time = date('Y-m-d H:i:s');
$current_hour_only = date('H:i:s');

// Session times
$morning_earliest = '08:00:00'; 
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_earliest = '12:30:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

function generateRecordId($session, $employee_id) {
    $prefix = ($session == 'morning') ? 'MNG' : 'AFT';
    $date = date('Ymd');
    $padded_id = str_pad($employee_id, 3, '0', STR_PAD_LEFT);
    return $prefix . $date . $padded_id;
}

// Determine which session to check in for based on current time
if($current_hour_only >= $morning_earliest && $current_hour_only < $morning_end) {
    $session = 'morning';
    $session_name = "Morning Session";
    $work_start_time = $morning_start;
    $earliest_time = $morning_earliest;
} elseif($current_hour_only >= $afternoon_earliest && $current_hour_only < $afternoon_end) {
    $session = 'afternoon';
    $session_name = "Afternoon Session";
    $work_start_time = $afternoon_start;
    $earliest_time = $afternoon_earliest;
}elseif($current_hour_only < $morning_earliest) {
    // before 08:00
    $_SESSION['error'] = "Check-in for Morning Session starts at 08:00. Please wait.";
    header("Location: dashboard.php");
    exit();
} else {
    $_SESSION['error'] = "Check-in for Afternoon Session starts at 12:30. Please wait.";
    header("Location: dashboard.php");
    exit();
}

// Check if already checked in for this session today
$check_query = "SELECT * FROM attendance_records 
                WHERE employee_id = '$employee_id' 
                AND record_date = '$today' 
                AND session = '$session'";
$check_result = mysqli_query($conn, $check_query);
$existing_record = mysqli_fetch_assoc($check_result);

// If already checked in (check_in_time is not NULL)
if($existing_record && !is_null($existing_record['check_in_time'])) {
    $_SESSION['error'] = "You have already checked in for the $session_name today!";
    header("Location: dashboard.php");
    exit();
}

// Calculate late minutes
$late_minutes = 0;
$status = 'present';
$status_message = "You're on time!";

if($current_hour_only > $work_start_time) {
    $late_minutes = round((strtotime($current_hour_only) - strtotime($work_start_time)) / 60);
    $status_message = "You are " . $late_minutes . " minutes late for $session_name.";
    $status = 'late';
}

if($existing_record) {
    // Update existing record (record exists but no check-in yet)
    $query = "UPDATE attendance_records 
              SET check_in_time = '$current_time', status = '$status', late_minutes = '$late_minutes' 
              WHERE employee_id = '$employee_id' AND record_date = '$today' AND session = '$session'";
} else {
    $record_id = generateRecordId($session, $employee_id);
    $query = "INSERT INTO attendance_records (record_id, employee_id, record_date, session, check_in_time, status, late_minutes) 
              VALUES ('$record_id', '$employee_id', '$today', '$session', '$current_time', '$status', '$late_minutes')";
}

if(mysqli_query($conn, $query)) {
    $message = "✅ Check-in successful for $session_name! ";
    $message .= $status_message;
    $_SESSION['success'] = $message;
    $_SESSION['checkin_time'] = date('h:i A');
} else {
    $_SESSION['error'] = "Check-in failed: " . mysqli_error($conn);
}

header("Location: dashboard.php");
exit();
?>