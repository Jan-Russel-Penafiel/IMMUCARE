<?php
session_start();
require 'config.php';
require_once 'vendor/autoload.php';
require_once 'notification_system.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin';
$admin_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Initialize notification system
$notification_system = new NotificationSystem();

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If admin name or email is missing from session, fetch from database
if (empty($admin_name) || empty($admin_email)) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        if (empty($admin_name)) {
            $admin_name = $user_data['name'];
            $_SESSION['user_name'] = $admin_name; // Update session
        }
        if (empty($admin_email)) {
            $admin_email = $user_data['email'];
            $_SESSION['user_email'] = $admin_email; // Update session
        }
    }
    $stmt->close();
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process patient actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Check for success message in session
if (isset($_SESSION['action_message'])) {
    $action_message = $_SESSION['action_message'];
    unset($_SESSION['action_message']); // Clear the message after displaying
}

// Add new patient
if ($action == 'add' && isset($_POST['add_patient'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $first_name = $_POST['first_name'];
        $middle_name = $_POST['middle_name'] ?? null;
        $last_name = $_POST['last_name'];
        $date_of_birth = $_POST['date_of_birth'];
        $gender = $_POST['gender'];
        $blood_type = $_POST['blood_type'] ?? null;
        $purok = $_POST['purok'];
        $city = $_POST['city'];
        $province = $_POST['province'];
        $postal_code = $_POST['postal_code'] ?? null;
        $phone_number = $_POST['phone_number'];
        $medical_history = isset($_POST['medical_history']) ? $_POST['medical_history'] : null;
        $allergies = isset($_POST['allergies']) ? $_POST['allergies'] : null;

        // Get user_id from form if linking to existing user
        $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
        $email_sent = false;
        $user_email = '';
        
        // Create new user account if requested
        if (isset($_POST['create_account']) && $_POST['create_account'] == 'on') {
            $user_email = $_POST['user_email'];
            $user_phone = $_POST['user_phone'];
            $user_name = $first_name . ' ' . $last_name;
            
            // Check if email already exists
            $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_email->bind_param("s", $user_email);
            $check_email->execute();
            $email_result = $check_email->get_result();
            
            if ($email_result->num_rows > 0) {
                throw new Exception("Email address already in use. Please use a different email.");
            }
            
            // Generate a random password
            $plainPassword = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
            
            // Insert new user
            $user_stmt = $conn->prepare("INSERT INTO users (role_id, user_type, name, email, phone, password, created_at) VALUES (4, 'patient', ?, ?, ?, ?, NOW())");
            $user_stmt->bind_param("ssss", $user_name, $user_email, $user_phone, $hashedPassword);
            
            if (!$user_stmt->execute()) {
                throw new Exception("Error creating user account: " . $conn->error);
            }
            
            $user_id = $conn->insert_id;

            // Send unified notification for new user account and patient profile
            $notification_system->sendPatientAccountNotification(
                $user_id,
                'created',
                [
                    'password' => $plainPassword,
                    'patient_details' => [
                        'first_name' => $first_name,
                        'middle_name' => $middle_name,
                        'last_name' => $last_name,
                        'date_of_birth' => $date_of_birth,
                        'gender' => $gender,
                        'phone_number' => $phone_number,
                        'purok' => $purok,
                        'city' => $city,
                        'province' => $province,
                        'medical_history' => $medical_history,
                        'allergies' => $allergies
                    ]
                ]
            );
        } elseif (!empty($user_id)) {
            // If linking to existing user, get their email
            $get_user = $conn->prepare("SELECT email, phone, name FROM users WHERE id = ?");
            $get_user->bind_param("i", $user_id);
            $get_user->execute();
            $user_result = $get_user->get_result();
            $user_data = $user_result->fetch_assoc();
            $user_email = $user_data['email'];
            $user_phone = $user_data['phone'];
            $user_name = $user_data['name'];

            // Send unified notification about linking to existing account
            $notification_system->sendPatientAccountNotification(
                $user_id,
                'created',
                [
                    'is_linking' => true,
                    'patient_details' => [
                        'first_name' => $first_name,
                        'middle_name' => $middle_name,
                        'last_name' => $last_name,
                        'date_of_birth' => $date_of_birth,
                        'gender' => $gender,
                        'phone_number' => $phone_number,
                        'purok' => $purok,
                        'city' => $city,
                        'province' => $province,
                        'medical_history' => $medical_history,
                        'allergies' => $allergies
                    ]
                ]
            );
        }
        
        // Insert patient record
        $stmt = $conn->prepare("INSERT INTO patients (user_id, first_name, middle_name, last_name, date_of_birth, gender, blood_type, purok, city, province, postal_code, phone_number, medical_history, allergies, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("isssssssssssss", $user_id, $first_name, $middle_name, $last_name, $date_of_birth, $gender, $blood_type, $purok, $city, $province, $postal_code, $phone_number, $medical_history, $allergies);
    
        if (!$stmt->execute()) {
            throw new Exception("Error adding patient: " . $conn->error);
        }
        
        $patient_id = $conn->insert_id;
        
        // Commit transaction
        $conn->commit();
        
        // Store success message in session
        $_SESSION['action_message'] = "Patient added successfully! " . 
            (!empty($user_email) ? "A notification has been sent via SMS and Email." : "");
        
        // Redirect to prevent form resubmission
        header("Location: admin_patients.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['action_message'] = "Error: " . $e->getMessage();
        header("Location: admin_patients.php?action=add");
        exit;
    }
}

// Edit patient
if ($action == 'edit' && isset($_POST['edit_patient'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
    $patient_id = $_POST['patient_id'];
    $first_name = $_POST['first_name'];
    $middle_name = $_POST['middle_name'] ?? null;
    $last_name = $_POST['last_name'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $blood_type = $_POST['blood_type'] ?? null;
    $purok = $_POST['purok'];
    $city = $_POST['city'];
    $province = $_POST['province'];
    $postal_code = $_POST['postal_code'] ?? null;
    $phone_number = $_POST['phone_number'];
    $medical_history = isset($_POST['medical_history']) ? $_POST['medical_history'] : null;
    $allergies = isset($_POST['allergies']) ? $_POST['allergies'] : null;

    // Get user_id from form if linking to existing user
    $user_id = !empty($_POST['user_id']) ? $_POST['user_id'] : null;
    $email_sent = false;
    $user_email = '';
    $original_user_id = null;
    
    // Check if the patient already has a user account
    $check_stmt = $conn->prepare("SELECT user_id FROM patients WHERE id = ?");
    $check_stmt->bind_param("i", $patient_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    if ($check_result->num_rows > 0) {
        $original_user_id = $check_result->fetch_assoc()['user_id'];
    }
    
    // Create new user account if requested
    if (isset($_POST['create_account']) && $_POST['create_account'] == 'on') {
        $user_email = $_POST['user_email'];
        $user_phone = $_POST['user_phone'];
        $user_name = $first_name . ' ' . $last_name;
        
        // Check if email already exists
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $user_email);
        $check_email->execute();
        $email_result = $check_email->get_result();
        
        if ($email_result->num_rows > 0) {
                throw new Exception("Error: Email address already in use. Please use a different email.");
            }
            
            // Generate a random password
            $plainPassword = substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 10);
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);
            
            // Insert new user
            $user_stmt = $conn->prepare("INSERT INTO users (role_id, user_type, name, email, phone, password, created_at) VALUES (4, 'patient', ?, ?, ?, ?, NOW())");
            $user_stmt->bind_param("ssss", $user_name, $user_email, $user_phone, $hashedPassword);
            
            if (!$user_stmt->execute()) {
                throw new Exception("Error creating user account: " . $conn->error);
            }
            
            $user_id = $conn->insert_id;
        }
        
        // Insert patient record
        $query = "UPDATE patients SET user_id = ?, first_name = ?, middle_name = ?, last_name = ?, date_of_birth = ?, gender = ?, blood_type = ?, purok = ?, city = ?, province = ?, postal_code = ?, phone_number = ?, medical_history = ?, allergies = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("isssssssssssssi", $user_id, $first_name, $middle_name, $last_name, $date_of_birth, $gender, $blood_type, $purok, $city, $province, $postal_code, $phone_number, $medical_history, $allergies, $patient_id);
    
        if (!$stmt->execute()) {
            throw new Exception("Error updating patient: " . $conn->error);
        }
        
            // If we have a user email but haven't sent an email yet (for existing user accounts)
            // Only send email if user_id has changed or a new user account was created
            if (!empty($user_email) && !$email_sent && (!empty($user_id) && $user_id != $original_user_id)) {
                $update_message = "Your ImmuCare patient profile has been updated.\n\n" .
                                 "Updated Information:\n" .
                                 "- Full Name: " . $first_name . " " . ($middle_name ? $middle_name . " " : "") . $last_name . "\n" .
                                 "- Date of Birth: " . date('F j, Y', strtotime($date_of_birth)) . "\n" .
                                 "- Gender: " . ucfirst($gender) . "\n" .
                                 "- Contact: " . $phone_number . "\n" .
                                 "- Address: Purok " . $purok . ", " . $city . ", " . $province . "\n\n" .
                                 ($medical_history ? "- Medical History has been updated\n" : "") .
                                 ($allergies ? "- Allergy information has been updated\n" : "") .
                                 "\nPlease review these changes and contact us immediately if you notice any discrepancies.";
                
                $notification_system->sendCustomNotification(
                    $user_id,
                    "Patient Profile Updated",
                    $update_message,
                    'both'
                );
            }
        
        // Commit transaction
        $conn->commit();
        
        // Store success message in session
        $_SESSION['action_message'] = "Patient updated successfully! " . 
            (!empty($user_email) ? "Notifications have been sent via SMS and Email to " . $user_email : "");
        
        // Redirect to prevent form resubmission
        header("Location: admin_patients.php");
        exit;
                } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['action_message'] = "Error: " . $e->getMessage();
        header("Location: admin_patients.php?action=edit&id=" . $patient_id);
        exit;
    }
}

// Delete patient
if ($action == 'delete' && isset($_GET['id'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $patient_id = $_GET['id'];
        
        // Check if patient has immunization records
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE patient_id = ?");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $immunization_count = $result->fetch_assoc()['count'];
        
        if ($immunization_count > 0) {
            throw new Exception("Cannot delete patient with existing immunization records. Please archive instead.");
        }
        
        // Get patient and user information before deletion
        $get_patient = $conn->prepare("SELECT p.*, u.id as user_id, u.email, u.phone FROM patients p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $get_patient->bind_param("i", $patient_id);
        $get_patient->execute();
        $patient_data = $get_patient->get_result()->fetch_assoc();
        
        // If patient has a user account, ask for confirmation before deletion
        if ($patient_data && $patient_data['user_id']) {
            if (!isset($_GET['confirm_user_delete'])) {
                $_SESSION['action_message'] = "This patient has an associated user account. <a href='?action=delete&id=" . $patient_id . "&confirm_user_delete=1' class='btn-delete' style='margin-left: 10px;'>Click here</a> to delete both the patient record and user account.";
                header("Location: admin_patients.php");
                exit;
            }
            
            // Note: Deletion notifications are disabled as per system policy
            // try {
            //     // Notification logic removed - deletions do not send notifications
            // } catch (Exception $e) {
            //     // Log notification error but continue with deletion
            //     error_log("Failed to send deletion notification: " . $e->getMessage());
            // }
            
            // Delete user account if confirmed
            if (isset($_GET['confirm_user_delete'])) {
                $delete_user = $conn->prepare("DELETE FROM users WHERE id = ?");
                $delete_user->bind_param("i", $patient_data['user_id']);
                if (!$delete_user->execute()) {
                    throw new Exception("Error deleting associated user account: " . $conn->error);
                }
            }
        }
        
        // Delete patient record
        $stmt = $conn->prepare("DELETE FROM patients WHERE id = ?");
        $stmt->bind_param("i", $patient_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error deleting patient: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Store success message in session
        $_SESSION['action_message'] = "Patient" . (isset($_GET['confirm_user_delete']) ? " and associated user account" : "") . " deleted successfully!";
        
        // Redirect
        header("Location: admin_patients.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $_SESSION['action_message'] = "Error: " . $e->getMessage();
        header("Location: admin_patients.php");
        exit;
    }
}

// Fetch health centers for dropdown
$health_centers_array = [];

// Get pre-selected user from URL if coming from admin_users.php
$preselected_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : null;
$preselected_user = null;

if ($preselected_user_id) {
    // Fetch the user details to pre-populate form
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $preselected_user_id);
    $user_stmt->execute();
    $preselected_user = $user_stmt->get_result()->fetch_assoc();
}

// Fetch existing users who don't have a patient record yet
$users_query = "SELECT u.* FROM users u 
               LEFT JOIN patients p ON u.id = p.user_id
               WHERE u.user_type = 'patient' AND p.id IS NULL
               ORDER BY u.name";
$users_result = $conn->query($users_query);

// Fetch patients list with user information
$patients_query = "SELECT p.*, 
                  TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                  u.name as user_name, u.email as user_email
                  FROM patients p 
                  LEFT JOIN users u ON p.user_id = u.id
                  ORDER BY p.last_name, p.first_name";
$patients_result = $conn->query($patients_query);

// Get patient data if editing
$edit_patient = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $patient_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT p.*, u.id as user_id, u.name as user_name, u.email as user_email 
                           FROM patients p 
                           LEFT JOIN users u ON p.user_id = u.id
                           WHERE p.id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $edit_patient = $stmt->get_result()->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Management - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .patient-form {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-buttons {
            margin-top: 20px;
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-submit:hover {
            background-color: #3367d6;
        }
        
        .btn-cancel {
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            margin-left: 10px;
            transition: var(--transition);
        }
        
        .btn-cancel:hover {
            background-color: #e9ecef;
        }
        
        .patients-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patients-table th,
        .patients-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .patients-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .gender-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .gender-male {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .gender-female {
            background-color: #fce4ec;
            color: #c2185b;
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
        
        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-bar input {
            flex: 1;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .search-bar button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .search-bar button:hover {
            background-color: #3367d6;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
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
                    <h2><?php echo $action == 'edit' ? 'Edit Patient' : ($action == 'add' ? 'Add New Patient' : 'Patient Management'); ?></h2>
                    <?php if ($action == ''): ?>
                        <a href="?action=add" class="btn-add" style="display: none;"><i class="fas fa-user-plus"></i> Add New Patient</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $action_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'edit'): ?>
                    <div class="patient-form">
                        <form method="POST" action="?action=edit&id=<?php echo $edit_patient['id']; ?>">
                            <input type="hidden" name="patient_id" value="<?php echo $edit_patient['id']; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="user_id">Link to User Account (Optional)</label>
                                    <select id="user_id" name="user_id">
                                        <option value="">-- No User Account --</option>
                                        <?php while ($user = $users_result->fetch_assoc()): ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                <?php echo ($preselected_user_id && $preselected_user_id == $user['id']) ? 'selected' : 
                                                    (($action == 'edit' && $edit_patient['user_id'] == $user['id']) ? 'selected' : ''); ?>>
                                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="create_account">Create New User Account</label>
                                    <div style="display: flex; align-items: center; margin-top: 10px;">
                                        <input type="checkbox" id="create_account" name="create_account" style="width: auto; margin-right: 10px;" 
                                            <?php echo ($preselected_user_id) ? 'disabled' : ''; ?>>
                                        <label for="create_account" style="margin-bottom: 0;">Create user account for this patient</label>
                                    </div>
                                </div>
                                
                                <div class="form-group user-account-field" style="display: none;">
                                    <label for="user_email">Email Address</label>
                                    <input type="email" id="user_email" name="user_email" value="">
                                </div>
                                
                                <div class="form-group user-account-field" style="display: none;">
                                    <label for="user_phone">Phone (for login)</label>
                                    <input type="text" id="user_phone" name="user_phone" value="">
                                </div>
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" 
                                        value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['first_name']) : 
                                            ($preselected_user ? explode(' ', $preselected_user['name'])[0] : ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="middle_name">Middle Name (Optional)</label>
                                    <input type="text" id="middle_name" name="middle_name" 
                                        value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['middle_name']) : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" 
                                        value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['last_name']) : 
                                            ($preselected_user ? (strpos($preselected_user['name'], ' ') !== false ? 
                                                substr($preselected_user['name'], strpos($preselected_user['name'], ' ') + 1) : '') : ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth">Birth Date</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['date_of_birth']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" required>
                                        <option value="">-- Select Gender --</option>
                                        <option value="male" <?php echo ($action == 'edit' && $edit_patient['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                        <option value="female" <?php echo ($action == 'edit' && $edit_patient['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                        <option value="other" <?php echo ($action == 'edit' && $edit_patient['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="blood_type">Blood Type</label>
                                    <select id="blood_type" name="blood_type">
                                        <option value="">-- Select Blood Type --</option>
                                        <option value="A+" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'A+') ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'A-') ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'B+') ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'B-') ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'AB+') ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'AB-') ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'O+') ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($action == 'edit' && $edit_patient['blood_type'] == 'O-') ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="text" id="phone_number" name="phone_number" 
                                        value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['phone_number']) : 
                                            ($preselected_user ? $preselected_user['phone'] : ''); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="purok">Purok</label>
                                    <input type="text" id="purok" name="purok" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['purok']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <input type="text" id="city" name="city" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['city']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['province']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_patient['postal_code']) : ''; ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="medical_history">Medical History (Optional)</label>
                                <textarea id="medical_history" name="medical_history"><?php echo $action == 'edit' ? htmlspecialchars($edit_patient['medical_history']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="allergies">Allergies (Optional)</label>
                                <textarea id="allergies" name="allergies"><?php echo $action == 'edit' ? htmlspecialchars($edit_patient['allergies']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="edit_patient" class="btn-submit">
                                    <i class="fas fa-save"></i> Update Patient
                                </button>
                                <a href="admin_patients.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php elseif ($action == 'add'): ?>
                    <div class="patient-form">
                        <h3>Add New Patient</h3>
                        <?php if ($preselected_user): ?>
                            <div class="alert" style="background-color: #e3f2fd; color: #1976d2; border: 1px solid #bbdefb;">
                                <i class="fas fa-info-circle"></i> Creating patient profile for user: <strong><?php echo htmlspecialchars($preselected_user['name']); ?></strong> (<?php echo htmlspecialchars($preselected_user['email']); ?>)
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="?action=add">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="user_id">Link to User Account (Optional)</label>
                                    <select id="user_id" name="user_id" <?php echo ($preselected_user_id) ? 'disabled' : ''; ?>>
                                        <option value="">-- No User Account --</option>
                                        <?php 
                                        // Reset the result pointer
                                        $users_result->data_seek(0);
                                        while ($user = $users_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $user['id']; ?>" 
                                                <?php echo ($preselected_user_id && $preselected_user_id == $user['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($user['name'] . ' (' . $user['email'] . ')'); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                    <?php if ($preselected_user_id): ?>
                                        <input type="hidden" name="user_id" value="<?php echo $preselected_user_id; ?>">
                                    <?php endif; ?>
                                </div>
                                
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="create_account" name="create_account" 
                                            <?php echo ($preselected_user_id) ? 'disabled' : ''; ?>>
                                        Create New User Account
                                    </label>
                                </div>
                                
                                <div class="form-group user-account-field" style="display: none;">
                                    <label for="user_email">User Email</label>
                                    <input type="email" id="user_email" name="user_email">
                                </div>
                                
                                <div class="form-group user-account-field" style="display: none;">
                                    <label for="user_phone">User Phone</label>
                                    <input type="text" id="user_phone" name="user_phone">
                                </div>
                                
                                <div class="form-group">
                                    <label for="first_name">First Name</label>
                                    <input type="text" id="first_name" name="first_name" value="<?php echo $action == 'add' ? 
                                        ($preselected_user ? explode(' ', $preselected_user['name'])[0] : '') : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name">
                                </div>
                                
                                <div class="form-group">
                                    <label for="last_name">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" value="<?php echo $action == 'add' ? 
                                        ($preselected_user ? (strpos($preselected_user['name'], ' ') !== false ? 
                                            substr($preselected_user['name'], strpos($preselected_user['name'], ' ') + 1) : '') : '') : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender" required>
                                        <option value="">-- Select Gender --</option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="blood_type">Blood Type</label>
                                    <select id="blood_type" name="blood_type">
                                        <option value="">-- Select Blood Type --</option>
                                        <option value="A+">A+</option>
                                        <option value="A-">A-</option>
                                        <option value="B+">B+</option>
                                        <option value="B-">B-</option>
                                        <option value="AB+">AB+</option>
                                        <option value="AB-">AB-</option>
                                        <option value="O+">O+</option>
                                        <option value="O-">O-</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone_number">Phone Number</label>
                                    <input type="text" id="phone_number" name="phone_number" value="<?php echo $action == 'add' ? 
                                        ($preselected_user ? $preselected_user['phone'] : '') : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="purok">Purok/Subdivision</label>
                                    <input type="text" id="purok" name="purok" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="city">City/Municipality</label>
                                    <input type="text" id="city" name="city" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="postal_code">Postal Code</label>
                                    <input type="text" id="postal_code" name="postal_code">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="medical_history">Medical History (Optional)</label>
                                <textarea id="medical_history" name="medical_history"></textarea>
                            </div>
                            
                            <div class="form-group">
                                <label for="allergies">Allergies (Optional)</label>
                                <textarea id="allergies" name="allergies"></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="add_patient" class="btn-submit">
                                    <i class="fas fa-save"></i> Add Patient
                                </button>
                                <a href="admin_patients.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="search-bar">
                        <input type="text" id="patient-search" placeholder="Search patients by name, ID, or parent name...">
                        <button type="button"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <div class="patients-list">
                        <?php $counter = 0; // Initialize counter ?>
                        <table class="patients-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Phone</th>
                                    <th>City</th>
                                    <th>Province</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($patients_result->num_rows > 0): ?>
                                    <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo ++$counter; ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['middle_name'] . ' ' . $patient['last_name']); ?>
                                                <?php if (!empty($patient['user_name'])): ?>
                                                    <div style="font-size: 0.6rem; color: #666;">
                                                        <i class="fas fa-user"></i> User Account: <?php echo htmlspecialchars($patient['user_email']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo $patient['age']; ?> years</td>
                                            <td>
                                                <span class="gender-badge gender-<?php echo $patient['gender']; ?>">
                                                    <?php echo ucfirst($patient['gender']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($patient['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['city']); ?></td>
                                            <td><?php echo htmlspecialchars($patient['province']); ?></td>
                                            <td class="action-buttons">
                                                <a href="admin_immunizations.php?patient_id=<?php echo $patient['id']; ?>" class="btn-view"><i class="fas fa-syringe"></i> Immunizations</a>
                                                <a href="?action=edit&id=<?php echo $patient['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="?action=delete&id=<?php echo $patient['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this patient?');"><i class="fas fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center;">No patients found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
                } else if (item.classList.contains('active') && item.getAttribute('href') !== '#') {
                    item.classList.remove('active');
                }
            });
            
            // Simple patient search functionality
            const searchInput = document.getElementById('patient-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.patients-table tbody tr');
                    
                    rows.forEach(row => {
                        const textContent = row.textContent.toLowerCase();
                        if (textContent.includes(searchValue)) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    });
                });
            }
            
            // Toggle user account creation fields
            const createAccountCheckbox = document.getElementById('create_account');
            const userAccountFields = document.querySelectorAll('.user-account-field');
            const existingUserDropdown = document.getElementById('user_id');
            
            if (createAccountCheckbox) {
                createAccountCheckbox.addEventListener('change', function() {
                    userAccountFields.forEach(field => {
                        field.style.display = this.checked ? 'block' : 'none';
                    });
                    
                    if (this.checked) {
                        existingUserDropdown.value = '';
                        existingUserDropdown.disabled = true;
                    } else {
                        existingUserDropdown.disabled = false;
                    }
                });
                
                // Also disable user selection when dropdown changes
                if (existingUserDropdown) {
                    existingUserDropdown.addEventListener('change', function() {
                        if (this.value !== '') {
                            createAccountCheckbox.checked = false;
                            createAccountCheckbox.disabled = true;
                            userAccountFields.forEach(field => {
                                field.style.display = 'none';
                            });
                        } else {
                            createAccountCheckbox.disabled = false;
                        }
                    });
                    
                    // Check if a user is pre-selected from URL
                    if (existingUserDropdown.options.length > 0) {
                        // If there's a selected option other than the blank one
                        const hasSelectedOption = Array.from(existingUserDropdown.options).some(option => 
                            option.value !== '' && option.selected);
                        
                        if (hasSelectedOption) {
                            createAccountCheckbox.checked = false;
                            createAccountCheckbox.disabled = true;
                            userAccountFields.forEach(field => {
                                field.style.display = 'none';
                            });
                            
                            // Show a notification message that this user was referred from the user management
                            const formElement = document.querySelector('.patient-form');
                            if (formElement) {
                                const notificationDiv = document.createElement('div');
                                notificationDiv.className = 'alert alert-success';
                                notificationDiv.style.marginBottom = '20px';
                                notificationDiv.innerHTML = '<i class="fas fa-info-circle"></i> This form has been pre-filled with information from the selected user account.';
                                formElement.insertBefore(notificationDiv, formElement.firstChild);
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html> 