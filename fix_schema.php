<?php
/**
 * Fix database schema issues for SMS system
 */

require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Database Schema Fix</h2>\n";
    
    // 1. Add user_id column to sms_logs if it doesn't exist
    echo "<h3>1. Fixing sms_logs table...</h3>\n";
    
    $result = $conn->query("SHOW COLUMNS FROM sms_logs LIKE 'user_id'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE sms_logs ADD COLUMN user_id INT(11) AFTER patient_id";
        if ($conn->query($sql)) {
            echo "✓ Added user_id column to sms_logs\n";
        } else {
            echo "❌ Failed to add user_id column: " . $conn->error . "\n";
        }
    } else {
        echo "- user_id column already exists in sms_logs\n";
    }
    
    // 2. Add location column to appointments if it doesn't exist
    echo "<h3>2. Fixing appointments table...</h3>\n";
    
    $result = $conn->query("SHOW COLUMNS FROM appointments LIKE 'location'");
    if ($result->num_rows == 0) {
        $sql = "ALTER TABLE appointments ADD COLUMN location VARCHAR(255) DEFAULT 'Main Clinic' AFTER purpose";
        if ($conn->query($sql)) {
            echo "✓ Added location column to appointments\n";
        } else {
            echo "❌ Failed to add location column: " . $conn->error . "\n";
        }
    } else {
        echo "- location column already exists in appointments\n";
    }
    
    // 3. Fix phone number formats
    echo "<h3>3. Fixing phone number formats...</h3>\n";
    
    // Update patients with valid Philippine phone numbers
    $patients_result = $conn->query("SELECT id, phone_number FROM patients WHERE phone_number IS NOT NULL");
    $fixed_phones = 0;
    
    while ($patient = $patients_result->fetch_assoc()) {
        $phone = $patient['phone_number'];
        $original_phone = $phone;
        
        // Clean and format phone number
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Convert to Philippine format
        if (strlen($phone) == 11 && substr($phone, 0, 2) == '09') {
            // 09XXXXXXXXX format
            $phone = '63' . substr($phone, 1);
        } elseif (strlen($phone) == 10 && substr($phone, 0, 1) == '9') {
            // 9XXXXXXXXX format
            $phone = '63' . $phone;
        } elseif (substr($phone, 0, 1) == '+') {
            $phone = substr($phone, 1);
        }
        
        // Validate Philippine mobile number format
        if (preg_match('/^63[0-9]{10}$/', $phone)) {
            if ($phone != $original_phone) {
                $stmt = $conn->prepare("UPDATE patients SET phone_number = ? WHERE id = ?");
                $stmt->bind_param("si", $phone, $patient['id']);
                if ($stmt->execute()) {
                    echo "✓ Fixed phone number for patient {$patient['id']}: $original_phone -> $phone\n";
                    $fixed_phones++;
                }
            }
        } else {
            echo "⚠ Invalid phone format for patient {$patient['id']}: $original_phone\n";
        }
    }
    
    if ($fixed_phones == 0) {
        echo "- No phone numbers needed fixing\n";
    }
    
    // 4. Insert some sample data if tables are empty
    echo "<h3>4. Adding sample data if needed...</h3>\n";
    
    // Check if we need sample appointments
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    $appointment_count = $result->fetch_assoc()['count'];
    
    if ($appointment_count == 0) {
        // Add sample appointment
        $stmt = $conn->prepare("
            INSERT INTO appointments (patient_id, staff_id, appointment_date, vaccine_id, purpose, location, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())
        ");
        
        $patient_id = 1;
        $staff_id = 1;
        $appointment_date = date('Y-m-d H:i:s', strtotime('+2 days 14:00:00'));
        $vaccine_id = 1;
        $purpose = 'COVID-19 Vaccination';
        $location = 'Main Clinic';
        
        $stmt->bind_param("iisisss", $patient_id, $staff_id, $appointment_date, $vaccine_id, $purpose, $location);
        
        if ($stmt->execute()) {
            echo "✓ Added sample appointment\n";
        } else {
            echo "⚠ Could not add sample appointment: " . $stmt->error . "\n";
        }
    }
    
    echo "<h3>5. Verification</h3>\n";
    
    // Verify fixes
    $result = $conn->query("SHOW COLUMNS FROM sms_logs LIKE 'user_id'");
    if ($result->num_rows > 0) {
        echo "✓ sms_logs.user_id column exists\n";
    }
    
    $result = $conn->query("SHOW COLUMNS FROM appointments LIKE 'location'");
    if ($result->num_rows > 0) {
        echo "✓ appointments.location column exists\n";
    }
    
    $result = $conn->query("SELECT COUNT(*) as count FROM patients WHERE phone_number REGEXP '^63[0-9]{10}$'");
    $valid_phones = $result->fetch_assoc()['count'];
    echo "✓ Valid Philippine phone numbers: $valid_phones\n";
    
    echo "<p><strong>✅ Schema fixes completed!</strong></p>\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>