<?php
session_start();
require 'config.php';
require_once 'notification_system.php';
require_once 'transaction_helper.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Location: login.php');
    exit;
}

// Get nurse information
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Nurse';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If user name or email is missing from session, fetch from database
if (empty($user_name) || empty($user_email)) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        if (empty($user_name)) {
            $user_name = $user_data['name'];
            $_SESSION['user_name'] = $user_name;
        }
        if (empty($user_email)) {
            $user_email = $user_data['email'];
            $_SESSION['user_email'] = $user_email;
        }
    }
    $stmt->close();
}

// Initialize notification system
$notification_system = new NotificationSystem();

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process appointment actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Initialize session message if not exists
if (!isset($_SESSION['action_message'])) {
    $_SESSION['action_message'] = '';
}

// Get message from session if exists
if (!empty($_SESSION['action_message'])) {
    $action_message = $_SESSION['action_message'];
    // Clear the message after displaying
    $_SESSION['action_message'] = '';
}

// Update appointment status
if (isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Generate transaction data
    $transactionData = TransactionHelper::generateTransactionData($conn);
    
    // Get appointment and patient details
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name, 
               p.last_name, 
               p.phone_number as patient_phone,
               u.email,
               u.phone as user_phone,
               u.id as user_id,
               v.name as vaccine_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN vaccines v ON a.vaccine_id = v.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appointment_id);
    $stmt->execute();
    $appointment_result = $stmt->get_result();
    $appointment_data = $appointment_result->fetch_assoc();
    
    // Check if appointment exists
    if (!$appointment_data) {
        $_SESSION['action_message'] = "Error: Appointment not found.";
        
        // Redirect to prevent form resubmission
        $redirect_params = $_GET;
        unset($redirect_params['action']);
        unset($redirect_params['id']);
        
        $redirect_url = $_SERVER['PHP_SELF'];
        if (!empty($redirect_params)) {
            $redirect_url .= '?' . http_build_query($redirect_params);
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
    
    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointments SET status = ?, notes = ?, transaction_id = ?, transaction_number = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssssi", $status, $notes, $transactionData['transaction_id'], $transactionData['transaction_number'], $appointment_id);
    
    if ($stmt->execute()) {
        // Send notification using the notification system
        $patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
        $appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
        $purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
        
        // Create shorter, concise message for SMS compatibility
        $status_specific_message = "";
        switch($status) {
            case 'confirmed':
                $status_specific_message = "CONFIRMED. Please arrive 15 minutes early.";
                break;
            case 'completed':
                $status_specific_message = "COMPLETED. Thank you for visiting us.";
                break;
            case 'cancelled':
                $status_specific_message = "CANCELLED. You may reschedule anytime.";
                break;
            case 'no_show':
                $status_specific_message = "MISSED. Please contact us to reschedule.";
                break;
            default:
                $status_specific_message = "UPDATED. Thank you.";
        }
        
        // Short message format for SMS (removes long details and medical terms)
        $status_message = "IMMUCARE: Your appointment on " . $appointment_date . " at " . $appointment_time . " is " . $status_specific_message .
                         (!empty($notes) ? " Note: " . $notes : "");
        
        $notification_system->sendCustomNotification(
            $appointment_data['user_id'],
            "Appointment Status Update: " . ucfirst($status),
            $status_message,
            'both'
        );
        
        $_SESSION['action_message'] = "Appointment status updated successfully! Notifications sent via Email and SMS.";
        
        // Redirect to prevent form resubmission (remove action parameters)
        $redirect_params = $_GET;
        unset($redirect_params['action']);
        unset($redirect_params['id']);
        
        $redirect_url = $_SERVER['PHP_SELF'];
        if (!empty($redirect_params)) {
            $redirect_url .= '?' . http_build_query($redirect_params);
        }
        
        header('Location: ' . $redirect_url);
        exit;
    } else {
        $_SESSION['action_message'] = "Error updating appointment status: " . $conn->error;
        
        // Redirect to prevent form resubmission (remove action parameters)
        $redirect_params = $_GET;
        unset($redirect_params['action']);
        unset($redirect_params['id']);
        
        $redirect_url = $_SERVER['PHP_SELF'];
        if (!empty($redirect_params)) {
            $redirect_url .= '?' . http_build_query($redirect_params);
        }
        
        header('Location: ' . $redirect_url);
        exit;
    }
}

// Handle search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR a.purpose LIKE '%$search%')";
}



// Handle filters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$date_condition = '';
if (!empty($filter_date)) {
    $filter_date = $conn->real_escape_string($filter_date);
    $date_condition = " AND DATE(a.appointment_date) = '$filter_date'";
}

$status_condition = '';
if (!empty($filter_status)) {
    $filter_status = $conn->real_escape_string($filter_status);
    $status_condition = " AND a.status = '$filter_status'";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Base query for appointments - show all appointments
$base_query = "FROM appointments a 
               JOIN patients p ON a.patient_id = p.id 
               LEFT JOIN vaccines v ON a.vaccine_id = v.id 
               WHERE 1=1";

// Get total number of appointments
$count_query = "SELECT COUNT(*) as count $base_query $search_condition $date_condition $status_condition";
$stmt = $conn->prepare($count_query);
$stmt->execute();

$stmt->execute();
$result = $stmt->get_result();
$total_appointments = $result->fetch_assoc()['count'];
$total_pages = ceil($total_appointments / $records_per_page);

// Get appointments with pagination - show all appointments
$query = "SELECT a.*, p.first_name, p.last_name, v.name as vaccine_name,
          a.transaction_id, a.transaction_number
          $base_query $search_condition $date_condition $status_condition
          ORDER BY a.appointment_date DESC 
          LIMIT ?, ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $offset, $records_per_page);

$stmt->execute();
$appointments = $stmt->get_result();

// Process logout
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}



$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointments - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .dashboard-container {
            max-width: 1200px;
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
            text-decoration: none;
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
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-form {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 5px 15px;
            width: 300px;
        }
        
        .search-form input {
            border: none;
            background: transparent;
            padding: 8px;
            width: 100%;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-form input:focus {
            outline: none;
        }
        
        .search-form button {
            background: transparent;
            border: none;
            color: var(--light-text);
            cursor: pointer;
        }
        
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-form select, .filter-form input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .filter-form button {
            padding: 8px 15px;
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-form button:hover {
            background-color: #e9ecef;
        }
        
        .add-btn {
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .add-btn i {
            margin-right: 5px;
        }
        
        .add-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; background-color: #f8f9fa; padding: 15px; border-radius: var(--border-radius); }
        .filter-group { display: flex; align-items: center; gap: 8px; }
        .filter-group label { font-weight: 500; }
        .filter-group select, .filter-group input { padding: 8px; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .filter-btn { background-color: var(--primary-color); color: white; border: none; padding: 8px 15px; border-radius: var(--border-radius); cursor: pointer; transition: var(--transition); }
        .filter-btn:hover { background-color: #3367d6; }
        .reset-btn { background-color: #f1f3f5; color: var(--text-color); border: none; padding: 8px 15px; border-radius: var(--border-radius); cursor: pointer; transition: var(--transition); }
        .reset-btn:hover { background-color: #e9ecef; }
        .appointments-table { width: 100%; border-collapse: collapse; }
        .appointments-table th, .appointments-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .appointments-table th { font-weight: 600; color: var(--primary-color); background-color: #f8f9fa; }
        .appointment-status { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 500; }
        .status-scheduled { background-color: #e3f2fd; color: #1976d2; }
        .status-confirmed { background-color: #e8f5e9; color: #2e7d32; }
        .status-completed { background-color: #f3e5f5; color: #7b1fa2; }
        .status-cancelled { background-color: #ffebee; color: #c62828; }
        .status-no_show { background-color: #fafafa; color: #757575; }
        .status-requested { background-color: #e3f2fd; color: #1976d2; }
        .btn-edit { padding: 8px 12px; border-radius: 5px; font-size: 0.8rem; text-decoration: none; transition: var(--transition); border: none; cursor: pointer; background-color: #007bff; color: white; }
        .btn-edit:hover { background-color: #0056b3; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border-radius: var(--border-radius); box-shadow: var(--shadow); width: 50%; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-title { font-size: 1.4rem; color: var(--primary-color); margin: 0; }
        .close-btn { font-size: 1.5rem; cursor: pointer; }
        .modal-form { margin-top: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; }
        .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .form-group textarea { height: 100px; resize: vertical; }
        .form-buttons { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }
        .btn-cancel { background-color: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: var(--border-radius); cursor: pointer; transition: var(--transition); font-size: 0.9rem; }
        .btn-cancel:hover { background-color: #5a6268; }
        .btn-submit { background-color: #007bff; color: white; border: none; padding: 10px 20px; border-radius: var(--border-radius); cursor: pointer; transition: var(--transition); font-size: 0.9rem; }
        .btn-submit:hover { background-color: #0056b3; }
        
        .patient-name {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .appointment-time {
            white-space: nowrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-requested {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-no_show {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn, .edit-btn, .complete-btn, .cancel-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .view-btn {
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .view-btn:hover {
            background-color: #d0e3ff;
        }
        
        .edit-btn {
            background-color: #e9f9ef;
            color: #28a745;
        }
        
        .edit-btn:hover {
            background-color: #d1f2e0;
        }
        
        .complete-btn {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .complete-btn:hover {
            background-color: #ffeaa7;
        }
        
        .complete-btn {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .complete-btn:hover {
            background-color: #b8daff;
        }
        
        .cancel-btn {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .cancel-btn:hover {
            background-color: #f5c6cb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .pagination a {
            background-color: #f8f9fa;
            color: var(--text-color);
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination span {
            background-color: var(--primary-color);
            color: white;
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
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form, .filter-form {
                width: 100%;
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
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-role">Nurse</div>
                    <div class="user-email"><?php echo htmlspecialchars($user_email); ?></div>
                </div>
                <a href="?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="nurse_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="nurse_immunizations.php"><i class="fas fa-syringe"></i> Immunizations</a></li>
                    <li><a href="nurse_vaccine_inventory.php"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Manage Appointments</h2>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($action_message); ?>
                    </div>
                <?php endif; ?>
                
                                <form action="" method="GET" class="filter-bar">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select id="status" name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="requested" <?php echo ($filter_status == 'requested') ? 'selected' : ''; ?>>Requested</option>
                            <option value="confirmed" <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo ($filter_status == 'no_show') ? 'selected' : ''; ?>>No Show</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="filter_date" value="<?php echo $filter_date; ?>">
                    </div>
                    
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                    <a href="nurse_appointments.php" class="reset-btn"><i class="fas fa-redo"></i> Reset</a>
                </form>
                
                <div class="appointments-list">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Transaction Info</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments && $appointments->num_rows > 0): ?>
                                <?php while ($appointment = $appointments->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $appointment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                        <td>
                                            <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                                <?php echo $appointment['status'] == 'no_show' ? 'No Show' : ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="transaction-info">
                                            <div class="small">
                                                <div class="badge bg-primary mb-1"><?php echo TransactionHelper::formatTransactionNumber($appointment['transaction_number']); ?></div><br>
                                                <div class="text-muted" style="font-size: 0.65rem;"><?php echo TransactionHelper::formatTransactionId($appointment['transaction_id']); ?></div>
                                            </div>
                                        </td>
                                        <td class="action-buttons">
                                            <button type="button" class="btn-edit" onclick="openStatusModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>', '<?php echo htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES); ?>')">
                                                <i class="fas fa-edit"></i> Update Status
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center;">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Appointment Status</h3>
                <span class="close-btn" onclick="closeStatusModal()">&times;</span>
            </div>
            <form method="POST" action="" class="modal-form">
                <input type="hidden" id="appointment_id" name="appointment_id">
                
                <div class="form-group">
                    <label for="status">Status:</label>
                    <select id="modal_status" name="status" required>
                        <option value="requested">Requested</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="no_show">No-Show</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes:</label>
                    <textarea id="notes" name="notes"></textarea>
                </div>
                
                <div class="form-buttons">
                    <button type="button" onclick="closeStatusModal()" class="btn-cancel">Cancel</button>
                    <button type="submit" name="update_status" class="btn-submit">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <style>
        @media screen and (max-width: 992px) { 
            .dashboard-content { grid-template-columns: 1fr; } 
            .sidebar { margin-bottom: 20px; } 
            .modal-content { width: 70%; } 
        }
        @media screen and (max-width: 768px) { 
            .dashboard-header { flex-direction: column; align-items: flex-start; } 
            .user-menu { margin-top: 20px; align-self: flex-end; } 
            .filter-bar { flex-direction: column; align-items: flex-start; } 
            .filter-group { width: 100%; } 
            .action-buttons { flex-direction: column; } 
            .modal-content { width: 90%; } 
        }
    </style>

    <script>
        // Modal functionality
        const statusModal = document.getElementById('statusModal');
        
        function openStatusModal(id, status, notes) {
            document.getElementById('appointment_id').value = id;
            document.getElementById('modal_status').value = status;
            document.getElementById('notes').value = notes;
            statusModal.style.display = 'block';
        }
        
        function closeStatusModal() {
            statusModal.style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == statusModal) {
                closeStatusModal();
            }
        }
        
        // Highlight active menu item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPath) {
                    item.classList.add('active');
                } else if (item.classList.contains('active') && item.getAttribute('href') !== '#') {
                    item.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html> 