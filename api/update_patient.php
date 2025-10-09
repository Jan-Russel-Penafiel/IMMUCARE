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

// Prepare variables
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$date_of_birth = $_POST['date_of_birth'];
$gender = $_POST['gender'];
$blood_type = $_POST['blood_type'] ?? null;
$phone_number = $_POST['phone_number'];
$purok = $_POST['purok'];
$city = $_POST['city'];
$province = $_POST['province'];
$medical_history = $_POST['medical_history'] ?? null;
$allergies = $_POST['allergies'] ?? null;
$diagnosis = $_POST['diagnosis'] ?? null;
$patient_id = $_POST['patient_id'];

// Prepare and execute update statement
$stmt = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, blood_type = ?, phone_number = ?, purok = ?, city = ?, province = ?, medical_history = ?, allergies = ?, diagnosis = ? WHERE id = ?");
$stmt->bind_param("ssssssssssssi", 
    $first_name,
    $last_name,
    $date_of_birth,
    $gender,
    $blood_type,
    $phone_number,
    $purok,
    $city,
    $province,
    $medical_history,
    $allergies,
    $diagnosis,
    $patient_id
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