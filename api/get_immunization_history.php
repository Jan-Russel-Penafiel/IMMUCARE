<?php
session_start();
require '../config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_GET['patient_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
    exit;
}

$patient_id = (int)$_GET['patient_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get immunization history
$query = "SELECT i.*, v.name as vaccine_name 
          FROM immunizations i 
          JOIN vaccines v ON i.vaccine_id = v.id 
          WHERE i.patient_id = ? 
          ORDER BY i.date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

$immunizations = [];
while ($row = $result->fetch_assoc()) {
    $immunizations[] = [
        'vaccine_name' => $row['vaccine_name'],
        'date' => date('M j, Y', strtotime($row['date'])),
        'status' => $row['status']
    ];
}

// Close database connection
$stmt->close();
$conn->close();

// Return immunization history
header('Content-Type: application/json');
echo json_encode(['success' => true, 'immunizations' => $immunizations]); 