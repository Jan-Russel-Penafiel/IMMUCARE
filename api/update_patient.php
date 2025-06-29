<?php
session_start();
require '../config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate required fields
$required_fields = ['patient_id', 'first_name', 'last_name', 'date_of_birth', 'gender', 'phone_number', 'purok', 'city', 'province'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Missing required fields: ' . implode(', ', $missing_fields)]);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Prepare and execute update statement
$stmt = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, phone_number = ?, purok = ?, city = ?, province = ? WHERE id = ?");
$stmt->bind_param("ssssssssi", 
    $_POST['first_name'],
    $_POST['last_name'],
    $_POST['date_of_birth'],
    $_POST['gender'],
    $_POST['phone_number'],
    $_POST['purok'],
    $_POST['city'],
    $_POST['province'],
    $_POST['patient_id']
);

if ($stmt->execute()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Patient updated successfully']);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Failed to update patient']);
}

// Close database connection
$stmt->close();
$conn->close(); 