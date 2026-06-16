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

// Get all employees - order by status (Active first, then Inactive) and then by Employee Code (Descending)
$query = "SELECT u.*, e.* FROM users u 
          JOIN employees e ON u.user_id = e.user_id 
          WHERE u.role = 'employee'
          ORDER BY CASE WHEN u.status = 'Active' THEN 0 ELSE 1 END, e.employee_code DESC";
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
    <title>Employee List</title>
    <link rel="stylesheet" href="employee_list.css">
</head>
<body>
<div class="main-container">
    <div class="card">
        <div class="card-header">
            <h5> Employee List</h5>
            <a href="add_employee.php" class="btn-add">
                <strong>+</strong>Add New Employee
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
                                        Deactivate
                                    </a>
                                    <?php else: ?>
                                    <a href="?activate=1&id=<?php echo $emp['employee_id']; ?>" class="btn-activate" onclick="return confirm('Are you sure you want to activate this employee?')">
                                        Activate
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