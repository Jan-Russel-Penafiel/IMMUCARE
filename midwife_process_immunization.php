<?php
session_start();
require 'config.php';
require_once 'transaction_helper.php';

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'midwife') {
    header('Location: login.php');
    exit;
}

// Get midwife information
$user_id = $_SESSION['user_id'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Validate and sanitize inputs
    $patient_id = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $vaccine_id = filter_input(INPUT_POST, 'vaccine_id', FILTER_VALIDATE_INT);
    $dose_number = filter_input(INPUT_POST, 'dose_number', FILTER_VALIDATE_INT);
    $batch_number = filter_input(INPUT_POST, 'batch_number', FILTER_SANITIZE_STRING);
    $expiration_date = filter_input(INPUT_POST, 'expiration_date', FILTER_SANITIZE_STRING);
    $administered_date = filter_input(INPUT_POST, 'administered_date', FILTER_SANITIZE_STRING);
    $next_dose_date = filter_input(INPUT_POST, 'next_dose_date', FILTER_SANITIZE_STRING) ?: null;
    $location = filter_input(INPUT_POST, 'location', FILTER_SANITIZE_STRING);
    $diagnosis = filter_input(INPUT_POST, 'diagnosis', FILTER_SANITIZE_STRING);
    
    // Validate required fields
    if (!$patient_id || !$vaccine_id || !$dose_number || !$batch_number || !$expiration_date || !$administered_date || !$location) {
        header('Location: midwife_immunization_records.php?error=All required fields must be filled');
        exit;
    }
    
    // Check if the patient exists
    $stmt = $conn->prepare("SELECT id FROM patients WHERE id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        header('Location: midwife_immunization_records.php?error=Invalid patient');
        exit;
    }
    
    // Check if the vaccine exists
    $stmt = $conn->prepare("SELECT id FROM vaccines WHERE id = ?");
    $stmt->bind_param("i", $vaccine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        header('Location: midwife_immunization_records.php?error=Invalid vaccine');
        exit;
    }
    
    // Generate transaction data
    $transactionData = TransactionHelper::generateTransactionData($conn);
    
    // Insert the immunization record
    $stmt = $conn->prepare("INSERT INTO immunizations (
        patient_id, 
        vaccine_id, 
        administered_by, 
        dose_number, 
        batch_number, 
        expiration_date, 
        administered_date, 
        next_dose_date, 
        location, 
        diagnosis,
        transaction_id,
        transaction_number,
        created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    $stmt->bind_param(
        "iiiiisssssis",
        $patient_id,
        $vaccine_id,
        $user_id,
        $dose_number,
        $batch_number,
        $expiration_date,
        $administered_date,
        $next_dose_date,
        $location,
        $diagnosis,
        $transactionData['transaction_id'],
        $transactionData['transaction_number']
    );
    
    if ($stmt->execute()) {
        // Check if this was the final dose and create a notification if needed
        $stmt = $conn->prepare("SELECT v.doses_required, p.user_id 
                               FROM vaccines v 
                               JOIN patients p ON p.id = ? 
                               WHERE v.id = ?");
        $stmt->bind_param("ii", $patient_id, $vaccine_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $vaccine_info = $result->fetch_assoc();
        
        if ($dose_number == $vaccine_info['doses_required']) {
            // This was the final dose, create a notification for the patient if they have a user account
            if ($vaccine_info['user_id']) {
                $stmt = $conn->prepare("INSERT INTO notifications (
                    user_id, 
                    title, 
                    message, 
                    type, 
                    sent_at, 
                    created_at
                ) VALUES (?, 'Vaccination Complete', 'You have completed all required doses for this vaccine.', 'system', NOW(), NOW())");
                $stmt->bind_param("i", $vaccine_info['user_id']);
                $stmt->execute();
            }
        }
        
        // Redirect with success message
        header('Location: midwife_immunization_records.php?success=1');
        exit;
    } else {
        // Redirect with error message
        header('Location: midwife_immunization_records.php?error=Failed to add immunization record: ' . $conn->error);
        exit;
    }
    
    $conn->close();
} else {
    // If not a POST request, redirect to the immunization records page
    header('Location: midwife_immunization_records.php');
    exit;
}
?> 