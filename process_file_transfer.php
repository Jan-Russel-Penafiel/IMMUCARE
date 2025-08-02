<?php
// Turn off all error reporting to prevent errors from breaking JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to catch any unexpected output
ob_start();

session_start();
require 'config.php';
require 'includes/file_transfer.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    // Clean any output that might have been generated
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    // Clean any output that might have been generated
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Process AJAX data transfer request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all required parameters exist
    if (!isset($_POST['health_center_id']) || !isset($_POST['data_types'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    $health_center_id = $_POST['health_center_id'];
    $selectedDataTypes = $_POST['data_types'];
    $selectedFormats = isset($_POST['formats']) ? $_POST['formats'] : ['excel', 'pdf'];
    
    // Get health center details
    $stmt = $conn->prepare("SELECT name, email FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $health_center = $result->fetch_assoc();
    
    if (!$health_center) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Health center not found']);
        exit;
    }
    
    // Prepare all data sets based on selected types
    $dataSets = [];
    
    // Get immunization records if selected
    if (in_array('immunizations', $selectedDataTypes)) {
        $stmt = $conn->prepare("
            SELECT 
                p.id as patient_id,
                CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) as name,
                TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                p.gender,
                v.name as vaccine,
                i.administered_date as date,
                i.dose_number,
                i.batch_number,
                i.next_dose_date,
                CASE 
                    WHEN i.next_dose_date IS NOT NULL AND i.next_dose_date > CURDATE() THEN 'Pending Next Dose'
                    WHEN i.next_dose_date IS NOT NULL AND i.next_dose_date <= CURDATE() THEN 'Due for Next Dose'
                    ELSE 'Completed'
                END as status
            FROM immunizations i
            JOIN patients p ON i.patient_id = p.id
            JOIN vaccines v ON i.vaccine_id = v.id
            ORDER BY i.administered_date DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $immunizationData = $result->fetch_all(MYSQLI_ASSOC);
        
        $dataSets[] = [
            'data' => $immunizationData,
            'label' => 'Immunization Records',
            'formats' => $selectedFormats
        ];
    }
    
    // Get vaccine inventory if selected
    if (in_array('vaccines', $selectedDataTypes)) {
        $stmt = $conn->prepare("
            SELECT 
                v.id,
                v.name as vaccine_name,
                v.manufacturer,
                v.batch_number,
                v.expiry_date,
                v.quantity_available,
                v.minimum_stock_level,
                v.storage_requirements,
                v.created_at,
                v.updated_at
            FROM vaccines v
            ORDER BY v.expiry_date ASC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $vaccineData = $result->fetch_all(MYSQLI_ASSOC);
        
        $dataSets[] = [
            'data' => $vaccineData,
            'label' => 'Vaccine Inventory',
            'formats' => $selectedFormats
        ];
    }
    
    // Get patient data if selected
    if (in_array('patients', $selectedDataTypes)) {
        $stmt = $conn->prepare("
            SELECT 
                p.id,
                CONCAT(p.first_name, ' ', COALESCE(p.middle_name, ''), ' ', p.last_name) as name,
                p.date_of_birth,
                TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                p.gender,
                p.contact_number,
                p.address,
                p.registration_date
            FROM patients p
            ORDER BY p.registration_date DESC
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $patientData = $result->fetch_all(MYSQLI_ASSOC);
        
        $dataSets[] = [
            'data' => $patientData,
            'label' => 'Patient Records',
            'formats' => $selectedFormats
        ];
    }
    
    // Initialize file transfer with multiple data sets
    $transfer = new FileTransfer();
    try {
        // Clean any output that might have been generated before
        ob_clean();
        
        if ($transfer->sendMultipleToGoogleDrive($dataSets, $health_center['email'], $health_center_id)) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true, 
                'message' => "Multiple data sets transferred to " . $health_center['name'] . " successfully. Files have been sent to " . $health_center['email'],
                'health_center' => [
                    'name' => $health_center['name'],
                    'email' => $health_center['email']
                ],
                'data_sets' => array_map(function($set) { 
                    return [
                        'label' => $set['label'],
                        'records' => count($set['data']),
                        'formats' => $set['formats']
                    ]; 
                }, $dataSets)
            ]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => "Error during data transfer. Please check the logs and try again."]);
        }
    } catch (Exception $e) {
        // Clean any output that might have been generated
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => "Error during data transfer: " . $e->getMessage()]);
    }
    
    // Close the database connection
    $conn->close();
    exit;
}

// If not a POST request
// Clean any output that might have been generated
ob_clean();
header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
exit;
