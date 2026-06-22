<?php
session_start();

require('../fpdf/fpdf.php');
include '../db_connection.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Session times
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

function getEmployeeDisplayName($conn, $employee_id) {
    if($employee_id == 'all') return 'All Employees';
    $query = "SELECT u.name, e.employee_code FROM employees e JOIN users u ON e.user_id = u.user_id WHERE e.employee_id = '$employee_id'";
    $result = mysqli_query($conn, $query);
    if($row = mysqli_fetch_assoc($result)) {
        return $row['employee_code'] . ' - ' . $row['name'];
    }
    return 'Selected Employee';
}

function calculateEarlyMinutesExport($check_out_time, $session) {
    global $morning_end, $afternoon_end;
    if(!$check_out_time) return 0;
    
    $work_end = ($session == 'morning') ? $morning_end : $afternoon_end;
    $check_out_only = date('H:i:s', strtotime($check_out_time));
    
    if($check_out_only < $work_end) {
        return round((strtotime($work_end) - strtotime($check_out_only)) / 60);
    }
    return 0;
}

//helper function
function getStatusInfo($actual_status) {
    $status_info = [
        'status_class' => '',
        'display_text' => ''
    ];
    
    switch($actual_status) {
        case 'left_early':
            $status_info['status_class'] = 'status-early-left';
            $status_info['display_text'] = 'Early Left';
            break;
        case 'late_early':
            $status_info['status_class'] = 'status-late-early';
            $status_info['display_text'] = 'Late + Early';
            break;
        case 'present':
            $status_info['status_class'] = 'status-present';
            $status_info['display_text'] = 'Present';
            break;
        case 'late':
            $status_info['status_class'] = 'status-late';
            $status_info['display_text'] = 'Late';
            break;
        case 'half_day':
            $status_info['status_class'] = 'status-half-day';
            $status_info['display_text'] = 'Half Day';
            break;
        case 'holiday':
            $status_info['status_class'] = 'status-holiday';
            $status_info['display_text'] = 'Holiday';
            break;
        case 'absent':
            $status_info['status_class'] = 'status-absent';
            $status_info['display_text'] = 'Absent';
            break;
        default:
            $status_info['status_class'] = 'status-absent';
            $status_info['display_text'] = 'Absent';
            break;
    }
    
    return $status_info;
}

function getStatusClass($actual_status) {
    switch($actual_status) {
        case 'left_early':
            return 'status-early-left';
        case 'late_early':
            return 'status-late-early';
        case 'present':
            return 'status-present';
        case 'late':
            return 'status-late';
        case 'half_day':
            return 'status-half-day';
        case 'holiday':
            return 'status-holiday';
        case 'absent':
            return 'status-absent';
        default:
            return 'status-absent';
    }
}

function getStatusText($actual_status) {
    switch($actual_status) {
        case 'left_early':
            return 'Early Left';
        case 'late_early':
            return 'Late + Early';
        case 'present':
            return 'Present';
        case 'late':
            return 'Late';
        case 'half_day':
            return 'Half Day';
        case 'holiday':
            return 'Holiday';
        case 'absent':
            return 'Absent';
        default:
            return 'Absent';
    }
}

function getSessionStatusDisplay($row) {
    if(isset($row['status']) && !empty($row['status'])) {
        if($row['status'] == 'present') return ['status' => 'present', 'class' => 'status-present', 'text' => 'Present'];
        if($row['status'] == 'late') return ['status' => 'late', 'class' => 'status-late', 'text' => 'Late'];
        if($row['status'] == 'half_day') return ['status' => 'half_day', 'class' => 'status-half-day', 'text' => 'Half Day'];
        if($row['status'] == 'holiday') return ['status' => 'holiday', 'class' => 'status-holiday', 'text' => 'Holiday'];
        if($row['status'] == 'absent') return ['status' => 'absent', 'class' => 'status-absent', 'text' => 'Absent'];
        if($row['status'] == 'left_early') return ['status' => 'left_early', 'class' => 'status-early-left', 'text' => 'Early Left'];
        if($row['status'] == 'late_early') return ['status' => 'late_early', 'class' => 'status-late-early', 'text' => 'Late + Early'];
    }
    
    if(!$row['check_in_time']) return ['status' => 'absent', 'class' => 'status-absent', 'text' => 'Absent'];
    
    $is_late = ($row['late_minutes'] > 0);
    return $is_late ? ['status' => 'late', 'class' => 'status-late', 'text' => 'Late'] : ['status' => 'present', 'class' => 'status-present', 'text' => 'Present'];
}

//status sorting priority
function getStatusPriority($status) {
    $priority = [
        'present' => 1,
        'late' => 2,
        'half_day' => 3,
        'holiday' => 4,
        'left_early' => 5,
        'late_early' => 6,
        'absent' => 7,
        null => 8
    ];
    return $priority[$status] ?? 8;
}

//PDF file export
if(isset($_GET['export_pdf']) && $_GET['export_pdf'] == '1') {
    // Get export parameters
    $export_report_type = $_GET['report_type'] ?? '';
    $export_start_date = $_GET['start_date'] ?? '';
    $export_end_date = $_GET['end_date'] ?? '';
    $export_selected_employee = $_GET['employee_id'] ?? 'all';
    
    $emp_condition = ($export_selected_employee != 'all') ? "AND a.employee_id = '$export_selected_employee'" : "";
    
    $query = "SELECT a.*, a.session as session, u.name, e.employee_code, e.department, e.position, e.employee_id, a.notes as reason, a.working_hours, a.late_minutes
          FROM attendance_records a
          JOIN employees e ON a.employee_id = e.employee_id
          JOIN users u ON e.user_id = u.user_id
          WHERE a.record_date BETWEEN '$export_start_date' AND '$export_end_date' $emp_condition
          ORDER BY 
              a.record_date,
              CASE 
                  WHEN a.status = 'present' THEN 1
                  WHEN a.status = 'late' THEN 2
                  WHEN a.status = 'half_day' THEN 3
                  WHEN a.status = 'holiday' THEN 4
                  WHEN a.status = 'left_early' THEN 5
                  WHEN a.status = 'late_early' THEN 6
                  WHEN a.status = 'absent' THEN 7
                  ELSE 8
              END,
              a.session ASC,
              u.name ASC";
    
    $result = mysqli_query($conn, $query);
    $export_data = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $session = $row['session'];
        $row['session'] = $session;
        $row['early_minutes'] = calculateEarlyMinutesExport($row['check_out_time'], $session);
        
        $status_info = getStatusInfo($row['status']);
        $row['display_status_text'] = $status_info['display_text'];
        $row['status_class'] = $status_info['status_class'];
        
        $export_data[] = $row;
    }
    
    //File name
    if($export_report_type == 'daily') {
        $date_display = date('d-m-Y', strtotime($export_start_date));
        $employee_name = ($export_selected_employee != 'all') ? getEmployeeDisplayName($conn, $export_selected_employee) : 'All_Employees';
        $clean_employee_name = preg_replace('/[^A-Za-z0-9]/', '_', $employee_name);
        $filename = "Daily_Attendance_Report_{$clean_employee_name}_{$date_display}.pdf";
        $report_title = "Daily Report - " . $date_display . " (" . $employee_name . ")";
    } elseif($export_report_type == 'monthly') {
        $month_year = date('F Y', strtotime($export_start_date));
        $employee_name = ($export_selected_employee != 'all') ? getEmployeeDisplayName($conn, $export_selected_employee) : 'All_Employees';
        $clean_employee_name = preg_replace('/[^A-Za-z0-9]/', '_', $employee_name);
        $filename = "Monthly_Attendance_Report_{$clean_employee_name}_{$month_year}.pdf";
        $report_title = "Monthly Report - " . $month_year . " (" . $employee_name . ")";
    } else {
        $start_display = date('d-m-Y', strtotime($export_start_date));
        $end_display = date('d-m-Y', strtotime($export_end_date));
        $employee_name = ($export_selected_employee != 'all') ? getEmployeeDisplayName($conn, $export_selected_employee) : 'All_Employees';
        $clean_employee_name = preg_replace('/[^A-Za-z0-9]/', '_', $employee_name);
        $filename = "Custom_Attendance_Report_{$clean_employee_name}_{$start_display}_to_{$end_display}.pdf";
        $report_title = "Custom Report - " . $start_display . " - " . $end_display . " (" . $employee_name . ")";
    }

    class PDF extends FPDF {
        function Header() {
            if($this->PageNo() == 1) {
                $this->Ln(5);
            }
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' | Generated: ' . date('d-m-Y H:i:s'), 0, 0, 'C');
        }
        
        function ReportTable($header, $data, $colWidths) {
            $this->SetFont('Arial', 'B', 7);
            $this->SetFillColor(52, 58, 64);
            $this->SetTextColor(255, 255, 255);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.2);
            
            for($i = 0; $i < count($header); $i++) {
                $this->Cell($colWidths[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 6);
            $this->SetFillColor(255, 255, 255);
            $fill = false;
            
            foreach($data as $row) {
                if($this->GetY() > 250) {
                    $this->AddPage();
                    $this->SetFont('Arial', 'B', 7);
                    $this->SetFillColor(52, 58, 64);
                    $this->SetTextColor(255, 255, 255);
                    for($i = 0; $i < count($header); $i++) {
                        $this->Cell($colWidths[$i], 7, $header[$i], 1, 0, 'C', true);
                    }
                    $this->Ln();
                    $this->SetTextColor(0, 0, 0);
                    $this->SetFont('Arial', '', 6);
                    $this->SetFillColor(255, 255, 255);
                }
                
                for($i = 0; $i < count($row); $i++) {
                    $cell_content = (strlen($row[$i]) > 25) ? substr($row[$i], 0, 22) . '...' : $row[$i];
                    $this->Cell($colWidths[$i], 5, $cell_content, 1, 0, 'L', $fill);
                }
                $this->Ln();
                $fill = !$fill;
            }
        }
        
        function SummaryTable($header, $data, $colWidths) {
            $this->SetFont('Arial', 'B', 8);
            $this->SetFillColor(0, 0, 0);
            $this->SetTextColor(255, 255, 255);
            $this->SetDrawColor(0, 0, 0);
            $this->SetLineWidth(0.2);
            
            for($i = 0; $i < count($header); $i++) {
                $this->Cell($colWidths[$i], 7, $header[$i], 1, 0, 'C', true);
            }
            $this->Ln();
            
            $this->SetTextColor(0, 0, 0);
            $this->SetFont('Arial', '', 7);
            $this->SetFillColor(255, 255, 255);
            $fill = false;
            
            foreach($data as $row) {
                for($i = 0; $i < count($row); $i++) {
                    $this->Cell($colWidths[$i], 6, $row[$i], 1, 0, 'C', $fill);
                }
                $this->Ln();
                $fill = !$fill;
            }
        }
    }

    $pdf = new PDF('L', 'mm', 'A4');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetMargins(8, 10, 8);
    $pdf->SetAutoPageBreak(true, 15);

    // Report Title
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 10, $report_title, 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 7, 'Generated On: ' . date('d-m-Y H:i:s'), 0, 1, 'C');
    $pdf->Ln(8);
    
    // DAILY REPORT
    if($export_report_type == 'daily') {
        // Separate morning and afternoon sessions
        $morning_export = [];
        $afternoon_export = [];
        foreach($export_data as $row) {
            if($row['session'] == 'morning') {
                $morning_export[] = $row;
            } else {
                $afternoon_export[] = $row;
            }
        }
        
        // MORNING SESSION
        if(count($morning_export) > 0) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 7, 'MORNING SESSION (9:00 - 12:00)', 0, 1, 'C');
            $pdf->Ln(2);
            
            $header = array('Emp Code', 'Name', 'Department', 'Position', 'Check In', 'Check Out', 'Hours', 'Status', 'Late (min)', 'Early Left (min)', 'Reason');
            $colWidths = array(16, 30, 25, 25, 20, 20, 12, 16, 16, 19, 40);
            
            $data = [];
            foreach($morning_export as $row) {
                $data[] = array(
                    $row['employee_code'],
                    $row['name'],
                    $row['department'],
                    $row['position'],
                    $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-',
                    $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-',
                    number_format($row['working_hours'], 2),
                    $row['display_status_text'],
                    $row['late_minutes'] > 0 ? $row['late_minutes'] : '-',
                    $row['early_minutes'] > 0 ? $row['early_minutes'] : '-',
                    substr(str_replace(array("\r\n", "\n", "\r"), " ", $row['reason'] ?? '-'), 0, 30)
                );
            }
            $pdf->ReportTable($header, $data, $colWidths);
            $pdf->Ln(3);
        }
        
        // AFTERNOON SESSION
        if(count($afternoon_export) > 0) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 7, 'AFTERNOON SESSION (13:00 - 18:00)', 0, 1, 'C');
            $pdf->Ln(2);
            
            $header = array('Emp Code', 'Name', 'Department', 'Position', 'Check In', 'Check Out', 'Hours', 'Status', 'Late (min)', 'Early Left (min)', 'Reason');
            $colWidths = array(16, 30, 25, 25, 20, 20, 12, 16, 16, 19, 40);
            
            $data = [];
            foreach($afternoon_export as $row) {
                $data[] = array(
                    $row['employee_code'],
                    $row['name'],
                    $row['department'],
                    $row['position'],
                    $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-',
                    $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-',
                    number_format($row['working_hours'], 2),
                    $row['display_status_text'],
                    $row['late_minutes'] > 0 ? $row['late_minutes'] : '-',
                    $row['early_minutes'] > 0 ? $row['early_minutes'] : '-',
                    substr(str_replace(array("\r\n", "\n", "\r"), " ", $row['reason'] ?? '-'), 0, 30)
                );
            }
            $pdf->ReportTable($header, $data, $colWidths);
            $pdf->Ln(3);
        }
        
        // EMPLOYEE SUMMARY TABLE
        $employee_daily_status = [];
        foreach($export_data as $row) {
            $emp_id = $row['employee_id'];
            if (!isset($employee_daily_status[$emp_id])) {
                $employee_daily_status[$emp_id] = [
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['name'],
                    'morning_status' => '-',
                    'afternoon_status' => '-',
                    'total_hours' => 0,
                    'total_late' => 0,
                    'total_early' => 0
                ];
            }
            
            if ($row['session'] == 'morning') {
                $employee_daily_status[$emp_id]['morning_status'] = $row['display_status_text'];
            } else {
                $employee_daily_status[$emp_id]['afternoon_status'] = $row['display_status_text'];
            }
            
            $employee_daily_status[$emp_id]['total_hours'] += $row['working_hours'];
            $employee_daily_status[$emp_id]['total_late'] += $row['late_minutes'];
            $employee_daily_status[$emp_id]['total_early'] += $row['early_minutes'];
        }
        
        // Sort by total hours descending
        usort($employee_daily_status, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        $pdf->Ln(5);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, 'EMPLOYEE SUMMARY', 0, 1, 'L');
        $pdf->Ln(2);
        
        $emp_header = array('Emp Code', 'Employee Name', 'Morning', 'Afternoon', 'Total Hours', 'Late (min)', 'Early Left (min)');
        $emp_colWidths = array(20, 40, 20, 20, 20, 16, 21);
        $emp_data = [];
        
        foreach($employee_daily_status as $data) {
            $emp_data[] = array(
                $data['employee_code'],
                substr($data['employee_name'], 0, 20),
                $data['morning_status'],
                $data['afternoon_status'],
                number_format($data['total_hours'], 2),
                $data['total_late'] > 0 ? $data['total_late'] : '-',
                $data['total_early'] > 0 ? $data['total_early'] : '-'
            );
        }
        $pdf->SummaryTable($emp_header, $emp_data, $emp_colWidths);
        
    } else {
        // MONTHLY/CUSTOM REPORT
        $header = array('Date', 'Code', 'Name', 'Department', 'Position', 'Session', 'In', 'Out', 'Hrs', 'Status', 'Late (min)', 'Early Left (min)', 'Reason');
        $colWidths = array(20, 16, 28, 25, 25, 12, 16, 16, 12, 18, 16, 19, 32);
        
        // Print main header
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(52, 58, 64);
        $pdf->SetTextColor(255, 255, 255);
        for($i = 0; $i < count($header); $i++) {
            $pdf->Cell($colWidths[$i], 7, $header[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();
        
        $current_date = '';
        $pdf->SetFont('Arial', '', 6);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(255, 255, 255);
        $fill = false;
        
        foreach($export_data as $row) {
            $row_date = date('d-m-Y', strtotime($row['record_date']));
            
            if($pdf->GetY() > 250) {
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 7);
                $pdf->SetFillColor(52, 58, 64);
                $pdf->SetTextColor(255, 255, 255);
                for($i = 0; $i < count($header); $i++) {
                    $pdf->Cell($colWidths[$i], 7, $header[$i], 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFillColor(255, 255, 255);
            }
            
            if($current_date != '' && $current_date != $row_date) {
                $pdf->Ln(1);
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(array_sum($colWidths), 6, $row_date, 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetFillColor(255, 255, 255);
                $fill = !$fill;
            } elseif($current_date == '') {
                $pdf->SetFont('Arial', 'B', 8);
                $pdf->SetFillColor(245, 245, 245);
                $pdf->SetTextColor(0, 0, 0);
                $pdf->Cell(array_sum($colWidths), 6, $row_date, 1, 1, 'C', true);
                $pdf->SetFont('Arial', '', 6);
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $current_date = $row_date;
            
            $pdf->Cell($colWidths[0], 5, $row_date, 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[1], 5, $row['employee_code'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[2], 5, $row['name'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[3], 5, $row['department'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[4], 5, $row['position'], 1, 0, 'L', $fill);
            $pdf->Cell($colWidths[5], 5, ($row['session'] == 'morning') ? 'AM' : 'PM', 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[6], 5, $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-', 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[7], 5, $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-', 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[8], 5, number_format($row['working_hours'], 2), 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[9], 5, $row['display_status_text'], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[10], 5, $row['late_minutes'] > 0 ? $row['late_minutes'] : '-', 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[11], 5, $row['early_minutes'] > 0 ? $row['early_minutes'] : '-', 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[12], 5, substr(str_replace(array("\r\n", "\n", "\r"), " ", $row['reason'] ?? '-'), 0, 25), 1, 0, 'L', $fill);
            $pdf->Ln();
            $fill = !$fill;
        }
        
        $pdf->Ln(8);
        
        // EMPLOYEE SUMMARY TABLE for MONTHLY/CUSTOM REPORT
        $monthly_employee_summary = [];
        foreach($export_data as $row) {
            $emp_id = $row['employee_id'];
            if (!isset($monthly_employee_summary[$emp_id])) {
                $monthly_employee_summary[$emp_id] = [
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['name'],
                    'total_hours' => 0,
                    'total_late' => 0,
                    'total_early' => 0,
                    'present_count' => 0,
                    'late_count' => 0,
                    'absent_count' => 0,
                    'half_day_count' => 0,
                    'holiday_count' => 0,
                    'left_early_count' => 0,
                    'late_early_count' => 0
                ];
            }
            
            $status = $row['status'];
            if($status == 'present') $monthly_employee_summary[$emp_id]['present_count']++;
            elseif($status == 'late') $monthly_employee_summary[$emp_id]['late_count']++;
            elseif($status == 'absent') $monthly_employee_summary[$emp_id]['absent_count']++;
            elseif($status == 'half_day') $monthly_employee_summary[$emp_id]['half_day_count']++;
            elseif($status == 'holiday') $monthly_employee_summary[$emp_id]['holiday_count']++;
            elseif($status == 'left_early') $monthly_employee_summary[$emp_id]['left_early_count']++;
            elseif($status == 'late_early') $monthly_employee_summary[$emp_id]['late_early_count']++;
            
            $monthly_employee_summary[$emp_id]['total_hours'] += $row['working_hours'];
            $monthly_employee_summary[$emp_id]['total_late'] += $row['late_minutes'];
            $monthly_employee_summary[$emp_id]['total_early'] += $row['early_minutes'];
        }
        
        // Sort by total hours descending
        usort($monthly_employee_summary, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, 'EMPLOYEE SUMMARY', 0, 1, 'C');
        $pdf->Ln(2);
        
        $emp_header = array('Code', 'Name', 'Present', 'Late', 'Early Left', 'Late+Early', 'Half', 'Holiday', 'Absent', 'Total Hrs', 'Late (min)', 'Early (min)');
        $emp_colWidths = array(16, 30, 12, 10, 20, 20, 10, 15, 15, 16, 14, 18);
        $emp_data = [];
        
        foreach($monthly_employee_summary as $data) {
            $emp_data[] = array(
                $data['employee_code'],
                substr($data['employee_name'], 0, 18),
                $data['present_count'],
                $data['late_count'],
                $data['left_early_count'],
                $data['late_early_count'],
                $data['half_day_count'],
                $data['holiday_count'],
                $data['absent_count'],
                number_format($data['total_hours'], 2),
                $data['total_late'] > 0 ? $data['total_late'] : '-',
                $data['total_early'] > 0 ? $data['total_early'] : '-'
            );
        }
        $pdf->SummaryTable($emp_header, $emp_data, $emp_colWidths);
    }
    
    $pdf->Output('D', $filename);
    exit();
}

//Excel file export
if(isset($_GET['export_excel']) && $_GET['export_excel'] == '1') {
    include '../db_connection.php';
    
    date_default_timezone_set('Asia/Kuala_Lumpur');
    
    // Session times
    $morning_start = '09:00:00';
    $morning_end = '12:00:00';
    $afternoon_start = '13:00:00';
    $afternoon_end = '18:00:00';
    
    // Get export parameters
    $export_report_type = $_GET['report_type'] ?? '';
    $export_start_date = $_GET['start_date'] ?? '';
    $export_end_date = $_GET['end_date'] ?? '';
    $export_selected_employee = $_GET['employee_id'] ?? 'all';
    
    $emp_condition = ($export_selected_employee != 'all') ? "AND a.employee_id = '$export_selected_employee'" : "";
    
    $query = "SELECT a.*, a.session as session, u.name, e.employee_code, e.department, e.position, e.employee_id, a.notes as reason, a.working_hours, a.late_minutes
              FROM attendance_records a
              JOIN employees e ON a.employee_id = e.employee_id
              JOIN users u ON e.user_id = u.user_id
              WHERE a.record_date BETWEEN '$export_start_date' AND '$export_end_date' $emp_condition
              ORDER BY 
                  a.record_date,
                  CASE 
                      WHEN a.status = 'present' THEN 1
                      WHEN a.status = 'late' THEN 2
                      WHEN a.status = 'half_day' THEN 3
                      WHEN a.status = 'holiday' THEN 4
                      WHEN a.status = 'left_early' THEN 5
                      WHEN a.status = 'late_early' THEN 6
                      WHEN a.status = 'absent' THEN 7
                      ELSE 8
                  END,
                  a.session ASC,
                  u.name ASC";
    
    $result = mysqli_query($conn, $query);
    $export_data = [];
    
    while($row = mysqli_fetch_assoc($result)) {
        $session = $row['session'];
        $row['session'] = $session;
        $row['early_minutes'] = calculateEarlyMinutesExport($row['check_out_time'], $session);
        
        $status_info = getStatusInfo($row['status']);
        $row['display_status_text'] = $status_info['display_text'];
        $row['status_class'] = $status_info['status_class'];
        
        $export_data[] = $row;
    }
    
    // Generate filename with employee name
    $employee_name_for_file = ($export_selected_employee != 'all') ? getEmployeeDisplayName($conn, $export_selected_employee) : 'All_Employees';
    $clean_employee_name = preg_replace('/[^A-Za-z0-9]/', '_', $employee_name_for_file);
    
    if($export_report_type == 'daily') {
        $date_display = date('d-m-Y', strtotime($export_start_date));
        $filename = "Daily_Attendance_Report_{$clean_employee_name}_{$date_display}.xls";
        $report_title = "Daily Report - " . $date_display . " (" . $employee_name_for_file . ")";
    } elseif($export_report_type == 'monthly') {
        $month_year = date('F Y', strtotime($export_start_date));
        $filename = "Monthly_Attendance_Report_{$clean_employee_name}_{$month_year}.xls";
        $report_title = "Monthly Report - " . $month_year . " (" . $employee_name_for_file . ")";
    } else {
        $start_display = date('d-m-Y', strtotime($export_start_date));
        $end_display = date('d-m-Y', strtotime($export_end_date));
        $filename = "Custom_Attendance_Report_{$clean_employee_name}_{$start_display}_to_{$end_display}.xls";
        $report_title = "Custom Report - " . $start_display . " - " . $end_display . " (" . $employee_name_for_file . ")";
    }
    
    // Set headers for Excel download
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    
    // Start HTML table format for Excel
    echo "<!DOCTYPE html>\n";
    echo "<html>\n";
    echo "<head>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<title>" . htmlspecialchars($report_title) . "</title>\n";
    echo "<style>\n";
    echo "body { font-family: Arial, sans-serif; }\n";
    echo ".report-table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }\n";
    echo ".report-table th, .report-table td { border: 1px solid #000000; padding: 8px; }\n";
    echo ".report-table th { background-color: #e0e0e0; font-weight: bold; text-align: center; }\n";
    echo ".report-table td { text-align: left; }\n";
    echo ".section-title { font-size: 14pt; font-weight: bold; margin: 20px 0 10px 0; text-align: center; background-color: #4472C4; color: #000000; padding: 5px; }\n";
    echo ".section-title-summary { font-size: 14pt; font-weight: bold; margin: 20px 0 10px 0; text-align: center; background-color: #4472C4; color: #000000; padding: 5px; }\n";
    echo ".summary-table { border-collapse: collapse; width: 80%; margin: 20px auto 0 auto; }\n";
    echo ".summary-table th, .summary-table td { border: 1px solid #000000; padding: 6px; }\n";
    echo ".summary-table th { font-weight: bold; background-color: #e0e0e0; text-align: center; }\n";
    echo ".header-title { text-align: center; margin-bottom: 20px; }\n";
    echo ".date-separator-row td { background-color: #f5f5f5; font-weight: bold; text-align: center; }\n";
    echo "td.text-center { text-align: center; }\n";
    echo "td.text-left { text-align: left; }\n";
    echo "</style>\n";
    echo "</head>\n";
    echo "<body>\n";
    
    // Report Header
    echo '<div class="header-title">';
    echo '<h2>' . htmlspecialchars($report_title) . '</h2>';
    echo '<p><strong>Generated On:</strong> ' . date('d-m-Y H:i:s') . '</p>';
    echo '</div>';
    
    echo '<br/>';
    
    // DAILY REPORT
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
        echo '<th>Early Left (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if(count($morning_export) > 0) {
            foreach($morning_export as $row) {
                echo '<tr>';
                echo '<td class="text-left">' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['name']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['department']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['position']) . '</td>';
                echo '<td class="text-center">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . number_format($row['working_hours'], 2) . '</td>';
                echo '<td class="text-center">' . $row['display_status_text'] . '</td>';
                echo '<td class="text-center">' . ($row['late_minutes'] > 0 ? $row['late_minutes'] : '-') . '</td>';
                echo '<td class="text-center">' . ($row['early_minutes'] > 0 ? $row['early_minutes'] : '-') . '</td>';
                echo '<td class="text-left">' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
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
        echo '<th>Early Left (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        if(count($afternoon_export) > 0) {
            foreach($afternoon_export as $row) {
                echo '<tr>';
                echo '<td class="text-left">' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['name']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['department']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['position']) . '</td>';
                echo '<td class="text-center">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . number_format($row['working_hours'], 2) . '</td>';
                echo '<td class="text-center">' . $row['display_status_text'] . '</td>';
                echo '<td class="text-center">' . ($row['late_minutes'] > 0 ? $row['late_minutes'] : '-') . '</td>';
                echo '<td class="text-center">' . ($row['early_minutes'] > 0 ? $row['early_minutes'] : '-') . '</td>';
                echo '<td class="text-left">' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="11" style="text-align: center;">No records found for afternoon session</td
            </tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '<br/>';
        echo '<br/>';
        
        // Employee Summary Table for Daily
        $employee_daily_status = [];
        foreach($export_data as $row) {
            $emp_id = $row['employee_id'];
            if (!isset($employee_daily_status[$emp_id])) {
                $employee_daily_status[$emp_id] = [
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['name'],
                    'total_hours' => 0,
                    'total_late' => 0,
                    'total_early' => 0,
                    'morning_status' => '-',
                    'afternoon_status' => '-'
                ];
            }
            $employee_daily_status[$emp_id]['total_hours'] += $row['working_hours'];
            $employee_daily_status[$emp_id]['total_late'] += $row['late_minutes'];
            $employee_daily_status[$emp_id]['total_early'] += $row['early_minutes'];
            
            if ($row['session'] == 'morning') {
                $employee_daily_status[$emp_id]['morning_status'] = $row['display_status_text'];
            } else {
                $employee_daily_status[$emp_id]['afternoon_status'] = $row['display_status_text'];
            }
        }
        
        // Sort by total hours descending
        usort($employee_daily_status, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        echo '<div class="section-title-summary">EMPLOYEE SUMMARY</div>';
        echo '<table class="summary-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Employee Code</th>';
        echo '<th>Employee Name</th>';
        echo '<th class="text-center">Morning</th>';
        echo '<th class="text-center">Afternoon</th>';
        echo '<th class="text-center">Total Hours</th>';
        echo '<th class="text-center">Late (min)</th>';
        echo '<th class="text-center">Early Left (min)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach($employee_daily_status as $data) {
            echo '<tr>';
            echo '<td class="text-left">' . htmlspecialchars($data['employee_code']) . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($data['employee_name']) . '</td>';
            echo '<td class="text-center">' . ($data['morning_status'] ?: '-') . '</td>';
            echo '<td class="text-center">' . ($data['afternoon_status'] ?: '-') . '</td>';
            echo '<td class="text-center">' . number_format($data['total_hours'], 2) . '</td>';
            echo '<td class="text-center">' . ($data['total_late'] > 0 ? $data['total_late'] : '-') . '</td>';
            echo '<td class="text-center">' . ($data['total_early'] > 0 ? $data['total_early'] : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        // MONTHLY/CUSTOM REPORT
        $grouped_by_date = [];
        foreach($export_data as $row) {
            $date = $row['record_date'];
            if (!isset($grouped_by_date[$date])) {
                $grouped_by_date[$date] = [];
            }
            $grouped_by_date[$date][] = $row;
        }
        
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
        echo '<th>Early Left (min)</th>';
        echo '<th>Reason</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach($grouped_by_date as $date => $records) {
            $row_date = date('d-m-Y', strtotime($date));
            
            // Sort records within this date by session and status priority
            usort($records, function($a, $b) {
                if ($a['session'] != $b['session']) {
                    return ($a['session'] == 'morning') ? -1 : 1;
                }
                $priority_a = getStatusPriority($a['status']);
                $priority_b = getStatusPriority($b['status']);
                
                if ($priority_a == $priority_b) {
                    return strcmp($a['name'], $b['name']);
                }
                return $priority_a - $priority_b;
            });
            
            // Date separator
            echo '<tr class="date-separator-row">';
            echo '<td colspan="13" style="background-color: #f5f5f5; font-weight: bold; text-align: center;">';
            echo '<strong>' . $row_date . '</strong>';
            echo '</td>';
            echo '</tr>';
            
            foreach($records as $row) {
                echo '<tr>';
                echo '<td class="text-center">' . $row_date . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['employee_code']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['name']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['department']) . '</td>';
                echo '<td class="text-left">' . htmlspecialchars($row['position']) . '</td>';
                echo '<td class="text-center">' . (($row['session'] == 'morning') ? 'AM' : 'PM') . '</td>';
                echo '<td class="text-center">' . ($row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . ($row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-') . '</td>';
                echo '<td class="text-center">' . number_format($row['working_hours'], 2) . '</td>';
                echo '<td class="text-center">' . $row['display_status_text'] . '</td>';
                echo '<td class="text-center">' . ($row['late_minutes'] > 0 ? $row['late_minutes'] : '-') . '</td>';
                echo '<td class="text-center">' . ($row['early_minutes'] > 0 ? $row['early_minutes'] : '-') . '</td>';
                echo '<td class="text-left">' . nl2br(htmlspecialchars(substr($row['reason'] ?? '-', 0, 200))) . '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '<br/><br/>';
        
        // Employee Summary Table for Monthly/Custom
        $monthly_employee_summary = [];
        foreach($export_data as $row) {
            $emp_id = $row['employee_id'];
            if (!isset($monthly_employee_summary[$emp_id])) {
                $monthly_employee_summary[$emp_id] = [
                    'employee_code' => $row['employee_code'],
                    'employee_name' => $row['name'],
                    'total_hours' => 0,
                    'total_late' => 0,
                    'total_early' => 0,
                    'present_count' => 0,
                    'late_count' => 0,
                    'absent_count' => 0,
                    'half_day_count' => 0,
                    'holiday_count' => 0,
                    'left_early_count' => 0,
                    'late_early_count' => 0
                ];
            }
            
            $status = $row['status'];
            if($status == 'present') {
                $monthly_employee_summary[$emp_id]['present_count']++;
            } elseif($status == 'late') {
                $monthly_employee_summary[$emp_id]['late_count']++;
            } elseif($status == 'absent') {
                $monthly_employee_summary[$emp_id]['absent_count']++;
            } elseif($status == 'half_day') {
                $monthly_employee_summary[$emp_id]['half_day_count']++;
            } elseif($status == 'holiday') {
                $monthly_employee_summary[$emp_id]['holiday_count']++;
            } elseif($status == 'left_early') {
                $monthly_employee_summary[$emp_id]['left_early_count']++;
            } elseif($status == 'late_early') {
                $monthly_employee_summary[$emp_id]['late_early_count']++;
            }
            
            $monthly_employee_summary[$emp_id]['total_hours'] += $row['working_hours'];
            $monthly_employee_summary[$emp_id]['total_late'] += $row['late_minutes'];
            $monthly_employee_summary[$emp_id]['total_early'] += $row['early_minutes'];
        }
        
        // Sort by total hours descending
        usort($monthly_employee_summary, function($a, $b) {
            return $b['total_hours'] <=> $a['total_hours'];
        });
        
        echo '<div class="section-title">EMPLOYEE SUMMARY</div>';
        echo '<table class="summary-table">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Employee Code</th>';
        echo '<th>Employee Name</th>';
        echo '<th class="text-center">Present</th>';
        echo '<th class="text-center">Late</th>';
        echo '<th class="text-center">Early Left</th>';
        echo '<th class="text-center">Late + Early</th>';
        echo '<th class="text-center">Half Day</th>';
        echo '<th class="text-center">Holiday</th>';
        echo '<th class="text-center">Absent</th>';
        echo '<th class="text-center">Total Hours</th>';
        echo '<th class="text-center">Late (min)</th>';
        echo '<th class="text-center">Early Left (min)</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach($monthly_employee_summary as $data) {
            echo '<tr>';
            echo '<td class="text-left">' . htmlspecialchars($data['employee_code']) . '</td>';
            echo '<td class="text-left">' . htmlspecialchars($data['employee_name']) . '</td>';
            echo '<td class="text-center" style="color: #28a745; font-weight: bold;">' . $data['present_count'] . '</td>';
            echo '<td class="text-center" style="color: #ffc107; font-weight: bold;">' . $data['late_count'] . '</td>';
            echo '<td class="text-center" style="color: #fd7e14; font-weight: bold;">' . $data['left_early_count'] . '</td>';
            echo '<td class="text-center" style="color: #e83e8c; font-weight: bold;">' . $data['late_early_count'] . '</td>';
            echo '<td class="text-center" style="color: #6f42c1; font-weight: bold;">' . $data['half_day_count'] . '</td>';
            echo '<td class="text-center" style="color: #007bff; font-weight: bold;">' . $data['holiday_count'] . '</td>';
            echo '<td class="text-center" style="color: #dc3545; font-weight: bold;">' . $data['absent_count'] . '</td>';
            echo '<td class="text-center" style="font-weight: bold;">' . number_format($data['total_hours'], 2) . '</td>';
            echo '<td class="text-center">' . ($data['total_late'] > 0 ? $data['total_late'] : '-') . '</td>';
            echo '<td class="text-center">' . ($data['total_early'] > 0 ? $data['total_early'] : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    }
    
    echo '</body>';
    echo '</html>';
    exit();
}

//Normal page load
include '../db_connection.php';
include 'header_admin.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Session times
$morning_start = '09:00:00';
$morning_end = '12:00:00';
$afternoon_start = '13:00:00';
$afternoon_end = '18:00:00';

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
            $selected_month = $_POST['month'] ?? date('m');
            $selected_year = $_POST['year'] ?? date('Y');
        }
        
        $selected_month = str_pad($selected_month, 2, '0', STR_PAD_LEFT);
        
        if($selected_year > $current_year || ($selected_year == $current_year && $selected_month > $current_month)) {
            $no_data_message = "Cannot select future month. Please select a month up to " . date('F Y');
        }
        
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
        $query = "SELECT a.*, a.session as session, u.name, e.employee_code, e.department, e.position, e.employee_id, a.notes as reason, a.working_hours, a.late_minutes
          FROM attendance_records a
          JOIN employees e ON a.employee_id = e.employee_id
          JOIN users u ON e.user_id = u.user_id
          WHERE a.record_date BETWEEN '$start_date' AND '$end_date' $emp_condition
          ORDER BY 
              a.record_date,
              CASE 
                  WHEN a.status = 'present' THEN 1
                  WHEN a.status = 'late' THEN 2
                  WHEN a.status = 'half_day' THEN 3
                  WHEN a.status = 'holiday' THEN 4
                  WHEN a.status = 'left_early' THEN 5
                  WHEN a.status = 'late_early' THEN 6
                  WHEN a.status = 'absent' THEN 7
                  ELSE 8
              END,
              a.session ASC,
              u.name ASC";
        
        $result = mysqli_query($conn, $query);
        $report_data = [];
        
        while($row = mysqli_fetch_assoc($result)) {
            $session = $row['session'];
            $row['session'] = $session;
            $row['display_status'] = getSessionStatusDisplay($row);
            $row['early_minutes'] = calculateEarlyMinutesExport($row['check_out_time'], $session); 
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
            
            $summary['total_hours'] += floatval($row['working_hours'] ?? 0);
            $summary['total_late_minutes'] += intval($row['late_minutes'] ?? 0);
            $summary['total_early_minutes'] += $row['early_minutes'];
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
    
    // Sort morning data by status priority
    usort($morning_data, function($a, $b) {
        $priority_a = getStatusPriority($a['status']);
        $priority_b = getStatusPriority($b['status']);
        
        if ($priority_a == $priority_b) {
            return strcmp($a['name'], $b['name']);
        }
        return $priority_a - $priority_b;
    });
    
    // Sort afternoon data by status priority
    usort($afternoon_data, function($a, $b) {
        $priority_a = getStatusPriority($a['status']);
        $priority_b = getStatusPriority($b['status']);
        
        if ($priority_a == $priority_b) {
            return strcmp($a['name'], $b['name']);
        }
        return $priority_a - $priority_b;
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report Results</title>
    <link rel="stylesheet" href="report_view.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <div class="header-actions">
                <a href="report.php" class="btn-back">← Back to Report Form</a>
                <div class="report-title">
                    <?php 
                    $employee_data = getEmployeeDisplayName($conn, $selected_employee);
                    $employee_display = is_array($employee_data) ? $employee_data['display'] : $employee_data;
                    
                    if($report_type == 'daily') {
                        echo 'Daily Report - ' . date('d-m-Y', strtotime($start_date)) . ' (' . $employee_display . ')';
                    } elseif($report_type == 'monthly') {
                        echo 'Monthly Report - ' . date('F Y', strtotime("$selected_year-$selected_month-01")) . ' (' . $employee_display . ')';
                    } else {
                        echo 'Custom Report - ' . date('d-m-Y', strtotime($start_date)) . ' to ' . date('d-m-Y', strtotime($end_date)) . ' (' . $employee_display . ')';
                    }
                    ?>
                </div>
                <div class="action-buttons">
                    <?php if($report_data !== null && count($report_data) > 0): ?>
                        <?php
                        $export_params = array(
                            'export_excel' => 1,
                            'report_type' => $report_type,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'employee_id' => $selected_employee_id
                        );
                        $pdf_params = array(
                            'export_pdf' => 1,
                            'report_type' => $report_type,
                            'start_date' => $start_date,
                            'end_date' => $end_date,
                            'employee_id' => $selected_employee_id
                        );
                        $export_url = '?' . http_build_query($export_params);
                        $pdf_url = '?' . http_build_query($pdf_params);
                        ?>
                        <a href="<?php echo $export_url; ?>" class="btn-excel"><img src="../excel_logo.png" alt="Excel Logo">Excel</a>
                        <a href="<?php echo $pdf_url; ?>" class="btn-pdf"><img src="../pdf_logo.png" alt="PDF Logo">PDF</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <?php if($no_data_message): ?>
            <div class="card-body">
                <div class="error-message"><?php echo $no_data_message; ?></div>
            </div>
        <?php elseif($report_data !== null && count($report_data) > 0): ?>
            <div class="card-body" id="reportContent">
                <?php if($report_type == 'daily'): ?>
                    <?php
                    // Calculate daily summary for each employee
                    $employee_daily_status = [];
                    
                    foreach($report_data as $row) {
                        $emp_id = $row['employee_id'];
                        
                        $actual_status = $row['status'];
                        $session = $row['session'];

                        // Determine display status
                        if ($actual_status == 'left_early') {
                            $display_status = 'Early Left';
                        } elseif ($actual_status == 'present') {
                            $display_status = 'Present';
                        } elseif ($actual_status == 'late') {
                            $display_status = 'Late';
                        } elseif ($actual_status == 'half_day') {
                            $display_status = 'Half Day';
                        } elseif ($actual_status == 'holiday') {
                            $display_status = 'Holiday';
                        } elseif ($actual_status == 'absent') {
                            $display_status = 'Absent';
                        } elseif ($actual_status == 'late_early') {
                            $display_status = 'Late + Early';
                        }
                        
                        if (!isset($employee_daily_status[$emp_id])) {
                            $employee_daily_status[$emp_id] = [
                                'morning_status' => '-',
                                'afternoon_status' => '-',
                                'morning_actual_status' => null,
                                'afternoon_actual_status' => null,
                                'employee_name' => $row['name'],
                                'employee_code' => $row['employee_code'],
                                'total_hours' => 0,
                                'total_late' => 0,
                                'total_early' => 0
                            ];
                        }
                        
                        if ($session == 'morning') {
                            $employee_daily_status[$emp_id]['morning_status'] = $display_status;
                            $employee_daily_status[$emp_id]['morning_actual_status'] = $actual_status;
                        } else {
                            $employee_daily_status[$emp_id]['afternoon_status'] = $display_status;
                            $employee_daily_status[$emp_id]['afternoon_actual_status'] = $actual_status;
                        }
                        
                        $employee_daily_status[$emp_id]['total_hours'] += floatval($row['working_hours'] ?? 0);
                        $employee_daily_status[$emp_id]['total_late'] += intval($row['late_minutes'] ?? 0);
                        $employee_daily_status[$emp_id]['total_early'] += $row['early_minutes'];
                    }
                    ?>
                    
                    <!-- Morning Session Table -->
                    <div class="section-title morning">MORNING SESSION (9:00 - 12:00)</div>
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
                                    <th>Late (min)</th>
                                    <th>Early Left (min)</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($morning_data) > 0): ?>
                                    <?php foreach($morning_data as $row): ?>
                                        <?php $status_info = getStatusInfo($row['status']); ?>
                                        <tr>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['position']); ?></td>
                                            <td style="text-align: center;"><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo number_format(floatval($row['working_hours'] ?? 0), 2); ?></td>
                                            <td style="text-align: center;">
                                                <span class="status-badge <?php echo $status_info['status_class']; ?>"><?php echo $status_info['display_text']; ?></span>
                                            </td>
                                            <td style="text-align: center;"><?php echo intval($row['late_minutes'] ?? 0) > 0 ? intval($row['late_minutes']) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $row['early_minutes'] > 0 ? $row['early_minutes'] : '-'; ?></td>
                                            <td class="reason-text">
                                                <?php if(!empty($row['reason'])): ?>
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
                    <div class="section-title afternoon">AFTERNOON SESSION (13:00 - 18:00)</div>
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
                                    <th>Late (min)</th>
                                    <th>Early Left (min)</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($afternoon_data) > 0): ?>
                                    <?php foreach($afternoon_data as $row): ?>
                                        <?php $status_info = getStatusInfo($row['status']); ?>
                                        <tr>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td style="text-align: left;"><?php echo htmlspecialchars($row['position']); ?></td>
                                            <td style="text-align: center;"><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo number_format(floatval($row['working_hours'] ?? 0), 2); ?></td>
                                            <td style="text-align: center;">
                                                <span class="status-badge <?php echo $status_info['status_class']; ?>"><?php echo $status_info['display_text']; ?></span>
                                            </td>
                                            <td style="text-align: center;"><?php echo intval($row['late_minutes'] ?? 0) > 0 ? intval($row['late_minutes']) : '-'; ?></td>
                                            <td style="text-align: center;"><?php echo $row['early_minutes'] > 0 ? $row['early_minutes'] : '-'; ?></td>
                                            <td class="reason-text">
                                                <?php if(!empty($row['reason'])): ?>
                                                    <?php echo nl2br(htmlspecialchars($row['reason'])); ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="11" class="no-data">No afternoon session records found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <!-- Employee Summary Table for Daily Report -->
                    <div class="summary-separator"></div>
                    <div class="section-title summary">EMPLOYEE SUMMARY</div>
                    <div class="table-responsive">
                        <table class="data-table summary-table">
                            <thead>
                                <tr>
                                    <th>Employee Code</th>
                                    <th>Employee Name</th>
                                    <th style="text-align: center;">Morning</th>
                                    <th style="text-align: center;">Afternoon</th>
                                    <th style="text-align: center;">Total Hours</th>
                                    <th style="text-align: center;">Late (min)</th>
                                    <th style="text-align: center;">Early Left (min)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(isset($employee_daily_status) && !empty($employee_daily_status)): ?>
                                    <?php 
                                    // Sort employee_daily_status by total_hours in descending order
                                    usort($employee_daily_status, function($a, $b) {
                                        return $b['total_hours'] <=> $a['total_hours'];
                                    });
                                    ?>
                                    <?php foreach($employee_daily_status as $emp_data): ?>
                                    <tr>
                                        <td style="text-align: left;"><?php echo htmlspecialchars($emp_data['employee_code']); ?></td>
                                        <td style="text-align: left;"><?php echo htmlspecialchars($emp_data['employee_name']); ?></td>
                                        <!-- Morning Status - Show '-' if no data -->
                                        <td style="text-align: center;">
                                            <?php if($emp_data['morning_actual_status'] !== null): ?>
                                                <span class="status-badge <?php echo getStatusClass($emp_data['morning_actual_status']); ?>">
                                                    <?php echo $emp_data['morning_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <!-- Afternoon Status - Show '-' if no data -->
                                        <td style="text-align: center;">
                                            <?php if($emp_data['afternoon_actual_status'] !== null): ?>
                                                <span class="status-badge <?php echo getStatusClass($emp_data['afternoon_actual_status']); ?>">
                                                    <?php echo $emp_data['afternoon_status']; ?>
                                                </span>
                                            <?php else: ?>
                                                <span style="color: #999;">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: center;"><?php echo number_format($emp_data['total_hours'], 2); ?></td>
                                        <td style="text-align: center;"><?php echo $emp_data['total_late'] > 0 ? $emp_data['total_late'] : '-'; ?></td>
                                        <td style="text-align: center;"><?php echo $emp_data['total_early'] > 0 ? $emp_data['total_early'] : '-'; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No data available</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                <!-- Monthly/Custom Report -->
                <div class="section-title morning">ATTENDANCE RECORDS</div>
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
                                <th>Late (min)</th>
                                <th>Early Left (min)</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $current_date = '';
                            foreach($report_data as $row): 
                                $row_date = date('d-m-Y', strtotime($row['record_date']));
                                if($current_date != '' && $current_date != $row_date):
                            ?>
                            <tr class="date-separator">
                                <td colspan="13"><strong><?php echo $row_date; ?></strong></td>
                            </tr>
                            <?php 
                                endif;
                                $current_date = $row_date;
                                
                                $status_info = getStatusInfo($row['status']);
                                $session_badge_class = ($row['session'] == 'morning') ? 'session-morning' : 'session-afternoon';
                                $session_display = ($row['session'] == 'morning') ? 'AM' : 'PM';
                            ?>
                            <tr>
                                <td style="text-align: center;"><?php echo $row_date; ?></td>
                                <td style="text-align: left;"><?php echo htmlspecialchars($row['employee_code']); ?></td>
                                <td style="text-align: left;"><?php echo htmlspecialchars($row['name']); ?></td>
                                <td style="text-align: left;"><?php echo htmlspecialchars($row['department']); ?></td>
                                <td style="text-align: left;"><?php echo htmlspecialchars($row['position']); ?></td>
                                <td style="text-align: center;">
                                    <span class="<?php echo $session_badge_class; ?>"><?php echo $session_display; ?></span>
                                </td>
                                <td style="text-align: center;"><?php echo $row['check_in_time'] ? date('h:i A', strtotime($row['check_in_time'])) : '-'; ?></td>
                                <td style="text-align: center;"><?php echo $row['check_out_time'] ? date('h:i A', strtotime($row['check_out_time'])) : '-'; ?></td>
                                <td style="text-align: center;"><?php echo number_format(floatval($row['working_hours'] ?? 0), 2); ?></td>
                                <td style="text-align: center;">
                                    <span class="status-badge <?php echo $status_info['status_class']; ?>"><?php echo $status_info['display_text']; ?></span>
                                </td>
                                <td style="text-align: center;"><?php echo intval($row['late_minutes'] ?? 0) > 0 ? intval($row['late_minutes']) : '-'; ?></td>
                                <td style="text-align: center;"><?php echo $row['early_minutes'] > 0 ? $row['early_minutes'] : '-'; ?></td>
                                <td class="reason-text">
                                    <?php if(!empty($row['reason'])): ?>
                                        <?php echo nl2br(htmlspecialchars(substr($row['reason'], 0, 100))); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Employee Summary Table for Monthly/Custom Report -->
                <?php
                // Calculate employee summary for monthly/custom report
                $monthly_employee_summary = [];
                foreach($report_data as $row) {
                    $emp_id = $row['employee_id'];
                    if (!isset($monthly_employee_summary[$emp_id])) {
                        $monthly_employee_summary[$emp_id] = [
                            'employee_code' => $row['employee_code'],
                            'employee_name' => $row['name'],
                            'total_hours' => 0,
                            'total_late' => 0,
                            'total_early' => 0,
                            'present_count' => 0,
                            'late_count' => 0,
                            'absent_count' => 0,
                            'half_day_count' => 0,
                            'holiday_count' => 0,
                            'early_left_count' => 0,
                            'late_early_count' => 0
                        ];
                    }
                    
                    $status = $row['status'];
                    if($status == 'present') {
                        $monthly_employee_summary[$emp_id]['present_count']++;
                    } elseif($status == 'late') {
                        $monthly_employee_summary[$emp_id]['late_count']++;
                    } elseif($status == 'absent') {
                        $monthly_employee_summary[$emp_id]['absent_count']++;
                    } elseif($status == 'half_day') {
                        $monthly_employee_summary[$emp_id]['half_day_count']++;
                    } elseif($status == 'holiday') {
                        $monthly_employee_summary[$emp_id]['holiday_count']++;
                    } elseif($status == 'left_early') {
                        $monthly_employee_summary[$emp_id]['early_left_count']++;
                    } elseif($status == 'late_early') {
                        $monthly_employee_summary[$emp_id]['late_early_count']++;
                    }
                    
                    $monthly_employee_summary[$emp_id]['total_hours'] += floatval($row['working_hours'] ?? 0);
                    $monthly_employee_summary[$emp_id]['total_late'] += intval($row['late_minutes'] ?? 0);
                    $monthly_employee_summary[$emp_id]['total_early'] += $row['early_minutes'];
                }
                ?>
                
                <div class="summary-separator"></div>
                <div class="section-title summary">EMPLOYEE SUMMARY</div>
                <div class="table-responsive">
                    <table class="data-table summary-table">
                        <thead>
                            <tr>
                                <th>Employee Code</th>
                                <th>Employee Name</th>
                                <th style="text-align: center;">Present</th>
                                <th style="text-align: center;">Late</th>
                                <th style="text-align: center;">Early Left</th>
                                <th style="text-align: center;">Late + Early</th>
                                <th style="text-align: center;">Half Day</th>
                                <th style="text-align: center;">Holiday</th>
                                <th style="text-align: center;">Absent</th>
                                <th style="text-align: center;">Total Hours</th>
                                <th style="text-align: center;">Late (min)</th>
                                <th style="text-align: center;">Early Left (min)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Sort monthly_employee_summary by total_hours in descending order
                            usort($monthly_employee_summary, function($a, $b) {
                                return $b['total_hours'] <=> $a['total_hours'];
                            });
                            ?>
                            <?php foreach($monthly_employee_summary as $data): ?>
                                <tr>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($data['employee_code']); ?></td>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($data['employee_name']); ?></td>
                                    <td style="text-align: center; color: #28a745; font-weight: bold;"><?php echo $data['present_count']; ?></td>
                                    <td style="text-align: center; color: #ffc107; font-weight: bold;"><?php echo $data['late_count']; ?></td>
                                    <td style="text-align: center; color: #fd7e14; font-weight: bold;"><?php echo $data['early_left_count']; ?></td>
                                    <td style="text-align: center; color: #e83e8c; font-weight: bold;"><?php echo $data['late_early_count']; ?></td>
                                    <td style="text-align: center; color: #6f42c1; font-weight: bold;"><?php echo $data['half_day_count']; ?></td>
                                    <td style="text-align: center; color: #007bff; font-weight: bold;"><?php echo $data['holiday_count']; ?></td>
                                    <td style="text-align: center; color: #dc3545; font-weight: bold;"><?php echo $data['absent_count']; ?></td>
                                    <td style="text-align: center; font-weight: bold;"><?php echo number_format($data['total_hours'], 2); ?></td>
                                    <td style="text-align: center;"><?php echo $data['total_late'] > 0 ? $data['total_late'] : '-'; ?></td>
                                    <td style="text-align: center;"><?php echo $data['total_early'] > 0 ? $data['total_early'] : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
</body>
</html>