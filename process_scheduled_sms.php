<?php
/**
 * Scheduled SMS Processor for ImmuCare
 * 
 * This script should be run by a cron job every minute to process
 * scheduled SMS messages using the enhanced IPROG SMS functionality
 * 
 * Cron job example:
 * * * * * * php /path/to/mic_new/process_scheduled_sms.php
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die("This script can only be run from the command line.");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/notification_system.php';

// Set time limit for script execution
set_time_limit(300); // 5 minutes max

// Log start
$start_time = microtime(true);
$log_prefix = "[SMS-CRON] " . date('Y-m-d H:i:s') . " - ";

error_log($log_prefix . "Starting scheduled SMS processing...");

try {
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Database connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    // Get SMS configuration
    $stmt = $conn->prepare("
        SELECT setting_key, setting_value 
        FROM system_settings 
        WHERE setting_key IN ('sms_enabled', 'sms_api_key', 'sms_provider', 'max_sms_retries')
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparing settings query: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $config = array();
    while ($row = $result->fetch_assoc()) {
        $config[$row['setting_key']] = $row['setting_value'];
    }
    
    // Check if SMS is enabled
    if (!isset($config['sms_enabled']) || $config['sms_enabled'] !== 'true') {
        error_log($log_prefix . "SMS is disabled in system settings. Exiting.");
        exit(0);
    }
    
    // Check if API key is configured
    if (empty($config['sms_api_key'])) {
        error_log($log_prefix . "SMS API key not configured. Exiting.");
        exit(1);
    }
    
    $max_retries = isset($config['max_sms_retries']) ? intval($config['max_sms_retries']) : 3;
    
    // Get pending SMS messages (due now or overdue)
    $stmt = $conn->prepare("
        SELECT 
            id, 
            patient_id, 
            user_id, 
            phone_number, 
            message, 
            notification_type,
            related_to,
            related_id,
            scheduled_at,
            created_at,
            COALESCE(retry_count, 0) as retry_count
        FROM sms_logs 
        WHERE status = 'pending'
        AND (scheduled_at IS NULL OR scheduled_at <= NOW())
        AND (retry_count IS NULL OR retry_count < ?)
        ORDER BY 
            CASE WHEN scheduled_at IS NULL THEN created_at ELSE scheduled_at END ASC
        LIMIT 100
    ");
    
    if (!$stmt) {
        throw new Exception("Error preparing pending SMS query: " . $conn->error);
    }
    
    $stmt->bind_param("i", $max_retries);
    $stmt->execute();
    $pending_messages = $stmt->get_result();
    $stmt->close();
    
    if ($pending_messages->num_rows == 0) {
        error_log($log_prefix . "No pending SMS messages found.");
        $conn->close();
        exit(0);
    }
    
    error_log($log_prefix . "Found " . $pending_messages->num_rows . " pending SMS messages to process.");
    
    // Process each message
    $processed = 0;
    $sent = 0;
    $failed = 0;
    
    while ($sms = $pending_messages->fetch_assoc()) {
        $processed++;
        
        try {
            error_log($log_prefix . "Processing SMS ID: " . $sms['id'] . " to " . $sms['phone_number']);
            
            // Send SMS using enhanced IPROG implementation
            $sms_result = sendSMSUsingIPROGEnhanced($sms['phone_number'], $sms['message'], $config['sms_api_key']);
            
            // Update SMS log with result
            $new_status = $sms_result['success'] ? 'sent' : 'failed';
            $sent_at = date('Y-m-d H:i:s');
            $provider_response = $sms_result['message'];
            $reference_id = $sms_result['success'] ? ($sms_result['reference_id'] ?? null) : null;
            $retry_count = $sms['retry_count'] + 1;
            
            $update_stmt = $conn->prepare("
                UPDATE sms_logs 
                SET 
                    status = ?, 
                    provider_response = ?, 
                    reference_id = ?, 
                    sent_at = ?,
                    retry_count = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            
            if ($update_stmt) {
                $update_stmt->bind_param("ssssii", 
                    $new_status, 
                    $provider_response, 
                    $reference_id, 
                    $sent_at,
                    $retry_count,
                    $sms['id']
                );
                
                if ($update_stmt->execute()) {
                    if ($sms_result['success']) {
                        $sent++;
                        error_log($log_prefix . "SMS ID " . $sms['id'] . " sent successfully.");
                    } else {
                        $failed++;
                        error_log($log_prefix . "SMS ID " . $sms['id'] . " failed: " . $sms_result['message']);
                    }
                } else {
                    error_log($log_prefix . "Failed to update SMS log for ID " . $sms['id'] . ": " . $update_stmt->error);
                }
                
                $update_stmt->close();
            } else {
                error_log($log_prefix . "Failed to prepare update statement for SMS ID " . $sms['id'] . ": " . $conn->error);
            }
            
            // Small delay between messages to avoid rate limiting
            usleep(250000); // 0.25 seconds
            
        } catch (Exception $e) {
            error_log($log_prefix . "Error processing SMS ID " . $sms['id'] . ": " . $e->getMessage());
            
            // Mark as failed if max retries reached
            if ($sms['retry_count'] >= $max_retries - 1) {
                $fail_stmt = $conn->prepare("
                    UPDATE sms_logs 
                    SET status = 'failed', 
                        provider_response = ?, 
                        updated_at = NOW() 
                    WHERE id = ?
                ");
                
                if ($fail_stmt) {
                    $error_msg = "Max retries exceeded: " . $e->getMessage();
                    $fail_stmt->bind_param("si", $error_msg, $sms['id']);
                    $fail_stmt->execute();
                    $fail_stmt->close();
                    $failed++;
                }
            }
        }
    }
    
    // Clean up old processed messages (optional)
    $cleanup_days = 30; // Keep logs for 30 days
    $cleanup_stmt = $conn->prepare("
        DELETE FROM sms_logs 
        WHERE status IN ('sent', 'failed') 
        AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        LIMIT 1000
    ");
    
    if ($cleanup_stmt) {
        $cleanup_stmt->bind_param("i", $cleanup_days);
        $cleanup_stmt->execute();
        $deleted = $cleanup_stmt->affected_rows;
        $cleanup_stmt->close();
        
        if ($deleted > 0) {
            error_log($log_prefix . "Cleaned up $deleted old SMS log entries.");
        }
    }
    
    $conn->close();
    
    // Calculate execution time
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    
    // Final log
    error_log($log_prefix . "Processing completed. Processed: $processed, Sent: $sent, Failed: $failed, Time: {$execution_time}ms");
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    $execution_time = round((microtime(true) - $start_time) * 1000, 2);
    error_log($log_prefix . "FATAL ERROR: " . $e->getMessage() . " (Time: {$execution_time}ms)");
    
    // Exit with error code
    exit(1);
}
?>