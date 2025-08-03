<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid immunization ID']);
    exit;
}

$immunization_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get immunization data for editing
$stmt = $conn->prepare("SELECT * FROM immunizations WHERE id = ? AND administered_by = ?");
$stmt->bind_param("ii", $immunization_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Immunization record not found']);
    exit;
}

$immunization = $result->fetch_assoc();
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'immunization' => $immunization
]);
?>
