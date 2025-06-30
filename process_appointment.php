<?php
session_start();
require 'config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and has appropriate role
$allowed_user_types = ['midwife', 'nurse', 'admin'];
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], $allowed_user_types)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Handle POST request for rescheduling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the data from the form
    $appointmentId = $_POST['appointmentId'] ?? '';
    $newDate = $_POST['newDate'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $staff_id = $_SESSION['user_id'];

    // Validate inputs
    if (empty($appointmentId) || empty($newDate) || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }

    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }

    try {
        // Start transaction
        $conn->begin_transaction();

        // Update the appointment
        $stmt = $conn->prepare("UPDATE appointments SET 
            appointment_date = ?, 
            last_updated = NOW(),
            updated_by = ?,
            reschedule_reason = ?
            WHERE id = ? AND staff_id = ?");
        
        $stmt->bind_param("sisii", $newDate, $staff_id, $reason, $appointmentId, $staff_id);
        
        if ($stmt->execute()) {
            // Get patient information for notification
            $stmt = $conn->prepare("SELECT p.id, p.first_name, p.last_name, p.email 
                                  FROM appointments a 
                                  JOIN patients p ON a.patient_id = p.id 
                                  WHERE a.id = ?");
            $stmt->bind_param("i", $appointmentId);
            $stmt->execute();
            $result = $stmt->get_result();
            $patient = $result->fetch_assoc();

            // Create notification
            if ($patient) {
                $notification_message = "Your appointment has been rescheduled to " . date('F j, Y - g:i A', strtotime($newDate));
                $stmt = $conn->prepare("INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'appointment_reschedule', NOW())");
                $stmt->bind_param("is", $patient['id'], $notification_message);
                $stmt->execute();
            }

            // Commit transaction
            $conn->commit();
            
            echo json_encode(['success' => true, 'message' => 'Appointment rescheduled successfully']);
        } else {
            throw new Exception('Failed to update appointment');
        }
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$user_id = null;
$patient_id = null;
$create_account = isset($_POST['create_account']) ? true : false;
$appointment_status = 'requested';

// Get form data
$first_name = $_POST['first_name'];
$last_name = $_POST['last_name'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$date_of_birth = $_POST['date_of_birth'];
$gender = $_POST['gender'];
$purok = $_POST['purok'];
$city = $_POST['city'];
$province = $_POST['province'];
$postal_code = isset($_POST['postal_code']) ? $_POST['postal_code'] : '';
$appointment_date = $_POST['appointment_date'];
$appointment_time = $_POST['appointment_time'];
$purpose = $_POST['purpose'];
$additional_info = isset($_POST['additional_info']) ? $_POST['additional_info'] : '';

// Format appointment datetime
$appointment_datetime = $appointment_date . ' ' . $appointment_time . ':00';

// Check if email already exists in the system
$check_email = $conn->prepare("SELECT id, user_type FROM users WHERE email = ?");
$check_email->bind_param("s", $email);
$check_email->execute();
$email_result = $check_email->get_result();

if ($email_result->num_rows > 0) {
    // User already exists
    $user_data = $email_result->fetch_assoc();
    $user_id = $user_data['id'];
    
    // Check if patient record exists for this user
    $check_patient = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
    $check_patient->bind_param("i", $user_id);
    $check_patient->execute();
    $patient_result = $check_patient->get_result();
    
    if ($patient_result->num_rows > 0) {
        // Patient record exists
        $patient_id = $patient_result->fetch_assoc()['id'];
    } else {
        // Create new patient record for existing user
        $stmt = $conn->prepare("INSERT INTO patients (user_id, first_name, last_name, date_of_birth, gender, purok, city, province, postal_code, phone_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssssssss", $user_id, $first_name, $last_name, $date_of_birth, $gender, $purok, $city, $province, $postal_code, $phone);
        
        if ($stmt->execute()) {
            $patient_id = $conn->insert_id;
        } else {
            die("Error creating patient record: " . $conn->error);
        }
    }
} else if ($create_account) {
    // Create new user account
    $user_name = $first_name . ' ' . $last_name;
    $password = generateRandomPassword();
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $user_stmt = $conn->prepare("INSERT INTO users (role_id, user_type, name, email, phone, password, created_at) VALUES (4, 'patient', ?, ?, ?, ?, NOW())");
    $user_stmt->bind_param("ssss", $user_name, $email, $phone, $hashed_password);
    
    if ($user_stmt->execute()) {
        $user_id = $conn->insert_id;
        
        // Create patient record
        $stmt = $conn->prepare("INSERT INTO patients (user_id, first_name, last_name, date_of_birth, gender, purok, city, province, postal_code, phone_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssssssss", $user_id, $first_name, $last_name, $date_of_birth, $gender, $purok, $city, $province, $postal_code, $phone);
        
        if ($stmt->execute()) {
            $patient_id = $conn->insert_id;
            
            // Send welcome email with login credentials
            sendWelcomeEmail($email, $user_name, $password, $conn, $user_id);
        } else {
            die("Error creating patient record: " . $conn->error);
        }
    } else {
        die("Error creating user account: " . $conn->error);
    }
} else {
    // Create patient record without user account
    $stmt = $conn->prepare("INSERT INTO patients (first_name, last_name, date_of_birth, gender, purok, city, province, postal_code, phone_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssssss", $first_name, $last_name, $date_of_birth, $gender, $purok, $city, $province, $postal_code, $phone);
    
    if ($stmt->execute()) {
        $patient_id = $conn->insert_id;
    } else {
        die("Error creating patient record: " . $conn->error);
    }
}

// Create appointment
$stmt = $conn->prepare("INSERT INTO appointments (patient_id, appointment_date, purpose, status, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
$stmt->bind_param("issss", $patient_id, $appointment_datetime, $purpose, $appointment_status, $additional_info);

if ($stmt->execute()) {
    $appointment_id = $conn->insert_id;
    
    // Send appointment confirmation email
    sendAppointmentConfirmation($email, $first_name . ' ' . $last_name, $appointment_datetime, $purpose, $conn, $user_id);
    
    // Redirect to confirmation page
    header("Location: appointment_confirmation.php?id=" . $appointment_id);
    exit;
} else {
    die("Error creating appointment: " . $conn->error);
}

// Helper functions
function generateRandomPassword($length = 10) {
    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_-=+;:,.?";
    $password = substr(str_shuffle($chars), 0, $length);
    return $password;
}

function sendWelcomeEmail($email, $name, $password, $conn, $user_id) {
    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ' . APP_NAME . ' - Account Created';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="' . APP_NAME . ' Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">Welcome to ' . APP_NAME . '!</h2>
                <p>Hello ' . $name . ',</p>
                <p>Your account has been successfully created. You can now log in to manage your appointments and immunization records.</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Email:</strong> ' . $email . '</p>
                    <p><strong>Password:</strong> ' . $password . '</p>
                </div>
                <p>We recommend changing your password after your first login.</p>
                <div style="text-align: center; margin-top: 30px;">
                    <a href="' . APP_URL . '/login.php" style="background-color: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Login to Your Account</a>
                </div>
                <p style="margin-top: 30px;">Thank you for choosing ' . APP_NAME . ' for your immunization needs.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        ';
        
        $mail->send();
        
        // Log the email
        $log_stmt = $conn->prepare("INSERT INTO email_logs (user_id, email_address, subject, message, status, sent_at, created_at) VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())");
        $subject = 'Welcome to ' . APP_NAME . ' - Account Created';
        $log_stmt->bind_param("isss", $user_id, $email, $subject, $mail->Body);
        $log_stmt->execute();
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}

function sendAppointmentConfirmation($email, $name, $appointment_datetime, $purpose, $conn, $user_id = null) {
    $mail = new PHPMailer(true);
    try {
        // Format date and time for display
        $date = date('l, F j, Y', strtotime($appointment_datetime));
        $time = date('g:i A', strtotime($appointment_datetime));
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = APP_NAME . ' - Appointment Request Received';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="' . APP_NAME . ' Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">Appointment Request Received</h2>
                <p>Hello ' . $name . ',</p>
                <p>Thank you for requesting an appointment with ' . APP_NAME . '. Your appointment request has been received and is pending confirmation.</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Date:</strong> ' . $date . '</p>
                    <p><strong>Time:</strong> ' . $time . '</p>
                    <p><strong>Purpose:</strong> ' . $purpose . '</p>
                </div>
                <p>Our staff will review your request and confirm your appointment shortly. You will receive another email once your appointment is confirmed.</p>
                <p style="margin-top: 30px;">Thank you for choosing ' . APP_NAME . ' for your healthcare needs.</p>
                <p>Best regards,<br>' . APP_NAME . ' Team</p>
            </div>
        ';
        
        $mail->send();
        
        // Log the email if user_id is provided
        if ($user_id) {
            $log_stmt = $conn->prepare("INSERT INTO email_logs (user_id, email_address, subject, message, status, sent_at, created_at) VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())");
            $subject = APP_NAME . ' - Appointment Request Received';
            $log_stmt->bind_param("isss", $user_id, $email, $subject, $mail->Body);
            $log_stmt->execute();
        }
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
    }
}

$conn->close();
?> 