<?php
require_once 'config.php';
require_once 'notification_system.php';

echo "<h1>SMS Notification Test</h1>";

try {
    // Create notification system instance
    $notification_system = new NotificationSystem();
    
    echo "<h2>Configuration Check:</h2>";
    echo "<p>SMS Provider: " . (defined('SMS_PROVIDER') ? SMS_PROVIDER : 'Not defined') . "</p>";
    echo "<p>API Key: " . (defined('IPROG_SMS_API_KEY') ? substr(IPROG_SMS_API_KEY, 0, 10) . '...' : 'Not defined') . "</p>";
    echo "<p>Sender ID: " . (defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'Not defined') . "</p>";
    
    // Test user ID - using a real user from the database
    $test_user_id = 18; // This should be a real user ID from your database
    
    echo "<h2>Sending Test Notification...</h2>";
    
    $result = $notification_system->sendCustomNotification(
        $test_user_id,
        "Test SMS Notification",
        "This is a test message to verify SMS functionality.",
        'sms'
    );
    
    if ($result) {
        echo "<p style='color: green;'>✅ SMS notification sent successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ SMS notification failed!</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Database Check - Recent SMS Logs:</h2>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 5");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Phone</th><th>Message</th><th>Status</th><th>Created At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['phone_number'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['message'], 0, 50)) . "...</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No SMS logs found.</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
}
table {
    width: 100%;
    border-collapse: collapse;
}
th, td {
    text-align: left;
    padding: 8px;
}
th {
    background-color: #f2f2f2;
}
</style>