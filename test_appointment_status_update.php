<?php
/**
 * Test Appointment Status Update SMS Notification
 */

require_once 'config.php';
require_once 'notification_system.php';

echo "=== Testing Appointment Status Update SMS Notification ===\n\n";

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get a real appointment from the database
$stmt = $conn->prepare("
    SELECT a.*, 
           p.first_name, 
           p.last_name, 
           p.phone_number as patient_phone,
           u.email,
           u.phone as user_phone,
           u.id as user_id,
           v.name as vaccine_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN vaccines v ON a.vaccine_id = v.id
    WHERE a.id = 3
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ No appointment found with ID 3. Please check your database.\n";
    exit;
}

$appointment_data = $result->fetch_assoc();

echo "Found appointment:\n";
echo "- ID: " . $appointment_data['id'] . "\n";
echo "- Patient: " . $appointment_data['first_name'] . " " . $appointment_data['last_name'] . "\n";
echo "- User ID: " . $appointment_data['user_id'] . "\n";
echo "- Phone: " . ($appointment_data['patient_phone'] ?: $appointment_data['user_phone']) . "\n";
echo "- Date: " . $appointment_data['appointment_date'] . "\n";
echo "- Current Status: " . $appointment_data['status'] . "\n\n";

// Initialize notification system
$notification_system = new NotificationSystem();

// Prepare notification message (same as in admin_appointments.php)
$patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
$appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
$appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
$purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
$status = 'confirmed'; // Test with confirmed status
$notes = 'This is a test notification.';

// Create shorter, concise message for SMS compatibility (same as admin_appointments.php)
$status_specific_message = "";
switch($status) {
    case 'confirmed':
        $status_specific_message = "CONFIRMED. Please arrive 15 minutes early.";
        break;
    case 'completed':
        $status_specific_message = "COMPLETED. Thank you for visiting us.";
        break;
    case 'cancelled':
        $status_specific_message = "CANCELLED. You may reschedule anytime.";
        break;
    case 'no_show':
        $status_specific_message = "MISSED. Please contact us to reschedule.";
        break;
    default:
        $status_specific_message = "UPDATED. Thank you.";
}

// Short message format for SMS (removes long details and medical terms)
$status_message = "IMMUCARE: Your appointment on " . $appointment_date . " at " . $appointment_time . " is " . $status_specific_message .
                 (!empty($notes) ? " Note: " . $notes : "");

echo "Sending notification...\n";
echo "Title: Appointment Status Update: " . ucfirst($status) . "\n";
echo "Channel: both (Email + SMS)\n\n";

try {
    $result = $notification_system->sendCustomNotification(
        $appointment_data['user_id'],
        "Appointment Status Update: " . ucfirst($status),
        $status_message,
        'both'
    );
    
    if ($result) {
        echo "✅ SUCCESS: Notification sent successfully!\n";
        echo "✅ Email notification sent\n";
        echo "✅ SMS notification sent\n";
        echo "\nCheck the sms_logs table for SMS details.\n";
        echo "Check the notifications table for notification record.\n";
    } else {
        echo "❌ FAILED: Notification could not be sent.\n";
        echo "Check error logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";

$conn->close();
?>
