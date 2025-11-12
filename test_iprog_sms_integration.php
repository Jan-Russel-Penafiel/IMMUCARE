<?php
/**
 * Test IProg SMS Integration
 * 
 * This file tests the IProg SMS integration to ensure it's working correctly.
 */

require_once 'config.php';
require_once 'includes/sms_helper.php';

// Test the SMS helper function
function testSMSHelper() {
    echo "<h2>Testing SMS Helper Function</h2>\n";
    
    // Test phone number (replace with your own test number)
    $test_phone = "09123456789"; // Replace with actual test number
    $test_message = "Hello, this is a test message from IMMUCARE using IProg SMS API.";
    
    echo "<p>Sending test SMS to: {$test_phone}</p>\n";
    echo "<p>Message: {$test_message}</p>\n";
    
    $result = sendSMS($test_phone, $test_message);
    
    echo "<p><strong>Result:</strong></p>\n";
    echo "<pre>" . print_r($result, true) . "</pre>\n";
    
    if ($result['status'] === 'sent') {
        echo "<p style='color: green;'><strong>✓ SMS sent successfully!</strong></p>\n";
    } else {
        echo "<p style='color: red;'><strong>✗ SMS failed to send.</strong></p>\n";
    }
}

// Test the NotificationSystem class
function testNotificationSystem() {
    echo "<h2>Testing NotificationSystem Class</h2>\n";
    
    try {
        require_once 'notification_system.php';
        $notificationSystem = new NotificationSystem();
        
        echo "<p style='color: green;'><strong>✓ NotificationSystem class loaded successfully!</strong></p>\n";
        echo "<p>IProg SMS integration is ready for use in the notification system.</p>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'><strong>✗ Error loading NotificationSystem:</strong> " . $e->getMessage() . "</p>\n";
    }
}

// Display API configuration
function displayConfiguration() {
    echo "<h2>Current SMS Configuration</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>SMS Provider:</strong> " . (defined('SMS_PROVIDER') ? SMS_PROVIDER : 'Not defined') . "</li>\n";
    echo "<li><strong>API Key:</strong> " . (defined('IPROG_SMS_API_KEY') ? substr(IPROG_SMS_API_KEY, 0, 10) . "..." : 'Not defined') . "</li>\n";
    echo "<li><strong>Sender ID:</strong> " . (defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'Not defined') . "</li>\n";
    echo "<li><strong>API Endpoint:</strong> https://sms.iprogtech.com/api/v1/sms_messages</li>\n";
    echo "</ul>\n";
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>IProg SMS Integration Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        h2 { color: #666; border-bottom: 1px solid #ccc; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 4px; }
        .warning { color: orange; background: #fff3cd; padding: 10px; border-radius: 4px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>IProg SMS Integration Test</h1>
    
    <div class="warning">
        <strong>⚠️ Warning:</strong> This test will send an actual SMS message. 
        Make sure to replace the test phone number with your own number before testing.
    </div>
    
    <?php
    displayConfiguration();
    testNotificationSystem();
    
    // Uncomment the line below to test actual SMS sending
    // testSMSHelper();
    ?>
    
    <h2>Manual Testing Instructions</h2>
    <ol>
        <li>Edit this file and replace the test phone number with your own</li>
        <li>Uncomment the <code>testSMSHelper();</code> line above</li>
        <li>Reload this page to send a test SMS</li>
        <li>Check your phone for the test message</li>
        <li>Check the browser output for API response details</li>
    </ol>
    
    <h2>Integration Notes</h2>
    <ul>
        <li>✅ Updated SMS helper to use IProg SMS API</li>
        <li>✅ Updated NotificationSystem class to use IProg SMS</li>
        <li>✅ Phone number formatting adjusted for IProg API format</li>
        <li>✅ API endpoint changed to https://sms.iprogtech.com/api/v1/sms_messages</li>
        <li>✅ Authentication method updated (api_token instead of Bearer token)</li>
        <li>✅ Request payload format updated for IProg SMS</li>
    </ul>
</body>
</html>