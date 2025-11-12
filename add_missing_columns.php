<?php
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Add reference_id column to sms_logs
    $result = $conn->query("SHOW COLUMNS FROM sms_logs LIKE 'reference_id'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE sms_logs ADD COLUMN reference_id VARCHAR(100) AFTER message";
        if ($conn->query($sql)) {
            echo "✓ Added reference_id column to sms_logs\n";
        } else {
            echo "❌ Failed to add reference_id column: " . $conn->error . "\n";
        }
    } else {
        echo "- reference_id column already exists in sms_logs\n";
    }
    
    // Also add email column to patients if missing
    $result = $conn->query("SHOW COLUMNS FROM patients LIKE 'email'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE patients ADD COLUMN email VARCHAR(255) AFTER phone_number";
        if ($conn->query($sql)) {
            echo "✓ Added email column to patients\n";
        } else {
            echo "❌ Failed to add email column: " . $conn->error . "\n";
        }
    } else {
        echo "- email column already exists in patients\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>