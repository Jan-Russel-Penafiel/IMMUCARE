<?php
require_once 'config.php';
require_once 'includes/sms_helper.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$result = $conn->query('SELECT phone_number FROM patients WHERE phone_number IS NOT NULL LIMIT 1');
$row = $result->fetch_assoc();

echo "Test phone: " . $row['phone_number'] . "\n\n";

// Now send an actual test SMS
$test_message = "Appointment on Jan 5, 2026 at 10:00 AM CONFIRMED. Arrive 15 mins early.";

echo "Sending SMS to: " . $row['phone_number'] . "\n";
echo "Message: " . $test_message . "\n\n";

$sms_result = sendSMS($row['phone_number'], $test_message);

echo "=== SMS RESULT ===\n";
echo "Status: " . $sms_result['status'] . "\n";
echo "Message: " . $sms_result['message'] . "\n";

if (isset($sms_result['response'])) {
    echo "\nAPI Response:\n";
    print_r($sms_result['response']);
}

$conn->close();
