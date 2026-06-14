<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

$today = date('Y-m-d');
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';
$current_time = date('H:i:s');

// Function to auto-create missing session records
function autoCreateSessionRecords($conn, $today_date, $current_time, $morning_end, $afternoon_end) {
    $morning_created = 0;
    $afternoon_created = 0;
    
    $emp_query = "SELECT e.employee_id, e.employee_code 
                  FROM employees e 
                  JOIN users u ON e.user_id = u.user_id 
                  WHERE u.role = 'employee' AND u.status = 'Active'";
    $emp_result = mysqli_query($conn, $emp_query);
    
    while($emp = mysqli_fetch_assoc($emp_result)) {
        $employee_id = $emp['employee_id'];
        
        $check_morning = "SELECT * FROM attendance_records 
                          WHERE employee_id = '$employee_id' AND record_date = '$today_date' AND session = 'morning'";
        $morning_result = mysqli_query($conn, $check_morning);
        
        if(mysqli_num_rows($morning_result) == 0) {
            $record_id = 'MNG' . date('Ymd') . str_pad($employee_id, 3, '0', STR_PAD_LEFT);
            $insert_query = "INSERT INTO attendance_records (record_id, employee_id, record_date, session, status) 
                             VALUES ('$record_id', '$employee_id', '$today_date', 'morning', 'absent')";
            if(mysqli_query($conn, $insert_query)) {
                $morning_created++;
            }
        }
        
        $check_afternoon = "SELECT * FROM attendance_records 
                            WHERE employee_id = '$employee_id' AND record_date = '$today_date' AND session = 'afternoon'";
        $afternoon_result = mysqli_query($conn, $check_afternoon);
        
        if(mysqli_num_rows($afternoon_result) == 0) {
            $record_id = 'AFT' . date('Ymd') . str_pad($employee_id, 3, '0', STR_PAD_LEFT);
            $insert_query = "INSERT INTO attendance_records (record_id, employee_id, record_date, session, status) 
                             VALUES ('$record_id', '$employee_id', '$today_date', 'afternoon', 'absent')";
            if(mysqli_query($conn, $insert_query)) {
                $afternoon_created++;
            }
        }
    }
    
    return ['morning' => $morning_created, 'afternoon' => $afternoon_created];
}

// Auto-create session records
$created = autoCreateSessionRecords($conn, $today, $current_time, $morning_end, $afternoon_end);

// Get statistics for today
$emp_query = "SELECT COUNT(*) as total FROM employees";
$emp_result = mysqli_query($conn, $emp_query);
$total_employees = mysqli_fetch_assoc($emp_result)['total'];

// Morning session stats
$morning_present = 0;
$morning_late = 0;
$morning_absent = 0;
$morning_half_day = 0;
$morning_holiday = 0;

$morning_stats = "SELECT status FROM attendance_records WHERE record_date = '$today' AND session = 'morning'";
$morning_result = mysqli_query($conn, $morning_stats);
while($row = mysqli_fetch_assoc($morning_result)) {
    if($row['status'] == 'present') $morning_present++;
    elseif($row['status'] == 'late') $morning_late++;
    elseif($row['status'] == 'half_day') $morning_half_day++;
    elseif($row['status'] == 'holiday') $morning_holiday++;
    else $morning_absent++;
}

// Afternoon session stats
$afternoon_present = 0;
$afternoon_late = 0;
$afternoon_absent = 0;
$afternoon_half_day = 0;
$afternoon_holiday = 0;

$afternoon_stats = "SELECT status FROM attendance_records WHERE record_date = '$today' AND session = 'afternoon'";
$afternoon_result = mysqli_query($conn, $afternoon_stats);
while($row = mysqli_fetch_assoc($afternoon_result)) {
    if($row['status'] == 'present') $afternoon_present++;
    elseif($row['status'] == 'late') $afternoon_late++;
    elseif($row['status'] == 'half_day') $afternoon_half_day++;
    elseif($row['status'] == 'holiday') $afternoon_holiday++;
    else $afternoon_absent++;
}

// Get ALL employees with their attendance records for both sessions
$employee_query = "SELECT u.name, e.employee_id, e.employee_code, e.department, e.position,
                          m.check_in_time as morning_in, m.check_out_time as morning_out, m.status as morning_status,
                          a.check_in_time as afternoon_in, a.check_out_time as afternoon_out, a.status as afternoon_status
                   FROM employees e
                   JOIN users u ON e.user_id = u.user_id
                   LEFT JOIN attendance_records m ON e.employee_id = m.employee_id AND m.record_date = '$today' AND m.session = 'morning'
                   LEFT JOIN attendance_records a ON e.employee_id = a.employee_id AND a.record_date = '$today' AND a.session = 'afternoon'
                   WHERE u.role = 'employee'
                   ORDER BY u.name";
$employee_result = mysqli_query($conn, $employee_query);

// Function to get badge class and text based on status
function getStatusBadge($status) {
    switch($status) {
        case 'present':
            return ['class' => 'badge-success', 'text' => 'Present'];
        case 'late':
            return ['class' => 'badge-warning', 'text' => 'Late'];
        case 'half_day':
            return ['class' => 'badge-info', 'text' => 'Half Day'];
        case 'holiday':
            return ['class' => 'badge-primary', 'text' => 'Holiday'];
        default:
            return ['class' => 'badge-danger', 'text' => 'Absent'];
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
            <h5>Employee Attendance</h5>
        </div>
        <div class="card-body">
            <!-- Summary Cards at Bottom -->
            <div class="summary-cards">
                <!-- Morning Session Card -->
                <div class="summary-card morning-card">
                    <div class="summary-card-header">
                        Morning Session (9:00 - 12:00)
                    </div>
                    <div class="summary-card-body">
                        <div class="summary-stats">
                            <div class="stat-item">
                                <div class="stat-value present"><?php echo $morning_present; ?></div>
                                <div class="stat-label">Present</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value late"><?php echo $morning_late; ?></div>
                                <div class="stat-label">Late</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value absent"><?php echo $morning_absent; ?></div>
                                <div class="stat-label">Absent</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value half-day"><?php echo $morning_half_day; ?></div>
                                <div class="stat-label">Half Day</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value holiday"><?php echo $morning_holiday; ?></div>
                                <div class="stat-label">Holiday</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value total"><?php echo $morning_present + $morning_late + $morning_absent + $morning_half_day + $morning_holiday; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Afternoon Session Card -->
                <div class="summary-card afternoon-card">
                    <div class="summary-card-header">
                        Afternoon Session (13:00 - 18:00)
                    </div>
                    <div class="summary-card-body">
                        <div class="summary-stats">
                            <div class="stat-item">
                                <div class="stat-value present"><?php echo $afternoon_present; ?></div>
                                <div class="stat-label">Present</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value late"><?php echo $afternoon_late; ?></div>
                                <div class="stat-label">Late</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value absent"><?php echo $afternoon_absent; ?></div>
                                <div class="stat-label">Absent</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value half-day"><?php echo $afternoon_half_day; ?></div>
                                <div class="stat-label">Half Day</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value holiday"><?php echo $afternoon_holiday; ?></div>
                                <div class="stat-label">Holiday</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value total"><?php echo $afternoon_present + $afternoon_late + $afternoon_absent + $afternoon_half_day + $afternoon_holiday; ?></div>
                                <div class="stat-label">Total</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th rowspan="2">Employee Code</th>
                            <th rowspan="2">Employee Name</th>
                            <th rowspan="2">Department</th>
                            <th rowspan="2">Position</th>
                            <th colspan="3">Morning Session (9:00 - 12:00)</th>
                            <th colspan="3">Afternoon Session (13:00 - 18:00)</th>
                        </tr>
                        <tr>
                            <th class="morning-group">Check In</th>
                            <th class="morning-group">Check Out</th>
                            <th class="morning-group">Status</th>
                            <th class="afternoon-group">Check In</th>
                            <th class="afternoon-group">Check Out</th>
                            <th class="afternoon-group">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(mysqli_num_rows($employee_result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($employee_result)): 
                                $morning_badge = getStatusBadge($row['morning_status']);
                                $afternoon_badge = getStatusBadge($row['afternoon_status']);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['department']); ?></td>
                                <td><?php echo htmlspecialchars($row['position']); ?></td>
                                
                                <!-- Morning Session Data -->
                                <td class="morning-data"><?php echo $row['morning_in'] ? date('h:i A', strtotime($row['morning_in'])) : '-'; ?></td>
                                <td class="morning-data"><?php echo $row['morning_out'] ? date('h:i A', strtotime($row['morning_out'])) : '-'; ?></td>
                                <td class="morning-data"><span class="badge <?php echo $morning_badge['class']; ?>"><?php echo $morning_badge['text']; ?></span></td>
                                
                                <!-- Afternoon Session Data -->
                                <td class="afternoon-data"><?php echo $row['afternoon_in'] ? date('h:i A', strtotime($row['afternoon_in'])) : '-'; ?></td>
                                <td class="afternoon-data"><?php echo $row['afternoon_out'] ? date('h:i A', strtotime($row['afternoon_out'])) : '-'; ?></td>
                                <td class="afternoon-data"><span class="badge <?php echo $afternoon_badge['class']; ?>"><?php echo $afternoon_badge['text']; ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center">No employees found</td
                            </tr>
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