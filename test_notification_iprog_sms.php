<?php
/**
 * Test NotificationSystem with iProg SMS Integration
 */

require_once 'notification_system.php';

echo "=== Testing NotificationSystem with iProg SMS ===\n\n";

try {
    $notification = new NotificationSystem();
    
    // Test sending a custom notification with SMS
    $test_user_id = 18; // Use a valid user ID from your database
    $title = "Test Notification";
    $message = "Testing iProg SMS integration via NotificationSystem class.";
    
    echo "Sending test notification...\n";
    echo "User ID: $test_user_id\n";
    echo "Title: $title\n";
    echo "Message: $message\n";
    echo "Channel: SMS only\n\n";
    
    $result = $notification->sendCustomNotification($test_user_id, $title, $message, 'sms');
    
    if ($result) {
        echo "✅ SUCCESS: Notification sent successfully!\n";
        echo "Check the sms_logs table for details.\n";
    } else {
        echo "❌ FAILED: Notification could not be sent.\n";
        echo "Check error logs for details.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "\nNote: This test uses the iProg SMS API via the sendSMS helper function.\n";
echo "The SMS helper function is located in includes/sms_helper.php\n";
?>
