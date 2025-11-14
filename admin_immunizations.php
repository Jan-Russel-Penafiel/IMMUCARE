<?php
session_start();
require 'config.php';
require_once 'vendor/autoload.php';
require_once 'notification_system.php';
require_once 'transaction_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$admin_email = $_SESSION['user_email'];

// Initialize notification system
$notification_system = new NotificationSystem();

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patient ID from URL
$patient_id = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : 0;

// Get patient information
$patient = null;
if ($patient_id > 0) {
    $stmt = $conn->prepare("SELECT p.*, CONCAT(p.first_name, ' ', p.middle_name, ' ', p.last_name) as full_name,
                           TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                           FROM patients p WHERE p.id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $patient = $result->fetch_assoc();
    } else {
        header('Location: admin_patients.php');
        exit;
    }
}

// Process immunization actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Add new immunization
if ($action == 'add' && isset($_POST['add_immunization'])) {
    $vaccine_id = $_POST['vaccine_id'];
    $administered_by = $_POST['administered_by'];
    $dose_number = $_POST['dose_number'];
    $batch_number = $_POST['batch_number'];
    $expiration_date = $_POST['expiration_date'];
    $administered_date = $_POST['administered_date'];
    $next_dose_date = !empty($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
    $location = $_POST['location'];
    $diagnosis = isset($_POST['diagnosis']) ? $_POST['diagnosis'] : null;

    // Generate transaction data
    $transactionData = TransactionHelper::generateTransactionData($conn);
    
    // Insert new immunization record
    $stmt = $conn->prepare("INSERT INTO immunizations (patient_id, vaccine_id, administered_by, dose_number, batch_number, expiration_date, administered_date, next_dose_date, location, diagnosis, transaction_id, transaction_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiissssssss", $patient_id, $vaccine_id, $administered_by, $dose_number, $batch_number, $expiration_date, $administered_date, $next_dose_date, $location, $diagnosis, $transactionData['transaction_id'], $transactionData['transaction_number']);

    if ($stmt->execute()) {
        $immunization_id = $conn->insert_id;
        
        // Get vaccine name for the notification
        $vaccine_query = $conn->prepare("SELECT name FROM vaccines WHERE id = ?");
        $vaccine_query->bind_param("i", $vaccine_id);
        $vaccine_query->execute();
        $vaccine_result = $vaccine_query->get_result();
        $vaccine_name = $vaccine_result->fetch_assoc()['name'];
        
        // Get administered by name for the notification
        $staff_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $staff_query->bind_param("i", $administered_by);
        $staff_query->execute();
        $staff_result = $staff_query->get_result();
        $administered_by_name = $staff_result->fetch_assoc()['name'];
        
        // Send notification if patient has a user account
        if ($patient['user_id']) {
            $immunization_message = "A new immunization record has been added to your profile.\n\n" .
                                  "Immunization Details:\n" .
                                  "- Vaccine: " . $vaccine_name . "\n" .
                                  "- Dose Number: " . $dose_number . "\n" .
                                  "- Date Administered: " . date('F j, Y', strtotime($administered_date)) . "\n" .
                                  "- Administered By: " . $administered_by_name . "\n" .
                                  "- Batch Number: " . $batch_number . "\n" .
                                  "- Next Dose Due: " . ($next_dose_date ? date('F j, Y', strtotime($next_dose_date)) : 'Not Required') . "\n\n" .
                                  "Important Notes:\n" .
                                  "- Keep this record for your personal files\n" .
                                  "- Watch for any side effects and report them immediately\n" .
                                  ($next_dose_date ? "- Mark your calendar for the next dose\n" : "") .
                                  "- Contact us if you experience any unusual symptoms\n\n" .
                                  "You can view your complete immunization history in your patient portal.";
            
            $notification_system->sendCustomNotification(
                $patient['user_id'],
                "New Immunization Record Added: " . $vaccine_name,
                $immunization_message,
                'both'
            );
        }
        
        $action = ''; // Return to list view
    } else {
        $action_message = "Error adding immunization record: " . $conn->error;
    }
}

// Edit immunization
if ($action == 'edit' && isset($_POST['edit_immunization'])) {
    $immunization_id = $_POST['immunization_id'];
    $vaccine_id = $_POST['vaccine_id'];
    $administered_by = $_POST['administered_by'];
    $dose_number = $_POST['dose_number'];
    $batch_number = $_POST['batch_number'];
    $expiration_date = $_POST['expiration_date'];
    $administered_date = $_POST['administered_date'];
    $next_dose_date = !empty($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
    $location = $_POST['location'];
    $diagnosis = isset($_POST['diagnosis']) ? $_POST['diagnosis'] : null;

    // Generate transaction data for update
    $transactionData = TransactionHelper::generateTransactionData($conn);
    
    // Update immunization record
    $stmt = $conn->prepare("UPDATE immunizations SET vaccine_id = ?, administered_by = ?, dose_number = ?, batch_number = ?, expiration_date = ?, administered_date = ?, next_dose_date = ?, location = ?, diagnosis = ?, transaction_id = ?, transaction_number = ? WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("iiiisssssssis", $vaccine_id, $administered_by, $dose_number, $batch_number, $expiration_date, $administered_date, $next_dose_date, $location, $diagnosis, $transactionData['transaction_id'], $transactionData['transaction_number'], $immunization_id, $patient_id);

    if ($stmt->execute()) {
        // Get vaccine name for the notification
        $vaccine_query = $conn->prepare("SELECT name FROM vaccines WHERE id = ?");
        $vaccine_query->bind_param("i", $vaccine_id);
        $vaccine_query->execute();
        $vaccine_result = $vaccine_query->get_result();
        $vaccine_name = $vaccine_result->fetch_assoc()['name'];
        
        // Get administered by name for the notification
        $staff_query = $conn->prepare("SELECT name FROM users WHERE id = ?");
        $staff_query->bind_param("i", $administered_by);
        $staff_query->execute();
        $staff_result = $staff_query->get_result();
        $administered_by_name = $staff_result->fetch_assoc()['name'];
        
        // Send update notification if patient has a user account
        if ($patient['user_id']) {
            $update_message = "An immunization record in your profile has been updated.\n\n" .
                            "Updated Immunization Details:\n" .
                            "- Vaccine: " . $vaccine_name . "\n" .
                            "- Dose Number: " . $dose_number . "\n" .
                            "- Date Administered: " . date('F j, Y', strtotime($administered_date)) . "\n" .
                            "- Administered By: " . $administered_by_name . "\n" .
                            "- Batch Number: " . $batch_number . "\n" .
                            "- Next Dose Due: " . ($next_dose_date ? date('F j, Y', strtotime($next_dose_date)) : 'Not Required') . "\n\n" .
                            "Please review these changes in your patient portal. " .
                            "If you notice any discrepancies, please contact our immunization department at " .
                            IMMUNIZATION_PHONE . " or via email at " . IMMUNIZATION_EMAIL . ".\n\n" .
                            "Remember to:\n" .
                            "- Keep your immunization records up to date\n" .
                            "- Report any delayed reactions or concerns\n" .
                            ($next_dose_date ? "- Schedule your next dose before " . date('F j, Y', strtotime($next_dose_date)) : "");
            
            $notification_system->sendCustomNotification(
                $patient['user_id'],
                "Immunization Record Updated: " . $vaccine_name,
                $update_message,
                'both'
            );
        }
        
        $action = ''; // Return to list view
    } else {
        $action_message = "Error updating immunization record: " . $conn->error;
    }
}

// Delete immunization
if ($action == 'delete' && isset($_GET['id'])) {
    $immunization_id = $_GET['id'];
    
    // Get immunization details before deleting
    $stmt = $conn->prepare("SELECT i.*, v.name as vaccine_name FROM immunizations i JOIN vaccines v ON i.vaccine_id = v.id WHERE i.id = ? AND i.patient_id = ?");
    $stmt->bind_param("ii", $immunization_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $immunization = $result->fetch_assoc();
        
        // Delete the immunization record
        $delete_stmt = $conn->prepare("DELETE FROM immunizations WHERE id = ? AND patient_id = ?");
        $delete_stmt->bind_param("ii", $immunization_id, $patient_id);
        
        if ($delete_stmt->execute()) {
            // Note: Deletion notifications are disabled as per system policy
            // if ($patient['user_id']) {
            //     // Notification logic removed - deletions do not send notifications
            // }
        } else {
            $action_message = "Error deleting immunization record: " . $conn->error;
        }
    } else {
        $action_message = "Immunization record not found.";
    }
    
    $action = ''; // Return to list view
}

// Get immunization data if editing
$edit_immunization = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $immunization_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT i.*, v.name as vaccine_name, v.manufacturer 
                           FROM immunizations i 
                           JOIN vaccines v ON i.vaccine_id = v.id
                           WHERE i.id = ? AND i.patient_id = ?");
    $stmt->bind_param("ii", $immunization_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $edit_immunization = $result->fetch_assoc();
    } else {
        header("Location: ?patient_id=" . $patient_id);
        exit;
    }
}

// Get immunization data if viewing
$view_immunization = null;
if ($action == 'view' && isset($_GET['id'])) {
    $immunization_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT i.*, v.name as vaccine_name, v.manufacturer, v.recommended_age,
                           u.name as administered_by_name
                           FROM immunizations i 
                           JOIN vaccines v ON i.vaccine_id = v.id
                           JOIN users u ON i.administered_by = u.id
                           WHERE i.id = ? AND i.patient_id = ?");
    $stmt->bind_param("ii", $immunization_id, $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $view_immunization = $result->fetch_assoc();
    } else {
        header("Location: ?patient_id=" . $patient_id);
        exit;
    }
}

// Fetch all available vaccines for dropdown
$vaccines_query = "SELECT * FROM vaccines ORDER BY name";
$vaccines_result = $conn->query($vaccines_query);
$vaccines = [];
while ($vaccine = $vaccines_result->fetch_assoc()) {
    $vaccines[] = $vaccine;
}

// Fetch all staff members who can administer vaccines
$staff_query = "SELECT id, name FROM users WHERE user_type IN ('nurse', 'midwife') ORDER BY name";
$staff_result = $conn->query($staff_query);
$staff_members = [];
while ($staff = $staff_result->fetch_assoc()) {
    $staff_members[] = $staff;
}

// Fetch patient's immunization records
$immunizations_query = "SELECT i.*, v.name as vaccine_name, v.manufacturer, 
                       u.name as administered_by_name,
                       i.transaction_id,
                       i.transaction_number
                       FROM immunizations i 
                       JOIN vaccines v ON i.vaccine_id = v.id
                       JOIN users u ON i.administered_by = u.id
                       WHERE i.patient_id = ?
                       ORDER BY i.administered_date DESC";
$stmt = $conn->prepare($immunizations_query);
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$immunizations_result = $stmt->get_result();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Immunizations - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Reuse styles from admin_patients.php */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .dashboard-logo {
            display: flex;
            align-items: center;
        }
        
        .dashboard-logo img {
            height: 40px;
            margin-right: 10px;
        }
        
        .dashboard-logo h1 {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin: 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
        }
        
        .user-info {
            margin-right: 20px;
            text-align: right;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--text-color);
        }
        
        .user-role {
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .user-email {
            font-size: 0.9rem;
            color: var(--light-text);
        }
        
        .logout-btn {
            padding: 8px 15px;
            background-color: #f1f3f5;
            color: var(--text-color);
            border-radius: 5px;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            background-color: #e9ecef;
        }
        
        .dashboard-content {
            display: grid;
            grid-template-columns: 1fr 4fr;
            gap: 30px;
        }
        
        .sidebar {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-radius: var(--border-radius);
            color: var(--text-color);
            transition: var(--transition);
            text-decoration: none;
        }
        
        .sidebar-menu a:hover {
            background-color: #f1f8ff;
            color: var(--primary-color);
        }
        
        .sidebar-menu a.active {
            background-color: #e8f0fe;
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .sidebar-menu i {
            margin-right: 10px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 30px;
        }
        
        .page-title {
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .patient-info {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .patient-info h3 {
            color: var(--primary-color);
            margin: 0 0 15px 0;
        }
        
        .patient-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .patient-detail {
            margin-bottom: 10px;
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .detail-value {
            color: var(--light-text);
        }
        
        .btn-add {
            background-color: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .btn-add:hover {
            background-color: #3367d6;
        }
        
        .immunizations-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .immunizations-table th,
        .immunizations-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .immunizations-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-view,
        .btn-edit,
        .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-view {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .btn-view:hover {
            background-color: #c8e6c9;
        }
        
        .btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-edit:hover {
            background-color: #bbdefb;
        }
        
        .btn-delete {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .btn-delete:hover {
            background-color: #ffcdd2;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
        }
        
        @media screen and (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .user-menu {
                margin-top: 20px;
                align-self: flex-end;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        /* Button Styles */
        .btn-submit,
        .btn-cancel,
        .btn-add,
        .btn-view,
        .btn-edit,
        .btn-delete,
        .btn-back {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .btn-submit {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-submit:hover {
            background-color: #3367d6;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-cancel {
            background-color: #f1f3f5;
            color: var(--text-color);
        }

        .btn-cancel:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        .btn-add {
            background-color: #2e7d32;
            color: white;
        }

        .btn-add:hover {
            background-color: #1b5e20;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-view {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }

        .btn-view:hover {
            background-color: #c8e6c9;
            transform: translateY(-1px);
        }

        .btn-edit {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }

        .btn-edit:hover {
            background-color: #bbdefb;
            transform: translateY(-1px);
        }

        .btn-delete {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }

        .btn-delete:hover {
            background-color: #ffcdd2;
            transform: translateY(-1px);
        }

        .btn-back {
            background-color: #f1f3f5;
            color: var(--text-color);
        }

        .btn-back:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        /* Form Button Container */
        .form-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        /* Disabled Button States */
        .btn-submit:disabled,
        .btn-add:disabled,
        .btn-edit:disabled,
        .btn-delete:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Button Icon Alignment */
        .btn-submit i,
        .btn-cancel i,
        .btn-add i,
        .btn-view i,
        .btn-edit i,
        .btn-delete i,
        .btn-back i {
            font-size: 1rem;
        }

        /* Mobile Responsive Buttons */
        @media screen and (max-width: 576px) {
            .btn-submit,
            .btn-cancel,
            .btn-add,
            .btn-view,
            .btn-edit,
            .btn-delete,
            .btn-back {
                width: 100%;
                justify-content: center;
            }

            .form-buttons {
                flex-direction: column;
            }
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group select,
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="date"],
        .form-group input[type="datetime-local"],
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #dde2e5;
            border-radius: 6px;
            font-size: 0.95rem;
            color: var(--text-color);
            background-color: white;
            transition: all 0.2s ease;
        }

        .form-group select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .form-group select:focus,
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="date"]:focus,
        .form-group input[type="datetime-local"]:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
        }

        .form-group select:hover,
        .form-group input[type="text"]:hover,
        .form-group input[type="number"]:hover,
        .form-group input[type="date"]:hover,
        .form-group input[type="datetime-local"]:hover,
        .form-group textarea:hover {
            border-color: #b3b3b3;
        }

        .form-group select:disabled,
        .form-group input:disabled,
        .form-group textarea:disabled {
            background-color: #f8f9fa;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        /* Placeholder Styles */
        .form-group input::placeholder,
        .form-group textarea::placeholder {
            color: #adb5bd;
        }

        /* Error State */
        .form-group.error select,
        .form-group.error input,
        .form-group.error textarea {
            border-color: #dc3545;
        }

        .form-group.error .error-message {
            color: #dc3545;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        /* Success State */
        .form-group.success select,
        .form-group.success input,
        .form-group.success textarea {
            border-color: #28a745;
        }

        /* Required Field Indicator */
        .form-group label.required:after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-logo">
                <img src="images/logo.svg" alt="ImmuCare Logo">
                <h1>ImmuCare</h1>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                    <div class="user-role">Administrator</div>
                    <div class="user-email"><?php echo htmlspecialchars($admin_email); ?></div>
                </div>
                <a href="admin_dashboard.php?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <li><a href="admin_patients.php" class="active"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="page-title">
                    <h2>Patient Immunizations</h2>
                    <?php if ($patient): ?>
                        <a href="?action=add&patient_id=<?php echo $patient_id; ?>" class="btn-add">
                            <i class="fas fa-plus"></i> Add Immunization
                        </a>
                    <?php endif; ?>
                </div>
                
                <?php if ($patient): ?>
                    <div class="patient-info">
                        <h3>Patient Information</h3>
                        <div class="patient-details">
                            <div class="patient-detail">
                                <div class="detail-label">Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                            </div>
                            <div class="patient-detail">
                                <div class="detail-label">Age</div>
                                <div class="detail-value"><?php echo $patient['age']; ?> years</div>
                            </div>
                            <div class="patient-detail">
                                <div class="detail-label">Gender</div>
                                <div class="detail-value"><?php echo ucfirst($patient['gender']); ?></div>
                            </div>
                            <div class="patient-detail">
                                <div class="detail-label">Date of Birth</div>
                                <div class="detail-value"><?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($action == 'add'): ?>
                        <div class="immunization-form">
                            <form method="POST" action="?action=add&patient_id=<?php echo $patient_id; ?>">
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="vaccine_id">Vaccine</label>
                                        <select id="vaccine_id" name="vaccine_id" required>
                                            <option value="">-- Select Vaccine --</option>
                                            <?php foreach ($vaccines as $vaccine): ?>
                                                <option value="<?php echo $vaccine['id']; ?>">
                                                    <?php echo htmlspecialchars($vaccine['name']); ?> 
                                                    (<?php echo htmlspecialchars($vaccine['manufacturer']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="administered_by">Administered By</label>
                                        <select id="administered_by" name="administered_by" required>
                                            <option value="">-- Select Staff Member --</option>
                                            <?php foreach ($staff_members as $staff): ?>
                                                <option value="<?php echo $staff['id']; ?>">
                                                    <?php echo htmlspecialchars($staff['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dose_number">Dose Number</label>
                                        <input type="number" id="dose_number" name="dose_number" min="1" value="1" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="batch_number">Batch Number</label>
                                        <input type="text" id="batch_number" name="batch_number" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="expiration_date">Expiration Date</label>
                                        <input type="date" id="expiration_date" name="expiration_date" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="administered_date">Administered Date</label>
                                        <input type="datetime-local" id="administered_date" name="administered_date" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="next_dose_date">Next Dose Date (Optional)</label>
                                        <input type="date" id="next_dose_date" name="next_dose_date">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="diagnosis">Diagnosis (Optional)</label>
                                    <textarea id="diagnosis" name="diagnosis"></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="add_immunization" class="btn-submit">
                                        <i class="fas fa-save"></i> Add Immunization
                                    </button>
                                    <a href="?patient_id=<?php echo $patient_id; ?>" class="btn-cancel">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'edit' && $edit_immunization): ?>
                        <div class="immunization-form">
                            <form method="POST" action="?action=edit&id=<?php echo $edit_immunization['id']; ?>&patient_id=<?php echo $patient_id; ?>">
                                <input type="hidden" name="immunization_id" value="<?php echo $edit_immunization['id']; ?>">
                                
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="vaccine_id">Vaccine</label>
                                        <select id="vaccine_id" name="vaccine_id" required>
                                            <option value="">-- Select Vaccine --</option>
                                            <?php foreach ($vaccines as $vaccine): ?>
                                                <option value="<?php echo $vaccine['id']; ?>" <?php echo ($edit_immunization['vaccine_id'] == $vaccine['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($vaccine['name']); ?> 
                                                    (<?php echo htmlspecialchars($vaccine['manufacturer']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="administered_by">Administered By</label>
                                        <select id="administered_by" name="administered_by" required>
                                            <option value="">-- Select Staff Member --</option>
                                            <?php foreach ($staff_members as $staff): ?>
                                                <option value="<?php echo $staff['id']; ?>" <?php echo ($edit_immunization['administered_by'] == $staff['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($staff['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="dose_number">Dose Number</label>
                                        <input type="number" id="dose_number" name="dose_number" min="1" value="<?php echo $edit_immunization['dose_number']; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="batch_number">Batch Number</label>
                                        <input type="text" id="batch_number" name="batch_number" value="<?php echo htmlspecialchars($edit_immunization['batch_number']); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="expiration_date">Expiration Date</label>
                                        <input type="date" id="expiration_date" name="expiration_date" value="<?php echo $edit_immunization['expiration_date']; ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="administered_date">Administered Date</label>
                                        <input type="datetime-local" id="administered_date" name="administered_date" value="<?php echo date('Y-m-d\TH:i', strtotime($edit_immunization['administered_date'])); ?>" required>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="next_dose_date">Next Dose Date (Optional)</label>
                                        <input type="date" id="next_dose_date" name="next_dose_date" value="<?php echo $edit_immunization['next_dose_date']; ?>">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="location">Location</label>
                                        <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($edit_immunization['location']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="diagnosis">Diagnosis (Optional)</label>
                                    <textarea id="diagnosis" name="diagnosis"><?php echo htmlspecialchars($edit_immunization['diagnosis']); ?></textarea>
                                </div>
                                
                                <div class="form-buttons">
                                    <button type="submit" name="edit_immunization" class="btn-submit">
                                        <i class="fas fa-save"></i> Update Immunization
                                    </button>
                                    <a href="?patient_id=<?php echo $patient_id; ?>" class="btn-cancel">Cancel</a>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($action == 'view' && $view_immunization): ?>
                        <div class="immunization-details">
                            <div class="details-header">
                                <h3>Immunization Details</h3>
                                <div class="header-actions">
                                    <a href="?action=edit&id=<?php echo $view_immunization['id']; ?>&patient_id=<?php echo $patient_id; ?>" class="btn-edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?patient_id=<?php echo $patient_id; ?>" class="btn-back">
                                        <i class="fas fa-arrow-left"></i> Back to List
                                    </a>
                                </div>
                            </div>
                            
                            <div class="details-grid">
                                <div class="detail-group">
                                    <div class="detail-label">Vaccine</div>
                                    <div class="detail-value">
                                        <?php echo htmlspecialchars($view_immunization['vaccine_name']); ?>
                                        <div class="detail-sub">Manufacturer: <?php echo htmlspecialchars($view_immunization['manufacturer']); ?></div>
                                        <div class="detail-sub">Recommended Age: <?php echo htmlspecialchars($view_immunization['recommended_age']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="detail-group">
                                    <div class="detail-label">Dose Information</div>
                                    <div class="detail-value">
                                        Dose <?php echo $view_immunization['dose_number']; ?>
                                        <div class="detail-sub">Batch Number: <?php echo htmlspecialchars($view_immunization['batch_number']); ?></div>
                                        <div class="detail-sub">Expiration Date: <?php echo date('M d, Y', strtotime($view_immunization['expiration_date'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="detail-group">
                                    <div class="detail-label">Administration Details</div>
                                    <div class="detail-value">
                                        <div>Administered By: <?php echo htmlspecialchars($view_immunization['administered_by_name']); ?></div>
                                        <div>Date: <?php echo date('M d, Y h:i A', strtotime($view_immunization['administered_date'])); ?></div>
                                        <div>Location: <?php echo htmlspecialchars($view_immunization['location']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="detail-group">
                                    <div class="detail-label">Next Dose</div>
                                    <div class="detail-value">
                                        <?php if ($view_immunization['next_dose_date']): ?>
                                            <?php echo date('M d, Y', strtotime($view_immunization['next_dose_date'])); ?>
                                            <?php
                                            $next_dose = strtotime($view_immunization['next_dose_date']);
                                            $now = time();
                                            $days_until = ceil(($next_dose - $now) / (60 * 60 * 24));
                                            if ($days_until > 0) {
                                                echo "<div class='detail-sub'>($days_until days remaining)</div>";
                                            } else {
                                                echo "<div class='detail-sub status-overdue'>Overdue by " . abs($days_until) . " days</div>";
                                            }
                                            ?>
                                        <?php else: ?>
                                            <span class="status-complete">No next dose required</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($view_immunization['diagnosis']): ?>
                                    <div class="detail-group full-width">
                                        <div class="detail-label">Diagnosis</div>
                                        <div class="detail-value">
                                            <?php echo nl2br(htmlspecialchars($view_immunization['diagnosis'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <style>
                                .immunization-details {
                                    background-color: #f8f9fa;
                                    border-radius: var(--border-radius);
                                    padding: 25px;
                                    margin-bottom: 30px;
                                }
                                
                                .details-header {
                                    display: flex;
                                    justify-content: space-between;
                                    align-items: center;
                                    margin-bottom: 20px;
                                }
                                
                                .details-header h3 {
                                    color: var(--primary-color);
                                    margin: 0;
                                }
                                
                                .header-actions {
                                    display: flex;
                                    gap: 10px;
                                }
                                
                                .btn-back {
                                    background-color: #f1f3f5;
                                    color: var(--text-color);
                                    padding: 8px 15px;
                                    border-radius: 5px;
                                    text-decoration: none;
                                    font-size: 0.9rem;
                                    transition: var(--transition);
                                }
                                
                                .btn-back:hover {
                                    background-color: #e9ecef;
                                }
                                
                                .details-grid {
                                    display: grid;
                                    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                                    gap: 20px;
                                }
                                
                                .detail-group {
                                    background-color: white;
                                    padding: 15px;
                                    border-radius: var(--border-radius);
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
                                }
                                
                                .detail-group.full-width {
                                    grid-column: 1 / -1;
                                }
                                
                                .detail-label {
                                    font-weight: 500;
                                    color: var(--primary-color);
                                    margin-bottom: 10px;
                                }
                                
                                .detail-value {
                                    color: var(--text-color);
                                }
                                
                                .detail-sub {
                                    font-size: 0.9rem;
                                    color: var(--light-text);
                                    margin-top: 5px;
                                }
                                
                                .status-complete {
                                    color: #2e7d32;
                                    background-color: #e8f5e9;
                                    padding: 3px 8px;
                                    border-radius: 3px;
                                    font-size: 0.9rem;
                                }
                                
                                .status-overdue {
                                    color: #c62828;
                                    background-color: #ffebee;
                                    padding: 3px 8px;
                                    border-radius: 3px;
                                    font-size: 0.9rem;
                                }
                            </style>
                        </div>
                    <?php endif; ?>
                    
                    <div class="immunizations-list">
                        <table class="immunizations-table">
                            <thead>
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Dose</th>
                                    <th>Administered Date</th>
                                    <th>Administered By</th>
                                    <th>Next Dose Date</th>
                                    <th>Transaction Info</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($immunizations_result->num_rows > 0): ?>
                                    <?php while ($immunization = $immunizations_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($immunization['vaccine_name']); ?>
                                                <div style="font-size: 0.8rem; color: #666;">
                                                    Manufacturer: <?php echo htmlspecialchars($immunization['manufacturer']); ?>
                                                </div>
                                            </td>
                                            <td>Dose <?php echo $immunization['dose_number']; ?></td>
                                            <td><?php echo date('M d, Y', strtotime($immunization['administered_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($immunization['administered_by_name']); ?></td>
                                            <td>
                                                <?php if ($immunization['next_dose_date']): ?>
                                                    <?php echo date('M d, Y', strtotime($immunization['next_dose_date'])); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td class="transaction-info">
                                                <div class="small">
                                                    <div class="badge bg-primary mb-1"><?php echo TransactionHelper::formatTransactionNumber($immunization['transaction_number']); ?></div><br>
                                                    <div class="text-muted" style="font-size: 0.65rem;"><?php echo TransactionHelper::formatTransactionId($immunization['transaction_id']); ?></div>
                                                </div>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="?action=view&id=<?php echo $immunization['id']; ?>&patient_id=<?php echo $patient_id; ?>" class="btn-view">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="?action=edit&id=<?php echo $immunization['id']; ?>&patient_id=<?php echo $patient_id; ?>" class="btn-edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?action=delete&id=<?php echo $immunization['id']; ?>&patient_id=<?php echo $patient_id; ?>" 
                                                   class="btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this immunization record?');">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No immunization records found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        Patient not found. Please select a valid patient.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active menu item
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPath) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html> 