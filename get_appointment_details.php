<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if appointment ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
    exit;
}

$appointment_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get appointment details with patient and vaccine information
$query = "SELECT a.*, 
                 CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                 p.phone_number as patient_phone,
                 CONCAT(p.purok, ', ', p.city, ', ', p.province) as patient_address,
                 p.date_of_birth,
                 p.gender,
                 p.medical_history,
                 p.allergies,
                 v.name as vaccine_name,
                 v.manufacturer,
                 v.description as vaccine_description,
                 v.recommended_age,
                 v.doses_required,
                 CONCAT(u.name) as staff_name
          FROM appointments a 
          JOIN patients p ON a.patient_id = p.id 
          LEFT JOIN vaccines v ON a.vaccine_id = v.id 
          LEFT JOIN users u ON a.staff_id = u.id
          WHERE a.id = ? AND (a.staff_id = ? OR a.staff_id IS NULL)";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
    exit;
}

$appointment = $result->fetch_assoc();

// Format the response
$response = [
    'success' => true,
    'appointment' => [
        'id' => $appointment['id'],
        'patient_name' => $appointment['patient_name'],
        'patient_phone' => $appointment['patient_phone'],
        'patient_address' => $appointment['patient_address'],
        'date_of_birth' => $appointment['date_of_birth'],
        'gender' => ucfirst($appointment['gender']),
        'medical_history' => $appointment['medical_history'],
        'allergies' => $appointment['allergies'],
        'appointment_date' => $appointment['appointment_date'],
        'vaccine_id' => $appointment['vaccine_id'],
        'vaccine_name' => $appointment['vaccine_name'],
        'vaccine_manufacturer' => $appointment['manufacturer'],
        'vaccine_description' => $appointment['vaccine_description'],
        'vaccine_recommended_age' => $appointment['recommended_age'],
        'vaccine_doses_required' => $appointment['doses_required'],
        'purpose' => $appointment['purpose'],
        'status' => $appointment['status'],
        'notes' => $appointment['notes'],
        'staff_name' => $appointment['staff_name'],
        'created_at' => $appointment['created_at'],
        'updated_at' => $appointment['updated_at']
    ]
];

$conn->close();

header('Content-Type: application/json');
echo json_encode($response);
?>
