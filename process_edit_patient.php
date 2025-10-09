<?php
session_start();
require 'config.php';

// Check if user is logged in and has appropriate role (midwife or nurse)
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: login.php');
    exit;
}

$user_type = $_SESSION['user_type'];
if ($user_type !== 'midwife' && $user_type !== 'nurse') {
    $_SESSION['error'] = 'Unauthorized access';
    header('Location: login.php');
    exit;
}

// Check if request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: ' . ($user_type === 'midwife' ? 'midwife_patients.php' : 'nurse_patients.php'));
    exit;
}

// Validate required fields
$required_fields = [
    'patient_id', 'first_name', 'last_name', 
    'date_of_birth', 'gender', 'phone_number', 
    'purok', 'city', 'province'
];

$missing_fields = [];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    $_SESSION['error'] = 'Missing required fields: ' . implode(', ', $missing_fields);
    header('Location: ' . ($user_type === 'midwife' ? 'midwife_patients.php' : 'nurse_patients.php'));
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $_SESSION['error'] = 'Database connection failed';
    header('Location: ' . ($user_type === 'midwife' ? 'midwife_patients.php' : 'nurse_patients.php'));
    exit;
}

try {
    // Verify patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $patient_id = (int)$_POST['patient_id'];
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Patient not found');
    }
    $stmt->close();

    // Prepare variables
    $first_name = trim($_POST['first_name']);
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : null;
    $last_name = trim($_POST['last_name']);
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $blood_type = isset($_POST['blood_type']) && !empty($_POST['blood_type']) ? $_POST['blood_type'] : null;
    $phone_number = trim($_POST['phone_number']);
    $purok = trim($_POST['purok']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : null;
    $medical_history = isset($_POST['medical_history']) ? trim($_POST['medical_history']) : null;
    $allergies = isset($_POST['allergies']) ? trim($_POST['allergies']) : null;
    $diagnosis = isset($_POST['diagnosis']) ? trim($_POST['diagnosis']) : null;

    // Update patient record
    $stmt = $conn->prepare("
        UPDATE patients 
        SET first_name = ?, 
            middle_name = ?,
            last_name = ?, 
            date_of_birth = ?, 
            gender = ?, 
            blood_type = ?,
            phone_number = ?, 
            purok = ?, 
            city = ?, 
            province = ?,
            postal_code = ?,
            medical_history = ?,
            allergies = ?,
            diagnosis = ?
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "ssssssssssssssi",
        $first_name,
        $middle_name,
        $last_name,
        $date_of_birth,
        $gender,
        $blood_type,
        $phone_number,
        $purok,
        $city,
        $province,
        $postal_code,
        $medical_history,
        $allergies,
        $diagnosis,
        $patient_id
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to update patient record');
    }

    $stmt->close();
    
    // Success message
    $_SESSION['success'] = 'Patient information updated successfully';
    
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
}

// Close database connection
$conn->close();

// Redirect back to patients page
header('Location: ' . ($user_type === 'midwife' ? 'midwife_patients.php' : 'nurse_patients.php'));
exit;
