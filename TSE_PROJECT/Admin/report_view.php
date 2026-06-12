<?php
session_start();

// CHECK FOR EXPORT ACTION FIRST - MUST BE BEFORE ANY OUTPUT
if(isset($_GET['export_excel']) && $_GET['export_excel'] == '1') {
    // Include only db_connection for export
    include '../db_connection.php';
    
    // Set timezone
    date_default_timezone_set('Asia/Kuala_Lumpur');
    
    // Session times
    $morning_start = '09:00:00';
    $morning_end = '12:00:00';
    $afternoon_start = '13:00:00';
    $afternoon_end = '18:00:00';

    
    function calculateWorkingHoursExport($check_in_time, $check_out_time, $session) {
        global $morning_start, $morning_end, $afternoon_start, $afternoon_end;
        
        if(!$check_in_time) return 0;
        
        $work_start = ($session == 'morning') ? $morning_start : $afternoon_start;
        $work_end = ($session == 'morning') ? $morning_end : $afternoon_end;
        
        $check_in_only = date('H:i:s', strtotime($check_in_time));
        $actual_start = ($check_in_only > $work_start) ? $check_in_only : $work_start;
        
        if($check_out_time) {
            $check_out_only = date('H:i:s', strtotime($check_out_time));
            $actual_end = ($check_out_only < $work_end) ? $check_out_only : $work_end;
            $hours = (strtotime($actual_end) - strtotime($actual_start)) / 3600;
            return round($hours > 0 ? $hours : 0, 2);
        }
        return 0;
    }
    
    function calculateLateMinutesExport($check_in_time, $session) {
        global $morning_start, $afternoon_start;
        if(!$check_in_time) return 0;
        
        $work_start = ($session == 'morning') ? $morning_start : $afternoon_start;
        $check_in_only = date('H:i:s', strtotime($check_in_time));
        
        if($check_in_only > $work_start) {
            return round((strtotime($check_in_only) - strtotime($work_start)) / 60);
        }
        return 0;
    }
    
    function calculateEarlyLeaveMinutesExport($check_out_time, $session) {
        global $morning_end, $afternoon_end;
        if(!$check_out_time) return 0;
        
        $work_end = ($session == 'morning') ? $morning_end : $afternoon_end;
        $check_out_only = date('H:i:s', strtotime($check_out_time));
        
        if($check_out_only < $work_end) {
            return round((strtotime($work_end) - strtotime($check_out_only)) / 60);
        }
        return 0;
    }
    
    function getSessionStatusDisplayExport($row) {
        if(isset($row['status']) && !empty($row['status'])) {
            if($row['status'] == 'present') return ['status' => 'present', 'text' => 'Present'];
            if($row['status'] == 'late') return ['status' => 'late', 'text' => 'Late'];
            if($row['status'] == 'half_day') return ['status' => 'half_day', 'text' => 'Half Day'];
            if($row['status'] == 'holiday') return ['status' => 'holiday', 'text' => 'Holiday'];
            if($row['status'] == 'absent') return ['status' => 'absent', 'text' => 'Absent'];
        }
        
        if(!$row['check_in_time']) return ['status' => 'absent', 'text' => 'Absent'];
        
        $session = $row['session'];
        $is_late = calculateLateMinutesExport($row['check_in_time'], $session) > 0;
        
        return $is_late ? ['status' => 'late', 'text' => 'Late'] : ['status' => 'present', 'text' => 'Present'];
    }
    
    // Get export parameters
    $export_report_type = $_GET['report_type'] ?? '';
    $export_start_date = $_GET['start_date'] ?? '';
    $export_end_date = $_GET['end_date'] ?? '';
    $export_selected_employee = $_GET['employee_id'] ?? 'all';
    
    $emp_condition = ($export_selected_employee != 'all') ? "AND a.employee_id = '$export_selected_employee'" : "";
    
    $query = "SELECT a.*, a.session as session, u.name, e.employee_code, e.department, e.position, e.employee_id, a.notes as reason
              FROM attendance_records a
              JOIN employees e ON a.employee_id = e.employee_id
              JOIN users u ON e.user_id = u.user_id
              WHERE a.record_date BETWEEN '$export_start_date' AND '$export_end_date' $emp_condition
              ORDER BY a.record_date, a.session ASC, a.check_in_time ASC";
    
    $result = mysqli_query($conn, $query);
    $export_data = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $session = $row['session'];
        $row['session'] = $session;
        $row['calculated_working_hours'] = calculateWorkingHoursExport($row['check_in_time'], $row['check_out_time'], $session);
        $row['calculated_late_minutes'] = calculateLateMinutesExport($row['check_in_time'], $session);
        $row['calculated_early_minutes'] = calculateEarlyLeaveMinutesExport($row['check_out_time'], $session);
        $row['display_status'] = getSessionStatusDisplayExport($row);
        $export_data[] = $row;
    }
    
    // Generate filename
    if($export_report_type == 'daily') {
        $filename = "Attendance_Report_Daily_" . date('d-m-Y', strtotime($export_start_date));
    } elseif($export_report_type == 'monthly') {
        $filename = "Attendance_Report_Monthly_" . date('F_Y', strtotime($export_start_date));
    } else {
        $filename = "Attendance_Report_Custom_" . date('d-m-Y', strtotime($export_start_date)) . "_to_" . date('d-m-Y', strtotime($export_end_date));
    }
    
    // Set headers for Excel download
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=$filename.xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    
    // Start HTML table format for better Excel display
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Attendance Report</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        .report-table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        .report-table th, .report-table td { border: 1px solid #000000; padding: 8px; text-align: left; vertical-align: top; }
        .report-table th { background-color: #ffffff; font-weight: bold; }
        .section-title { font-size: 14pt; font-weight: bold; margin: 20px 0 10px 0; }
        .summary-table { border-collapse: collapse; width: 50%; margin-top: 20px; }
        .summary-table th, .summary-table td { border: 1px solid #000000; padding: 6px; }
        .summary-table th { font-weight: bold; }
    </style>';
    echo '</head>';
    echo '<body>';
    
    // Generate filename
    if($export_report_type == 'daily') {
        $filename = "Attendance_Report_Daily_" . date('d-m-Y', strtotime($export_start_date));
    } elseif($export_report_type == 'monthly') {
        $filename = "Attendance_Report_Monthly_" . date('F_Y', strtotime($export_start_date));
    } else {
        $filename = "Attendance_Report_Custom_" . date('d-m-Y', strtotime($export_start_date)) . "_to_" . date('d-m-Y', strtotime($export_end_date));
    }

    // Set headers for Excel download
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=$filename.xls");
    header("Pragma: no-cache");
    header("Expires: 0");

    // Start HTML table format for better Excel display
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Attendance Report</title>';
    echo '<style>
        body { font-family: Arial, sans-serif; }
        .report-table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        .report-table th, .report-table td { border: 1px solid #000000; padding: 8px; text-align: left; vertical-align: top; }
        .report-table th { background-color: #ffffff; font-weight: bold; text-align: center; }
        .report-table td { text-align: left; }
        .section-title { font-size: 14pt; font-weight: bold; margin: 20px 0 10px 0; text-align: center; }
        .summary-table { border-collapse: collapse; width: 50%; margin: 20px auto 0 auto; }
        .summary-table th, .summary-table td { border: 1px solid #000000; padding: 6px; }
        .summary-table th { font-weight: bold; background-color: #ffffff; text-align: center; }
        .summary-table td { text-align: left; }
        .header-title { text-align: center; margin-bottom: 20px; }
        .header-title h2 { margin-bottom: 5px; }
        .header-title p { margin: 2px 0; }
        .separator { margin: 30px 0 20px 0; border-top: 2px solid #000000; }
    </style>';
    echo '</head>';
    echo '<body>';

if($export_report_type == 'daily') {
    $date_display = date('d-m-Y', strtotime($export_start_date));
    $employee_name = ($export_selected_employee != 'all') ? getEmployeeNameById($conn, $export_selected_employee) : 'All_Employees';
    $filename = "Attendance_Report_Daily_{$employee_name}_{$date_display}";
} elseif($export_report_type == 'monthly') {
    $month_year = date('F_Y', strtotime($export_start_date));
    $employee_name = ($export_selected_employee != 'all') ? getEmployeeNameById($conn, $export_selected_employee) : 'All_Employees';
    $filename = "Attendance_Report_Monthly_{$employee_name}_{$month_year}";
} else {
    $start_display = date('d-m-Y', strtotime($export_start_date));
    $end_display = date('d-m-Y', strtotime($export_end_date));
    $employee_name = ($export_selected_employee != 'all') ? getEmployeeNameById($conn, $export_selected_employee) : 'All_Employees';
    $filename = "Attendance_Report_Custom_{$employee_name}_{$start_display}_to_{$end_display}";
}

// Add helper function to get employee name
function getEmployeeNameById($conn, $employee_id) {
    $query = "SELECT u.name FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.employee_id = '$employee_id'";
    $result = mysqli_query($conn, $query);
    if($row = mysqli_fetch_assoc($result)) {
        return preg_replace('/[^A-Za-z0-9]/', '_', $row['name']);
    }
    return 'Selected_Employee';
}

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=$filename.xls");
header("Pragma: no-cache");
header("Expires: 0");

// Get employee display name for header
$employee_display = ($export_selected_employee != 'all') ? getEmployeeNameForDisplay($conn, $export_selected_employee) : 'All Employees';

function getEmployeeNameForDisplay($conn, $employee_id) {
    $query = "SELECT u.name, e.employee_code FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.employee_id = '$employee_id'";
    $result = mysqli_query($conn, $query);
    if($row = mysqli_fetch_assoc($result)) {
        return $row['employee_code'] . ' - ' . $row['name'];
    }
    return 'Selected Employee';
}

// Start HTML table format for better Excel display
echo '<html>';
echo '<head>';
echo '<meta charset="UTF-8">';
echo '<title>Attendance Report - ' . strtoupper($export_report_type) . '</title>';
echo '<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .report-table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
    .report-table th, .report-table td { border: 1px solid #000000; padding: 8px; }
    .report-table th { background-color: #ffffff; font-weight: bold; text-align: center; }
    .report-table td { text-align: left; }
    .section-title { font-size: 14pt; font-weight: bold; margin: 20px 0 10px 0; text-align: center; }
    .summary-table { border-collapse: collapse; width: 50%; margin: 10px auto 0 auto; }
    .summary-table th, .summary-table td { border: 1px solid #000000; padding: 6px; }
    .summary-table th { font-weight: bold; background-color: #ffffff; text-align: center; }
    .summary-table td { text-align: left; }
    .header-title { text-align: center; margin-bottom: 20px; }
    .header-title h2 { margin-bottom: 5px; }
    .header-title p { margin: 2px 0; }
    .separator { margin: 30px 0 20px 0; border-top: 2px solid #000000; }
    .summary-wrapper { text-align: center; margin-top: 20px; }
</style>';
echo '</head>';
echo '<body>';

if($export_report_type == 'daily') {
        $morning_export = [];
        $afternoon_export = [];
        foreach($export_data as $row) {
            if($row['session'] == 'morning') {
                $morning_export[] = $row;
            } else {
                $afternoon_export[] = $row;
            }
        }
        
        // Report Header with ALL information
        echo '<div class="header-title">';
        echo '<h2 style="text-align: center;">ATTENDANCE REPORT</h2>';
        echo '<p style="text-align: center; margin: 5px 0;"><strong>Report Type:</strong> Daily Report</p>';
        echo '<p style="text-align: center; margin: 2px 0;"><strong>Date:</strong> ' . date('d-m-Y', strtotime($export_start_date)) . '</p>';
        echo '<p style="text-align: center; margin: 2px 0;"><strong>Employee:</strong> ' . $employee_display . '</p>';
        echo '<p style="text-align: center; margin: 2px 0;"><strong>Generated On:</strong> ' . date('d-m-Y H:i:s') . '</p>';
        echo '</div>';
        
        echo '<br/>';

        // MORNING SESSION TABLE
        echo '<div class="section-title">MORNING SESSION (9:00 - 12:00)</div>';        
        echo '<table class="report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Employee Code</th>';
        echo '<th>Employee Name</th>';
        echo '<th>Department</th>';
        echo '<th>Position</th>';
        echo '<th>Check In</th>';
        echo '<th>Check Out</th>';
        echo '<th>Hours</th>';
        echo '<th>Status</th>';
        echo '<th>Late (min)</th>';
        echo '<th>Early (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if(count($morning_export) > 0) {
            foreach($morning_export as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['department']) . '</td>';
                echo '<td>' . htmlspecialchars($row['position']) . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . number_format($row['calculated_working_hours'], 2) . '</td>';
                echo '<td style="text-align: center;">' . $row['display_status']['text'] . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] : '0') . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] : '0') . '</td>';
                echo '<td>' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="11" style="text-align: center;">No records found for morning session</td
            </tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '<br/><br/>';
        
        // AFTERNOON SESSION TABLE
        echo '<div class="section-title">AFTERNOON SESSION (13:00 - 18:00)</div>';        
        echo '<table class="report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Employee Code</th>';
        echo '<th>Employee Name</th>';
        echo '<th>Department</th>';
        echo '<th>Position</th>';
        echo '<th>Check In</th>';
        echo '<th>Check Out</th>';
        echo '<th>Hours</th>';
        echo '<th>Status</th>';
        echo '<th>Late (min)</th>';
        echo '<th>Early (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if(count($afternoon_export) > 0) {
            foreach($afternoon_export as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['department']) . '</td>';
                echo '<td>' . htmlspecialchars($row['position']) . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . number_format($row['calculated_working_hours'], 2) . '</td>';
                echo '<td style="text-align: center;">' . $row['display_status']['text'] . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] : '0') . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] : '0') . '</td>';
                echo '<td>' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="11" style="text-align: center;">No records found for afternoon session</td
            </tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // SUMMARY TABLE - Directly after afternoon section (no separator)
        $present = $late = $absent = $half_day = $holiday = $total_hours = 0;
        foreach($export_data as $row) {
            $status = $row['display_status']['status'];
            if($status == 'present') $present++;
            elseif($status == 'late') $late++;
            elseif($status == 'absent') $absent++;
            elseif($status == 'half_day') $half_day++;
            elseif($status == 'holiday') $holiday++;
            $total_hours += $row['calculated_working_hours'];
        }
        
        // Summary header and table
        echo '<br></br><table class="summary-table">';
        echo '<tr><th style="text-align: center;">Metric</th><th style="text-align: center;">Count</th></tr>';
        echo '<tr><td style="text-align: left;">Present</td><td style="text-align: center;">' . $present . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Late</td><td style="text-align: center;">' . $late . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Absent</td><td style="text-align: center;">' . $absent . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Half Day</td><td style="text-align: center;">' . $half_day . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Holiday</td><td style="text-align: center;">' . $holiday . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;"><strong>Total Working Hours</strong></td<td style="text-align: center;"><strong>' . number_format($total_hours, 1) . '</strong></td>
        </tr>';
        echo '</table>';
        
    } else {
        // MONTHLY/CUSTOM REPORT
        echo '<div class="header-title">';
        echo '<h2 style="text-align: center;">ATTENDANCE REPORT</h2>';
        if($export_report_type == 'monthly') {
            echo '<p style="text-align: center;"><strong>Report Type:</strong> Monthly Report</p>';
            echo '<p style="text-align: center;"><strong>Period:</strong> ' . date('F Y', strtotime($export_start_date)) . '</p>';
        } else {
            echo '<p style="text-align: center;"><strong>Report Type:</strong> Custom Date Range Report</p>';
            echo '<p style="text-align: center;"><strong>Period:</strong> ' . date('d-m-Y', strtotime($export_start_date)) . ' to ' . date('d-m-Y', strtotime($export_end_date)) . '</p>';
        }
        echo '<p style="text-align: center;"><strong>Employee:</strong> ' . $employee_display . '</p>';
        echo '<p style="text-align: center;"><strong>Generated On:</strong> ' . date('d-m-Y H:i:s') . '</p>';
        echo '</div>';
        echo '<br/>';
        
        echo '<table class="report-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Date</th>';
        echo '<th>Employee Code</th>';
        echo '<th>Employee Name</th>';
        echo '<th>Department</th>';
        echo '<th>Position</th>';
        echo '<th>Session</th>';
        echo '<th>Check In</th>';
        echo '<th>Check Out</th>';
        echo '<th>Hours</th>';
        echo '<th>Status</th>';
        echo '<th>Late (min)</th>';
        echo '<th>Early (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if(count($export_data) > 0) {
            foreach($export_data as $row) {
                echo '<tr>';
                echo '<td style="text-align: center;">' . date('d-m-Y', strtotime($row['record_date'])) . '</td>';
                echo '<td>' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['department']) . '</td>';
                echo '<td>' . htmlspecialchars($row['position']) . '</td>';
                echo '<td style="text-align: center;">' . (($row['session'] == 'morning') ? 'Morning' : 'Afternoon') . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td style="text-align: center;">' . number_format($row['calculated_working_hours'], 2) . '</td>';
                echo '<td style="text-align: center;">' . $row['display_status']['text'] . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] : '0') . '</td>';
                echo '<td style="text-align: center;">' . ($row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] : '0') . '</td>';
                echo '<td>' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="13" style="text-align: center;">No records found</td>
            </tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        
        // SUMMARY TABLE - Directly after main table (no separator)
        $present = $late = $absent = $half_day = $holiday = $total_hours = $total_late = $total_early = 0;

        if(!empty($export_data)) {
            foreach($export_data as $row) {
                $status = $row['display_status']['status'] ?? '';
                if($status == 'present') $present++;
                elseif($status == 'late') $late++;
                elseif($status == 'absent') $absent++;
                elseif($status == 'half_day') $half_day++;
                elseif($status == 'holiday') $holiday++;
                $total_hours += $row['calculated_working_hours'] ?? 0;
                $total_late += $row['calculated_late_minutes'] ?? 0;
                $total_early += $row['calculated_early_minutes'] ?? 0;
            }
        }
        
        // Summary header and table
        echo '<br></br><table class="summary-table">';
        echo '<tr><th style="text-align: center;">Metric</th><th style="text-align: center;">Value</th>
        </tr>';
        echo '<tr><td style="text-align: left;">Present</td><td style="text-align: center;">' . $present . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Late</td><td style="text-align: center;">' . $late . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Absent</td><td style="text-align: center;">' . $absent . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Half Day</td><td style="text-align: center;">' . $half_day . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Holiday</td><td style="text-align: center;">' . $holiday . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Total Late Minutes</td><td style="text-align: center;">' . $total_late . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;">Total Early Minutes</td><td style="text-align: center;">' . $total_early . '</td>
        </tr>';
        echo '<tr><td style="text-align: left;"><strong>Total Working Hours</strong></td><td style="text-align: center;"><strong>' . number_format($total_hours, 1) . '</strong></td>
        </tr>';
        echo '</table>';
    }

    echo '</body>';
    echo '</html>';
    
    exit();
}

include '../db_connection.php';
include 'header_admin.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Session times
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

// Helper function to get session from check_in time (fallback)
function getSessionFromTime($check_in_time) {
    if(!$check_in_time) return 'unknown';
    $hour = date('H', strtotime($check_in_time));
    return ($hour < 12) ? 'morning' : 'afternoon';
}

// Helper function to calculate working hours for a session
function calculateWorkingHours($check_in_time, $check_out_time, $session) {
    global $morning_start, $morning_end, $afternoon_start, $afternoon_end;
    
    if(!$check_in_time) return 0;
    
    $work_start = ($session == 'morning') ? $morning_start : $afternoon_start;
    $work_end = ($session == 'morning') ? $morning_end : $afternoon_end;
    
    $check_in_only = date('H:i:s', strtotime($check_in_time));
    $actual_start = ($check_in_only > $work_start) ? $check_in_only : $work_start;
    
    if($check_out_time) {
        $check_out_only = date('H:i:s', strtotime($check_out_time));
        $actual_end = ($check_out_only < $work_end) ? $check_out_only : $work_end;
        $hours = (strtotime($actual_end) - strtotime($actual_start)) / 3600;
        return round($hours > 0 ? $hours : 0, 2);
    }
    return 0;
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

// Helper function to get status for a session (from database or calculated)
function getSessionStatusDisplay($row) {
    if(isset($row['status']) && !empty($row['status'])) {
        if($row['status'] == 'present') return ['status' => 'present', 'class' => 'status-present', 'text' => 'Present'];
        if($row['status'] == 'late') return ['status' => 'late', 'class' => 'status-late', 'text' => 'Late'];
        if($row['status'] == 'half_day') return ['status' => 'half_day', 'class' => 'status-half-day', 'text' => 'Half Day'];
        if($row['status'] == 'holiday') return ['status' => 'holiday', 'class' => 'status-holiday', 'text' => 'Holiday'];
        if($row['status'] == 'absent') return ['status' => 'absent', 'class' => 'status-absent', 'text' => 'Absent'];
    }
    
    if(!$row['check_in_time']) return ['status' => 'absent', 'class' => 'status-absent', 'text' => 'Absent'];
    
    $session = $row['session'];
    $is_late = calculateLateMinutes($row['check_in_time'], $session) > 0;
    
    return $is_late ? ['status' => 'late', 'class' => 'status-late', 'text' => 'Late'] : ['status' => 'present', 'class' => 'status-present', 'text' => 'Present'];
}

$report_data = null;
$report_type = '';
$selected_employee = 'all';
$selected_employee_id = '';
$start_date = '';
$end_date = '';
$selected_month = date('m');
$selected_year = date('Y');
$summary = [];
$no_data_message = '';

$today_date = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $report_type = $_POST['report_type'] ?? '';
    $selected_employee_id = $_POST['employee_filter'] ?? 'all';
    $selected_employee = $selected_employee_id;
    
    $emp_condition = ($selected_employee_id != 'all') ? "AND a.employee_id = '$selected_employee_id'" : "";
    
    if($report_type == 'daily') {
        $start_date = $_POST['date'] ?? '';
        if($start_date > $today_date) {
            $no_data_message = "Cannot select future date. Please select a date up to " . date('d-m-Y', strtotime($today_date));
        }
        $end_date = $start_date;
        
    } elseif($report_type == 'monthly') {
        if(isset($_POST['month_year']) && !empty($_POST['month_year'])) {
            $month_year_parts = explode('-', $_POST['month_year']);
            $selected_month = $month_year_parts[1] ?? date('m');
            $selected_year = $month_year_parts[0] ?? date('Y');
        } else {
            // Fallback for old format
            $selected_month = $_POST['month'] ?? date('m');
            $selected_year = $_POST['year'] ?? date('Y');
        }
        
        $selected_month = str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        // Validate future month
        if($selected_year > $current_year || ($selected_year == $current_year && $selected_month > $current_month)) {
            $no_data_message = "Cannot select future month. Please select a month up to " . date('F Y');
        }
        
        // Calculate start and end dates for the month
        $start_date = "$selected_year-$selected_month-01";
        $end_date = date('Y-m-t', strtotime($start_date));
        
    } elseif($report_type == 'custom') {
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if($start_date > $today_date) {
            $no_data_message = "Start date cannot be in the future.";
        }
        if($end_date > $today_date) {
            $no_data_message = "End date cannot be in the future.";
        }
        if($end_date < $start_date) {
            $no_data_message = "End date cannot be earlier than start date.";
        }
    }
    
    if(empty($no_data_message)) {
        $query = "SELECT a.*, a.session as session, u.name, e.employee_code, e.department, e.position, e.employee_id, a.notes as reason
                  FROM attendance_records a
                  JOIN employees e ON a.employee_id = e.employee_id
                  JOIN users u ON e.user_id = u.user_id
                  WHERE a.record_date BETWEEN '$start_date' AND '$end_date' $emp_condition
                  ORDER BY a.record_date, a.session ASC, a.check_in_time ASC";
        
        $result = mysqli_query($conn, $query);
        $report_data = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $session = $row['session'];
            $row['session'] = $session;
            $row['calculated_working_hours'] = calculateWorkingHours($row['check_in_time'], $row['check_out_time'], $session);
            $row['calculated_late_minutes'] = calculateLateMinutes($row['check_in_time'], $session);
            $row['calculated_early_minutes'] = calculateEarlyLeaveMinutes($row['check_out_time'], $session);
            $row['display_status'] = getSessionStatusDisplay($row);
            $report_data[] = $row;
        }
        
        if(count($report_data) == 0) {
            $no_data_message = "No attendance records found for the selected criteria.";
        }
        
        $summary = [
            'total_records' => count($report_data),
            'present' => 0, 'late' => 0, 'absent' => 0,
            'half_day' => 0, 'holiday' => 0,
            'total_hours' => 0, 'total_late_minutes' => 0, 'total_early_minutes' => 0
        ];
        
        foreach($report_data as $row) {
            $status = $row['display_status']['status'];
            if($status == 'present') $summary['present']++;
            elseif($status == 'late') $summary['late']++;
            elseif($status == 'half_day') $summary['half_day']++;
            elseif($status == 'holiday') $summary['holiday']++;
            else $summary['absent']++;
            
            $summary['total_hours'] += $row['calculated_working_hours'];
            $summary['total_late_minutes'] += $row['calculated_late_minutes'];
            $summary['total_early_minutes'] += $row['calculated_early_minutes'];
        }
    }
}

$morning_data = [];
$afternoon_data = [];
if($report_type == 'daily' && $report_data) {
    foreach($report_data as $row) {
        if($row['session'] == 'morning') {
            $morning_data[] = $row;
        } else {
            $afternoon_data[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report Results</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 80px;
            margin-top: 60px;
        }
        
        .main-container {
            max-width: 1400px;
            margin: 20px auto 40px;
            padding: 0 20px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #ddd;
            overflow: hidden;
        }
        
        .card-header {
            background: #dc3545;
            color: white;
            padding: 12px 20px;
            font-weight: bold;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            justify-content: flex-end;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-excel {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .btn-excel:hover {
            background: #218838;
        }
        
        .btn-back {
            background: #007bff;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-back:hover {
            background: #0056b3;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        
        .data-table th {
            background: #343a40;
            color: white;
            padding: 10px;
            text-align: left;
        }
        
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
        }
        
        .status-present { background: #28a745; color: white; }
        .status-late { background: #ffc107; color: #333; }
        .status-absent { background: #dc3545; color: white; }
        .status-half-day { background: #6f42c1; color: white; }
        .status-holiday { background: #007bff; color: white; }
        
        .session-morning { background: #e8f4fd; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        .session-afternoon { background: #fff4e6; padding: 2px 6px; border-radius: 4px; font-size: 11px; }
        
        .summary-box {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-around;
            flex-wrap: wrap;
            gap: 10px;
            border: 1px solid #eee;
        }
        
        .summary-item {
            text-align: center;
        }
        
        .summary-item h4 {
            font-size: 20px;
            color: #333;
        }
        
        .summary-item p {
            color: #666;
            font-size: 10px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin: 15px 0 10px 0;
            padding: 8px 12px;
            background: #007bff;
            color: white;
            border-radius: 5px;
        }
        
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 14px;
        }
        
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 15px;
            border: 1px solid #f5c6cb;
            text-align: center;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .reason-text {
            font-size: 11px;
            color: #666;
            max-width: 200px;
            word-wrap: break-word;
        }
        
        .reason-label {
            font-size: 10px;
            color: #999;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="header-actions">
        <a href="report.php" class="btn-back">← Back to Report Form</a>
        <div class="action-buttons">
            <?php if($report_data !== null && count($report_data) > 0): ?>
                <?php
                // Build export URL with current parameters
                $export_params = array(
                    'export_excel' => 1,
                    'report_type' => $report_type,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'employee_id' => $selected_employee_id
                );
                $export_url = '?' . http_build_query($export_params);
                ?>
                <a href="<?php echo $export_url; ?>" class="btn-excel" style="text-decoration: none; display: inline-block;">📊 Export to Excel</a>
            <?php endif; ?>
            <button class="btn-secondary" onclick="window.print()">🖨️ Print Report</button>
            <button class="btn-secondary" onclick="exportToPDF()">📄 Export to PDF</button>
        </div>
    </div>
    
    <?php if($no_data_message): ?>
        <div class="card">
            <div class="card-body">
                <div class="error-message">
                    ⚠️ <?php echo $no_data_message; ?>
                </div>
            </div>
        </div>
    <?php elseif($report_data !== null && count($report_data) > 0): ?>
        <div class="card" id="reportContent">
            <div class="card-header">
                <?php 
                if($report_type == 'daily') {
                    echo 'Daily Report - ' . date('d-m-Y', strtotime($start_date));
                } elseif($report_type == 'monthly') {
                    echo 'Monthly Report - ' . date('F Y', strtotime("$selected_year-$selected_month-01"));
                } else {
                    echo 'Custom Report - ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date));
                }
                echo ($selected_employee != 'all') ? ' (Selected Employee)' : ' (All Employees)';
                ?>
            </div>
            <div class="card-body">
                <div class="summary-box">  
                    <div class="summary-item">
                        <h4 style="color: #28a745;"><?php echo $summary['present']; ?></h4>
                        <p>Present</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #ffc107;"><?php echo $summary['late']; ?></h4>
                        <p>Late</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #6f42c1;"><?php echo $summary['half_day']; ?></h4>
                        <p>Half Day</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #007bff;"><?php echo $summary['holiday']; ?></h4>
                        <p>Holiday</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #dc3545;"><?php echo $summary['absent']; ?></h4>
                        <p>Absent</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #17a2b8;"><?php echo number_format($summary['total_hours'], 1); ?></h4>
                        <p>Total Hours</p>
                    </div>
                    <div class="summary-item">
                        <h4 style="color: #dc3545;"><?php echo $summary['total_late_minutes']; ?></h4>
                        <p>Late Mins</p>
                    </div>
                </div>
                
                <?php if($report_type == 'daily'): ?>
                    <!-- Morning Session Table -->
                    <div class="section-title">
                        🌅 Morning Session (9:00 - 12:00)
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Late</th>
                                    <th>Early</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($morning_data) > 0): ?>
                                    <?php foreach($morning_data as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                        <td><?php echo htmlspecialchars($row['name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td><?php echo htmlspecialchars($row['position']); ?></td>
                                        <td><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                        <td><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                        <td><?php echo number_format($row['calculated_working_hours'], 2); ?></td>
                                        <td><span class="status-badge <?php echo $row['display_status']['class']; ?>"><?php echo $row['display_status']['text']; ?></span></td>
                                        <td><?php echo $row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] . ' min' : '-'; ?></td>
                                        <td><?php echo $row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] . ' min' : '-'; ?></td>
                                        <td class="reason-text">
                                            <?php if(!empty($row['reason'])): ?>
                                                <div class="reason-label">Reason:</div>
                                                <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="no-data">No morning session records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Afternoon Session Table -->
                    <div class="section-title">
                        🌙 Afternoon Session (13:00 - 18:00)
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Late</th>
                                    <th>Early</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($afternoon_data) > 0): ?>
                                    <?php foreach($afternoon_data as $row): ?>
                                    <tr>
                                        <td><?php echo $row['employee_code']; ?></td>
                                        <td><?php echo $row['name']; ?></td>
                                        <td><?php echo $row['department']; ?></td>
                                        <td><?php echo $row['position']; ?></td>
                                        <td><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                        <td><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                        <td><?php echo number_format($row['calculated_working_hours'], 2); ?></td>
                                        <td><span class="status-badge <?php echo $row['display_status']['class']; ?>"><?php echo $row['display_status']['text']; ?></span></td>
                                        <td><?php echo $row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] . ' min' : '-'; ?></td>
                                        <td><?php echo $row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] . ' min' : '-'; ?></td>
                                        <td class="reason-text">
                                            <?php if(!empty($row['reason'])): ?>
                                                <div class="reason-label">Reason:</div>
                                                <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="no-data">No afternoon session records found</td
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Session</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Hours</th>
                                    <th>Status</th>
                                    <th>Late</th>
                                    <th>Early</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($report_data as $row): ?>
                                <tr>
                                    <td><?php echo date('d-m-Y', strtotime($row['record_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['department']); ?></td>
                                    <td><?php echo htmlspecialchars($row['position']); ?></td>
                                    <td class="<?php echo ($row['session'] == 'morning') ? 'session-morning' : 'session-afternoon'; ?>">
                                        <?php echo ($row['session'] == 'morning') ? 'Morning' : 'Afternoon'; ?>
                                    </td>
                                    <td><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                    <td><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                    <td><?php echo number_format($row['calculated_working_hours'], 2); ?></td>
                                    <td><span class="status-badge <?php echo $row['display_status']['class']; ?>"><?php echo $row['display_status']['text']; ?></span></td>
                                    <td><?php echo $row['calculated_late_minutes'] > 0 ? $row['calculated_late_minutes'] . ' min' : '-'; ?></td>
                                    <td><?php echo $row['calculated_early_minutes'] > 0 ? $row['calculated_early_minutes'] . ' min' : '-'; ?></td>
                                    <td class="reason-text">
                                        <?php if(!empty($row['reason'])): ?>
                                            <div class="reason-label">Reason:</div>
                                            <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function exportToPDF() {
        const element = document.getElementById('reportContent');
        const opt = {
            margin: [0.5, 0.5, 0.5, 0.5],
            filename: 'attendance_report.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2, letterRendering: true },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
</body>
</html>