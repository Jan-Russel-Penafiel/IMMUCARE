<?php
/**
 * IPROG SMS Configuration Verification Script
 * 
 * This script verifies that the SMS system is properly configured for IPROG SMS
 */

require_once 'config.php';
require_once 'notification_system.php';

echo "<h2>IPROG SMS Configuration Verification</h2>\n";

try {
    // Test database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h3>1. Database Configuration Check</h3>\n";
    
    // Check system settings
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_enabled', 'sms_provider', 'sms_api_key')");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $settings = [];
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Check SMS enabled
    if (isset($settings['sms_enabled']) && $settings['sms_enabled'] === 'true') {
        echo "✓ SMS is enabled\n";
    } else {
        echo "❌ SMS is not enabled (current value: " . ($settings['sms_enabled'] ?? 'not set') . ")\n";
    }
    
    // Check SMS provider
    if (isset($settings['sms_provider']) && $settings['sms_provider'] === 'iprog') {
        echo "✓ SMS provider is set to IPROG\n";
    } else {
        echo "❌ SMS provider is not set to IPROG (current value: " . ($settings['sms_provider'] ?? 'not set') . ")\n";
    }
    
    // Check API key
    if (isset($settings['sms_api_key']) && $settings['sms_api_key'] === '1ef3b27ea753780a90cbdf07d027fb7b52791004') {
        echo "✓ IPROG SMS API key is correctly configured\n";
    } else {
        echo "❌ IPROG SMS API key is not correctly set\n";
        echo "  Expected: 1ef3b27ea753780a90cbdf07d027fb7b52791004\n";
        echo "  Current: " . (isset($settings['sms_api_key']) ? substr($settings['sms_api_key'], 0, 20) . '...' : 'not set') . "\n";
    }
    
    echo "<h3>2. Configuration Constants Check</h3>\n";
    
    // Check config.php constants
    if (defined('SMS_PROVIDER') && SMS_PROVIDER === 'iprog') {
        echo "✓ SMS_PROVIDER constant is set to 'iprog'\n";
    } else {
        echo "❌ SMS_PROVIDER constant is not set to 'iprog' (current: " . (defined('SMS_PROVIDER') ? SMS_PROVIDER : 'not defined') . ")\n";
    }
    
    if (defined('IPROG_SMS_API_KEY') && IPROG_SMS_API_KEY === '1ef3b27ea753780a90cbdf07d027fb7b52791004') {
        echo "✓ IPROG_SMS_API_KEY constant is correctly set\n";
    } else {
        echo "❌ IPROG_SMS_API_KEY constant is not correctly set\n";
    }
    
    echo "<h3>3. SMS Functions Test</h3>\n";
    
    // Test getSMSConfigEnhanced function
    $sms_config = getSMSConfigEnhanced($conn);
    if ($sms_config) {
        echo "✓ SMS configuration retrieved successfully\n";
        echo "  - Status: " . $sms_config['status'] . "\n";
        echo "  - Provider: " . $sms_config['provider'] . "\n";
        echo "  - API Key: " . substr($sms_config['api_key'], 0, 20) . "...\n";
        
        if ($sms_config['provider'] === 'iprog' && $sms_config['api_key'] === '1ef3b27ea753780a90cbdf07d027fb7b52791004') {
            echo "✓ SMS configuration is correct for IPROG SMS\n";
        } else {
            echo "❌ SMS configuration does not match expected IPROG settings\n";
        }
    } else {
        echo "❌ Could not retrieve SMS configuration\n";
    }
    
    echo "<h3>4. Test SMS API Function</h3>\n";
    
    // Test the IPROG SMS function (without actually sending)
    $test_phone = '639171234567';
    $test_message = 'Test message for IPROG SMS verification';
    $test_api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004';
    
    echo "Testing IPROG SMS function with:\n";
    echo "  - Phone: $test_phone\n";
    echo "  - API Key: " . substr($test_api_key, 0, 20) . "...\n";
    echo "  - Message: Test message\n";
    
    // Note: This would actually send an SMS, so we'll just validate the function exists
    if (function_exists('sendSMSUsingIPROGEnhanced')) {
        echo "✓ sendSMSUsingIPROGEnhanced function is available\n";
        echo "⚠ Note: To avoid sending actual SMS, we're not executing the send test\n";
    } else {
        echo "❌ sendSMSUsingIPROGEnhanced function not found\n";
    }
    
    echo "<h3>5. Auto-Fix Configuration</h3>\n";
    
    $fixes_needed = false;
    
    // Auto-fix SMS settings if needed
    if (!isset($settings['sms_enabled']) || $settings['sms_enabled'] !== 'true') {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_enabled', 'true') ON DUPLICATE KEY UPDATE setting_value = 'true'");
        if ($stmt->execute()) {
            echo "✓ Fixed: SMS enabled setting\n";
            $fixes_needed = true;
        }
    }
    
    if (!isset($settings['sms_provider']) || $settings['sms_provider'] !== 'iprog') {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_provider', 'iprog') ON DUPLICATE KEY UPDATE setting_value = 'iprog'");
        if ($stmt->execute()) {
            echo "✓ Fixed: SMS provider setting\n";
            $fixes_needed = true;
        }
    }
    
    if (!isset($settings['sms_api_key']) || $settings['sms_api_key'] !== '1ef3b27ea753780a90cbdf07d027fb7b52791004') {
        $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('sms_api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004';
        $stmt->bind_param("ss", $api_key, $api_key);
        if ($stmt->execute()) {
            echo "✓ Fixed: IPROG SMS API key setting\n";
            $fixes_needed = true;
        }
    }
    
    if (!$fixes_needed) {
        echo "✓ No configuration fixes needed\n";
    }
    
    echo "<h3>6. Summary</h3>\n";
    
    // Final verification
    $final_check = getSMSConfigEnhanced($conn);
    if ($final_check && $final_check['status'] === 'active' && 
        $final_check['provider'] === 'iprog' && 
        $final_check['api_key'] === '1ef3b27ea753780a90cbdf07d027fb7b52791004') {
        echo "<p><strong>🎉 SUCCESS!</strong> Your SMS system is properly configured for IPROG SMS!</p>\n";
        echo "<p><strong>Configuration Summary:</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Provider: IPROG SMS</li>\n";
        echo "<li>API Key: 1ef3b27ea753780a90cbdf07d027fb7b52791004</li>\n";
        echo "<li>Status: Active</li>\n";
        echo "</ul>\n";
        echo "<p>You can now use the SMS functionality for appointment reminders and notifications!</p>\n";
    } else {
        echo "<p><strong>❌ Configuration Issues Found</strong></p>\n";
        echo "<p>Please check the issues above and run this script again.</p>\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p><strong>❌ Error:</strong> " . $e->getMessage() . "</p>\n";
}
?>