<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Location: login.php');
    exit;
}

// Get nurse information
$user_id = $_SESSION['user_id'];

// Check if report type is specified
if (!isset($_GET['type'])) {
    die("Error: Report type not specified");
}

$report_type = $_GET['type'];
$allowed_types = ['patients', 'appointments', 'diagnoses'];

if (!in_array($report_type, $allowed_types)) {
    die("Error: Invalid report type");
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set headers for CSV download
$filename = $report_type . '_report_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Based on report type, fetch data and write to CSV
switch ($report_type) {
    case 'patients':
        // Add CSV headers
        fputcsv($output, [
            'ID', 'Name', 'Date of Birth', 'Gender', 'Phone Number', 
            'Address', 'Medical History', 'Allergies', 
            'Immunization Count', 'Appointment Count'
        ]);
        
        // Get patients data
        $stmt = $conn->prepare("
            SELECT 
                p.*,
                COUNT(DISTINCT i.id) as immunization_count,
                COUNT(DISTINCT a.id) as appointment_count
            FROM patients p
            LEFT JOIN immunizations i ON p.id = i.patient_id
            LEFT JOIN appointments a ON p.id = a.patient_id
            GROUP BY p.id
            ORDER BY p.last_name, p.first_name
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Output each row of data
        while ($row = $result->fetch_assoc()) {
            $address = $row['purok'] . ', ' . $row['city'] . ', ' . $row['province'];
            if (!empty($row['postal_code'])) {
                $address .= ' ' . $row['postal_code'];
            }
            
            fputcsv($output, [
                $row['id'],
                $row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'] . ' ' : '') . $row['last_name'],
                date('Y-m-d', strtotime($row['date_of_birth'])),
                $row['gender'],
                $row['phone_number'],
                $address,
                $row['medical_history'] ?? 'None',
                $row['allergies'] ?? 'None',
                $row['immunization_count'],
                $row['appointment_count']
            ]);
        }
        break;
        
    case 'appointments':
        // Add CSV headers
        fputcsv($output, [
            'ID', 'Date & Time', 'Patient Name', 'Purpose', 
            'Vaccine', 'Status', 'Notes', 'Created At'
        ]);
        
        // Get appointments data
        $stmt = $conn->prepare("
            SELECT 
                a.*,
                p.first_name,
                p.last_name,
                v.name as vaccine_name
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            LEFT JOIN vaccines v ON a.vaccine_id = v.id
            WHERE a.staff_id = ?
            ORDER BY a.appointment_date DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Output each row of data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                $row['id'],
                date('Y-m-d H:i:s', strtotime($row['appointment_date'])),
                $row['first_name'] . ' ' . $row['last_name'],
                $row['purpose'],
                $row['vaccine_name'] ?? 'N/A',
                $row['status'],
                $row['notes'] ?? '',
                date('Y-m-d H:i:s', strtotime($row['created_at']))
            ]);
        }
        break;
        
    case 'diagnoses':
        // Add CSV headers
        fputcsv($output, [
            'Date', 'Patient Name', 'Vaccine', 'Dose', 
            'Batch Number', 'Diagnosis', 'Location'
        ]);
        
        // Get diagnoses data
        $stmt = $conn->prepare("
            SELECT 
                p.first_name,
                p.last_name,
                i.administered_date,
                i.diagnosis,
                i.location,
                v.name as vaccine_name,
                i.batch_number,
                i.dose_number
            FROM patients p
            JOIN immunizations i ON p.id = i.patient_id
            JOIN vaccines v ON i.vaccine_id = v.id
            WHERE i.administered_by = ?
            ORDER BY i.administered_date DESC
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // Output each row of data
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [
                date('Y-m-d', strtotime($row['administered_date'])),
                $row['first_name'] . ' ' . $row['last_name'],
                $row['vaccine_name'],
                $row['dose_number'],
                $row['batch_number'],
                $row['diagnosis'] ?? 'No diagnosis recorded',
                $row['location'] ?? 'N/A'
            ]);
        }
        break;
}

// Close database connection
$stmt->close();
$conn->close();
?> 