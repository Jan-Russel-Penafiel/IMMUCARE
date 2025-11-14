<?php
session_start();
require 'config.php';
require_once 'notification_system.php';

// Generate or get notification form token
if (!isset($_SESSION['notification_form_token'])) {
    $_SESSION['notification_form_token'] = bin2hex(random_bytes(32));
}

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Location: login.php');
    exit;
}

// Get nurse information
$nurse_id = $_SESSION['user_id'];
$nurse_name = $_SESSION['user_name'];
$nurse_email = $_SESSION['user_email'];

// Initialize notification system
$notification_system = new NotificationSystem();

// Initialize arrays for users
$grouped_users = [
    'patient' => [],
    'midwife' => [],
    'nurse' => []
];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get users query with proper error handling
try {
    $users_query = "
        SELECT 
            u.id,
            u.name,
            u.user_type,
            CASE 
                WHEN u.user_type = 'patient' THEN CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, ''))
                ELSE u.name
            END as display_name
        FROM users u
        LEFT JOIN patients p ON u.id = p.user_id
        WHERE u.id != ? 
        AND u.user_type IN ('patient')
        AND u.is_active = 1
        ORDER BY u.user_type, display_name";

    if ($users_stmt = $conn->prepare($users_query)) {
        $users_stmt->bind_param("i", $nurse_id);
        $users_stmt->execute();
        $users_result = $users_stmt->get_result();

        // Group users by type
        while ($user = $users_result->fetch_assoc()) {
            // Ensure display name is not empty
            if (empty(trim($user['display_name']))) {
                $user['display_name'] = $user['name'];
            }
            
            // Only add to group if it's a valid user type
            if (isset($grouped_users[$user['user_type']])) {
                $grouped_users[$user['user_type']][] = $user;
            }
        }
        
        $users_stmt->close();
    } else {
        throw new Exception("Failed to prepare user query: " . $conn->error);
    }
} catch (Exception $e) {
    // Log the error and initialize empty groups
    error_log("Error fetching users: " . $e->getMessage());
    $grouped_users = [
        'patient' => [],
        'midwife' => [],
        'nurse' => []
    ];
}

// Process notification actions
$action_message = '';
$action_type = 'success';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Check for flash messages
if (isset($_SESSION['notification_message'])) {
    $action_message = $_SESSION['notification_message'];
    $action_type = $_SESSION['notification_type'] ?? 'success';
    unset($_SESSION['notification_message'], $_SESSION['notification_type']);
}

// Send notification
if ($action == 'send' && isset($_POST['send_notification'])) {
    // Verify form token
    if (!isset($_POST['form_token']) || $_POST['form_token'] !== $_SESSION['notification_form_token']) {
        $action_message = "Invalid form submission.";
        $action_type = 'error';
    } else {
        // Validate required fields
        $required_fields = ['title_type', 'message', 'notification_type'];
        $errors = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
            }
        }
        
        // Validate recipients
        if (empty($_POST['recipients']) && empty($_POST['recipient_groups'])) {
            $errors[] = 'Please select at least one recipient or group.';
        }
        
        // Validate title based on title type
        if ($_POST['title_type'] === 'custom' && empty($_POST['title'])) {
            $errors[] = 'Custom title is required when selecting Custom Title option.';
        }
        
        if (empty($errors)) {
            $title = $_POST['title_type'] === 'custom' ? trim($_POST['title']) : trim($_POST['title_type']);
            $message = trim($_POST['message']);
            $type = $_POST['notification_type'];
            
            // Get all selected recipients
            $recipients = [];
            
            // Individual recipients
            if (!empty($_POST['recipients'])) {
                $recipients = array_merge($recipients, $_POST['recipients']);
            }
            
            // Group recipients
            if (!empty($_POST['recipient_groups'])) {
                foreach ($_POST['recipient_groups'] as $group) {
                    if (isset($grouped_users[$group])) {
                        foreach ($grouped_users[$group] as $user) {
                            $recipients[] = $user['id'];
                        }
                    }
                }
            }
            
            // Remove duplicates
            $recipients = array_unique($recipients);
            
            // Send to all recipients
            $success_count = 0;
            $failed_count = 0;
            $email_sent = 0;
            $sms_sent = 0;
            
            foreach ($recipients as $recipient_id) {
                if ($type === 'email_sms') {
                    // Send both email and SMS
                    $result_email = $notification_system->sendCustomNotification($recipient_id, $title, $message, 'email');
                    $result_sms = $notification_system->sendCustomNotification($recipient_id, $title, $message, 'sms');
                    
                    if ($result_email) {
                        $email_sent++;
                    }
                    if ($result_sms) {
                        $sms_sent++;
                    }
                    
                    if ($result_email || $result_sms) {
                        $success_count++;
                    } else {
                        $failed_count++;
                    }
                } else {
                    $result = $notification_system->sendCustomNotification($recipient_id, $title, $message, $type);
                    
                    if ($result) {
                        $success_count++;
                        // Count specific delivery types for display
                        if ($type === 'email') {
                            $email_sent++;
                        } elseif ($type === 'sms') {
                            $sms_sent++;
                        }
                    } else {
                        $failed_count++;
                    }
                }
            }
            
            if ($success_count > 0) {
                // Generate new token for next submission
                $_SESSION['notification_form_token'] = bin2hex(random_bytes(32));
                
                // Store success message in session
                $_SESSION['notification_message'] = "Notification sent successfully to {$success_count} recipient(s).";
                if ($type === 'email_sms') {
                    $_SESSION['notification_message'] .= " (Email: {$email_sent}, SMS: {$sms_sent})";
                }
                if ($failed_count > 0) {
                    $_SESSION['notification_message'] .= " Failed to send to {$failed_count} recipient(s).";
                    $_SESSION['notification_type'] = 'warning';
                } else {
                    $_SESSION['notification_type'] = 'success';
                }
                
                // Redirect to prevent resubmission
                header('Location: nurse_notifications.php');
                exit;
            } else {
                $action_message = "Error sending notifications. Please try again.";
                $action_type = 'error';
            }
        } else {
            $action_message = implode('<br>', $errors);
            $action_type = 'error';
        }
    }
}

// Get recent notifications - show all notifications
$notifications_query = "SELECT n.*, u.name as user_name, u.email as user_email
                       FROM notifications n 
                       LEFT JOIN users u ON n.user_id = u.id 
                       ORDER BY n.created_at DESC
                       LIMIT 50";
$notifications_result = $conn->query($notifications_query);

// Close the connection
$conn->close();

// Function to safely count group members
function getGroupCount($group, $grouped_users) {
    return isset($grouped_users[$group]) ? count($grouped_users[$group]) : 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4285f4;
            --secondary-color: #34a853;
            --accent-color: #fbbc05;
            --text-color: #333;
            --light-text: #666;
            --bg-light: #f9f9f9;
            --bg-white: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }

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

        .alert-warning {
            background-color: #fff3e0;
            color: #ef6c00;
            border: 1px solid #ffe0b2;
        }
        
        .notification-form {
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .form-section {
            padding: 25px;
            border-bottom: 1px solid #eee;
        }
        
        .form-section:last-child {
            border-bottom: none;
        }
        
        .form-section-title {
            color: var(--primary-color);
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .form-section-title i {
            margin-right: 10px;
            font-size: 1.2rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group:last-child {
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.1);
            outline: none;
        }
        
        .form-group textarea {
            height: 120px;
            resize: vertical;
        }
        
        .recipients-container {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            padding: 0;
            overflow: hidden;
        }
        
        .recipient-section {
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .recipient-section:last-child {
            border-bottom: none;
        }
        
        .recipient-section-title {
            font-size: 1rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .recipient-section-title i {
            margin-right: 8px;
        }
        
        .group-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .group-checkbox {
            background-color: white;
            padding: 10px 15px;
            border-radius: var(--border-radius);
            border: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        .group-checkbox:hover {
            border-color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .group-checkbox input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .individual-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .recipient-group {
            background-color: white;
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px solid #e9ecef;
        }
        
        .recipient-group h5 {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 0 0 10px 0;
            padding-bottom: 8px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .recipient-checkbox {
            padding: 6px 0;
            transition: all 0.2s ease;
        }

        .recipient-checkbox:hover {
            background-color: #f8f9fa;
        }
        
        .recipient-checkbox label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .recipient-checkbox input[type="checkbox"] {
            margin-right: 8px;
        }
        
        .form-buttons {
            padding: 20px;
            background-color: #f8f9fa;
            border-top: 1px solid #e9ecef;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-submit,
        .btn-cancel {
            padding: 12px 24px;
            border-radius: var(--border-radius);
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }
        
        .btn-submit:hover {
            background-color: #3367d6;
        }
        
        .btn-submit i {
            margin-right: 8px;
        }
        
        .btn-cancel {
            background-color: #e9ecef;
            color: var(--text-color);
            border: none;
        }
        
        .btn-cancel:hover {
            background-color: #dee2e6;
        }
        
        .recipient-count {
            background-color: #e8f0fe;
            color: var(--primary-color);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            margin-left: 8px;
        }
        
        .notifications-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .notifications-table th,
        .notifications-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .notifications-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
        }

        .notifications-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: top;
        }
        
        .notification-content-cell {
            max-width: 500px;
            padding: 15px !important;
        }

        .notification-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 1rem;
        }

        .notification-message {
            color: var(--text-color);
            margin-bottom: 8px;
            font-size: 0.9rem;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .notification-recipient {
            color: var(--light-text);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
        }

        .notification-recipient i {
            margin-right: 5px;
            font-size: 0.9rem;
        }

        .notification-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.9rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            color: var(--light-text);
        }

        .meta-item i {
            margin-right: 5px;
            font-size: 0.9rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-sent {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .status-failed {
            background-color: #ffebee;
            color: #d32f2f;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .action-btn {
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 0.85rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
        }

        .action-btn i {
            margin-right: 4px;
        }

        .btn-view {
            background-color: #e3f2fd;
            color: #1976d2;
        }

        .btn-view:hover {
            background-color: #bbdefb;
        }
        
        .alert {
            position: relative;
            padding-right: 35px;
        }

        .close-alert {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            font-size: 20px;
            color: inherit;
            opacity: 0.7;
            cursor: pointer;
            padding: 0 5px;
        }

        .close-alert:hover {
            opacity: 1;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }

        .alert.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }

        .delivery-info {
            margin-top: 8px;
            padding: 8px 12px;
            background-color: #e8f0fe;
            border-radius: var(--border-radius);
            display: flex;
            align-items: flex-start;
            gap: 8px;
            font-size: 0.9rem;
            color: var(--primary-color);
        }
        
        .info-icon {
            color: var(--primary-color);
            font-size: 1rem;
        }
        
        .info-text {
            flex: 1;
            line-height: 1.4;
        }

        .recipient-list {
            max-height: 200px;
            overflow-y: auto;
            padding-right: 5px;
        }
        
        .recipient-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .recipient-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .recipient-list::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }
        
        .recipient-list::-webkit-scrollbar-thumb:hover {
            background: #999;
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
                    <div class="user-name"><?php echo htmlspecialchars($nurse_name); ?></div>
                    <div class="user-role">Nurse</div>
                    <div class="user-email"><?php echo htmlspecialchars($nurse_email); ?></div>
                </div>
                <a href="nurse_dashboard.php?logout=1" class="logout-btn">
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
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="page-title">
                    <h2><?php echo $action == 'send' ? 'Send Notification' : 'Notification Management'; ?></h2>
                    <?php if ($action != 'send'): ?>
                        <a href="?action=send" class="btn-add"><i class="fas fa-paper-plane"></i> Send Notification</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-<?php echo $action_type; ?>" id="notification-alert">
                        <?php echo $action_message; ?>
                        <button type="button" class="close-alert" onclick="this.parentElement.style.display='none';">×</button>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'send'): ?>
                    <div class="notification-form">
                        <form method="POST" action="?action=send" class="notification-form">
                            <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($_SESSION['notification_form_token']); ?>">
                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-envelope"></i> Notification Details
                                </div>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="title_type">Notification Type</label>
                                        <select id="title_type" name="title_type" onchange="toggleCustomTitle()" required>
                                            <option value="">-- Select Type --</option>
                                            <option value="Appointment Reminder">Appointment Reminder</option>
                                            <option value="Vaccination Due">Vaccination Due</option>
                                            <option value="Health Check Reminder">Health Check Reminder</option>
                                            <option value="Test Results Available">Test Results Available</option>
                                            <option value="Medication Reminder">Medication Reminder</option>
                                            <option value="Schedule Change">Schedule Change</option>
                                            <option value="custom">Custom Title...</option>
                                        </select>
                                    </div>
                                    
                                    <div class="form-group" id="custom_title_group" style="display: none;">
                                        <label for="title">Custom Title</label>
                                        <input type="text" id="title" name="title" placeholder="Enter custom title">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="notification_type">Delivery Method</label>
                                        <select id="notification_type" name="notification_type" required>
                                            <option value="">-- Select Method --</option>
                                            <option value="email">Email Only</option>
                                            <option value="sms">SMS Only</option>
                                            <option value="email_sms">Email & SMS</option>
                                            <option value="system">System</option>
                                        </select>
                                        <div class="delivery-info" id="delivery_info" style="display: none;">
                                            <div class="info-icon">
                                                <i class="fas fa-info-circle"></i>
                                            </div>
                                            <div class="info-text"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-users"></i> Recipients
                                </div>
                                <div class="recipients-container">
                                    <div class="recipient-section">
                                        <div class="recipient-section-title">
                                            <i class="fas fa-layer-group"></i> Select Groups
                                        </div>
                                        <div class="group-list">
                                            <?php $count = getGroupCount('patient', $grouped_users); ?>
                                            <?php if ($count > 0): ?>
                                                <div class="group-checkbox">
                                                    <input type="checkbox" name="recipient_groups[]" value="patient" id="group_patient" onchange="toggleGroup('patient')">
                                                    <label for="group_patient">All Patients <span class="recipient-count"><?php echo $count; ?></span></label>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="recipient-section">
                                        <div class="recipient-section-title">
                                            <i class="fas fa-user"></i> Select Individual Recipients
                                        </div>
                                        <div class="individual-list">
                                            <?php if (!empty($grouped_users['patient'])): ?>
                                                <div class="recipient-group">
                                                    <h5>
                                                        <span>Patients</span>
                                                        <span class="recipient-count"><?php echo count($grouped_users['patient']); ?></span>
                                                    </h5>
                                                    <div class="recipient-list" id="patient-list">
                                                        <?php foreach ($grouped_users['patient'] as $user): ?>
                                                            <div class="recipient-checkbox" data-name="<?php echo strtolower($user['display_name']); ?>">
                                                                <label>
                                                                    <input type="checkbox" name="recipients[]" value="<?php echo $user['id']; ?>" class="patient-checkbox">
                                                                    <?php echo htmlspecialchars($user['display_name']); ?>
                                                                </label>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <div class="form-section-title">
                                    <i class="fas fa-comment-alt"></i> Message
                                </div>
                                <div class="form-group">
                                    <textarea id="message" name="message" placeholder="Enter your message here..." required></textarea>
                                </div>
                            </div>

                            <div class="form-buttons">
                                <a href="nurse_notifications.php" class="btn-cancel">Cancel</a>
                                <button type="submit" name="send_notification" class="btn-submit">
                                    <i class="fas fa-paper-plane"></i> Send Notification
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <table class="notifications-table">
                            <thead>
                                <tr>
                                    <th>Notification</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($notifications_result->num_rows > 0): ?>
                                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="notification-content-cell">
                                                <div class="notification-title">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </div>
                                                <div class="notification-message">
                                                    <?php echo htmlspecialchars($notification['message']); ?>
                                                </div>
                                                <div class="notification-recipient">
                                                    <i class="fas fa-user"></i>
                                                    <?php echo htmlspecialchars($notification['user_name'] ?? 'Unknown User'); ?>
                                                </div>
                                                <div class="notification-meta">
                                                    <span class="meta-item">
                                                        <i class="fas fa-calendar"></i>
                                                        <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                                    </span>
                                                    <span class="meta-item">
                                                        <i class="fas fa-clock"></i>
                                                        <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <a href="#" onclick="viewNotification('<?php echo htmlspecialchars(addslashes($notification['title'])); ?>', '<?php echo htmlspecialchars(addslashes($notification['message'])); ?>'); return false;" class="action-btn btn-view">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" style="text-align: center; padding: 30px;">No notifications found.</td>
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
            // Handle delivery method selection
            const deliveryMethodSelect = document.getElementById('notification_type');
            const deliveryInfo = document.getElementById('delivery_info');
            
            if (deliveryMethodSelect && deliveryInfo) {
                deliveryMethodSelect.addEventListener('change', function() {
                    const infoText = deliveryInfo.querySelector('.info-text');
                    
                    switch (this.value) {
                        case 'email_sms':
                            infoText.textContent = 'Recipients will receive both email and SMS notifications. Make sure they have both contact methods set up.';
                            deliveryInfo.style.display = 'flex';
                            break;
                        case 'email':
                            infoText.textContent = 'Recipients will receive notifications via email only.';
                            deliveryInfo.style.display = 'flex';
                            break;
                        case 'sms':
                            infoText.textContent = 'Recipients will receive notifications via SMS only.';
                            deliveryInfo.style.display = 'flex';
                            break;
                        case 'system':
                            infoText.textContent = 'Recipients will receive notifications in their system inbox only.';
                            deliveryInfo.style.display = 'flex';
                            break;
                        default:
                            deliveryInfo.style.display = 'none';
                    }
                });
            }
            
            // Auto-hide success messages after 5 seconds
            const alert = document.getElementById('notification-alert');
            if (alert && alert.classList.contains('alert-success')) {
                setTimeout(function() {
                    alert.classList.add('fade-out');
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
            
            // Prevent form resubmission on page reload
            if (window.history.replaceState) {
                window.history.replaceState(null, null, window.location.href);
            }

            // Handle group checkbox
            const groups = ['patient'];
            groups.forEach(group => {
                const individualCheckboxes = document.querySelectorAll(`.${group}-checkbox`);
                const groupCheckbox = document.getElementById(`group_${group}`);
                
                if (groupCheckbox && individualCheckboxes.length > 0) {
                    // Update group checkbox when all individuals are selected
                    individualCheckboxes.forEach(checkbox => {
                        checkbox.addEventListener('change', () => {
                            const allChecked = Array.from(individualCheckboxes).every(cb => cb.checked);
                            groupCheckbox.checked = allChecked;
                        });
                    });
                }
            });
        });
        
        // Function to view notification details
        function viewNotification(title, message) {
            const modal = document.createElement('div');
            modal.style.position = 'fixed';
            modal.style.left = '0';
            modal.style.top = '0';
            modal.style.width = '100%';
            modal.style.height = '100%';
            modal.style.backgroundColor = 'rgba(0,0,0,0.4)';
            modal.style.zIndex = '1000';
            modal.style.display = 'flex';
            modal.style.justifyContent = 'center';
            modal.style.alignItems = 'center';
            
            const modalContent = document.createElement('div');
            modalContent.style.backgroundColor = '#fff';
            modalContent.style.padding = '30px';
            modalContent.style.borderRadius = '8px';
            modalContent.style.width = '50%';
            modalContent.style.maxWidth = '600px';
            modalContent.style.boxShadow = '0 4px 10px rgba(0,0,0,0.1)';
            
            const closeBtn = document.createElement('span');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.float = 'right';
            closeBtn.style.fontSize = '24px';
            closeBtn.style.fontWeight = 'bold';
            closeBtn.style.cursor = 'pointer';
            closeBtn.onclick = function() {
                document.body.removeChild(modal);
            };
            
            const modalTitle = document.createElement('h3');
            modalTitle.textContent = title;
            modalTitle.style.marginBottom = '20px';
            modalTitle.style.color = '#4285f4';
            
            const modalMessage = document.createElement('p');
            modalMessage.textContent = message;
            modalMessage.style.lineHeight = '1.6';
            
            modalContent.appendChild(closeBtn);
            modalContent.appendChild(modalTitle);
            modalContent.appendChild(modalMessage);
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            modal.onclick = function(event) {
                if (event.target === modal) {
                    document.body.removeChild(modal);
                }
            };
        }
        
        function toggleCustomTitle() {
            const titleType = document.getElementById('title_type');
            const customTitleGroup = document.getElementById('custom_title_group');
            const customTitleInput = document.getElementById('title');
            
            if (titleType.value === 'custom') {
                customTitleGroup.style.display = 'block';
                customTitleInput.setAttribute('required', 'required');
            } else {
                customTitleGroup.style.display = 'none';
                customTitleInput.removeAttribute('required');
                customTitleInput.value = '';
            }
        }
        
        function toggleGroup(group) {
            const groupCheckbox = document.getElementById(`group_${group}`);
            const individualCheckboxes = document.querySelectorAll(`.${group}-checkbox`);
            
            individualCheckboxes.forEach(checkbox => {
                checkbox.checked = groupCheckbox.checked;
                checkbox.disabled = groupCheckbox.checked;
            });
        }
    </script>
</body>
</html>
