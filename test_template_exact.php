<?php
require_once 'config.php';
require_once 'includes/sms_helper.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$result = $conn->query('SELECT phone_number FROM patients WHERE phone_number IS NOT NULL LIMIT 1');
$row = $result->fetch_assoc();

echo "Test phone: " . $row['phone_number'] . "\n\n";

// Test 1: Try exact template message (no variable content)
echo "=== TEST 1: Exact Template Message ===\n";
$exact_template = "VMC\nThis is an important message from the Organization.\nThank you. - Respective Personnel";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://sms.iprogtech.com/api/v1/sms_messages",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'api_token' => IPROG_SMS_API_KEY,
        'phone_number' => '63' . substr(preg_replace('/[^0-9]/', '', $row['phone_number']), -10),
        'message' => $exact_template
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"]
]);

echo "Message being sent:\n" . $exact_template . "\n\n";

$response = curl_exec($ch);
curl_close($ch);

echo "Response: " . $response . "\n\n";

// Test 2: Try with newlines replaced by spaces
echo "=== TEST 2: With Spaces Instead of Newlines ===\n";
$space_template = "VMC This is an important message from the Organization. Thank you. - Respective Personnel";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "https://sms.iprogtech.com/api/v1/sms_messages",
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'api_token' => IPROG_SMS_API_KEY,
        'phone_number' => '63' . substr(preg_replace('/[^0-9]/', '', $row['phone_number']), -10),
        'message' => $space_template
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json"]
]);

echo "Message being sent:\n" . $space_template . "\n\n";

$response = curl_exec($ch);
curl_close($ch);

echo "Response: " . $response . "\n\n";

// Test 3: Check what successful messages looked like before
echo "=== TEST 3: Previous Successful SMS Format ===\n";
$query = "SELECT message, provider_response FROM sms_logs WHERE status = 'sent' ORDER BY id DESC LIMIT 3";
$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while ($sms = $result->fetch_assoc()) {
        $response_data = json_decode($sms['provider_response'], true);
        if (isset($response_data['status']) && $response_data['status'] == 200) {
            echo "Previous successful message format:\n";
            echo $sms['message'] . "\n\n";
        }
    }
} else {
    echo "No successful SMS found in logs.\n";
}

$conn->close();
