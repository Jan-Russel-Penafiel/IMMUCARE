<?php
require_once 'config.php';
require_once 'includes/sms_helper.php';

// Test with a very simple message
$phone = '09677726912';
$message = "IMMUCARE: Your appointment is confirmed. Please arrive 15 minutes early.";

echo "Testing simple SMS message...\n";
echo "Phone: $phone\n";
echo "Message: $message\n\n";

$result = sendSMS($phone, $message);

echo "Result:\n";
print_r($result);
?>
