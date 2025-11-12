<?php
/**
 * Enhanced SMS Usage Example for ImmuCare
 * 
 * This file demonstrates how to use the enhanced SMS notification functionality
 * integrated from the smart system's sms_functions.php
 */

require_once 'config.php';
require_once 'notification_system.php';

try {
    // Initialize the notification system
    $notification = new NotificationSystem();
    
    echo "<h2>ImmuCare Enhanced SMS System Examples</h2>\n";
    
    // Example 1: Send a simple SMS to a patient
    echo "<h3>Example 1: Send SMS to Patient</h3>\n";
    $patient_id = 1; // Replace with actual patient ID
    $message = "IMMUCARE: This is a test message to verify SMS functionality.";
    
    $result1 = $notification->sendPatientSMS($patient_id, $message, 'test', null, 'system_test', 1);
    echo "Result: " . ($result1['success'] ? "SUCCESS" : "FAILED") . " - " . $result1['message'] . "\n\n";
    
    // Example 2: Schedule an SMS for later
    echo "<h3>Example 2: Schedule SMS for Future Delivery</h3>\n";
    $scheduled_time = date('Y-m-d H:i:s', strtotime('+1 hour')); // Schedule for 1 hour from now
    $scheduled_message = "IMMUCARE: This is a scheduled message that will be sent later.";
    
    $result2 = $notification->sendPatientSMS($patient_id, $scheduled_message, 'scheduled_test', $scheduled_time, 'system_test', 2);
    echo "Result: " . ($result2['success'] ? "SUCCESS" : "FAILED") . " - " . $result2['message'] . "\n\n";
    
    // Example 3: Send appointment reminder
    echo "<h3>Example 3: Send Appointment Reminder</h3>\n";
    $appointment_results = $notification->sendAppointmentReminders();
    echo "Appointment Reminders Sent:\n";
    echo "- Total: " . $appointment_results['total'] . "\n";
    echo "- Email Sent: " . $appointment_results['email_sent'] . "\n";
    echo "- SMS Sent: " . $appointment_results['sms_sent'] . "\n";
    echo "- Email Failed: " . $appointment_results['email_failed'] . "\n";
    echo "- SMS Failed: " . $appointment_results['sms_failed'] . "\n\n";
    
    // Example 4: Send immunization due notifications
    echo "<h3>Example 4: Send Immunization Due Notifications</h3>\n";
    $immunization_results = $notification->sendImmunizationDueNotifications();
    echo "Immunization Notifications Sent:\n";
    echo "- Total: " . $immunization_results['total'] . "\n";
    echo "- Email Sent: " . $immunization_results['email_sent'] . "\n";
    echo "- SMS Sent: " . $immunization_results['sms_sent'] . "\n";
    echo "- Email Failed: " . $immunization_results['email_failed'] . "\n";
    echo "- SMS Failed: " . $immunization_results['sms_failed'] . "\n\n";
    
    // Example 5: Process scheduled SMS messages
    echo "<h3>Example 5: Process Scheduled SMS Messages</h3>\n";
    $process_result = $notification->processScheduledMessages();
    echo "Process Result: " . ($process_result['success'] ? "SUCCESS" : "FAILED") . " - " . $process_result['message'] . "\n\n";
    
    // Example 6: Get SMS logs
    echo "<h3>Example 6: Recent SMS Logs</h3>\n";
    $logs = $notification->getSMSLogs(null, 5); // Get last 5 SMS logs
    echo "Recent SMS Logs:\n";
    foreach ($logs as $log) {
        echo "- ID: " . $log['id'] . " | ";
        echo "Patient: " . ($log['patient_name'] ?? 'Unknown') . " | ";
        echo "Phone: " . $log['phone_number'] . " | ";
        echo "Status: " . $log['status'] . " | ";
        echo "Type: " . $log['notification_type'] . " | ";
        echo "Sent: " . $log['sent_at'] . "\n";
    }
    echo "\n";
    
    // Example 7: Get SMS statistics
    echo "<h3>Example 7: SMS Statistics</h3>\n";
    $stats = $notification->getSMSStatistics();
    echo "SMS Statistics:\n";
    echo "- Total SMS: " . $stats['total'] . "\n";
    echo "- Sent: " . $stats['sent'] . "\n";
    echo "- Failed: " . $stats['failed'] . "\n";
    echo "- Pending: " . $stats['pending'] . "\n";
    echo "- Unique Patients: " . $stats['unique_patients'] . "\n";
    echo "By Type:\n";
    foreach ($stats['by_type'] as $type => $count) {
        echo "  - " . ucfirst($type) . ": " . $count . "\n";
    }
    echo "\n";
    
    // Example 8: Send custom notification
    echo "<h3>Example 8: Send Custom Notification</h3>\n";
    $custom_title = "System Maintenance Notice";
    $custom_message = "The ImmuCare system will be down for maintenance on Sunday from 2 AM to 4 AM. We apologize for any inconvenience.";
    
    $custom_result = $notification->sendCustomNotification($patient_id, $custom_title, $custom_message, 'both');
    echo "Custom Notification Result: " . ($custom_result ? "SUCCESS" : "FAILED") . "\n\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    error_log("SMS Usage Example Error: " . $e->getMessage());
}

// Direct function usage examples (without class)
echo "<h2>Direct Function Usage Examples</h2>\n";

try {
    // Example 9: Direct SMS sending using enhanced function
    echo "<h3>Example 9: Direct SMS using Enhanced Function</h3>\n";
    
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    
    $direct_result = sendSMSNotificationToPatientEnhanced(
        1, // patient_id
        "IMMUCARE: Direct SMS test message using enhanced function.", 
        $conn, 
        'direct_test', 
        null, // not scheduled
        null, // user_id will be auto-detected
        'system_test',
        99
    );
    
    echo "Direct SMS Result: " . ($direct_result['success'] ? "SUCCESS" : "FAILED") . " - " . $direct_result['message'] . "\n\n";
    
    // Example 10: Process scheduled SMS directly
    echo "<h3>Example 10: Process Scheduled SMS Directly</h3>\n";
    processScheduledSMSEnhanced($conn);
    echo "Scheduled SMS processing completed. Check logs for details.\n\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error in direct functions: " . $e->getMessage() . "\n";
}

echo "<h2>Configuration Notes</h2>\n";
echo "<p><strong>Important:</strong> Make sure to configure the following in your system:</p>\n";
echo "<ul>\n";
echo "<li>Set 'sms_enabled' to 'true' in system_settings table</li>\n";
echo "<li>Configure 'sms_api_key' with your IPROG SMS API key: 1ef3b27ea753780a90cbdf07d027fb7b52791004</li>\n";
echo "<li>Set 'sms_provider' to 'iprog' in system_settings table</li>\n";
echo "<li>Ensure sms_logs table exists with proper structure</li>\n";
echo "<li>Test with a small number of messages first</li>\n";
echo "</ul>\n";

echo "<h2>Database Setup</h2>\n";
echo "<p>Ensure your sms_logs table has the following structure:</p>\n";
echo "<pre>\n";
echo "CREATE TABLE IF NOT EXISTS sms_logs (\n";
echo "    id INT AUTO_INCREMENT PRIMARY KEY,\n";
echo "    patient_id INT,\n";
echo "    user_id INT,\n";
echo "    phone_number VARCHAR(20) NOT NULL,\n";
echo "    message TEXT NOT NULL,\n";
echo "    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',\n";
echo "    notification_type VARCHAR(50) DEFAULT 'general',\n";
echo "    provider_response TEXT,\n";
echo "    reference_id VARCHAR(100),\n";
echo "    related_to VARCHAR(50) DEFAULT 'general',\n";
echo "    related_id INT,\n";
echo "    scheduled_at DATETIME NULL,\n";
echo "    sent_at DATETIME NULL,\n";
echo "    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
echo "    INDEX idx_patient_id (patient_id),\n";
echo "    INDEX idx_status (status),\n";
echo "    INDEX idx_scheduled (scheduled_at),\n";
echo "    INDEX idx_created (created_at)\n";
echo ");\n";
echo "</pre>\n";

echo "<h2>System Settings</h2>\n";
echo "<p>Add these entries to your system_settings table:</p>\n";
echo "<pre>\n";
echo "INSERT INTO system_settings (setting_key, setting_value) VALUES\n";
echo "('sms_enabled', 'true'),\n";
echo "('sms_provider', 'iprog'),\n";
echo "('sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004'),\n";
echo "('appointment_reminder_days', '2'),\n";
echo "('email_enabled', 'true');\n";
echo "</pre>\n";
?>