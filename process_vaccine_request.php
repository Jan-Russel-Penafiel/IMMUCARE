<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if required data is provided
if (!isset($_POST['vaccine_name']) || !isset($_POST['manufacturer'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required data']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();

    // Check if vaccine already exists
    $query = "SELECT id FROM vaccines WHERE name = ? AND manufacturer = ?";
    $stmt = $conn->prepare($query);
    $vaccine_name = $_POST['vaccine_name'];
    $manufacturer = $_POST['manufacturer'];
    $stmt->bind_param("ss", $vaccine_name, $manufacturer);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        throw new Exception('This vaccine already exists in the system');
    }

    // Insert new vaccine
    $query = "INSERT INTO vaccines (
                name,
                manufacturer,
                description,
                recommended_age,
                doses_required,
                days_between_doses,
                created_at
              ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
              
    $stmt = $conn->prepare($query);
    
    $description = isset($_POST['notes']) ? $_POST['notes'] : '';
    $recommended_age = isset($_POST['recommended_age']) ? $_POST['recommended_age'] : null;
    $doses_required = isset($_POST['doses_required']) ? (int)$_POST['doses_required'] : 1;
    $days_between_doses = isset($_POST['days_between_doses']) ? (int)$_POST['days_between_doses'] : null;

    $stmt->bind_param("ssssii", 
        $vaccine_name,
        $manufacturer,
        $description,
        $recommended_age,
        $doses_required,
        $days_between_doses
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add new vaccine');
    }

    // Commit transaction
    $conn->commit();

    // Close database connection
    $stmt->close();
    $conn->close();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Vaccine added successfully'
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
        $conn->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 