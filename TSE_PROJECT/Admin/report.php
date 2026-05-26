<?php
session_start();
include '../db_connection.php';
include 'header_admin.php';

// Set timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Check if admin is logged in
if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: login_admin.php");
    exit();
}

$today_date = date('Y-m-d');
$current_month = date('m');
$current_year = date('Y');
$selected_month = date('m');
$selected_year = date('Y');
$selected_employee = 'all';
$start_date = '';
$end_date = '';
$report_type = '';

// Get all employees for dropdown
$emp_query = "SELECT e.employee_id, u.name, e.employee_code, e.department 
              FROM users u 
              JOIN employees e ON u.user_id = e.user_id 
              WHERE u.role = 'employee' AND u.status = 'Active'
              ORDER BY u.name";
$emp_result = mysqli_query($conn, $emp_query);
$employees = [];
while($row = mysqli_fetch_assoc($emp_result)) {
    $employees[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Admin Dashboard</title>
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
            max-width: 800px;
            margin: 100px auto 40px;
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
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #555;
            font-size: 13px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #dc3545;
        }
        
        .btn-primary {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 30px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        
        .btn-primary:hover {
            background: #c82333;
        }
        
        .btn-group {
            text-align: center;
            margin-top: 10px;
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-top: 80px;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            Generate Attendance Report
        </div>
        <div class="card-body">
            <form method="POST" action="report_view.php" id="reportForm" target="_blank">
                <div class="form-row">
                    <div class="form-group">
                        <label>Report Type</label>
                        <select name="report_type" id="reportType" required>
                            <option value="daily">Daily Report</option>
                            <option value="monthly">Monthly Report</option>
                            <option value="custom">Custom Date Range</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Employee</label>
                        <select name="employee_filter" id="employeeFilter">
                            <option value="all">All Employees</option>
                            <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>">
                                    <?php echo $emp['employee_code'] . ' - ' . $emp['name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="dailyDateGroup">
                        <label>Date</label>
                        <input type="date" name="date" id="dailyDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo $today_date; ?>">
                    </div>
                    <div class="form-group" id="monthlyGroup" style="display: none;">
                        <label>Month</label>
                        <select name="month" id="monthSelect">
                            <?php 
                            for($m = 1; $m <= 12; $m++): 
                                $month_val = str_pad($m, 2, '0', STR_PAD_LEFT);
                            ?>
                                <option value="<?php echo $month_val; ?>">
                                    <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" id="monthlyYearGroup" style="display: none;">
                        <label>Year</label>
                        <select name="year" id="yearSelect">
                            <?php for($y = date('Y') - 5; $y <= date('Y'); $y++): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group" id="customStartGroup" style="display: none;">
                        <label>Start Date</label>
                        <input type="date" name="start_date" id="customStartDate" value="<?php echo date('Y-m-01'); ?>" max="<?php echo $today_date; ?>">
                    </div>
                    <div class="form-group" id="customEndGroup" style="display: none;">
                        <label>End Date</label>
                        <input type="date" name="end_date" id="customEndDate" value="<?php echo date('Y-m-d'); ?>" max="<?php echo $today_date; ?>">
                    </div>
                </div>
                <div class="btn-group">
                    <button type="submit" class="btn-primary">Generate Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const reportType = document.getElementById('reportType');
    const dailyDateGroup = document.getElementById('dailyDateGroup');
    const monthlyGroup = document.getElementById('monthlyGroup');
    const monthlyYearGroup = document.getElementById('monthlyYearGroup');
    const customStartGroup = document.getElementById('customStartGroup');
    const customEndGroup = document.getElementById('customEndGroup');
    const todayDate = '<?php echo $today_date; ?>';
    const currentYear = <?php echo $current_year; ?>;
    const currentMonth = <?php echo $current_month; ?>;
    
    function toggleDateFields() {
        const type = reportType.value;
        
        dailyDateGroup.style.display = 'none';
        monthlyGroup.style.display = 'none';
        monthlyYearGroup.style.display = 'none';
        customStartGroup.style.display = 'none';
        customEndGroup.style.display = 'none';
        
        if(type == 'daily') {
            dailyDateGroup.style.display = 'block';
        } else if(type == 'monthly') {
            monthlyGroup.style.display = 'block';
            monthlyYearGroup.style.display = 'block';
            updateMonthOptions();
        } else if(type == 'custom') {
            customStartGroup.style.display = 'block';
            customEndGroup.style.display = 'block';
            
            // Get the date inputs
            const startDateInput = document.getElementById('customStartDate');
            const endDateInput = document.getElementById('customEndDate');
            
            // Set initial min for end date based on start date
            if(startDateInput.value) {
                endDateInput.min = startDateInput.value;
            }
            
            // When start date changes, update end date min and also adjust end date if needed
            startDateInput.addEventListener('change', function() {
                endDateInput.min = this.value;
                if(endDateInput.value < this.value) {
                    endDateInput.value = this.value;
                }
            });
            
            // When end date changes, validate it's not before start date
            endDateInput.addEventListener('change', function() {
                const startDate = startDateInput.value;
                if(this.value < startDate) {
                    alert('End date cannot be earlier than start date!');
                    this.value = startDate;
                }
            });
        }
    }
    
    function updateMonthOptions() {
        const year = parseInt(document.getElementById('yearSelect').value);
        const monthSelect = document.getElementById('monthSelect');
        
        for(let i = 0; i < monthSelect.options.length; i++) {
            const monthValue = parseInt(monthSelect.options[i].value);
            if(year === currentYear && monthValue > currentMonth) {
                monthSelect.options[i].disabled = true;
            } else {
                monthSelect.options[i].disabled = false;
            }
        }
    }
    
    document.getElementById('yearSelect').addEventListener('change', updateMonthOptions);
    
    reportType.addEventListener('change', toggleDateFields);
    toggleDateFields();
    
    // Form validation before submit
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        const type = reportType.value;
        
        if(type == 'daily') {
            const date = document.getElementById('dailyDate').value;
            if(date > todayDate) {
                e.preventDefault();
                alert('Cannot select future date. Please select a date up to ' + todayDate);
                return false;
            }
        } else if(type == 'monthly') {
            const year = document.getElementById('yearSelect').value;
            const month = document.getElementById('monthSelect').value;
            
            if(year == currentYear && parseInt(month) > currentMonth) {
                e.preventDefault();
                alert('Cannot select future month!');
                return false;
            }
        } else if(type == 'custom') {
            const startDate = document.getElementById('customStartDate').value;
            const endDate = document.getElementById('customEndDate').value;
            
            if(startDate > todayDate) {
                e.preventDefault();
                alert('Start date cannot be in the future!');
                return false;
            }
            if(endDate > todayDate) {
                e.preventDefault();
                alert('End date cannot be in the future!');
                return false;
            }
            if(endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be earlier than start date!');
                return false;
            }
        }
    });
</script>
</body>
</html>