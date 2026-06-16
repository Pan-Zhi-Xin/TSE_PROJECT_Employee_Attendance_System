<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

include("../db_connection.php");
include 'header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

$employee_id = $_SESSION['user_id'];
$message = "";

$today = date("Y-m-d");
$displayDate = date("d/m/Y");

$checkSql = "
    SELECT COUNT(*) AS total
    FROM attendance_records
    WHERE employee_id = ?
      AND record_date = ?
";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("is", $employee_id, $today);
$checkStmt->execute();
$checkResult = $checkStmt->get_result()->fetch_assoc();
$hasAnyRecord = ($checkResult['total'] > 0);
$checkStmt->close();

$abnormalRecords = [];
$sql = "
    SELECT
        record_id,
        session,
        status,
        notes
    FROM attendance_records
    WHERE employee_id = ?
      AND record_date = ?
      AND status IN ('late','absent','early_leave')
    ORDER BY FIELD(session,'morning','afternoon')
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $employee_id, $today);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $abnormalRecords[] = $row;
}
$stmt->close();

$canSubmit = count($abnormalRecords) > 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $reasons = $_POST['reason'] ?? [];
    $allUpdated = true;

    foreach ($reasons as $record_id => $reason) {
        $reason = trim($reason);
        if ($reason === '') {
            $allUpdated = false;
            $message = "Please provide reasons for all abnormal records.";
            break;
        }

        $updateSql = "
            UPDATE attendance_records
            SET notes = ?
            WHERE record_id = ?
            AND employee_id = ?
        ";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("sii", $reason, $record_id, $employee_id);
        $updateStmt->execute();
        $updateStmt->close();
    }

    if ($allUpdated) {
        $message = "All reasons submitted successfully.";

        $abnormalRecords = [];
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $employee_id, $today);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $abnormalRecords[] = $row;
        }
        $stmt->close();
        $canSubmit = count($abnormalRecords) > 0;
    }
}

$statusLabels = [
    'late'        => 'Late',
    'absent'      => 'Absent',
    'early_leave' => 'Early Leave'
];
$sessionLabels = [
    'morning'   => 'Morning',
    'afternoon' => 'Afternoon'
];
?>

<style>
.reason-container {
    max-width: 700px;
    margin: 80px auto 40px auto;
}
.reason-card {
    background: #fff;
    border-radius: 20px;
    padding: 35px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.reason-title {
    text-align: center;
    font-weight: 700;
    margin-bottom: 30px;
    color: #333;
}
.form-group {
    margin-bottom: 20px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #444;
}
.form-control {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #dcdfe6;
    border-radius: 10px;
    transition: all 0.3s ease;
}
.form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102,126,234,0.15);
    outline: none;
}
textarea.form-control {
    min-height: 90px;
    resize: vertical;
}
.status-box {
    padding: 14px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 20px;
    border: 1px solid #e5e7eb;
}
.status-late {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.status-absent {
    background: #fee2e2;
    color: #991b1b;
    border-color: #ef4444;
}
.status-early_leave {
    background: #dbeafe;
    color: #1e40af;
    border-color: #3b82f6;
}
.submit-btn {
    width: 100%;
    border: none;
    border-radius: 12px;
    padding: 14px;
    color: white;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transition: 0.3s;
}
.submit-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102,126,234,0.3);
}
.submit-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}
.message {
    padding: 12px;
    border-radius: 10px;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 600;
    background: #d1fae5;
    color: #065f46;
    border: 1px solid #10b981;
}
.info-box {
    padding: 15px;
    border-radius: 12px;
    text-align: center;
    font-weight: 600;
    border: 1px solid #10b981;
}
.info-box.success {
    background: #d1fae5;
    color: #065f46;
}
.info-box.empty {
    background: #fef3c7;
    color: #92400e;
    border-color: #f59e0b;
}
.record-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 15px;
    background: #fafafa;
}
.record-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.record-header span {
    font-weight: 600;
}
.badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}
.badge-late { background: #fef3c7; color: #92400e; }
.badge-absent { background: #fee2e2; color: #991b1b; }
.badge-early_leave { background: #dbeafe; color: #1e40af; }
</style>

<div class="main-container">
    <div class="reason-container">
        <div class="reason-card">
            <h2 class="reason-title">📋 Attendance Reason Submission</h2>

            <?php if ($message !== ""): ?>
                <div class="message"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="form-group">
                <label>Date</label>
                <input type="text" class="form-control" value="<?php echo $displayDate; ?>" readonly>
            </div>

            <?php if ($canSubmit): ?>
                <div class="form-group">
                    <label>Abnormal Attendance Records for Today (Please provide a reason for each occurrence)</label>
                    <form method="POST">
                        <?php foreach ($abnormalRecords as $record): 
                            $recordId = $record['record_id'];
                            $session = $record['session'];
                            $status = $record['status'];
                            $existingNote = $record['notes'] ?? '';
                        ?>
                            <div class="record-item">
                                <div class="record-header">
                                    <span><?php echo $sessionLabels[$session] ?? $session; ?></span>
                                    <span class="badge badge-<?php echo $status; ?>">
                                        <?php echo $statusLabels[$status] ?? $status; ?>
                                    </span>
                                </div>
                                <textarea 
                                    name="reason[<?php echo $recordId; ?>]" 
                                    class="form-control" 
                                    placeholder="Please explain the reason for <?php echo $statusLabels[$status] ?? $status; ?>"
                                    rows="3"
                                ><?php echo htmlspecialchars($existingNote); ?></textarea>
                            </div>
                        <?php endforeach; ?>
                        <button type="submit" name="submit" class="submit-btn">
                            Submit Reason
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <?php if ($hasAnyRecord): ?>
                    <div class="info-box success">You are marked Present today. No reason is required.</div>
                <?php else: ?>
                    <div class="info-box empty">No attendance records found for today.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>