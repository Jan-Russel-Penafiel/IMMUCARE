<?php
/**
 * Test Immunization Record Notification SMS
 * This test verifies that the immunization notification with next dose date works correctly
 */

require_once 'notification_system.php';

echo "=== Testing Immunization Record Notification ===\n\n";

try {
    $notification = new NotificationSystem();
    
    // Test with a real immunization record (using ID 1 as example)
    echo "Sending immunization record notification for immunization ID 1...\n";
    $result = $notification->sendImmunizationRecordNotification(1);
    
    if (isset($result['error'])) {
        echo "Error: " . $result['error'] . "\n";
    } else {
        echo "Results:\n";
        echo "- Email sent: " . ($result['email_sent'] ? 'Yes' : 'No') . "\n";
        echo "- SMS sent: " . ($result['sms_sent'] ? 'Yes' : 'No') . "\n";
        
        if ($result['sms_sent']) {
            echo "\n✅ SUCCESS: Immunization notification with next dose date sent successfully!\n";
        } else {
            echo "\n⚠️ WARNING: SMS notification was not sent.\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
?>
