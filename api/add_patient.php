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
$required_fields = [
    'email', 'password', 'confirm_password',
    'first_name', 'last_name', 'date_of_birth', 
    'gender', 'phone_number', 'purok', 'city', 'province'
];
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

// Validate email format
if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Validate password match
if ($_POST['password'] !== $_POST['confirm_password']) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $_POST['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Email address already exists');
    }
    $stmt->close();

    // Get current timestamp
    $current_time = date('Y-m-d H:i:s');

    // Create user account
    $stmt = $conn->prepare("
        INSERT INTO users (
            role_id,
            user_type,
            name,
            email,
            phone,
            password,
            is_active,
            created_at
        ) VALUES (
            4,
            'patient',
            ?,
            ?,
            ?,
            ?,
            1,
            ?
        )
    ");

    $hashed_password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $user_name = $_POST['first_name'] . ' ' . $_POST['last_name'];
    
    $stmt->bind_param(
        "sssss",
        $user_name,
        $_POST['email'],
        $_POST['phone_number'],
        $hashed_password,
        $current_time
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create user account');
    }
    
    $user_id = $stmt->insert_id;
    $stmt->close();

    // Create patient record
    $stmt = $conn->prepare("
        INSERT INTO patients (
            user_id,
            first_name,
            middle_name,
            last_name,
            date_of_birth,
            gender,
            purok,
            city,
            province,
            postal_code,
            phone_number,
            medical_history,
            allergies,
            created_at
        ) VALUES (
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?,
            ?
        )
    ");

    $stmt->bind_param(
        "issssssssssss",
        $user_id,
        $_POST['first_name'],
        $_POST['middle_name'] ?? null,
        $_POST['last_name'],
        $_POST['date_of_birth'],
        $_POST['gender'],
        $_POST['purok'],
        $_POST['city'],
        $_POST['province'],
        $_POST['postal_code'] ?? null,
        $_POST['phone_number'],
        $_POST['medical_history'] ?? null,
        $_POST['allergies'] ?? null,
        $current_time
    );

    if (!$stmt->execute()) {
        throw new Exception('Failed to create patient record');
    }

    $patient_id = $stmt->insert_id;
    
    // Commit transaction
    $conn->commit();
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => 'Patient added successfully',
        'patient_id' => $patient_id,
        'user_id' => $user_id
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// Close database connection
if (isset($stmt)) {
    $stmt->close();
}
$conn->close(); 