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

$employee_id = $_SESSION['employee_id'];
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

// Helper function to get session from check_in time
function getSession($check_in_time) {
    if(!$check_in_time) return 'unknown';
    $hour = date('H', strtotime($check_in_time));
    if($hour < 12) return 'morning';
    return 'afternoon';
}

// Helper function to calculate working hours for a session
function calculateWorkingHours($check_in_time, $check_out_time) {

    if(!$check_in_time || !$check_out_time) {
        return 0;
    }

    $check_in = strtotime($check_in_time);
    $check_out = strtotime($check_out_time);

    $total_seconds = $check_out - $check_in;

    // Lunch break
    $lunch_start = strtotime(date('Y-m-d 12:00:00', $check_in));
    $lunch_end = strtotime(date('Y-m-d 13:00:00', $check_in));

    // If attendance overlaps lunch period
    if($check_in < $lunch_end && $check_out > $lunch_start) {

        $overlap_start = max($check_in, $lunch_start);
        $overlap_end = min($check_out, $lunch_end);

        $total_seconds -= max(0, $overlap_end - $overlap_start);
    }

    return round(max($total_seconds / 3600, 0), 2);
}

// Helper function to calculate late minutes
function calculateLateMinutes($check_in_time, $session) {
    global $morning_start, $afternoon_start;
    if(!$check_in_time) return 0;
    
    $work_start = ($session == 'morning') ? $morning_start : $afternoon_start;
    $check_in_only = date('H:i:s', strtotime($check_in_time));
    
    if($check_in_only > $work_start) {
        return round((strtotime($check_in_only) - strtotime($work_start)) / 60);
    }
    return 0;
}

// Helper function to calculate early leave minutes
function calculateEarlyLeaveMinutes($check_out_time, $session) {
    global $morning_end, $afternoon_end;
    if(!$check_out_time) return 0;
    
    $work_end = ($session == 'morning') ? $morning_end : $afternoon_end;
    $check_out_only = date('H:i:s', strtotime($check_out_time));
    
    if($check_out_only < $work_end) {
        return round((strtotime($work_end) - strtotime($check_out_only)) / 60);
    }
    return 0;
}

// Helper function to get status for a session
function getSessionStatus($check_in_time, $check_out_time, $session, $db_status) {
    // If database has a special status (half_day, holiday), use it
    if($db_status == 'half_day') return 'half_day';
    if($db_status == 'holiday') return 'holiday';
    
    if(!$check_in_time) return 'absent';
    
    $is_late = calculateLateMinutes($check_in_time, $session) > 0;
    $is_early = calculateEarlyLeaveMinutes($check_out_time, $session) > 0;
    
    if($is_late && $is_early) return 'late';
    if($is_late) return 'late';
    if($is_early) return 'early_leave';
    return 'present';
}

// Get all attendance records
$query = "SELECT * FROM attendance_records 
          WHERE employee_id = '$employee_id' 
          ORDER BY record_date DESC, check_in_time ASC";
$result = mysqli_query($conn, $query);

// Group records by date first, then by session
$daily_data = [];
while($row = mysqli_fetch_assoc($result)) {
    $date = $row['record_date'];
    $session = getSession($row['check_in_time']);
    $db_status = $row['status'];
    
    if(!isset($daily_data[$date])) {
        $daily_data[$date] = [
            'date' => $date,
            'morning' => null,
            'afternoon' => null
        ];
    }
    
    $working_hours = calculateWorkingHours($row['check_in_time'], $row['check_out_time'], $session);
    $late_minutes = calculateLateMinutes($row['check_in_time'], $session);
    $early_minutes = calculateEarlyLeaveMinutes($row['check_out_time'], $session);
    $status = getSessionStatus($row['check_in_time'], $row['check_out_time'], $session, $db_status);
    
    $session_data = [
        'check_in_time' => $row['check_in_time'],
        'check_out_time' => $row['check_out_time'],
        'working_hours' => $working_hours,
        'late_minutes' => $late_minutes,
        'early_minutes' => $early_minutes,
        'status' => $status
    ];
    
    if($session == 'morning') {
        $daily_data[$date]['morning'] = $session_data;
    } else {
        $daily_data[$date]['afternoon'] = $session_data;
    }
}

// Sort by date descending
krsort($daily_data);

// Group by month
$monthly_data = [];
foreach($daily_data as $date => $day) {
    $month_key = date('Y-m', strtotime($date));
    $month_name = date('F Y', strtotime($date));
    
    if(!isset($monthly_data[$month_key])) {
        $monthly_data[$month_key] = [
            'name' => $month_name,
            'days' => [],
            'morning_present' => 0, 'morning_late' => 0, 'morning_half_day' => 0, 'morning_holiday' => 0,
            'afternoon_present' => 0, 'afternoon_late' => 0, 'afternoon_half_day' => 0, 'afternoon_holiday' => 0,
            'total_hours' => 0, 'total_late_minutes' => 0, 'total_early_minutes' => 0,
            'absent_morning' => 0, 'absent_afternoon' => 0
        ];
    }
    
    // Process morning session
    if($day['morning']) {
        $status = $day['morning']['status'];
        if($status == 'present') $monthly_data[$month_key]['morning_present']++;
        elseif($status == 'late') $monthly_data[$month_key]['morning_late']++;
        elseif($status == 'half_day') $monthly_data[$month_key]['morning_half_day']++;
        elseif($status == 'holiday') $monthly_data[$month_key]['morning_holiday']++;
        $monthly_data[$month_key]['total_hours'] += $day['morning']['working_hours'];
        $monthly_data[$month_key]['total_late_minutes'] += $day['morning']['late_minutes'];
        $monthly_data[$month_key]['total_early_minutes'] += $day['morning']['early_minutes'];
    } else {
        $monthly_data[$month_key]['absent_morning']++;
    }
    
    // Process afternoon session
    if($day['afternoon']) {
        $status = $day['afternoon']['status'];
        if($status == 'present') $monthly_data[$month_key]['afternoon_present']++;
        elseif($status == 'late') $monthly_data[$month_key]['afternoon_late']++;
        elseif($status == 'half_day') $monthly_data[$month_key]['afternoon_half_day']++;
        elseif($status == 'holiday') $monthly_data[$month_key]['afternoon_holiday']++;
        $monthly_data[$month_key]['total_hours'] += $day['afternoon']['working_hours'];
        $monthly_data[$month_key]['total_late_minutes'] += $day['afternoon']['late_minutes'];
        $monthly_data[$month_key]['total_early_minutes'] += $day['afternoon']['early_minutes'];
    } else {
        $monthly_data[$month_key]['absent_afternoon']++;
    }
    
    $monthly_data[$month_key]['days'][] = $day;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance History</title>
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
            width: 90%;
            max-width: 1200px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        .monthly-card {
            background: white;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            overflow: hidden;
        }
        
        .monthly-header {
            background: #007bff;
            color: white;
            padding: 12px 20px;
            font-size: 16px;
            font-weight: bold;
        }
        
        .stats-row {
            display: flex;
            flex-wrap: wrap;
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
            gap: 15px;
        }
        
        .stat-box {
            flex: 1;
            min-width: 90px;
            text-align: center;
            padding: 10px;
            background: white;
            border: 1px solid #eee;
            border-radius: 6px;
        }
        
        .stat-number {
            font-size: 22px;
            font-weight: bold;
            color: #333;
        }
        
        .stat-label {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
        }
        
        .stat-number.present { color: #28a745; }
        .stat-number.late { color: #ffc107; }
        .stat-number.half-day { color: #6f42c1; }
        .stat-number.holiday { color: #007bff; }
        .stat-number.absent { color: #dc3545; }
        
        .total-box {
            background: #007bff;
            color: white;
        }
        .total-box .stat-number { color: white; }
        .total-box .stat-label { color: rgba(255,255,255,0.9); }
        
        .table-wrapper {
            overflow-x: auto;
        }
        
        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        
        .attendance-table th {
            background: #e9ecef;
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .attendance-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .attendance-table tr:hover {
            background: #f8f9fa;
        }
        
        .session-morning {
            background: #e8f4fd;
            padding: 8px;
            border-radius: 6px;
            border-left: 3px solid #007bff;
        }
        
        .session-afternoon {
            background: #fff4e6;
            padding: 8px;
            border-radius: 6px;
            border-left: 3px solid #fd7e14;
        }
        
        .session-title {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .session-time {
            font-size: 11px;
            color: #555;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: normal;
        }
        
        .badge-present { background: #28a745; color: white; }
        .badge-late { background: #ffc107; color: #333; }
        .badge-half-day { background: #6f42c1; color: white; }
        .badge-holiday { background: #007bff; color: white; }
        .badge-absent { background: #dc3545; color: white; }
        
        .late-min { color: #dc3545; font-size: 10px; }
        .early-min { color: #17a2b8; font-size: 10px; }
        
        .hours-cell {
            font-weight: bold;
            color: #007bff;
            text-align: center;
            vertical-align: middle;
        }
        
        .no-data {
            text-align: center;
            padding: 50px;
            color: #999;
        }
        
        @media (max-width: 768px) {
            .main-container {
                width: 95%;
                margin-top: 80px;
            }
            .stats-row {
                flex-direction: column;
            }
            .stat-box {
                min-width: auto;
            }
            .attendance-table th,
            .attendance-table td {
                padding: 6px;
                font-size: 11px;
            }
        }
    </style>
</head>
<body>

<div class="main-container">
    
    <?php if(count($monthly_data) > 0): ?>
        <?php foreach($monthly_data as $month_key => $month): ?>
            <div class="monthly-card">
                <!-- Header -->
                <div class="monthly-header">
                    <?php echo $month['name']; ?>
                </div>
                
                <!-- Stats - 5 Cards -->
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-number present"><?php echo $month['morning_present'] + $month['afternoon_present']; ?></div>
                        <div class="stat-label">Present</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number late"><?php echo $month['morning_late'] + $month['afternoon_late']; ?></div>
                        <div class="stat-label">Late</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number half-day"><?php echo $month['morning_half_day'] + $month['afternoon_half_day']; ?></div>
                        <div class="stat-label">Half Day</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number holiday"><?php echo $month['morning_holiday'] + $month['afternoon_holiday']; ?></div>
                        <div class="stat-label">Holiday</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-number absent"><?php echo $month['absent_morning'] + $month['absent_afternoon']; ?></div>
                        <div class="stat-label">Absent</div>
                    </div>
                    <div class="stat-box total-box">
                        <div class="stat-number"><?php echo number_format($month['total_hours'], 1); ?></div>
                        <div class="stat-label">Total Hours</div>
                    </div>
                </div>
                
                <!-- Table -->
                <div class="table-wrapper">
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Morning (9:00 - 12:00)</th>
                                <th>Afternoon (13:00 - 18:00)</th>
                                <th>Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($month['days'] as $day): 
                                $total_day_hours = 0;
                            ?>
                            <tr>
                                <td style="vertical-align: middle;">
                                    <?php echo date('d-m-Y', strtotime($day['date'])); ?>
                                </td>
                                
                                <!-- Morning -->
                                <td>
                                    <?php if($day['morning']): 
                                        $total_day_hours += $day['morning']['working_hours'];
                                        $status = $day['morning']['status'];
                                        $badge = '';
                                        if($status == 'present') $badge = 'badge-present';
                                        elseif($status == 'late') $badge = 'badge-late';
                                        elseif($status == 'half_day') $badge = 'badge-half-day';
                                        elseif($status == 'holiday') $badge = 'badge-holiday';
                                        else $badge = 'badge-absent';
                                        $text = ucfirst(str_replace('_', ' ', $status));
                                    ?>
                                        <div class="session-morning">
                                            <div class="session-title">
                                                <?php echo date('h:i A', strtotime($day['morning']['check_in_time'])); ?> 
                                                - <?php echo $day['morning']['check_out_time'] ? date('h:i A', strtotime($day['morning']['check_out_time'])) : '--:--'; ?>
                                            </div>
                                            <div style="margin: 5px 0;">
                                                <span class="badge <?php echo $badge; ?>"><?php echo $text; ?></span>
                                                <?php if($day['morning']['late_minutes'] > 0 && $status == 'late'): ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="session-morning" style="background: #f8f9fa; text-align: center;">
                                            <span class="badge badge-absent">Absent</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Afternoon -->
                                <td>
                                    <?php if($day['afternoon']): 
                                        $total_day_hours += $day['afternoon']['working_hours'];
                                        $status = $day['afternoon']['status'];
                                        $badge = '';
                                        if($status == 'present') $badge = 'badge-present';
                                        elseif($status == 'late') $badge = 'badge-late';
                                        elseif($status == 'half_day') $badge = 'badge-half-day';
                                        elseif($status == 'holiday') $badge = 'badge-holiday';
                                        else $badge = 'badge-absent';
                                        $text = ucfirst(str_replace('_', ' ', $status));
                                    ?>
                                        <div class="session-afternoon">
                                            <div class="session-title">
                                                <?php echo date('h:i A', strtotime($day['afternoon']['check_in_time'])); ?> 
                                                - <?php echo $day['afternoon']['check_out_time'] ? date('h:i A', strtotime($day['afternoon']['check_out_time'])) : '--:--'; ?>
                                            </div>
                                            <div style="margin: 5px 0;">
                                                <span class="badge <?php echo $badge; ?>"><?php echo $text; ?></span>
                                                <?php if($day['afternoon']['late_minutes'] > 0 && $status == 'late'): ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="session-afternoon" style="background: #f8f9fa; text-align: center;">
                                            <span class="badge badge-absent">Absent</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Total Hours -->
                                <td class="hours-cell">
                                    <?php echo number_format($total_day_hours, 2); ?> hrs
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="monthly-card">
            <div class="no-data">
                No attendance records found
            </div>
        </div>
    <?php endif; ?>
</div>

</body>
</html>