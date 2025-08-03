<?php
session_start();
require 'config.php';

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'midwife') {
    header('Location: login.php');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: midwife_immunization_records.php?error=Invalid+request');
    exit;
}

// Validate required fields
$required_fields = ['immunization_id', 'patient_id', 'vaccine_id', 'dose_number', 'batch_number', 'expiration_date', 'administered_date', 'location'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        header('Location: midwife_immunization_records.php?error=Missing+required+field:+' . $field);
        exit;
    }
}

$immunization_id = (int)$_POST['immunization_id'];
$patient_id = (int)$_POST['patient_id'];
$vaccine_id = (int)$_POST['vaccine_id'];
$dose_number = (int)$_POST['dose_number'];
$batch_number = trim($_POST['batch_number']);
$expiration_date = $_POST['expiration_date'];
$administered_date = $_POST['administered_date'];
$location = trim($_POST['location']);
$next_dose_date = !empty($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
$diagnosis = !empty($_POST['diagnosis']) ? trim($_POST['diagnosis']) : null;
$user_id = $_SESSION['user_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    header('Location: midwife_immunization_records.php?error=Database+connection+failed');
    exit;
}

// Verify that the immunization record belongs to this midwife
$stmt = $conn->prepare("SELECT id FROM immunizations WHERE id = ? AND administered_by = ?");
$stmt->bind_param("ii", $immunization_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $conn->close();
    header('Location: midwife_immunization_records.php?error=Immunization+record+not+found+or+access+denied');
    exit;
}

// Update the immunization record
$stmt = $conn->prepare("UPDATE immunizations SET 
                        patient_id = ?, 
                        vaccine_id = ?, 
                        dose_number = ?, 
                        batch_number = ?, 
                        expiration_date = ?, 
                        administered_date = ?, 
                        next_dose_date = ?, 
                        location = ?, 
                        diagnosis = ?,
                        updated_at = NOW()
                        WHERE id = ? AND administered_by = ?");

$stmt->bind_param("iiissssssii", $patient_id, $vaccine_id, $dose_number, $batch_number, $expiration_date, $administered_date, $next_dose_date, $location, $diagnosis, $immunization_id, $user_id);

if ($stmt->execute()) {
    $conn->close();
    header('Location: midwife_immunization_records.php?success=Immunization+record+updated+successfully');
} else {
    $conn->close();
    header('Location: midwife_immunization_records.php?error=Failed+to+update+immunization+record');
}
exit;
?>
