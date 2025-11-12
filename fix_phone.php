<?php
require_once 'config.php';

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    
    // Fix the invalid phone number
    $stmt = $conn->prepare("UPDATE patients SET phone_number = ? WHERE id = ?");
    $new_phone = "09677726912";
    $patient_id = 1;
    
    $stmt->bind_param("si", $new_phone, $patient_id);
    
    if ($stmt->execute()) {
        echo "✓ Updated phone number for patient 1 to: $new_phone\n";
    } else {
        echo "❌ Failed to update phone number: " . $stmt->error . "\n";
    }
    
    // Verify the update
    $result = $conn->query("SELECT id, phone_number FROM patients WHERE id = 1");
    $patient = $result->fetch_assoc();
    echo "Patient 1 phone number is now: " . $patient['phone_number'] . "\n";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>