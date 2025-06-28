<?php
session_start();
require 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin_name = $_SESSION['user_name'];
$admin_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process notification actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Send notification
if ($action == 'send' && isset($_POST['send_notification'])) {
    $title = $_POST['title'];
    $message = $_POST['message'];
    $user_id = $_POST['user_id'] ?? $admin_id; // Default to admin if not specified
    $type = $_POST['notification_type'];
    
    // Create notification record
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
    $stmt->bind_param("isss", $user_id, $title, $message, $type);
    
    if ($stmt->execute()) {
        $notification_id = $conn->insert_id;
        
        // In a real application, this would trigger a background job to send notifications
        // For demonstration, we'll simulate successful delivery after a delay
        $delivery_time = date('Y-m-d H:i:s', strtotime('+2 minutes'));
        $stmt = $conn->prepare("UPDATE notifications SET sent_at = ? WHERE id = ?");
        $stmt->bind_param("si", $delivery_time, $notification_id);
        $stmt->execute();
        
        $action_message = "Notification queued for delivery.";
        $action = ''; // Return to list view
    } else {
        $action_message = "Error creating notification: " . $conn->error;
    }
}

// Delete notification
if ($action == 'delete' && isset($_GET['id'])) {
    $notification_id = $_GET['id'];
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    
    if ($stmt->execute()) {
        if ($conn->affected_rows > 0) {
            $action_message = "Notification deleted successfully!";
        } else {
            $action_message = "Could not delete notification.";
        }
    } else {
        $action_message = "Error deleting notification: " . $conn->error;
    }
    
    $action = ''; // Return to list view
}

// Get health centers for dropdown
$stmt = $conn->prepare("SELECT id, name FROM health_centers WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$health_centers = $stmt->get_result();
$health_centers_array = [];
while ($center = $health_centers->fetch_assoc()) {
    $health_centers_array[$center['id']] = $center['name'];
}

// Get recent notifications
$notifications_query = "SELECT n.*, u.name as user_name
                       FROM notifications n 
                       LEFT JOIN users u ON n.user_id = u.id 
                       ORDER BY n.created_at DESC
                       LIMIT 50";
$notifications_result = $conn->query($notifications_query);

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
        
        .notification-form {
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
            height: 120px;
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
        }
        
        .notification-title {
            font-weight: 500;
        }
        
        .notification-message {
            color: var(--light-text);
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .notification-status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-inactive {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .status-read {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-unread {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-view,
        .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .btn-view {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .btn-view:hover {
            background-color: #bbdefb;
        }
        
        .btn-delete {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .btn-delete:hover {
            background-color: #ffcdd2;
        }
        
        .health-center-select {
            display: none;
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
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php" class="active"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
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
                    <div class="alert alert-success">
                        <?php echo $action_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'send'): ?>
                    <div class="notification-form">
                        <form method="POST" action="?action=send">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="title">Notification Title</label>
                                    <input type="text" id="title" name="title" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="notification_type">Notification Type</label>
                                    <select id="notification_type" name="notification_type" required>
                                        <option value="">-- Select Type --</option>
                                        <option value="email">Email</option>
                                        <option value="sms">SMS</option>
                                        <option value="system">System</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="user_id">Recipient</label>
                                    <select id="user_id" name="user_id">
                                        <option value="<?php echo $admin_id; ?>">Me (<?php echo htmlspecialchars($admin_name); ?>)</option>
                                        <?php 
                                        // In a real application, fetch users from database
                                        $users_query = "SELECT id, name FROM users WHERE id != $admin_id ORDER BY name";
                                        $users_result = $conn->query($users_query);
                                        while ($users_result && $user = $users_result->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $user['id']; ?>">
                                                <?php echo htmlspecialchars($user['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea id="message" name="message" required></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="send_notification" class="btn-submit">
                                    <i class="fas fa-paper-plane"></i> Send Notification
                                </button>
                                <a href="admin_notifications.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="notifications-list">
                        <table class="notifications-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Message</th>
                                    <th>Recipients</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($notifications_result->num_rows > 0): ?>
                                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                        <tr>
                                            <td class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></td>
                                            <td class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($notification['user_name']); ?>
                                            </td>
                                            <td><?php echo ucfirst($notification['type']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></td>
                                            <td>
                                                <span class="notification-status <?php echo $notification['is_read'] ? 'status-read' : 'status-unread'; ?>">
                                                    <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="#" class="btn-view" onclick="viewNotification('<?php echo htmlspecialchars($notification['title'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($notification['message'], ENT_QUOTES); ?>')">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if (!$notification['is_read']): ?>
                                                    <a href="?action=delete&id=<?php echo $notification['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                                        <i class="fas fa-trash"></i> Delete
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No notifications found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Notification View Modal (Added with JavaScript) -->
    
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
            
            // Show/hide health center select based on recipient type
            const recipientTypeSelect = document.getElementById('recipient_type');
            const healthCenterSelect = document.querySelector('.health-center-select');
            
            if (recipientTypeSelect) {
                recipientTypeSelect.addEventListener('change', function() {
                    if (this.value === 'health_center') {
                        healthCenterSelect.style.display = 'block';
                        document.getElementById('health_center_id').setAttribute('required', 'required');
                    } else {
                        healthCenterSelect.style.display = 'none';
                        document.getElementById('health_center_id').removeAttribute('required');
                    }
                });
            }
        });
        
        // Function to view notification details
        function viewNotification(title, message) {
            // Create modal elements
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
            
            // Append elements
            modalContent.appendChild(closeBtn);
            modalContent.appendChild(modalTitle);
            modalContent.appendChild(modalMessage);
            modal.appendChild(modalContent);
            document.body.appendChild(modal);
            
            // Close when clicking outside of modal content
            modal.onclick = function(event) {
                if (event.target === modal) {
                    document.body.removeChild(modal);
                }
            };
        }
    </script>
</body>
</html> 