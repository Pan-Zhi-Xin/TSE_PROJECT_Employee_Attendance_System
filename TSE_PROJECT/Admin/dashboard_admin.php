<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

date_default_timezone_set('Asia/Kuala_Lumpur');

$today = date('Y-m-d');
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';
$current_time = date('H:i:s');

function autoCreateAbsentRecords($conn, $today_date, $current_time, $morning_end, $afternoon_end) {
    $morning_created = 0;
    $afternoon_created = 0;
    
    $create_morning_absent = ($current_time > $morning_end);
    $create_afternoon_absent = ($current_time > $afternoon_end);
    
    if (!$create_morning_absent && !$create_afternoon_absent) {
        return ['morning' => 0, 'afternoon' => 0];
    }
    
    $emp_query = "SELECT e.employee_id, e.employee_code 
                  FROM employees e 
                  JOIN users u ON e.user_id = u.user_id 
                  WHERE u.role = 'employee' AND u.status = 'Active'";
    $emp_result = mysqli_query($conn, $emp_query);
    
    while($emp = mysqli_fetch_assoc($emp_result)) {
        $employee_id = $emp['employee_id'];
        
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
    }
    return ['morning' => $morning_created, 'afternoon' => $afternoon_created];
}

$created = autoCreateAbsentRecords($conn, $today, $current_time, $morning_end, $afternoon_end);

$morning_present = $morning_late = $morning_absent = $morning_half_day = $morning_holiday = $morning_left_early = $morning_late_early = 0;

$morning_stats = "SELECT a.status, a.check_out_time, a.check_in_time, a.late_minutes 
                  FROM attendance_records a
                  JOIN employees e ON a.employee_id = e.employee_id
                  JOIN users u ON e.user_id = u.user_id
                  WHERE a.record_date = '$today' AND a.session = 'morning'
                  AND u.role = 'employee' AND u.status = 'Active'";
$morning_result = mysqli_query($conn, $morning_stats);
if($morning_result) {
    while($row = mysqli_fetch_assoc($morning_result)) {
        $status = $row['status'];
        $check_out = $row['check_out_time'];
        $is_early = ($check_out && $check_out < $morning_end);
        
        if($status == 'late_early') {
            $morning_late_early++;
        }
        elseif($is_early && ($status == 'present' || $status == 'late')) {
            $morning_left_early++;
        } elseif($status == 'present') {
            $morning_present++;
        } elseif($status == 'late') {
            $morning_late++;
        } elseif($status == 'half_day') {
            $morning_half_day++;
        } elseif($status == 'holiday') {
            $morning_holiday++;
        } elseif($status == 'left_early') {
            $morning_left_early++;
        } elseif($status == 'absent' || $status == '') {
            $morning_absent++;
        } else {
            $morning_absent++;
        }
    }
}

$afternoon_present = $afternoon_late = $afternoon_absent = $afternoon_half_day = $afternoon_holiday = $afternoon_left_early = $afternoon_late_early = 0;

$afternoon_stats = "SELECT a.status, a.check_out_time, a.check_in_time, a.late_minutes 
                    FROM attendance_records a
                    JOIN employees e ON a.employee_id = e.employee_id
                    JOIN users u ON e.user_id = u.user_id
                    WHERE a.record_date = '$today' AND a.session = 'afternoon'
                    AND u.role = 'employee' AND u.status = 'Active'";
$afternoon_result = mysqli_query($conn, $afternoon_stats);
if($afternoon_result) {
    while($row = mysqli_fetch_assoc($afternoon_result)) {
        $status = $row['status'];
        $check_out = $row['check_out_time'];
        $is_early = ($check_out && $check_out < $afternoon_end);
        
        if($status == 'late_early') {
            $afternoon_late_early++;
        }
        elseif($is_early && ($status == 'present' || $status == 'late')) {
            $afternoon_left_early++;
        } elseif($status == 'present') {
            $afternoon_present++;
        } elseif($status == 'late') {
            $afternoon_late++;
        } elseif($status == 'half_day') {
            $afternoon_half_day++;
        } elseif($status == 'holiday') {
            $afternoon_holiday++;
        } elseif($status == 'left_early') {
            $afternoon_left_early++;
        } elseif($status == 'absent' || $status == '') {
            $afternoon_absent++;
        } else {
            $afternoon_absent++;
        }
    }
}

function calculateEarlyMinutes($check_out_time, $session_end) {
    if (!$check_out_time) return 0;
    $check_out_only = date('H:i:s', strtotime($check_out_time));
    if ($check_out_only < $session_end) {
        return round((strtotime($session_end) - strtotime($check_out_only)) / 60);
    }
    return 0;
}

$employee_query = "SELECT u.name, e.employee_id, e.employee_code, e.department, e.position,
                          m.check_in_time as morning_in, m.check_out_time as morning_out, m.status as morning_status, m.late_minutes as morning_late,
                          a.check_in_time as afternoon_in, a.check_out_time as afternoon_out, a.status as afternoon_status, a.late_minutes as afternoon_late
                   FROM employees e
                   JOIN users u ON e.user_id = u.user_id
                   LEFT JOIN attendance_records m ON e.employee_id = m.employee_id AND m.record_date = '$today' AND m.session = 'morning'
                   LEFT JOIN attendance_records a ON e.employee_id = a.employee_id AND a.record_date = '$today' AND a.session = 'afternoon'
                   WHERE u.role = 'employee' AND u.status = 'Active'
                   ORDER BY u.name ASC";

$employee_result = mysqli_query($conn, $employee_query);

function getStatusBadge($status, $check_out_time = null, $session_end = null) {
    if ($status === null) {
        return ['class' => 'status-none', 'text' => '-'];
    }
    
    $is_early = false;
    if($check_out_time && $session_end && $check_out_time < $session_end) {
        $is_early = true;
    }
    
    if($status == 'late_early') {
        return ['class' => 'status-late-early', 'text' => 'Late + Early'];
    }
    
    if($is_early && ($status == 'present' || $status == 'late')) {
        return ['class' => 'status-early-left', 'text' => 'Early Left'];
    }
    
    switch($status) {
        case 'present':    return ['class' => 'status-present',   'text' => 'Present'];
        case 'late':       return ['class' => 'status-late',      'text' => 'Late'];
        case 'half_day':   return ['class' => 'status-half-day',  'text' => 'Half Day'];
        case 'holiday':    return ['class' => 'status-holiday',   'text' => 'Holiday'];
        case 'left_early': return ['class' => 'status-early-left','text' => 'Early Left'];
        case 'absent':     return ['class' => 'status-absent',    'text' => 'Absent'];
        default:           return ['class' => 'status-absent',    'text' => 'Absent'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="dashboard_admin.css">
</head>
<body>
<div class="main-container">
    <!-- Welcome Section -->
    <div class="time-card">
        <div class="time-card-left">
            <h3>Welcome back, <?php echo isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Admin'; ?>!</h3>
        </div>
        <div class="time-card-right">
            <div class="date-display" id="currentDate"></div>
            <h1 id="currentTime">--:--:--</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            Employee Attendance - <?php echo date('d F Y'); ?>
        </div>
        <div class="card-body">
            <!-- Summary Cards -->
            <div class="summary-cards">
                <!-- Morning Session Card -->
                <div class="summary-card morning-card">
                    <div class="summary-card-header">Morning Session (9:00 - 12:00)</div>
                    <div class="summary-card-body">
                        <div class="summary-stats">
                            <div class="stat-item"><div class="stat-value present"><?php echo $morning_present; ?></div><div class="stat-label">Present</div></div>
                            <div class="stat-item"><div class="stat-value late"><?php echo $morning_late; ?></div><div class="stat-label">Late</div></div>
                            <div class="stat-item"><div class="stat-value early-left"><?php echo $morning_left_early; ?></div><div class="stat-label">Early Left</div></div>
                            <div class="stat-item"><div class="stat-value late-early"><?php echo $morning_late_early; ?></div><div class="stat-label">Late + Early</div></div>
                            <div class="stat-item"><div class="stat-value half-day"><?php echo $morning_half_day; ?></div><div class="stat-label">Half Day</div></div>
                            <div class="stat-item"><div class="stat-value holiday"><?php echo $morning_holiday; ?></div><div class="stat-label">Holiday</div></div>
                            <div class="stat-item"><div class="stat-value absent"><?php echo $morning_absent; ?></div><div class="stat-label">Absent</div></div>
                            <div class="stat-item"><div class="stat-value total"><?php echo $morning_present + $morning_late + $morning_left_early + $morning_late_early + $morning_half_day + $morning_holiday + $morning_absent; ?></div><div class="stat-label">Total</div></div>
                        </div>
                    </div>
                </div>
                
                <!-- Afternoon Session Card -->
                <div class="summary-card afternoon-card">
                    <div class="summary-card-header">Afternoon Session (13:00 - 18:00)</div>
                    <div class="summary-card-body">
                        <div class="summary-stats">
                            <div class="stat-item"><div class="stat-value present"><?php echo $afternoon_present; ?></div><div class="stat-label">Present</div></div>
                            <div class="stat-item"><div class="stat-value late"><?php echo $afternoon_late; ?></div><div class="stat-label">Late</div></div>
                            <div class="stat-item"><div class="stat-value early-left"><?php echo $afternoon_left_early; ?></div><div class="stat-label">Early Left</div></div>
                            <div class="stat-item"><div class="stat-value late-early"><?php echo $afternoon_late_early; ?></div><div class="stat-label">Late + Early</div></div>
                            <div class="stat-item"><div class="stat-value half-day"><?php echo $afternoon_half_day; ?></div><div class="stat-label">Half Day</div></div>
                            <div class="stat-item"><div class="stat-value holiday"><?php echo $afternoon_holiday; ?></div><div class="stat-label">Holiday</div></div>
                            <div class="stat-item"><div class="stat-value absent"><?php echo $afternoon_absent; ?></div><div class="stat-label">Absent</div></div>
                            <div class="stat-item"><div class="stat-value total"><?php echo $afternoon_present + $afternoon_late + $afternoon_left_early + $afternoon_late_early + $afternoon_half_day + $afternoon_holiday + $afternoon_absent; ?></div><div class="stat-label">Total</div></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Table with auto-width columns (no colgroup or % widths) -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Emp Code</th>
                            <th rowspan="2">Emp Name</th>
                            <th rowspan="2">Department</th>
                            <th rowspan="2">Position</th>
                            <th colspan="5">Morning Session (9:00 - 12:00)</th>
                            <th colspan="5">Afternoon Session (13:00 - 18:00)</th>
                        </tr>
                        <tr>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Late (min)</th>
                            <th>Early (min)</th>
                            <th>Status</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                            <th>Late (min)</th>
                            <th>Early (min)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($employee_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($employee_result)):
                                $morning_early = calculateEarlyMinutes($row['morning_out'], $morning_end);
                                $afternoon_early = calculateEarlyMinutes($row['afternoon_out'], $afternoon_end);
                                $morning_badge = getStatusBadge($row['morning_status'], $row['morning_out'], $morning_end);
                                $afternoon_badge = getStatusBadge($row['afternoon_status'], $row['afternoon_out'], $afternoon_end);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                <!-- Morning -->
                                <td class="morning-data"><?php echo $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : '-'; ?></td>
                                <td class="morning-data"><?php echo $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : '-'; ?></td>
                                <td class="morning-data"><?php echo intval($row['morning_late'] ?? 0) > 0 ? intval($row['morning_late']) : '-'; ?></td>
                                <td class="morning-data"><?php echo $morning_early > 0 ? $morning_early : '-'; ?></td>
                                <td class="morning-data"><span class="status-badge <?php echo $morning_badge['class']; ?>"><?php echo $morning_badge['text']; ?></span></td>
                                <!-- Afternoon -->
                                <td class="afternoon-data"><?php echo $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : '-'; ?></td>
                                <td class="afternoon-data"><?php echo $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : '-'; ?></td>
                                <td class="afternoon-data"><?php echo intval($row['afternoon_late'] ?? 0) > 0 ? intval($row['afternoon_late']) : '-'; ?></td>
                                <td class="afternoon-data"><?php echo $afternoon_early > 0 ? $afternoon_early : '-'; ?></td>
                                <td class="afternoon-data"><span class="status-badge <?php echo $afternoon_badge['class']; ?>"><?php echo $afternoon_badge['text']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="14" class="text-center">No active employees found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').innerHTML = '📅 ' + now.toLocaleDateString('en-MY', options);
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        document.getElementById('currentTime').innerHTML = `${hours}:${minutes}:${seconds}`;
    }
    setInterval(updateDateTime, 1000);
    updateDateTime();
</script>
</body>
</html>