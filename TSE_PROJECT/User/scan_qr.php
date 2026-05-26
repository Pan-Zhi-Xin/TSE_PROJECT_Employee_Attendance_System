<?php
session_start();
include '../db_connection.php';
include 'header.php';

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'employee') {
    header("Location: login.php");
    exit();
}

// Generate unique QR token for this session
$qr_token = bin2hex(random_bytes(32));
$expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
$employee_id = $_SESSION['employee_id'];

// Delete old unused QR sessions
$clean_query = "DELETE FROM qr_sessions WHERE expires_at < NOW() OR is_used = 'Used'";
mysqli_query($conn, $clean_query);

// Save QR session to database (removed session_type)
$query = "INSERT INTO qr_sessions (employee_id, qr_token, expires_at, is_used) 
          VALUES ('$employee_id', '$qr_token', '$expires_at', 'Active')";
mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code Attendance</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container
        {
            margin-top: 80px;
        }
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .qr-code {
            padding: 20px;
            background: white;
            display: inline-block;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="row">
            <div class="col-md-6 offset-md-3">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-qrcode"></i> Your QR Code for Check-in</h5>
                    </div>
                    <div class="card-body text-center">
                        <div class="qr-container">
                            <div class="qr-code">
                                <?php
                                // Generate QR code using Google Charts API (simple solution)
                                $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qr_token);
                                ?>
                                <img src="<?php echo $qr_url; ?>" alt="QR Code" width="200" height="200">
                            </div>
                            <p class="mt-3 text-muted">
                                <i class="fas fa-info-circle"></i> Show this QR code to the scanner for check-in<br>
                                <small>This QR code expires in 5 minutes</small>
                            </p>
                            <p class="text-danger small">
                                <i class="fas fa-exclamation-triangle"></i> Note: This QR code is for check-in only
                            </p>
                        </div>
                        <div class="mt-3">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>