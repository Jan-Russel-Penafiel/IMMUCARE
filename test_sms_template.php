<?php
/**
 * SMS Template Test Script
 * Tests the new VMC header/footer template format
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'includes/sms_helper.php';

echo "<h1>SMS Template Test</h1>";
echo "<pre>";

// Test 1: Check if API key is defined
echo "=== TEST 1: API Key Check ===\n";
if (defined('IPROG_SMS_API_KEY')) {
    $masked_key = substr(IPROG_SMS_API_KEY, 0, 10) . '...' . substr(IPROG_SMS_API_KEY, -5);
    echo "✓ IPROG_SMS_API_KEY is defined: {$masked_key}\n";
} else {
    echo "✗ IPROG_SMS_API_KEY is NOT defined!\n";
}

// Test 2: Test formatSMSMessage function
echo "\n=== TEST 2: Message Formatting ===\n";
$test_message = "Appointment on Jan 5, 2026 at 10:00 AM CONFIRMED. Arrive 15 mins early.";
$formatted = formatSMSMessage($test_message);
echo "Original message:\n{$test_message}\n\n";
echo "Formatted message:\n{$formatted}\n";
echo "Message length: " . strlen($formatted) . " characters\n";

// Test 3: Check if message format matches iProg template
echo "\n=== TEST 3: Template Validation ===\n";
$expected_header = "VMC";
$expected_footer = "Thank you. - Respective Personnel";

if (strpos($formatted, $expected_header) === 0) {
    echo "✓ Header 'VMC' is present at the start\n";
} else {
    echo "✗ Header 'VMC' is NOT at the start!\n";
}

if (strpos($formatted, $expected_footer) !== false) {
    echo "✓ Footer 'Thank you. - Respective Personnel' is present\n";
} else {
    echo "✗ Footer is NOT present!\n";
}

// Test 4: Send actual test SMS (if phone number provided)
echo "\n=== TEST 4: Send Test SMS ===\n";
$test_phone = isset($_GET['phone']) ? $_GET['phone'] : '';

if (!empty($test_phone)) {
    echo "Sending SMS to: {$test_phone}\n";
    echo "Message: {$test_message}\n\n";
    
    $result = sendSMS($test_phone, $test_message);
    
    echo "Result:\n";
    echo "Status: " . $result['status'] . "\n";
    echo "Message: " . $result['message'] . "\n";
    
    if (isset($result['response'])) {
        echo "API Response:\n";
        print_r($result['response']);
    }
    
    if ($result['status'] === 'sent') {
        echo "\n✓ SMS sent successfully! Check if credits were deducted.\n";
    } else {
        echo "\n✗ SMS failed to send!\n";
        
        // Check for common issues
        echo "\n=== Troubleshooting ===\n";
        
        // Check response for errors
        if (isset($result['response']['message']) && is_array($result['response']['message'])) {
            echo "API Error Messages:\n";
            foreach ($result['response']['message'] as $error) {
                echo "  - {$error}\n";
            }
        }
        
        // Check if it's a template issue
        if (isset($result['response']['status']) && $result['response']['status'] == 500) {
            echo "\n⚠ This might be a template mismatch issue.\n";
            echo "The iProg SMS API requires messages to match an approved template.\n";
            echo "Your template uses:\n";
            echo "  Header: VMC\n";
            echo "  Footer: Thank you. - Respective Personnel\n";
        }
    }
} else {
    echo "To test sending SMS, add ?phone=YOUR_PHONE_NUMBER to the URL\n";
    echo "Example: test_sms_template.php?phone=09123456789\n";
}

// Test 5: Check PHP error log for recent SMS errors
echo "\n=== TEST 5: Recent Error Log Check ===\n";
$error_log_path = ini_get('error_log');
if ($error_log_path && file_exists($error_log_path)) {
    $lines = file($error_log_path);
    $recent_lines = array_slice($lines, -20);
    $sms_errors = array_filter($recent_lines, function($line) {
        return stripos($line, 'sms') !== false || stripos($line, 'iprog') !== false;
    });
    
    if (!empty($sms_errors)) {
        echo "Recent SMS-related log entries:\n";
        foreach ($sms_errors as $line) {
            echo "  " . trim($line) . "\n";
        }
    } else {
        echo "No recent SMS-related errors in log.\n";
    }
} else {
    echo "Could not read error log. Path: " . ($error_log_path ?: 'Not set') . "\n";
}

echo "</pre>";

// Show form for easy testing
?>
<hr>
<h2>Quick Test Form</h2>
<form method="get">
    <label>Phone Number: <input type="text" name="phone" placeholder="09123456789" value="<?= htmlspecialchars($test_phone) ?>"></label>
    <button type="submit">Send Test SMS</button>
</form>
