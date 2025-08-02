<?php
session_start();
require 'config.php';
require 'includes/file_transfer.php';

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

// Note: Transfer processing has been moved to process_file_transfer.php for AJAX handling

// Get transfer message from session if exists
$transfer_message = '';
$transfer_message_type = '';
if (isset($_SESSION['transfer_message'])) {
    $transfer_message = $_SESSION['transfer_message']['text'];
    $transfer_message_type = $_SESSION['transfer_message']['type'];
    // Clear the message from session
    unset($_SESSION['transfer_message']);
}

// Fetch system statistics
// Count users by role
$stmt = $conn->prepare("SELECT r.name, COUNT(u.id) as count FROM users u JOIN roles r ON u.role_id = r.id GROUP BY r.name");
$stmt->execute();
$result = $stmt->get_result();
$user_stats = [];
while ($row = $result->fetch_assoc()) {
    $user_stats[$row['name']] = $row['count'];
}

// Count patients
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients");
$stmt->execute();
$result = $stmt->get_result();
$patient_count = $result->fetch_assoc()['count'];

// Count immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations");
$stmt->execute();
$result = $stmt->get_result();
$immunization_count = $result->fetch_assoc()['count'];

// Count upcoming appointments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date > NOW() AND status = 'confirmed'");
$stmt->execute();
$result = $stmt->get_result();
$appointment_count = $result->fetch_assoc()['count'];

// Get recent data transfers
$stmt = $conn->prepare("SELECT dt.*, u.name as initiated_by_name FROM data_transfers dt LEFT JOIN users u ON dt.initiated_by = u.id ORDER BY dt.created_at DESC LIMIT 5");
$stmt->execute();
$recent_transfers = $stmt->get_result();

// Get health centers for transfer dropdown
$stmt = $conn->prepare("SELECT id, name FROM health_centers WHERE is_active = 1");
$stmt->execute();
$health_centers = $stmt->get_result();

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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ImmuCare</title>
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
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
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
            height: fit-content;
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            transition: var(--transition);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            font-size: 2.2rem;
            color: var(--primary-color);
            background: #f1f8ff;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-color);
            line-height: 1;
        }
        
        .stat-label {
            color: var(--light-text);
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .admin-sections {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 30px;
        }
        
        .admin-section {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 25px;
        }
        
        .admin-section h3 {
            font-size: 1.4rem;
            color: var(--text-color);
            margin: 0 0 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .transfer-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            gap: 10px;
            text-decoration: none;
        }
        
        .transfer-btn:hover {
            background-color: #3367d6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(66, 133, 244, 0.2);
        }
        
        .transfer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .transfer-table th,
        .transfer-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .transfer-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .transfer-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }
        
        .status-completed {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .status-pending {
            background-color: #fff8e1;
            color: #f57c00;
        }
        
        .status-failed {
            background-color: #ffebee;
            color: #c62828;
        }
        
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }
        
        .checkbox-item input[type="checkbox"] {
            margin-right: 8px;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-item label {
            cursor: pointer;
            font-weight: normal;
            margin-bottom: 0;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            text-align: center;
            color: white;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3367d6;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(66, 133, 244, 0.2);
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .admin-sections {
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
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
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
                <a href="?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Admin Dashboard</h2>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="stat-value"><?php echo isset($user_stats['admin']) ? $user_stats['admin'] : 0; ?></div>
                        <div class="stat-label">Administrators</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-nurse"></i>
                        </div>
                        <div class="stat-value"><?php echo isset($user_stats['midwife']) ? $user_stats['midwife'] : 0; ?></div>
                        <div class="stat-label">Midwives</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hospital-user"></i>
                        </div>
                        <div class="stat-value"><?php echo isset($user_stats['nurse']) ? $user_stats['nurse'] : 0; ?></div>
                        <div class="stat-label">Nurses</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo $patient_count; ?></div>
                        <div class="stat-label">Patients</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <div class="stat-value"><?php echo $immunization_count; ?></div>
                        <div class="stat-label">Immunizations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $appointment_count; ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                </div>
                
                <div class="admin-sections">
                    <div class="admin-section">
                        <h3>Data Transfer to Municipal Health Center</h3>
                        
                        <?php if (!empty($transfer_message)): ?>
                            <div class="alert alert-<?php echo $transfer_message_type; ?>" id="transferAlert">
                                <?php echo htmlspecialchars($transfer_message); ?>
                                <button type="button" class="alert-close" onclick="this.parentElement.style.display='none';">&times;</button>
                            </div>
                        <?php endif; ?>
                        
                        <form class="data-transfer-form" id="dataTransferForm">
                            <div class="form-group">
                                <label for="health_center_id">Select Health Center:</label>
                                <select name="health_center_id" id="health_center_id" required class="form-control">
                                    <option value="">-- Select Health Center --</option>
                                    <?php
                                    // Get health centers for transfer dropdown
                                    $query = "SELECT id, name, email FROM health_centers WHERE is_active = 1 ORDER BY name";
                                    $result = $conn->query($query);
                                    
                                    if ($result && $result->num_rows > 0) {
                                        while ($center = $result->fetch_assoc()) {
                                            echo '<option value="' . htmlspecialchars($center['id']) . '">' 
                                                . htmlspecialchars($center['name']) 
                                                . ' (' . htmlspecialchars($center['email']) . ')'
                                                . '</option>';
                                        }
                                    } else {
                                        echo '<option value="" disabled>No health centers found. Please add health centers in the database.</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Select Data Types to Transfer:</label>
                                <div class="checkbox-group" style="margin-top: 10px;">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="data_types[]" value="immunizations" id="immunizations" checked>
                                        <label for="immunizations">Immunization Records</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="data_types[]" value="vaccines" id="vaccines">
                                        <label for="vaccines">Vaccine Inventory</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="data_types[]" value="patients" id="patients">
                                        <label for="patients">Patient Records</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>File Formats:</label>
                                <div class="checkbox-group" style="margin-top: 10px;">
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="formats[]" value="excel" id="excel" checked>
                                        <label for="excel">Excel (.xlsx)</label>
                                    </div>
                                    <div class="checkbox-item">
                                        <input type="checkbox" name="formats[]" value="pdf" id="pdf" checked>
                                        <label for="pdf">PDF</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-group button-group">
                                <button type="button" class="transfer-btn" id="transferBtn">
                                    <i class="fas fa-sync-alt"></i>
                                    <span>Transfer Data</span>
                                </button>
                            </div>
                            
                            <!-- Transfer Progress -->
                            <div id="transferProgress" style="display: none; margin-top: 20px;">
                                <div class="progress-bar-container" style="height: 20px; background-color: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                                    <div class="progress-bar" style="height: 100%; width: 0%; background-color: #4285f4; transition: width 0.3s;"></div>
                                </div>
                                <div class="progress-status" style="text-align: center; font-size: 14px; color: #666;">Preparing files...</div>
                            </div>
                        </form>
                        
                        <div class="transfer-history">
                            <h4>Recent Transfers</h4>
                            <table class="transfer-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Destination</th>
                                        <th>Type</th>
                                        <th>Status</th>
                                        <th>Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($recent_transfers->num_rows > 0): ?>
                                        <?php while ($transfer = $recent_transfers->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($transfer['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($transfer['destination']); ?></td>
                                                <td><?php echo ucfirst($transfer['transfer_type']); ?></td>
                                                <td>
                                                    <span class="transfer-status status-<?php echo $transfer['status']; ?>">
                                                        <?php echo ucfirst($transfer['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $transfer['record_count'] ? $transfer['record_count'] : '-'; ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5">No recent transfers found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="admin-section">
                        <h3>Quick Actions</h3>
                        <div class="quick-actions">
                            <a href="admin_users.php?action=add" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-user-plus"></i> Add New User
                            </a>
                            <a href="admin_patients.php?action=add" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-user-plus"></i> Add New Patient
                            </a>
                            <a href="admin_vaccines.php?action=add" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-plus-circle"></i> Add New Vaccine
                            </a>
                            <a href="admin_reports.php" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-chart-bar"></i> Generate Reports
                            </a>
                            <a href="admin_notifications.php?action=send" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-bell"></i> Send Notifications
                            </a>
                            <a href="admin_settings.php" class="btn btn-primary" style="margin-right: 10px; margin-bottom: 10px; display: inline-block;">
                                <i class="fas fa-cog"></i> System Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add any JavaScript functionality here
        document.addEventListener('DOMContentLoaded', function() {
            // Example: Highlight the active menu item
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPath) {
                    item.classList.add('active');
                } else if (item.classList.contains('active') && item.getAttribute('href') !== '#') {
                    item.classList.remove('active');
                }
            });
            
            // Reset button state on page load
            const submitBtn = document.getElementById('transferBtn');
            if (submitBtn) {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Transfer Data';
            }
        });

        // Auto-hide alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alert = document.getElementById('transferAlert');
            if (alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });

        // AJAX Form submission handling
        document.getElementById('transferBtn').addEventListener('click', function() {
            const form = document.getElementById('dataTransferForm');
            const healthCenterId = document.getElementById('health_center_id').value;
            const submitBtn = document.getElementById('transferBtn');
            const progressBar = document.querySelector('#transferProgress .progress-bar');
            const progressStatus = document.querySelector('#transferProgress .progress-status');
            const transferProgress = document.getElementById('transferProgress');
            
            // Validate health center selection
            if (!healthCenterId) {
                alert('Please select a health center');
                return;
            }
            
            // Validate at least one data type is selected
            const dataTypeCheckboxes = document.querySelectorAll('input[name="data_types[]"]:checked');
            if (dataTypeCheckboxes.length === 0) {
                alert('Please select at least one data type to transfer');
                return;
            }
            
            // Validate at least one format is selected
            const formatCheckboxes = document.querySelectorAll('input[name="formats[]"]:checked');
            if (formatCheckboxes.length === 0) {
                alert('Please select at least one file format');
                return;
            }
            
            // Disable form resubmission
            if (submitBtn.classList.contains('loading')) {
                return;
            }
            
            // Show loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.querySelector('span').textContent = 'Transferring...';
            
            // Show progress bar
            transferProgress.style.display = 'block';
            progressStatus.textContent = 'Preparing files...';
            
            // Simulate progress - in a real application, you might use WebSockets for actual progress
            let progress = 0;
            const progressInterval = setInterval(() => {
                progress += Math.random() * 15;
                if (progress > 95) {
                    progress = 95; // Hold at 95% until complete
                    clearInterval(progressInterval);
                }
                progressBar.style.width = progress + '%';
                
                // Update status messages based on progress
                if (progress < 30) {
                    progressStatus.textContent = 'Collecting data...';
                } else if (progress < 60) {
                    progressStatus.textContent = 'Generating files...';
                } else if (progress < 90) {
                    progressStatus.textContent = 'Sending files...';
                } else {
                    progressStatus.textContent = 'Almost done...';
                }
            }, 300);
            
            // Create FormData object
            const formData = new FormData(form);
            
            // Add any missing checkboxes (unchecked boxes aren't included in FormData)
            const allDataTypes = ['immunizations', 'vaccines', 'patients'];
            const selectedDataTypes = Array.from(dataTypeCheckboxes).map(cb => cb.value);
            
            const allFormats = ['excel', 'pdf'];
            const selectedFormats = Array.from(formatCheckboxes).map(cb => cb.value);
            
            // Send AJAX request
            fetch('process_file_transfer.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Clear progress interval
                clearInterval(progressInterval);
                
                // Complete the progress bar
                progressBar.style.width = '100%';
                progressStatus.textContent = 'Transfer complete!';
                
                // Show success/error message
                const alertType = data.success ? 'success' : 'error';
                const alertMessage = data.message;
                
                // Create alert
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${alertType}`;
                alertDiv.innerHTML = alertMessage + '<button type="button" class="alert-close" onclick="this.parentElement.style.display=\'none\';">&times;</button>';
                
                // Find the form's parent container
                const formContainer = form.closest('.admin-section');
                
                // Insert alert at the top of the container, after the h3
                formContainer.insertBefore(alertDiv, formContainer.querySelector('h3').nextSibling);
                
                // Reset button state
                setTimeout(() => {
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    submitBtn.querySelector('span').textContent = 'Transfer Data';
                    
                    // Hide progress after a moment
                    setTimeout(() => {
                        transferProgress.style.display = 'none';
                    }, 2000);
                }, 1000);
                
                // Update the recent transfers table if successful
                if (data.success) {
                    updateRecentTransfersTable(data);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                clearInterval(progressInterval);
                
                // Show error message
                const alertDiv = document.createElement('div');
                alertDiv.className = 'alert alert-error';
                alertDiv.innerHTML = 'An error occurred during the transfer. Please try again.' + 
                    '<button type="button" class="alert-close" onclick="this.parentElement.style.display=\'none\';">&times;</button>';
                
                // Find the form's parent container
                const formContainer = form.closest('.admin-section');
                
                // Insert alert at the top of the container, after the h3
                formContainer.insertBefore(alertDiv, formContainer.querySelector('h3').nextSibling);
                
                // Reset button state
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
                submitBtn.querySelector('span').textContent = 'Transfer Data';
                transferProgress.style.display = 'none';
            });
            
            // Store submission timestamp
            localStorage.setItem('lastTransferSubmission', Date.now());
        });
        
        // Function to update the recent transfers table
        function updateRecentTransfersTable(data) {
            // This function would update the transfers table with the new transfer
            // For now just log the data
            console.log('Transfer successful:', data);
            
            // In a real implementation, you might:
            // 1. Get the transfers table
            // 2. Create a new row with the transfer data
            // 3. Add it to the top of the table
        }

        // Check for recent submission on page load
        document.addEventListener('DOMContentLoaded', function() {
            const lastSubmission = localStorage.getItem('lastTransferSubmission');
            if (lastSubmission) {
                const timeSinceSubmission = Date.now() - parseInt(lastSubmission);
                // Clear if more than 5 minutes have passed
                if (timeSinceSubmission > 300000) {
                    localStorage.removeItem('lastTransferSubmission');
                }
            }
        });
    </script>
</body>
</html>

<?php
// Close the database connection at the very end of the file
$conn->close();
?> 