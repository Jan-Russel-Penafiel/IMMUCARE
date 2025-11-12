<?php
require_once 'config.php';
require_once 'notification_system.php';

echo "<h1>Appointment Status Update SMS Test</h1>";

// Simulate the same flow as admin_appointments.php
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $notification_system = new NotificationSystem();
    
    // Get a real appointment from the database
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name, 
               p.last_name, 
               p.phone_number as patient_phone,
               u.email,
               u.phone as user_phone,
               u.id as user_id,
               v.name as vaccine_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN vaccines v ON a.vaccine_id = v.id
        WHERE a.id = ?
    ");
    
    // Use the first appointment ID from our check
    $appointment_id = 3; // From the appointments check earlier
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment_result = $stmt->get_result();
    $appointment_data = $appointment_result->fetch_assoc();
    
    if ($appointment_data) {
        echo "<h2>Appointment Data Found:</h2>";
        echo "<ul>";
        echo "<li>Patient: " . $appointment_data['first_name'] . ' ' . $appointment_data['last_name'] . "</li>";
        echo "<li>User ID: " . $appointment_data['user_id'] . "</li>";
        echo "<li>Phone: " . ($appointment_data['patient_phone'] ?? 'No phone') . "</li>";
        echo "<li>Email: " . $appointment_data['email'] . "</li>";
        echo "<li>Status: " . $appointment_data['status'] . "</li>";
        echo "</ul>";
        
        // Simulate status update notification (same as admin_appointments.php)
        $patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
        $appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
        $purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
        
        $status = 'confirmed'; // Test with confirmed status
        $notes = 'Test notification from admin system';
        
        // Get status-specific message
        $status_specific_message = "Your appointment has been confirmed. Please arrive 15 minutes early.";
        
        $status_message = "Your appointment status has been updated.\n\n" .
                         "Appointment Details:\n" .
                         "- Purpose: " . $purpose . "\n" .
                         "- Date: " . $appointment_date . "\n" .
                         "- Time: " . $appointment_time . "\n" .
                         "- New Status: " . ucfirst($status) . "\n\n" .
                         $status_specific_message . "\n" .
                         (!empty($notes) ? "\nAdditional Notes: " . $notes . "\n" : "") .
                         "\nIf you have any questions or need to make changes, please contact us.";
        
        echo "<h2>Testing Status Update Notification...</h2>";
        echo "<p>Message to be sent:</p>";
        echo "<pre>" . htmlspecialchars($status_message) . "</pre>";
        
        $result = $notification_system->sendCustomNotification(
            $appointment_data['user_id'],
            "Appointment Status Update: " . ucfirst($status),
            $status_message,
            'both' // Send both SMS and email
        );
        
        if ($result) {
            echo "<p style='color: green;'>✅ Appointment status update notification sent successfully!</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to send appointment status update notification!</p>";
        }
        
    } else {
        echo "<p style='color: red;'>No appointment found with ID: $appointment_id</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

echo "<h2>Recent SMS Logs (Last 3):</h2>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $result = $conn->query("SELECT * FROM sms_logs ORDER BY created_at DESC LIMIT 3");
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>User ID</th><th>Phone</th><th>Status</th><th>Message Preview</th><th>Created At</th></tr>";
        
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['user_id'] . "</td>";
            echo "<td>" . $row['phone_number'] . "</td>";
            echo "<td>" . $row['status'] . "</td>";
            echo "<td>" . htmlspecialchars(substr($row['message'], 0, 80)) . "...</td>";
            echo "<td>" . $row['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No SMS logs found.</p>";
    }
    
    $conn->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; padding: 20px; max-width: 1000px; margin: 0 auto; }
table { border-collapse: collapse; width: 100%; margin: 20px 0; }
th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
th { background-color: #f2f2f2; }
pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
ul { background: #f8f9fa; padding: 15px; border-radius: 5px; }
</style>