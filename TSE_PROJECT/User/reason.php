<?php
session_start();
include("../db_connection.php");
include 'header.php';

// Check login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee')
{
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];
$message = "";

// Today's date
$today = date("Y-m-d");

// Get today's attendance status
$attendance_status = "No Record Found";

$status_sql = "SELECT status
               FROM attendance_records
               WHERE employee_id = ?
               AND record_date = ?
               LIMIT 1";

$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("is", $employee_id, $today);
$status_stmt->execute();

$status_result = $status_stmt->get_result();

if ($row = $status_result->fetch_assoc())
{
    $attendance_status = $row['status'];
}

// Status color class
$statusClass = '';

switch(strtolower($attendance_status))
{
    case 'present':
        $statusClass = 'status-present';
        break;

    case 'late':
        $statusClass = 'status-late';
        break;

    case 'absent':
        $statusClass = 'status-absent';
        break;

    case 'early leave':
        $statusClass = 'status-early';
        break;
}

// Submit reason
if (isset($_POST['submit']))
{
    $record_date = $today;
    $session_type = $_POST['session_type'];
    $reason = trim($_POST['reason']);

    $sql = "UPDATE attendance_records
            SET notes = ?
            WHERE employee_id = ?
            AND record_date = ?
            AND session = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "siss",
        $reason,
        $employee_id,
        $record_date,
        $session_type
    );

    if ($stmt->execute())
    {
        if ($stmt->affected_rows > 0)
        {
            $message = "Reason submitted successfully.";
        }
        else
        {
            $message = "No attendance record found for today and selected session.";
        }
    }
    else
    {
        $message = "Error: " . $conn->error;
    }
}
?>

<style>
.reason-container{
    max-width:700px;
    margin:80px auto 40px auto;
}

.reason-card{
    background:#fff;
    border-radius:20px;
    padding:35px;
    box-shadow:0 8px 25px rgba(0,0,0,0.08);
}

.reason-title{
    text-align:center;
    font-weight:700;
    margin-bottom:30px;
    color:#333;
}

.form-group{
    margin-bottom:20px;
}

.form-group label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
    color:#444;
}

.form-control,
.form-select{
    width:100%;
    padding:12px 15px;
    border:1px solid #dcdfe6;
    border-radius:10px;
    transition:all 0.3s ease;
}

.form-control:focus,
.form-select:focus{
    border-color:#667eea;
    box-shadow:0 0 0 4px rgba(102,126,234,0.15);
    outline:none;
}

textarea.form-control{
    min-height:140px;
    resize:vertical;
}

.status-box{
    padding:14px;
    border-radius:12px;
    text-align:center;
    font-weight:600;
    font-size:16px;
    margin-bottom:20px;
    border:1px solid #e5e7eb;
}

.status-present{
    background:#d1fae5;
    color:#065f46;
    border-color:#10b981;
}

.status-late{
    background:#fef3c7;
    color:#92400e;
    border-color:#f59e0b;
}

.status-absent{
    background:#fee2e2;
    color:#991b1b;
    border-color:#ef4444;
}

.status-early{
    background:#dbeafe;
    color:#1e40af;
    border-color:#3b82f6;
}

.submit-btn{
    width:100%;
    border:none;
    border-radius:12px;
    padding:14px;
    color:white;
    font-size:16px;
    font-weight:600;
    background:linear-gradient(
        135deg,
        #667eea 0%,
        #764ba2 100%
    );
    transition:0.3s;
}

.submit-btn:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(102,126,234,0.3);
}

.message{
    padding:12px;
    border-radius:10px;
    margin-bottom:20px;
    text-align:center;
    font-weight:600;
    background:#d1fae5;
    color:#065f46;
    border:1px solid #10b981;
}

@media(max-width:768px)
{
    .reason-container{
        margin:20px;
    }

    .reason-card{
        padding:25px;
    }
}
</style>

<div class="main-container">
    <div class="reason-container">

        <div class="reason-card">

            <h2 class="reason-title">
                Attendance Reason Form
            </h2>

            <?php if($message != ""): ?>
                <div class="message">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <form method="POST">

                <div class="form-group">
                    <label>Date</label>
                    <input
                        type="date"
                        class="form-control"
                        value="<?php echo $today; ?>"
                        readonly>
                </div>

                <div class="form-group">
                    <label>Today's Attendance Status</label>

                    <div class="status-box <?php echo $statusClass; ?>">
                        <?php echo htmlspecialchars($attendance_status); ?>
                    </div>
                </div>

                <div class="form-group">
                    <label>Session</label>

                    <select name="session_type"
                            class="form-select"
                            required>

                        <option value="">
                            Select Session
                        </option>

                        <option value="morning">
                            Morning
                        </option>

                        <option value="afternoon">
                            Afternoon
                        </option>

                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Reason for Late / Absent / Early Leave
                    </label>

                    <textarea
                        name="reason"
                        class="form-control"
                        placeholder="Enter your reason here..."
                        required></textarea>
                </div>

                <button
                    type="submit"
                    name="submit"
                    class="submit-btn">
                    Submit Reason
                </button>

            </form>

        </div>

    </div>
</div>

</body>
</html>
