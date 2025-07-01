<?php
session_start();
require 'config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Get patient ID
$stmt = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    echo json_encode(['success' => false, 'message' => 'Patient record not found']);
    exit;
}

$patient_id = $patient['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $vaccine_id = !empty($_POST['vaccine_id']) ? $_POST['vaccine_id'] : null;
    $purpose = $_POST['purpose'] ?? '';
    $notes = $_POST['notes'] ?? '';

    // Validate inputs
    if (empty($appointment_date) || empty($appointment_time) || empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        exit;
    }

    // Format appointment datetime
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;

    try {
        // Start transaction
        $conn->begin_transaction();

        // Create appointment
        $stmt = $conn->prepare("INSERT INTO appointments (
            patient_id, 
            appointment_date, 
            vaccine_id,
            purpose, 
            notes,
            status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'requested', NOW())");

        $stmt->bind_param("isiss", $patient_id, $appointment_datetime, $vaccine_id, $purpose, $notes);

        if (!$stmt->execute()) {
            throw new Exception('Failed to create appointment');
        }

        $appointment_id = $conn->insert_id;

        // Create notification for staff
        $notification_message = "New appointment request from patient #" . $patient_id . " for " . date('F j, Y - g:i A', strtotime($appointment_datetime));
        
        // Send notification to all staff (nurses and midwives)
        $staff_stmt = $conn->prepare("SELECT id FROM users WHERE user_type IN ('nurse', 'midwife')");
        $staff_stmt->execute();
        $staff_result = $staff_stmt->get_result();
        
        while ($staff = $staff_result->fetch_assoc()) {
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, created_at) 
                VALUES (?, 'New Appointment Request', ?, 'appointment_request', NOW())");
            $notify_stmt->bind_param("is", $staff['id'], $notification_message);
            $notify_stmt->execute();
        }

        // Commit transaction
        $conn->commit();

        echo json_encode([
            'success' => true, 
            'message' => 'Appointment requested successfully. You will be notified once it is confirmed.'
        ]);

    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

$conn->close(); 