<?php
/**
 * Test script for iProg SMS integration
 * This script tests the SMS functionality with the new iProg SMS API
 */

require_once 'config.php';
require_once 'includes/sms_helper.php';

// Verify configuration
echo "<h2>Configuration Check</h2>\n";
echo "<ul>\n";
echo "<li>SMS Provider: " . (defined('SMS_PROVIDER') ? SMS_PROVIDER : '<span style="color:red;">NOT DEFINED</span>') . "</li>\n";
echo "<li>API Key: " . (defined('IPROG_SMS_API_KEY') ? 'Configured (' . substr(IPROG_SMS_API_KEY, 0, 8) . '...)' : '<span style="color:red;">NOT DEFINED</span>') . "</li>\n";
echo "<li>Sender ID: " . (defined('SMS_SENDER_ID') ? SMS_SENDER_ID : '<span style="color:red;">NOT DEFINED</span>') . "</li>\n";
echo "</ul>\n";

// Check if all required constants are defined
$config_ok = defined('SMS_PROVIDER') && defined('IPROG_SMS_API_KEY') && defined('SMS_SENDER_ID');
if (!$config_ok) {
    echo "<p style='color: red; font-weight: bold;'>❌ Configuration incomplete! Please check config.php</p>\n";
    exit;
}
echo "<p style='color: green; font-weight: bold;'>✅ Configuration looks good!</p>\n";

// Test phone number (use your actual phone number for testing)
$test_phone = '639677726912'; // Replace with your test number
$test_message = 'IMMUCARE: This is a test message from the new iProg SMS integration.';

echo "<h1>iProg SMS Integration Test</h1>\n";
echo "<p>Testing SMS with the following configuration:</p>\n";
echo "<ul>\n";
echo "<li>API URL: https://sms.iprogtech.com/api/v1/sms_messages</li>\n";
echo "<li>API Key: " . substr(IPROG_SMS_API_KEY, 0, 10) . "...</li>\n";
echo "<li>SMS Provider: " . SMS_PROVIDER . "</li>\n";
echo "<li>Test Phone: " . $test_phone . "</li>\n";
echo "</ul>\n";

echo "<h2>Sending Test SMS...</h2>\n";

try {
    $result = sendSMS($test_phone, $test_message);
    
    echo "<h3>Result:</h3>\n";
    echo "<pre>" . print_r($result, true) . "</pre>\n";
    
    if ($result['status'] === 'sent') {
        echo "<p style='color: green;'><strong>✓ SMS sent successfully!</strong></p>\n";
    } else {
        echo "<p style='color: red;'><strong>✗ SMS failed to send.</strong></p>\n";
        echo "<p>Error: " . $result['message'] . "</p>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Exception occurred:</strong> " . $e->getMessage() . "</p>\n";
}

echo "<hr>\n";
echo "<h2>Testing Direct API Call</h2>\n";

// Test direct API call
$api_url = "https://sms.iprogtech.com/api/v1/sms_messages";

// Format phone number properly for direct API test
$formatted_phone = preg_replace('/[^0-9]/', '', $test_phone);
if (substr($formatted_phone, 0, 1) === '0') {
    $formatted_phone = '63' . substr($formatted_phone, 1);
} elseif (!preg_match('/^63/', $formatted_phone)) {
    $formatted_phone = '63' . $formatted_phone;
}

$api_data = [
    'api_token' => IPROG_SMS_API_KEY,
    'phone_number' => $formatted_phone,
    'message' => $test_message
];

echo "<p>Formatted Phone Number: " . $formatted_phone . "</p>\n";
echo "<p>API Data:</p>\n";
echo "<pre>" . json_encode($api_data, JSON_PRETTY_PRINT) . "</pre>\n";

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "<h3>Direct API Response:</h3>\n";
if (!empty($curl_error)) {
    echo "<p style='color: red;'>cURL Error: " . htmlspecialchars($curl_error) . "</p>\n";
}
echo "<p>HTTP Code: " . $http_code . "</p>\n";
echo "<p>Response: <code>" . htmlspecialchars($response) . "</code></p>\n";

$response_data = json_decode($response, true);
if ($response_data !== null) {
    echo "<p>Parsed Response:</p>\n";
    echo "<pre>" . print_r($response_data, true) . "</pre>\n";
    
    if ($http_code === 200 || $http_code === 201) {
        echo "<p style='color: green;'><strong>✓ Direct API call successful!</strong></p>\n";
    } else {
        echo "<p style='color: red;'><strong>✗ Direct API call failed!</strong></p>\n";
    }
} else {
    echo "<p style='color: orange;'><strong>⚠ Could not parse JSON response</strong></p>\n";
}

?>
<style>
body { 
    font-family: Arial, sans-serif; 
    max-width: 800px; 
    margin: 20px auto; 
    padding: 20px;
    line-height: 1.6;
}
h1 { color: #333; border-bottom: 2px solid #007bff; }
h2 { color: #007bff; border-bottom: 1px solid #ddd; }
h3 { color: #555; }
pre { 
    background: #f8f9fa; 
    padding: 15px; 
    border-radius: 5px; 
    border: 1px solid #dee2e6;
    overflow-x: auto;
}
code { 
    background: #f8f9fa; 
    padding: 2px 4px; 
    border-radius: 3px; 
    font-family: monospace;
}
ul { 
    background: #f8f9fa; 
    padding: 15px; 
    border-radius: 5px; 
    border-left: 4px solid #007bff;
}
hr { margin: 30px 0; border: none; border-top: 2px solid #dee2e6; }
.success { color: #28a745; font-weight: bold; }
.error { color: #dc3545; font-weight: bold; }
.warning { color: #ffc107; font-weight: bold; }
</style>