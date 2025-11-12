<?php
/**
 * Check database structure for ImmuCare SMS system
 */

require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    echo "<h2>Database Structure Check</h2>\n";
    
    // Check patients table structure
    echo "<h3>1. Patients Table Structure</h3>\n";
    $result = $conn->query("DESCRIBE patients");
    if ($result) {
        echo "Patients table columns:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ Patients table does not exist or error: " . $conn->error . "\n";
    }
    
    // Check appointments table structure
    echo "<h3>2. Appointments Table Structure</h3>\n";
    $result = $conn->query("DESCRIBE appointments");
    if ($result) {
        echo "Appointments table columns:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ Appointments table does not exist or error: " . $conn->error . "\n";
    }
    
    // Check users table structure
    echo "<h3>3. Users Table Structure</h3>\n";
    $result = $conn->query("DESCRIBE users");
    if ($result) {
        echo "Users table columns:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ Users table does not exist or error: " . $conn->error . "\n";
    }
    
    // Check sms_logs table structure
    echo "<h3>4. SMS Logs Table Structure</h3>\n";
    $result = $conn->query("DESCRIBE sms_logs");
    if ($result) {
        echo "SMS logs table columns:\n";
        while ($row = $result->fetch_assoc()) {
            echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "❌ SMS logs table does not exist or error: " . $conn->error . "\n";
    }
    
    // Check for sample data
    echo "<h3>5. Sample Data Check</h3>\n";
    
    // Check patients
    $result = $conn->query("SELECT COUNT(*) as count FROM patients");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "Patients count: $count\n";
        
        if ($count > 0) {
            $result = $conn->query("SELECT id, first_name, last_name, phone_number FROM patients LIMIT 3");
            echo "Sample patients:\n";
            while ($row = $result->fetch_assoc()) {
                echo "- ID: {$row['id']}, Name: {$row['first_name']} {$row['last_name']}, Phone: " . ($row['phone_number'] ?? 'NULL') . "\n";
            }
        }
    }
    
    // Check appointments
    $result = $conn->query("SELECT COUNT(*) as count FROM appointments");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "Appointments count: $count\n";
    }
    
    // Check users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "Users count: $count\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>