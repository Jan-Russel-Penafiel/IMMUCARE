<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get all active vaccines
$query = "SELECT id, name, manufacturer, description, recommended_age, doses_required 
          FROM vaccines 
          WHERE is_active = 1 
          ORDER BY name ASC";

$result = $conn->query($query);

$vaccines = [];
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $vaccines[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'manufacturer' => $row['manufacturer'],
            'description' => $row['description'],
            'recommended_age' => $row['recommended_age'],
            'doses_required' => $row['doses_required']
        ];
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'vaccines' => $vaccines
]);
?>
