<?php
session_start();
require 'config.php';

// Check if appointment ID is provided
if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$appointment_id = intval($_GET['id']);

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get appointment details
$stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name, p.phone_number 
                       FROM appointments a 
                       JOIN patients p ON a.patient_id = p.id 
                       WHERE a.id = ?");
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: index.php');
    exit;
}

$appointment = $result->fetch_assoc();
$conn->close();

// Format date and time for display
$date = date('l, F j, Y', strtotime($appointment['appointment_date']));
$time = date('g:i A', strtotime($appointment['appointment_date']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Confirmation - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .confirmation-container {
            max-width: 800px;
            margin: 80px auto;
            padding: 40px;
            background-color: #ffffff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .confirmation-icon {
            width: 80px;
            height: 80px;
            background-color: #e8f5e9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .confirmation-icon i {
            font-size: 40px;
            color: #2e7d32;
        }
        
        .confirmation-title {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .confirmation-message {
            font-size: 1.1rem;
            color: var(--text-color);
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .appointment-details {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 15px;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            flex: 0 0 150px;
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-color);
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-requested {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-no_show {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .next-steps {
            margin-bottom: 30px;
            text-align: left;
        }
        
        .next-steps h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .next-steps ul {
            padding-left: 20px;
        }
        
        .next-steps li {
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .action-buttons {
            margin-top: 30px;
        }
        
        .btn {
            margin: 0 10px;
        }
        
        @media screen and (max-width: 768px) {
            .confirmation-container {
                padding: 30px 20px;
                margin: 40px 20px;
            }
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                margin-bottom: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="confirmation-container">
        <div class="confirmation-icon">
            <i class="fas fa-check"></i>
        </div>
        <h1 class="confirmation-title">Appointment Request Received</h1>
        <p class="confirmation-message">
            Thank you for requesting an appointment with ImmuCare. Your appointment request has been received and is pending confirmation. We will contact you shortly to confirm your appointment.
        </p>
        
        <div class="appointment-details">
            <div class="detail-row">
                <div class="detail-label">Reference ID:</div>
                <div class="detail-value"><?php echo $appointment_id; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Patient Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Date:</div>
                <div class="detail-value"><?php echo $date; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Time:</div>
                <div class="detail-value"><?php echo $time; ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Purpose:</div>
                <div class="detail-value"><?php echo htmlspecialchars($appointment['purpose']); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status:</div>
                <div class="detail-value">
                    <span class="status-badge status-<?php echo $appointment['status']; ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div class="next-steps">
            <h3>What's Next?</h3>
            <ul>
                <li>Our staff will review your appointment request and contact you to confirm the date and time.</li>
                <li>You will receive an email confirmation once your appointment is confirmed.</li>
                <li>Please arrive 15 minutes before your scheduled appointment time.</li>
                <li>If you need to reschedule or cancel your appointment, please contact us at least 24 hours in advance.</li>
            </ul>
        </div>
        
        <div class="action-buttons">
            <a href="index.php" class="btn btn-outline">Return to Home</a>
            <a href="login.php" class="btn btn-primary">Login to Your Account</a>
        </div>
    </div>
</body>
</html> 