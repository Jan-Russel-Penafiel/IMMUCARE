<?php
session_start();
require 'config.php';
require_once 'notification_system.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Validate required fields
$required_fields = ['appointment_id', 'appointment_date', 'purpose', 'status'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
        exit;
    }
}

$appointment_id = (int)$_POST['appointment_id'];
$appointment_date = $_POST['appointment_date'];
$purpose = trim($_POST['purpose']);
$status = $_POST['status'];
$vaccine_id = !empty($_POST['vaccine_id']) ? (int)$_POST['vaccine_id'] : null;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
$user_id = $_SESSION['user_id'];

// Validate status
$valid_statuses = ['requested', 'confirmed', 'completed', 'cancelled', 'no_show'];
if (!in_array($status, $valid_statuses)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Validate date format
$date_obj = DateTime::createFromFormat('Y-m-d\TH:i', $appointment_date);
if (!$date_obj) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Convert to MySQL datetime format
$mysql_date = $date_obj->format('Y-m-d H:i:s');

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Check if appointment exists and user has permission to edit it
$check_query = "SELECT id, staff_id FROM appointments WHERE id = ?";
$stmt = $conn->prepare($check_query);
$stmt->bind_param("i", $appointment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Appointment not found']);
    exit;
}

$appointment = $result->fetch_assoc();

// Check if user is assigned to this appointment or if appointment is unassigned
if ($appointment['staff_id'] !== null && $appointment['staff_id'] != $user_id) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this appointment']);
    exit;
}

// If vaccine_id is provided, validate it exists
if ($vaccine_id !== null) {
    $vaccine_check = $conn->prepare("SELECT id FROM vaccines WHERE id = ? AND is_active = 1");
    $vaccine_check->bind_param("i", $vaccine_id);
    $vaccine_check->execute();
    $vaccine_result = $vaccine_check->get_result();
    
    if ($vaccine_result->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid vaccine selected']);
        exit;
    }
}

// Update the appointment
$update_query = "UPDATE appointments 
                 SET appointment_date = ?, 
                     purpose = ?, 
                     status = ?, 
                     vaccine_id = ?, 
                     notes = ?,
                     staff_id = COALESCE(staff_id, ?)
                 WHERE id = ?";

$stmt = $conn->prepare($update_query);
$stmt->bind_param("sssisii", $mysql_date, $purpose, $status, $vaccine_id, $notes, $user_id, $appointment_id);

if ($stmt->execute()) {
    // Get updated appointment details for notification
    $notification_query = "SELECT a.*, p.first_name, p.last_name, p.email, p.phone, 
                                 v.name as vaccine_name, 
                                 CONCAT(s.first_name, ' ', s.last_name) as staff_name
                          FROM appointments a 
                          LEFT JOIN patients p ON a.patient_id = p.id 
                          LEFT JOIN vaccines v ON a.vaccine_id = v.id 
                          LEFT JOIN users s ON a.staff_id = s.id 
                          WHERE a.id = ?";
    
    $notification_stmt = $conn->prepare($notification_query);
    $notification_stmt->bind_param("i", $appointment_id);
    $notification_stmt->execute();
    $notification_result = $notification_stmt->get_result();
    
    if ($notification_result->num_rows > 0) {
        $appointment_data = $notification_result->fetch_assoc();
        
        // Initialize notification system
        $notificationSystem = new NotificationSystem();
        
        // Send status update notification
        $notificationSystem->sendAppointmentStatusNotification($appointment_id, $status);
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Appointment updated successfully'
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to update appointment: ' . $conn->error
    ]);
}

$conn->close();
?>
