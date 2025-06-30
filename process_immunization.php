<?php
session_start();
require 'config.php';

// Check if user is logged in and has appropriate role
$allowed_user_types = ['nurse', 'midwife', 'admin'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed_user_types)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate required fields
$required_fields = ['patient_id', 'vaccine_id', 'administered_date', 'dose_number', 'batch_number'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
        exit;
    }
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

    // Insert immunization record
    $stmt = $conn->prepare("INSERT INTO immunizations (patient_id, vaccine_id, administered_by, administered_date, dose_number, batch_number, next_dose_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $patient_id = (int)$_POST['patient_id'];
    $vaccine_id = (int)$_POST['vaccine_id'];
    $administered_by = $_SESSION['user_id'];
    $administered_date = $_POST['administered_date'];
    $dose_number = (int)$_POST['dose_number'];
    $batch_number = $_POST['batch_number'];
    $next_dose_date = !empty($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
    $notes = !empty($_POST['notes']) ? $_POST['notes'] : null;

    $stmt->bind_param("iiisisss", $patient_id, $vaccine_id, $administered_by, $administered_date, $dose_number, $batch_number, $next_dose_date, $notes);
    $stmt->execute();

    // Update vaccine inventory (if you have an inventory system)
    // Add your inventory update logic here

    // Commit transaction
    $conn->commit();

    // Close statement and connection
    $stmt->close();
    $conn->close();

    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Immunization record saved successfully']);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Close connection
    $conn->close();

    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error saving immunization record: ' . $e->getMessage()]);
} 