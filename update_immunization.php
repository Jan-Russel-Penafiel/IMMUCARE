<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$immunization_id = isset($_POST['immunization_id']) ? (int)$_POST['immunization_id'] : 0;
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$vaccine_id = isset($_POST['vaccine_id']) ? (int)$_POST['vaccine_id'] : 0;
$administered_date = isset($_POST['administered_date']) ? $_POST['administered_date'] : '';
$dose_number = isset($_POST['dose_number']) ? (int)$_POST['dose_number'] : 1;
$batch_number = isset($_POST['batch_number']) ? trim($_POST['batch_number']) : '';
$expiration_date = isset($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
$next_dose_date = isset($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
$location = isset($_POST['location']) ? trim($_POST['location']) : null;
$diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : null;
$user_id = $_SESSION['user_id'];

// Validation
if ($immunization_id <= 0 || $patient_id <= 0 || $vaccine_id <= 0 || empty($administered_date) || empty($batch_number)) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Verify the immunization belongs to this nurse
$stmt = $conn->prepare("SELECT id FROM immunizations WHERE id = ? AND administered_by = ?");
$stmt->bind_param("ii", $immunization_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Immunization record not found or access denied']);
    exit;
}

// Update immunization record
$stmt = $conn->prepare("UPDATE immunizations 
                        SET patient_id = ?, vaccine_id = ?, dose_number = ?, batch_number = ?, 
                            expiration_date = ?, administered_date = ?, next_dose_date = ?, 
                            location = ?, diagnosis = ?, updated_at = NOW()
                        WHERE id = ? AND administered_by = ?");

$stmt->bind_param("iiissssssii", $patient_id, $vaccine_id, $dose_number, $batch_number, 
                  $expiration_date, $administered_date, $next_dose_date, $location, 
                  $diagnosis, $immunization_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Immunization record updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error updating immunization record: ' . $conn->error]);
}

$conn->close();
?>
