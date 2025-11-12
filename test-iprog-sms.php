<?php
/**
 * Test IPROG SMS Integration for MIC Project
 */

require_once 'includes/sms_helper.php';

// Test phone number - replace with a real number for testing
$test_phone = '09123456789';
$test_message = 'Test SMS from MIC using IPROG SMS API';

echo "<h2>Testing IPROG SMS Integration - MIC Project</h2>";

// Test sendSMS function
echo "<h3>Testing sendSMS() function:</h3>";
$result = sendSMS($test_phone, $test_message);

echo "<pre>";
print_r($result);
echo "</pre>";

// Test sendSMSUsingIPROG function directly
echo "<h3>Testing sendSMSUsingIPROG() function directly:</h3>";
$api_key = '1ef3b27ea753780a90cbdf07d027fb7b52791004'; // Your IPROG API key
$direct_result = sendSMSUsingIPROG($test_phone, $test_message, $api_key);

echo "<pre>";
print_r($direct_result);
echo "</pre>";

// Test OTP function
echo "<h3>Testing sendOTP() function:</h3>";
$otp = rand(100000, 999999);
$otp_result = sendOTP($test_phone, $otp);

echo "<pre>";
print_r($otp_result);
echo "</pre>";

echo "<p><strong>Note:</strong> Replace the test phone number with a real number to test actual SMS sending.</p>";
?>