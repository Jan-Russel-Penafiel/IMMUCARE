<?php
/**
 * Comprehensive SMS System Test
 */

require_once 'notification_system.php';
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>ImmuCare SMS System Test</h2>\n";
    
    // 1. Test SMS Configuration
    echo "<h3>1. SMS Configuration Test</h3>\n";
    $sms_config = getSMSConfigEnhanced($conn);
    if ($sms_config && $sms_config['provider'] === 'iprog') {
        echo "✓ SMS Provider: IPROG\n";
        echo "✓ API Key configured: " . substr($sms_config['api_key'], 0, 10) . "...\n";
    } else {
        echo "❌ SMS configuration not found or invalid\n";
        exit;
    }
    
    // 2. Test Notification System Class
    echo "<h3>2. NotificationSystem Class Test</h3>\n";
    $notification = new NotificationSystem();
    echo "✓ NotificationSystem class initialized\n";
    
    // 3. Test Database Structure
    echo "<h3>3. Database Structure Test</h3>\n";
    
    // Check required tables and columns
    $tables_columns = [
        'patients' => ['id', 'phone_number', 'email'],
        'appointments' => ['id', 'patient_id', 'appointment_date', 'location', 'status'],
        'sms_logs' => ['id', 'patient_id', 'user_id', 'message', 'status']
    ];
    
    foreach ($tables_columns as $table => $columns) {
        echo "Checking table: $table\n";
        foreach ($columns as $column) {
            $result = $conn->query("SHOW COLUMNS FROM $table LIKE '$column'");
            if ($result->num_rows > 0) {
                echo "  ✓ Column $column exists\n";
            } else {
                echo "  ❌ Column $column missing\n";
            }
        }
    }
    
    // 4. Test Phone Number Validation
    echo "<h3>4. Phone Number Validation Test</h3>\n";
    $result = $conn->query("SELECT id, phone_number FROM patients WHERE phone_number IS NOT NULL");
    
    while ($patient = $result->fetch_assoc()) {
        $phone = $patient['phone_number'];
        
        // Test the phone validation from sendSMSUsingIPROGEnhanced
        $clean_phone = str_replace([' ', '-'], '', $phone);
        if (substr($clean_phone, 0, 1) === '0') {
            $clean_phone = '63' . substr($clean_phone, 1);
        } elseif (substr($clean_phone, 0, 1) === '+') {
            $clean_phone = substr($clean_phone, 1);
        }
        
        if (preg_match('/^63[0-9]{10}$/', $clean_phone)) {
            echo "✓ Patient {$patient['id']}: $phone (valid)\n";
        } else {
            echo "❌ Patient {$patient['id']}: $phone (invalid format)\n";
        }
    }
    
    // 5. Test SMS Sending (Test Mode)
    echo "<h3>5. SMS Sending Test (Dry Run)</h3>\n";
    
    // Get a patient with valid phone
    $result = $conn->query("SELECT * FROM patients WHERE phone_number REGEXP '^63[0-9]{10}$' LIMIT 1");
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        echo "Testing with patient: {$patient['first_name']} {$patient['last_name']} ({$patient['phone_number']})\n";
        
        // Test basic SMS sending function
        $test_message = "Test message from ImmuCare SMS System - " . date('Y-m-d H:i:s');
        $response = sendSMSUsingIPROGEnhanced($patient['phone_number'], $test_message, $sms_config['api_key']);
        
        if ($response['success']) {
            echo "✓ SMS sending test successful\n";
            echo "  Message: $test_message\n";
            echo "  Response: {$response['message']}\n";
        } else {
            echo "❌ SMS sending test failed: {$response['message']}\n";
        }
        
        // Test NotificationSystem class method
        echo "\nTesting NotificationSystem::sendPatientSMS method:\n";
        $notification_result = $notification->sendPatientSMS(
            $patient['id'], 
            $test_message,
            'test'
        );
        
        if ($notification_result) {
            echo "✓ NotificationSystem SMS method successful\n";
        } else {
            echo "❌ NotificationSystem SMS method failed\n";
        }
        
    } else {
        echo "❌ No patients with valid phone numbers found\n";
    }
    
    // 6. Test Appointment Notifications
    echo "<h3>6. Appointment Notification Test</h3>\n";
    
    $result = $conn->query("
        SELECT a.*, p.first_name, p.last_name, p.phone_number 
        FROM appointments a 
        JOIN patients p ON a.patient_id = p.id 
        WHERE p.phone_number REGEXP '^63[0-9]{10}$' 
        LIMIT 1
    ");
    
    if ($result->num_rows > 0) {
        $appointment = $result->fetch_assoc();
        echo "Testing appointment reminder for: {$appointment['first_name']} {$appointment['last_name']}\n";
        echo "Appointment: " . date('M j, Y g:i A', strtotime($appointment['appointment_date'])) . "\n";
        
        // Test appointment reminder
        echo "\nTesting appointment notification:\n";
        $appointment_result = $notification->sendAppointmentStatusNotification($appointment['id'], 'confirmed');
        
        if ($appointment_result) {
            echo "✓ Appointment notification sent successfully\n";
        } else {
            echo "❌ Appointment notification failed\n";
        }
    } else {
        echo "❌ No appointments with valid patient phone numbers found\n";
    }
    
    // 7. Check SMS Logs
    echo "<h3>7. SMS Logs Check</h3>\n";
    
    $result = $conn->query("SELECT COUNT(*) as count FROM sms_logs");
    $log_count = $result->fetch_assoc()['count'];
    echo "Total SMS logs: $log_count\n";
    
    if ($log_count > 0) {
        $result = $conn->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 5");
        echo "Recent SMS logs:\n";
        while ($log = $result->fetch_assoc()) {
            echo "  - {$log['created_at']}: {$log['message']} (Status: {$log['status']})\n";
        }
    }
    
    echo "<h3>✅ SMS System Test Complete</h3>\n";
    echo "<p>Check the results above to verify SMS functionality is working properly.</p>\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>