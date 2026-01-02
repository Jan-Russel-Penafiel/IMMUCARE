<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Get successful SMS messages
$query = "SELECT id, message, provider_response FROM sms_logs WHERE status = 'sent' ORDER BY id DESC LIMIT 10";
$result = $conn->query($query);

echo "=== Recent Successful SMS Messages ===\n\n";

if ($result && $result->num_rows > 0) {
    while ($sms = $result->fetch_assoc()) {
        $response_data = json_decode($sms['provider_response'], true);
        $api_status = isset($response_data['status']) ? $response_data['status'] : 'N/A';
        $was_successful = ($api_status == 200);
        
        echo "ID: " . $sms['id'] . "\n";
        echo "API Status: " . $api_status . " (" . ($was_successful ? "SUCCESS" : "FAILED") . ")\n";
        echo "Message:\n" . $sms['message'] . "\n";
        echo "---\n\n";
    }
} else {
    echo "No SMS records found.\n";
}

$conn->close();
