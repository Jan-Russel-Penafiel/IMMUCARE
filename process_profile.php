<?php
session_start();
require 'config.php';

// Check if user is logged in and has appropriate role
$allowed_user_types = ['patient', 'nurse', 'midwife', 'admin'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed_user_types)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];

// If staff is creating/updating profile for a patient
if ($_SESSION['user_type'] !== 'patient' && isset($_POST['patient_user_id'])) {
    $user_id = $_POST['patient_user_id'];
}

// Validate required fields
$required_fields = ['first_name', 'last_name', 'date_of_birth', 'gender', 'purok', 'city', 'province', 'phone_number'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit;
    }
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if patient profile already exists
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Patient profile already exists']);
    exit;
}

// Prepare the insert statement
$stmt = $conn->prepare("INSERT INTO patients (
    user_id, first_name, middle_name, last_name, date_of_birth, gender,
    blood_type, purok, city, province, postal_code, phone_number,
    medical_history, allergies, created_at
) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
)");

// Bind parameters
$stmt->bind_param("issssssssssssss",
    $user_id,
    $_POST['first_name'],
    $_POST['middle_name'],
    $_POST['last_name'],
    $_POST['date_of_birth'],
    $_POST['gender'],
    $_POST['blood_type'],
    $_POST['purok'],
    $_POST['city'],
    $_POST['province'],
    $_POST['postal_code'],
    $_POST['phone_number'],
    $_POST['medical_history'],
    $_POST['allergies']
);

// Execute the statement
if ($stmt->execute()) {
    // Create a notification for the new profile
    $notification_stmt = $conn->prepare("INSERT INTO notifications (
        user_id, title, message, type, created_at
    ) VALUES (?, ?, ?, 'system', NOW())");
    
    $title = "Profile Created Successfully";
    $message = "Welcome! Your patient profile has been created successfully. You can now schedule appointments and manage your immunization records.";
    
    $notification_stmt->bind_param("iss",
        $user_id,
        $title,
        $message
    );
    $notification_stmt->execute();
    
    echo json_encode(['success' => true, 'message' => 'Profile created successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating profile: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?> 