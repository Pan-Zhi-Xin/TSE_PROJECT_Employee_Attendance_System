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

$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

// Find which session has an open check-in (checked in but not checked out)
$open_query = "SELECT * FROM attendance_records 
               WHERE employee_id = '$employee_id' 
               AND record_date = '$today' 
               AND check_in_time IS NOT NULL 
               AND check_out_time IS NULL";
$open_result = mysqli_query($conn, $open_query);
$record = mysqli_fetch_assoc($open_result);

if(!$record) {
    // Check if user has any check-ins today
    $check_any = "SELECT COUNT(*) as total FROM attendance_records 
                  WHERE employee_id = '$employee_id' AND record_date = '$today'";
    $any_result = mysqli_query($conn, $check_any);
    $any_row = mysqli_fetch_assoc($any_result);
    
    if($any_row['total'] > 0) {
        $_SESSION['error'] = "You have already checked out from all sessions today!";
    } else {
        $_SESSION['error'] = "No check-in found. Please check in first!";
    }
    header("Location: dashboard.php");
    exit();
}

$session = $record['session'];
$session_name = ($session == 'morning') ? "Morning Session" : "Afternoon Session";
$work_start_time = ($session == 'morning') ? $morning_start : $afternoon_start;
$work_end_time = ($session == 'morning') ? $morning_end : $afternoon_end;

// Calculate early leave minutes (if checking out before session end)
$early_minutes = 0;
$early_message = "";
$is_early = false;

if($current_hour_only < $work_end_time) {
    $early_minutes = round((strtotime($work_end_time) - strtotime($current_hour_only)) / 60);
    $early_message = " You left " . $early_minutes . " minutes early.";
    $is_early = true;
}

// Calculate late minutes from check-in
$check_in_time = $record['check_in_time'];
$check_in_hour_only = date('H:i:s', strtotime($check_in_time));
$late_minutes = 0;
$is_late = false;

if($check_in_hour_only > $work_start_time) {
    $late_minutes = round((strtotime($check_in_hour_only) - strtotime($work_start_time)) / 60);
    $is_late = true;
}

// Determine status
$current_status = $record['status']; // Get current status from database

if($is_late && $is_early) {
    // Both late AND early leave -> late_early
    $status = 'late_early';
    $status_message = "You were " . $late_minutes . " minutes late and left " . $early_minutes . " minutes early.";
} elseif($is_late) {
    // Only late
    $status = 'late';
    $status_message = "You were " . $late_minutes . " minutes late.";
} elseif($is_early) {
    // Only early leave
    $status = 'left_early';
    $status_message = "You left " . $early_minutes . " minutes early.";
} else {
    // On time
    $status = 'present';
    $status_message = "You completed the session on time.";
}

// Calculate working hours for this session
$working_hours = round((strtotime($current_time) - strtotime($check_in_time)) / 3600, 2);

// Update the record with check-out time and new status
$query = "UPDATE attendance_records 
          SET check_out_time = '$current_time', 
              working_hours = '$working_hours',
              status = '$status'
          WHERE employee_id = '$employee_id' AND record_date = '$today' AND session = '$session'";

if(mysqli_query($conn, $query)) {
    $_SESSION['success'] = "✅ Check-out successful for $session_name! Session duration: " . number_format($working_hours, 2) . " hours. " . $status_message;
} else {
    $_SESSION['error'] = "Check-out failed: " . mysqli_error($conn);
}

header("Location: dashboard.php");
exit();
?>