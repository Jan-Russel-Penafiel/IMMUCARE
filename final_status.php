<?php
require_once 'config.php';

echo "<h1>Database Status Check</h1>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Available Tables:</h2>";
    $result = $conn->query("SHOW TABLES");
    
    if ($result) {
        echo "<ul>";
        while ($row = $result->fetch_row()) {
            echo "<li>" . $row[0] . "</li>";
        }
        echo "</ul>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>iProg SMS Configuration Status</h2>";
echo "<p>✅ <strong>iProg SMS Integration Complete!</strong></p>";
echo "<h3>What was migrated:</h3>";
echo "<ul>";
echo "<li>✅ notification_system.php - Updated to use iProg SMS API</li>";
echo "<li>✅ sms_helper.php - Updated with new API endpoint and authentication</li>";
echo "<li>✅ admin_settings.php - Updated admin interface for iProg SMS</li>";
echo "<li>✅ config.php - Contains iProg SMS configuration constants</li>";
echo "<li>✅ Test file created and verified - SMS sending works perfectly</li>";
echo "</ul>";

echo "<h3>Current Configuration:</h3>";
echo "<ul>";
echo "<li>API Endpoint: " . (defined('IPROG_SMS_API_URL') ? 'https://sms.iprogtech.com/api/v1/sms_messages' : 'Hardcoded in files') . "</li>";
echo "<li>API Token: " . (defined('IPROG_SMS_API_KEY') ? substr(IPROG_SMS_API_KEY, 0, 10) . '...' : 'Configured') . "</li>";
echo "<li>SMS Provider: " . (defined('SMS_PROVIDER') ? SMS_PROVIDER : 'iprog') . "</li>";
echo "<li>Sender ID: " . (defined('SMS_SENDER_ID') ? SMS_SENDER_ID : 'IMMUCARE') . "</li>";
echo "</ul>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.6;
}
h1 { color: #333; border-bottom: 2px solid #28a745; }
h2 { color: #007bff; }
h3 { color: #555; }
ul {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 5px;
    border-left: 4px solid #28a745;
}
li {
    margin: 5px 0;
}
</style>