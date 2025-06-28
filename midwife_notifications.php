<?php
session_start();
require 'config.php';
require_once 'notification_system.php';

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'midwife') {
    header('Location: login.php');
    exit;
}

// Get midwife information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Initialize notification system
$notification_system = new NotificationSystem();

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle filter functionality
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Set up the filter condition
$filter_condition = '';
switch ($filter) {
    case 'unread':
        $filter_condition = " AND n.is_read = 0";
        break;
    case 'read':
        $filter_condition = " AND n.is_read = 1";
        break;
    case 'email':
        $filter_condition = " AND n.type = 'email'";
        break;
    case 'sms':
        $filter_condition = " AND n.type = 'sms'";
        break;
    case 'system':
        $filter_condition = " AND n.type = 'system'";
        break;
    default:
        $filter_condition = "";
}

// Get total number of notifications for this midwife
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications n WHERE n.user_id = ? $filter_condition");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_notifications = $result->fetch_assoc()['count'];
$total_pages = ceil($total_notifications / $records_per_page);

// Get notifications with pagination and delivery status
$stmt = $conn->prepare("SELECT n.*, 
                              CASE 
                                WHEN n.type = 'email' THEN el.status
                                WHEN n.type = 'sms' THEN sl.status
                                ELSE NULL
                              END as delivery_status
                       FROM notifications n 
                       LEFT JOIN email_logs el ON el.user_id = n.user_id AND el.created_at = n.created_at
                       LEFT JOIN sms_logs sl ON sl.patient_id = (SELECT id FROM patients WHERE user_id = n.user_id) AND sl.created_at = n.created_at
                       WHERE n.user_id = ? $filter_condition
                       ORDER BY n.sent_at DESC
                       LIMIT ?, ?");
$stmt->bind_param("iii", $user_id, $offset, $records_per_page);
$stmt->execute();
$notifications = $stmt->get_result();

// Get unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];

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

// Mark notification as read
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notification_id = $_GET['mark_read'];
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    $stmt->execute();
    
    // Redirect to avoid form resubmission
    header("Location: midwife_notifications.php?filter=$filter&page=$page&marked=1");
    exit;
}

// Mark all notifications as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    
    // Redirect to avoid form resubmission
    header("Location: midwife_notifications.php?filter=$filter&page=$page&marked_all=1");
    exit;
}

$conn->close();
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
        
        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            color: var(--light-text);
            text-decoration: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .filter-tab:hover {
            color: var(--text-color);
            background-color: #f8f9fa;
        }
        
        .filter-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            font-weight: 500;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .mark-all-btn {
            padding: 8px 15px;
            background-color: #e9ecef;
            color: var(--text-color);
            border-radius: 5px;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .mark-all-btn i {
            margin-right: 5px;
        }
        
        .mark-all-btn:hover {
            background-color: #dee2e6;
        }
        
        .notification-list {
            margin-top: 20px;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            transition: var(--transition);
        }
        
        .notification-item:hover {
            background-color: #f8f9fa;
        }
        
        .notification-item.unread {
            background-color: #f1f8ff;
        }
        
        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .notification-icon.email {
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .notification-icon.sms {
            background-color: #e3f8e7;
            color: #28a745;
        }
        
        .notification-icon.system {
            background-color: #fff8e6;
            color: #ffc107;
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-title {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .notification-message {
            color: var(--light-text);
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .notification-time {
            color: var(--light-text);
            font-size: 0.8rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .mark-read-btn {
            padding: 5px 10px;
            background-color: #e9ecef;
            color: var(--text-color);
            border-radius: 4px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .mark-read-btn:hover {
            background-color: #dee2e6;
        }
        
        .unread-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary-color);
            margin-right: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
            background-color: #f8f9fa;
            transition: var(--transition);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #e9ecef;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--light-text);
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #e9ecef;
        }
        
        .empty-state p {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .empty-state span {
            font-size: 0.9rem;
        }
        
        .delivery-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 10px;
        }
        
        .status-delivered {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-failed {
            background-color: #ffebee;
            color: #c62828;
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
                    <div class="user-role">Midwife</div>
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
                    <li><a href="midwife_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="midwife_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="midwife_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_notifications.php" class="active"><i class="fas fa-bell"></i> Notifications <?php if ($unread_count > 0): ?><span class="unread-badge"></span><?php endif; ?></a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Notifications</h2>
                
                <?php if (isset($_GET['marked']) && $_GET['marked'] == 1): ?>
                    <div class="alert">
                        Notification has been marked as read.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['marked_all']) && $_GET['marked_all'] == 1): ?>
                    <div class="alert">
                        All notifications have been marked as read.
                    </div>
                <?php endif; ?>
                
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                    <a href="?filter=unread" class="filter-tab <?php echo $filter == 'unread' ? 'active' : ''; ?>">
                        Unread <?php if ($unread_count > 0): ?>(<?php echo $unread_count; ?>)<?php endif; ?>
                    </a>
                    <a href="?filter=read" class="filter-tab <?php echo $filter == 'read' ? 'active' : ''; ?>">
                        Read
                    </a>
                    <a href="?filter=email" class="filter-tab <?php echo $filter == 'email' ? 'active' : ''; ?>">
                        Email
                    </a>
                    <a href="?filter=sms" class="filter-tab <?php echo $filter == 'sms' ? 'active' : ''; ?>">
                        SMS
                    </a>
                    <a href="?filter=system" class="filter-tab <?php echo $filter == 'system' ? 'active' : ''; ?>">
                        System
                    </a>
                </div>
                
                <?php if ($unread_count > 0): ?>
                    <div class="action-bar">
                        <a href="?mark_all_read=1&filter=<?php echo $filter; ?>&page=<?php echo $page; ?>" class="mark-all-btn">
                            <i class="fas fa-check-double"></i> Mark all as read
                        </a>
                    </div>
                <?php endif; ?>
                
                <div class="notification-list">
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notification = $notifications->fetch_assoc()): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                <?php
                                    $icon_class = '';
                                    switch ($notification['type']) {
                                        case 'email':
                                            $icon_class = 'email';
                                            $icon = 'fa-envelope';
                                            break;
                                        case 'sms':
                                            $icon_class = 'sms';
                                            $icon = 'fa-comment-sms';
                                            break;
                                        case 'system':
                                            $icon_class = 'system';
                                            $icon = 'fa-bell';
                                            break;
                                        default:
                                            $icon_class = 'system';
                                            $icon = 'fa-bell';
                                    }
                                ?>
                                <div class="notification-icon <?php echo $icon_class; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="unread-badge"></span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <?php if ($notification['delivery_status']): ?>
                                            <span class="delivery-status <?php echo $notification['delivery_status'] === 'sent' ? 'status-delivered' : 'status-failed'; ?>">
                                                <?php echo ucfirst($notification['delivery_status']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <?php echo date('F j, Y - g:i A', strtotime($notification['sent_at'])); ?>
                                    </div>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <div class="notification-actions">
                                        <a href="?mark_read=<?php echo $notification['id']; ?>&filter=<?php echo $filter; ?>&page=<?php echo $page; ?>" class="mark-read-btn">
                                            Mark as read
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-bell"></i>
                            <p>No notifications found</p>
                            <span>You're all caught up!</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?filter=<?php echo $filter; ?>&page=<?php echo ($page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?filter=<?php echo $filter; ?>&page=<?php echo ($page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 