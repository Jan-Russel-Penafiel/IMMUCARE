<?php
require_once 'config.php';

echo "<h1>iProg SMS Migration Script</h1>";
echo "<h2>Updating database settings...</h2>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p>✓ Connected to database successfully</p>";
    
    // Read and execute the SQL migration
    $sql = file_get_contents('migrate_to_iprog_sms.sql');
    
    if (!$sql) {
        die("<p style='color: red;'>✗ Could not read migration file</p>");
    }
    
    echo "<p>✓ Migration file loaded</p>";
    
    // Execute each statement
    if ($conn->multi_query($sql)) {
        echo "<p style='color: green;'>✓ Migration executed successfully!</p>";
        
        // Clear any remaining results
        while ($conn->next_result()) {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        }
        
        echo "<h3>Verifying settings...</h3>";
        
        // Check the updated settings
        $check_query = "SELECT setting_name, setting_value FROM settings WHERE setting_name LIKE 'iprog_%' OR setting_name = 'sms_provider'";
        $result = $conn->query($check_query);
        
        if ($result && $result->num_rows > 0) {
            echo "<table border='1' cellpadding='5' cellspacing='0'>";
            echo "<tr><th>Setting Name</th><th>Setting Value</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                $display_value = $row['setting_value'];
                // Mask API key for security
                if ($row['setting_name'] == 'iprog_sms_api_key') {
                    $display_value = substr($display_value, 0, 10) . '...';
                }
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['setting_name']) . "</td>";
                echo "<td>" . htmlspecialchars($display_value) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
            
            echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully!</p>";
            echo "<p>Your system is now configured to use iProg SMS API.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Migration failed: " . $conn->error . "</p>";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 20px auto;
    padding: 20px;
    line-height: 1.6;
}
h1 { color: #333; border-bottom: 2px solid #007bff; }
h2 { color: #007bff; }
table {
    width: 100%;
    border-collapse: collapse;
    margin: 20px 0;
}
th {
    background-color: #007bff;
    color: white;
    padding: 10px;
}
td {
    padding: 8px;
}
tr:nth-child(even) {
    background-color: #f8f9fa;
}
</style>