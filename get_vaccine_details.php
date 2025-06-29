<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if vaccine ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid vaccine ID']);
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
    // Get vaccine details
    $query = "SELECT 
                v.*,
                (SELECT COUNT(*) FROM immunizations WHERE vaccine_id = v.id) as times_administered,
                (SELECT COUNT(DISTINCT patient_id) FROM immunizations WHERE vaccine_id = v.id) as patients_vaccinated
              FROM vaccines v 
              WHERE v.id = ?";
              
    $stmt = $conn->prepare($query);
    $vaccine_id = (int)$_GET['id'];
    $stmt->bind_param("i", $vaccine_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('Vaccine not found');
    }

    $vaccine = $result->fetch_assoc();
    
    // Get recent immunizations
    $query = "SELECT 
                i.administered_date,
                i.dose_number,
                i.batch_number,
                CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                u.name as administered_by
              FROM immunizations i
              LEFT JOIN patients p ON i.patient_id = p.id
              LEFT JOIN users u ON i.administered_by = u.id
              WHERE i.vaccine_id = ?
              ORDER BY i.administered_date DESC
              LIMIT 5";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $vaccine_id);
    $stmt->execute();
    $recent_immunizations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $vaccine['recent_immunizations'] = $recent_immunizations;

    // Close database connection
    $stmt->close();
    $conn->close();

    // Return vaccine details as JSON
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'vaccine' => $vaccine
    ]);

} catch (Exception $e) {
    if (isset($conn)) {
        $conn->close();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} 