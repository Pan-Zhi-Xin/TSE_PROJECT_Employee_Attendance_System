<?php
session_start();
include '../db_connection.php';

// Handle employee deactivation - MUST be before any HTML output
if(isset($_GET['deactivate']) && isset($_GET['id'])) {
    $employee_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $user_query = "SELECT user_id FROM employees WHERE employee_id = '$employee_id'";
    $user_result = mysqli_query($conn, $user_query);
    if($user_result && mysqli_num_rows($user_result) > 0) {
        $user = mysqli_fetch_assoc($user_result);
        $deactivate_query = "UPDATE users SET status = 'Inactive' WHERE user_id = '{$user['user_id']}'";
        mysqli_query($conn, $deactivate_query);
    }
    header("Location: employee_list.php");
    exit();
}

// Handle employee reactivation - MUST be before any HTML output
if(isset($_GET['activate']) && isset($_GET['id'])) {
    $employee_id = mysqli_real_escape_string($conn, $_GET['id']);
    
    $user_query = "SELECT user_id FROM employees WHERE employee_id = '$employee_id'";
    $user_result = mysqli_query($conn, $user_query);
    if($user_result && mysqli_num_rows($user_result) > 0) {
        $user = mysqli_fetch_assoc($user_result);
        $activate_query = "UPDATE users SET status = 'Active' WHERE user_id = '{$user['user_id']}'";
        mysqli_query($conn, $activate_query);
    }
    header("Location: employee_list.php");
    exit();
}

// Now include header after all redirects are processed
include 'header_admin.php';

// Get all employees - order by status (Active first, then Inactive)
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.role = 'employee'
          ORDER BY CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END, u.name";
$result = mysqli_query($conn, $query);

$employees = [];
while($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee List - Admin Dashboard</title>
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
        
        /* Centered Main Container */
        .main-container {
            max-width: 1400px;
            margin: 100px auto 40px;
            padding: 0 20px;
        }
        
        /* Card Styles */
        .card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: #dc3545;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h5 {
            margin: 0;
            font-size: 18px;
        }
        
        .btn-add {
            background: white;
            color: #dc3545;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-add:hover {
            background: #f8f9fa;
            transform: translateY(-1px);
        }
        
        .search-area {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
        }
        
        .search-box {
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 25px;
            font-size: 14px;
            width: 300px;
            outline: none;
            transition: all 0.3s;
        }
        
        .search-box:focus {
            border-color: #dc3545;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        
        .data-table thead {
            background: #343a40;
            color: white;
        }
        
        .data-table th,
        .data-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Profile Picture Styles */
        .profile-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #dc3545;
        }
        
        .profile-placeholder {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            font-size: 20px;
            border: 2px solid #dc3545;
        }
        
        /* Status Styles */
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        
        /* Button Styles */
        .btn-edit {
            background: #ffc107;
            color: #333;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-edit:hover {
            background: #e0a800;
        }
        
        .btn-deactivate {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-deactivate:hover {
            background: #c82333;
        }
        
        .btn-activate {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .btn-activate:hover {
            background: #218838;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .inactive-row {
            background-color: #fff5f5;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .main-container {
                max-width: 100%;
            }
            .data-table th,
            .data-table td {
                padding: 8px 10px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .main-container {
                margin-top: 80px;
                padding: 0 15px;
            }
            .card-header {
                flex-direction: column;
                text-align: center;
            }
            .search-area {
                justify-content: center;
            }
            .search-box {
                width: 100%;
            }
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-users"></i> Employee List</h5>
            <a href="add_employee.php" class="btn-add">
                <i class="fas fa-plus"></i> Add New Employee
            </a>
        </div>
        
        <div class="search-area">
            <input type="text" id="searchInput" class="search-box" placeholder="🔍 Search by Employee Code / Name">
        </div>
        
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Profile</th>
                            <th>Employee Code</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($employees as $emp): 
                            $row_class = ($emp['status'] == 'Inactive') ? 'inactive-row' : '';
                            $profile_pic_path = "../profile_picture/" . ($emp['profile_picture'] ?? '');
                            $has_profile = !empty($emp['profile_picture']) && file_exists($profile_pic_path);
                        ?>
                        <tr class="<?php echo $row_class; ?>">
                            <td>
                                <?php if($has_profile): ?>
                                    <img src="<?php echo $profile_pic_path; ?>" alt="Profile" class="profile-img">
                                <?php else: ?>
                                    <div class="profile-placeholder">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($emp['employee_code']); ?></td>
                            <td><?php echo htmlspecialchars($emp['name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['department']); ?></td>
                            <td><?php echo htmlspecialchars($emp['position']); ?></td>
                            <td><?php echo $emp['contact_number'] ? htmlspecialchars($emp['contact_number']) : '-'; ?></td>
                            <td>
                                <span class="<?php echo $emp['status'] == 'Active' ? 'status-active' : 'status-inactive'; ?>">
                                    <i class="fas <?php echo $emp['status'] == 'Active' ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i>
                                    <?php echo $emp['status']; ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="edit_employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn-edit" href="edit_employee.php">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <?php if($emp['status'] == 'Active'): ?>
                                    <a href="?deactivate=1&id=<?php echo $emp['employee_id']; ?>" class="btn-deactivate" onclick="return confirm('Are you sure you want to deactivate this employee?')">
                                        <i class="fas fa-trash"></i> Deactivate
                                    </a>
                                    <?php else: ?>
                                    <a href="?activate=1&id=<?php echo $emp['employee_id']; ?>" class="btn-activate" onclick="return confirm('Are you sure you want to activate this employee?')">
                                        <i class="fas fa-check"></i> Activate
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    // Search functionality - search by Employee Code or Name
    const searchInput = document.getElementById('searchInput');
    const table = document.querySelector('.data-table');
    
    if(searchInput && table) {
        const rows = table.querySelectorAll('tbody tr');
        
        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            
            rows.forEach(row => {
                const employeeCode = row.cells[1]?.innerText.toLowerCase() || '';
                const employeeName = row.cells[2]?.innerText.toLowerCase() || '';
                
                if(employeeCode.includes(searchTerm) || employeeName.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        searchInput.addEventListener('keyup', filterTable);
        searchInput.addEventListener('search', filterTable);
    }
</script>
</body>
</html>