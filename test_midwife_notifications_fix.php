<?php
/**
 * Test Midwife Notifications SMS Fix
 */

require_once 'config.php';
require_once 'notification_system.php';

echo "=== Testing Midwife Notifications SMS Fix ===\n\n";

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get a test patient
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.phone, p.first_name, p.last_name, p.phone_number as patient_phone
    FROM users u
    LEFT JOIN patients p ON u.id = p.user_id
    WHERE u.user_type = 'patient' AND u.is_active = 1
    LIMIT 1
");

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "❌ No patient found for testing.\n";
    exit;
}

$patient = $result->fetch_assoc();
echo "Found test patient:\n";
echo "- ID: " . $patient['id'] . "\n";
echo "- Name: " . ($patient['first_name'] ? $patient['first_name'] . " " . $patient['last_name'] : $patient['name']) . "\n";
echo "- Email: " . $patient['email'] . "\n";
echo "- Phone: " . ($patient['patient_phone'] ?: $patient['phone']) . "\n\n";

// Initialize notification system
$notification_system = new NotificationSystem();

// Test the same logic as midwife_notifications.php
$title = "Test Notification";
$message = "This is a test notification to verify SMS functionality.";
$type = 'email_sms'; // This was causing the issue
$recipients = [$patient['id']];

echo "Testing notification sending...\n";
echo "Title: $title\n";
echo "Type: $type (should map to 'both' channel)\n";
echo "Recipients: " . count($recipients) . "\n\n";

// Simulate the fixed logic from midwife_notifications.php
$success_count = 0;
$failed_count = 0;
$email_sent = 0;
$sms_sent = 0;

foreach ($recipients as $recipient_id) {
    // Map email_sms to 'both' channel (which notification_system.php expects)
    $delivery_channel = ($type === 'email_sms') ? 'both' : $type;
    
    echo "Calling sendCustomNotification with channel: " . $delivery_channel . "\n";
    
    try {
        $result = $notification_system->sendCustomNotification($recipient_id, $title, $message, $delivery_channel);
        
        if ($result) {
            $success_count++;
            // Count delivery types for display
            if ($type === 'email_sms') {
                $email_sent++;
                $sms_sent++;
            } elseif ($type === 'email') {
                $email_sent++;
            } elseif ($type === 'sms') {
                $sms_sent++;
            }
            echo "✅ SUCCESS: Notification sent successfully!\n";
        } else {
            $failed_count++;
            echo "❌ FAILED: Notification could not be sent.\n";
        }
    } catch (Exception $e) {
        $failed_count++;
        echo "❌ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n=== Results ===\n";
echo "Success Count: $success_count\n";
echo "Failed Count: $failed_count\n";
echo "Email Sent: $email_sent\n";
echo "SMS Sent: $sms_sent\n";

if ($success_count > 0) {
    $message_text = "Notification sent successfully to {$success_count} recipient(s).";
    if ($type === 'email_sms') {
        $message_text .= " (Email: {$email_sent}, SMS: {$sms_sent})";
    }
    echo "\n✅ SUCCESS MESSAGE: $message_text\n";
} else {
    echo "\n❌ FAILURE MESSAGE: Error sending notifications. Please try again.\n";
}

echo "\n=== Test Complete ===\n";

$conn->close();
?>