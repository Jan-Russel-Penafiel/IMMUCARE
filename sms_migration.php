<?php
/**
 * Database Migration for Enhanced SMS System
 * 
 * This script creates/updates the necessary database tables and settings
 * for the enhanced SMS notification system in ImmuCare
 */

require_once 'config.php';

try {
    // Create database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    echo "<h2>ImmuCare Enhanced SMS System - Database Migration</h2>\n";
    
    // 1. Create/Update sms_logs table
    echo "<h3>1. Creating/Updating sms_logs table...</h3>\n";
    
    $sms_logs_sql = "
    CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NULL,
        user_id INT NULL,
        phone_number VARCHAR(20) NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
        retry_count INT DEFAULT 0,
        notification_type VARCHAR(50) DEFAULT 'general',
        provider_response TEXT,
        reference_id VARCHAR(100) NULL,
        related_to VARCHAR(50) DEFAULT 'general',
        related_id INT NULL,
        scheduled_at DATETIME NULL,
        sent_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_patient_id (patient_id),
        INDEX idx_user_id (user_id),
        INDEX idx_status (status),
        INDEX idx_notification_type (notification_type),
        INDEX idx_scheduled (scheduled_at),
        INDEX idx_sent (sent_at),
        INDEX idx_created (created_at),
        INDEX idx_related (related_to, related_id),
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($sms_logs_sql)) {
        echo "✓ sms_logs table created/updated successfully\n";
    } else {
        throw new Exception("Error creating sms_logs table: " . $conn->error);
    }
    
    // Check if we need to add new columns to existing sms_logs table
    echo "<h3>2. Checking for missing columns in sms_logs table...</h3>\n";
    
    $columns_to_add = [
        'notification_type' => "ADD COLUMN notification_type VARCHAR(50) DEFAULT 'general' AFTER status",
        'related_to' => "ADD COLUMN related_to VARCHAR(50) DEFAULT 'general' AFTER reference_id", 
        'related_id' => "ADD COLUMN related_id INT NULL AFTER related_to",
        'scheduled_at' => "ADD COLUMN scheduled_at DATETIME NULL AFTER related_id",
        'updated_at' => "ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        'retry_count' => "ADD COLUMN retry_count INT DEFAULT 0 AFTER status"
    ];
    
    // Check existing columns
    $result = $conn->query("DESCRIBE sms_logs");
    $existing_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    foreach ($columns_to_add as $column => $sql) {
        if (!in_array($column, $existing_columns)) {
            $alter_sql = "ALTER TABLE sms_logs " . $sql;
            if ($conn->query($alter_sql)) {
                echo "✓ Added column: $column\n";
            } else {
                echo "⚠ Warning: Could not add column $column: " . $conn->error . "\n";
            }
        } else {
            echo "- Column $column already exists\n";
        }
    }
    
    // 3. Create/Update system_settings table
    echo "<h3>3. Creating/Updating system_settings table...</h3>\n";
    
    $system_settings_sql = "
    CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT,
        description TEXT,
        setting_type ENUM('text', 'boolean', 'number', 'json') DEFAULT 'text',
        is_public BOOLEAN DEFAULT FALSE,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_key (setting_key),
        INDEX idx_public (is_public)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($system_settings_sql)) {
        echo "✓ system_settings table created/updated successfully\n";
    } else {
        echo "⚠ Warning: Could not create system_settings table: " . $conn->error . "\n";
    }
    
    // 4. Check and add missing columns to system_settings table
    echo "<h3>4. Checking system_settings table structure...</h3>\n";
    
    // Check existing columns in system_settings
    $result = $conn->query("DESCRIBE system_settings");
    $existing_settings_columns = [];
    while ($row = $result->fetch_assoc()) {
        $existing_settings_columns[] = $row['Field'];
    }
    
    // Add missing columns to system_settings if they don't exist
    $settings_columns_to_add = [
        'description' => "ADD COLUMN description TEXT AFTER setting_value",
        'setting_type' => "ADD COLUMN setting_type ENUM('text', 'boolean', 'number', 'json') DEFAULT 'text' AFTER description",
        'is_public' => "ADD COLUMN is_public BOOLEAN DEFAULT FALSE AFTER setting_type"
    ];
    
    foreach ($settings_columns_to_add as $column => $sql) {
        if (!in_array($column, $existing_settings_columns)) {
            $alter_sql = "ALTER TABLE system_settings " . $sql;
            if ($conn->query($alter_sql)) {
                echo "✓ Added column to system_settings: $column\n";
            } else {
                echo "⚠ Warning: Could not add column $column to system_settings: " . $conn->error . "\n";
            }
        } else {
            echo "- Column $column already exists in system_settings\n";
        }
    }
    
    // 5. Insert default SMS settings
    echo "<h3>5. Adding default SMS settings...</h3>\n";
    
    $default_settings = [
        ['sms_enabled', 'true', 'Enable/disable SMS notifications', 'boolean', 0],
        ['sms_provider', 'iprog', 'SMS service provider (iprog)', 'text', 0],
        ['sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'IPROG SMS API key', 'text', 0],
        ['email_enabled', 'true', 'Enable/disable email notifications', 'boolean', 0],
        ['appointment_reminder_days', '2', 'Days before appointment to send reminder', 'number', 1],
        ['immunization_reminder_days', '7', 'Days before due date to send immunization reminder', 'number', 1],
        ['max_sms_retries', '3', 'Maximum number of SMS retry attempts', 'number', 0],
        ['sms_rate_limit', '100', 'Maximum SMS messages per hour', 'number', 0]
    ];
    
    // Check if we have the extended columns, otherwise use basic insert
    $has_extended_columns = in_array('description', $existing_settings_columns) && 
                           in_array('setting_type', $existing_settings_columns) && 
                           in_array('is_public', $existing_settings_columns);
    
    if ($has_extended_columns) {
        $insert_setting_sql = "
        INSERT INTO system_settings (setting_key, setting_value, description, setting_type, is_public) 
        VALUES (?, ?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            description = VALUES(description),
            setting_type = VALUES(setting_type),
            is_public = VALUES(is_public),
            updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $conn->prepare($insert_setting_sql);
        
        foreach ($default_settings as $setting) {
            $stmt->bind_param("ssssi", $setting[0], $setting[1], $setting[2], $setting[3], $setting[4]);
            if ($stmt->execute()) {
                echo "✓ Setting added/updated: " . $setting[0] . "\n";
            } else {
                echo "⚠ Warning: Could not add setting " . $setting[0] . ": " . $stmt->error . "\n";
            }
        }
        
        $stmt->close();
    } else {
        // Basic insert for minimal table structure
        $basic_insert_sql = "
        INSERT INTO system_settings (setting_key, setting_value) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value)
        ";
        
        $stmt = $conn->prepare($basic_insert_sql);
        
        foreach ($default_settings as $setting) {
            $stmt->bind_param("ss", $setting[0], $setting[1]);
            if ($stmt->execute()) {
                echo "✓ Setting added/updated (basic): " . $setting[0] . "\n";
            } else {
                echo "⚠ Warning: Could not add setting " . $setting[0] . ": " . $stmt->error . "\n";
            }
        }
        
        $stmt->close();
    }
    
    // 6. Create indexes for better performance
    echo "<h3>6. Adding performance indexes...</h3>\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_sms_logs_patient_status ON sms_logs (patient_id, status)",
        "CREATE INDEX IF NOT EXISTS idx_sms_logs_type_created ON sms_logs (notification_type, created_at)",
        "CREATE INDEX IF NOT EXISTS idx_sms_logs_scheduled_status ON sms_logs (scheduled_at, status)"
    ];
    
    foreach ($indexes as $index_sql) {
        if ($conn->query($index_sql)) {
            echo "✓ Index created successfully\n";
        } else {
            // Ignore errors for existing indexes
            if (strpos($conn->error, 'Duplicate key name') === false) {
                echo "⚠ Warning: Could not create index: " . $conn->error . "\n";
            }
        }
    }
    
    // 7. Create notifications table if it doesn't exist
    echo "<h3>7. Creating/Updating notifications table...</h3>\n";
    
    $notifications_sql = "
    CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('system', 'appointment', 'immunization', 'reminder', 'alert') DEFAULT 'system',
        is_read BOOLEAN DEFAULT FALSE,
        sent_at DATETIME NULL,
        read_at DATETIME NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_type (type),
        INDEX idx_created (created_at),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($notifications_sql)) {
        echo "✓ notifications table created/updated successfully\n";
    } else {
        echo "⚠ Warning: Could not create notifications table: " . $conn->error . "\n";
    }
    
    // 7. Test the configuration
    echo "<h3>8. Testing SMS configuration...</h3>\n";
    
    // Check if we can retrieve SMS settings
    $test_sql = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_enabled', 'sms_provider', 'sms_api_key')";
    $result = $conn->query($test_sql);
    
    if ($result && $result->num_rows > 0) {
        echo "✓ SMS settings are accessible:\n";
        while ($row = $result->fetch_assoc()) {
            $display_value = ($row['setting_key'] == 'sms_api_key') ? 
                substr($row['setting_value'], 0, 10) . '...' : 
                $row['setting_value'];
            echo "  - " . $row['setting_key'] . ": " . $display_value . "\n";
        }
    } else {
        echo "⚠ Warning: Could not retrieve SMS settings\n";
    }
    
    // 8. Display next steps
    echo "<h3>9. Migration Complete!</h3>\n";
    echo "<p><strong>Next Steps:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Update the 'sms_api_key' setting with your actual IPROG SMS API key</li>\n";
    echo "<li>Test SMS functionality using sms_usage_example.php</li>\n";
    echo "<li>Set up a cron job to run processScheduledSMS() every minute</li>\n";
    echo "<li>Monitor sms_logs table for message delivery status</li>\n";
    echo "<li>Configure notification preferences for different user types</li>\n";
    echo "</ul>\n";
    
    echo "<h3>Cron Job Example:</h3>\n";
    echo "<pre>";
    echo "# Add this to your crontab to process scheduled SMS every minute:\n";
    echo "* * * * * php " . __DIR__ . "/process_scheduled_sms.php\n";
    echo "</pre>\n";
    
    $conn->close();
    echo "<p><strong>✅ Database migration completed successfully!</strong></p>\n";
    
} catch (Exception $e) {
    echo "<p><strong>❌ Migration failed:</strong> " . $e->getMessage() . "</p>\n";
    error_log("SMS Migration Error: " . $e->getMessage());
}
?>