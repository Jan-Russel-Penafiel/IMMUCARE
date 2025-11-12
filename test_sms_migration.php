<?php
/**
 * Simple test to verify the SMS system migration
 */

require_once 'config.php';

try {
    // Test database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>SMS System Migration Test</h2>\n";
    
    // 1. Check if sms_logs table exists and has required columns
    echo "<h3>1. Testing sms_logs table structure...</h3>\n";
    
    $result = $conn->query("DESCRIBE sms_logs");
    if (!$result) {
        echo "❌ sms_logs table does not exist\n";
    } else {
        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        
        $required_columns = [
            'id', 'patient_id', 'user_id', 'phone_number', 'message', 
            'status', 'notification_type', 'provider_response', 'reference_id',
            'related_to', 'related_id', 'scheduled_at', 'sent_at', 
            'created_at', 'updated_at', 'retry_count'
        ];
        
        $missing_columns = array_diff($required_columns, $columns);
        
        if (empty($missing_columns)) {
            echo "✓ sms_logs table structure is correct\n";
            echo "  Columns: " . implode(', ', $columns) . "\n";
        } else {
            echo "⚠ Missing columns in sms_logs: " . implode(', ', $missing_columns) . "\n";
        }
    }
    
    // 2. Check if system_settings table exists and has SMS settings
    echo "<h3>2. Testing system_settings table...</h3>\n";
    
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'sms_%'");
    if (!$result) {
        echo "❌ Could not query system_settings table\n";
    } else {
        if ($result->num_rows > 0) {
            echo "✓ SMS settings found:\n";
            while ($row = $result->fetch_assoc()) {
                $value = ($row['setting_key'] == 'sms_api_key') ? 
                    substr($row['setting_value'], 0, 10) . '...' : 
                    $row['setting_value'];
                echo "  - " . $row['setting_key'] . ": " . $value . "\n";
            }
        } else {
            echo "⚠ No SMS settings found in system_settings\n";
        }
    }
    
    // 3. Test inserting a sample SMS log entry
    echo "<h3>3. Testing SMS log insertion...</h3>\n";
    
    $test_stmt = $conn->prepare("
        INSERT INTO sms_logs 
        (phone_number, message, status, notification_type, related_to, created_at) 
        VALUES (?, ?, 'pending', 'test', 'migration_test', NOW())
    ");
    
    if ($test_stmt) {
        $test_phone = '639171234567';
        $test_message = 'Test SMS for migration verification';
        
        $test_stmt->bind_param("ss", $test_phone, $test_message);
        
        if ($test_stmt->execute()) {
            $test_id = $conn->insert_id;
            echo "✓ Successfully inserted test SMS log (ID: $test_id)\n";
            
            // Clean up test entry
            $cleanup_stmt = $conn->prepare("DELETE FROM sms_logs WHERE id = ?");
            $cleanup_stmt->bind_param("i", $test_id);
            $cleanup_stmt->execute();
            $cleanup_stmt->close();
            echo "✓ Test entry cleaned up\n";
        } else {
            echo "❌ Failed to insert test SMS log: " . $test_stmt->error . "\n";
        }
        
        $test_stmt->close();
    } else {
        echo "❌ Failed to prepare test insert statement: " . $conn->error . "\n";
    }
    
    // 4. Check if notification_system.php functions are available
    echo "<h3>4. Testing notification system functions...</h3>\n";
    
    if (file_exists('notification_system.php')) {
        include_once 'notification_system.php';
        
        if (function_exists('sendSMSUsingIPROGEnhanced')) {
            echo "✓ sendSMSUsingIPROGEnhanced function is available\n";
        } else {
            echo "❌ sendSMSUsingIPROGEnhanced function not found\n";
        }
        
        if (function_exists('getSMSConfigEnhanced')) {
            echo "✓ getSMSConfigEnhanced function is available\n";
            
            // Test getting SMS config
            $sms_config = getSMSConfigEnhanced($conn);
            if ($sms_config) {
                echo "✓ SMS configuration retrieved successfully\n";
                echo "  - Status: " . $sms_config['status'] . "\n";
                echo "  - Provider: " . $sms_config['provider'] . "\n";
                echo "  - API Key: " . substr($sms_config['api_key'], 0, 10) . "...\n";
            } else {
                echo "⚠ Could not retrieve SMS configuration\n";
            }
        } else {
            echo "❌ getSMSConfigEnhanced function not found\n";
        }
        
        if (class_exists('NotificationSystem')) {
            echo "✓ NotificationSystem class is available\n";
        } else {
            echo "❌ NotificationSystem class not found\n";
        }
    } else {
        echo "❌ notification_system.php file not found\n";
    }
    
    echo "<h3>5. Summary</h3>\n";
    echo "<p>If all tests show ✓ (checkmarks), your SMS system migration was successful!</p>\n";
    echo "<p>If there are ❌ (X marks) or ⚠ (warnings), please run the migration script again or check the issues.</p>\n";
    
    echo "<h3>Next Steps</h3>\n";
    echo "<ul>\n";
    echo "<li>Update your SMS API key in system_settings</li>\n";
    echo "<li>Test SMS sending with sms_usage_example.php</li>\n";
    echo "<li>Set up the cron job for scheduled SMS processing</li>\n";
    echo "</ul>\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p><strong>❌ Test failed:</strong> " . $e->getMessage() . "</p>\n";
}
?>