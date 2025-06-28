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

$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get current settings to determine what changed
    $stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_provider', 'sms_enabled', 'email_enabled', 'appointment_reminder_days', 'auto_sync_mhc', 'philsms_api_key', 'philsms_sender_id')");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $current_settings = [];
    while ($row = $result->fetch_assoc()) {
        $current_settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Process each setting
    $settings = [
        'sms_provider' => isset($_POST['sms_provider']) ? $_POST['sms_provider'] : 'philsms',
        'sms_enabled' => isset($_POST['sms_enabled']) ? 'true' : 'false',
        'email_enabled' => isset($_POST['email_enabled']) ? 'true' : 'false',
        'appointment_reminder_days' => isset($_POST['appointment_reminder_days']) ? (int)$_POST['appointment_reminder_days'] : 2,
        'auto_sync_mhc' => isset($_POST['auto_sync_mhc']) ? 'true' : 'false',
        'philsms_api_key' => isset($_POST['philsms_api_key']) ? $_POST['philsms_api_key'] : '',
        'philsms_sender_id' => isset($_POST['philsms_sender_id']) ? $_POST['philsms_sender_id'] : 'IMMUCARE'
    ];
    
    // Update each setting
    foreach ($settings as $key => $value) {
        // Check if setting exists
        if (isset($current_settings[$key])) {
            // Update if value changed
            if ($current_settings[$key] !== $value) {
                $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
                $stmt->bind_param("sis", $value, $admin_id, $key);
                $stmt->execute();
            }
        } else {
            // Insert new setting
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by, created_at) VALUES (?, ?, ?, ?, NOW())");
            $descriptions = [
                'philsms_api_key' => 'PhilSMS API Key',
                'philsms_sender_id' => 'PhilSMS Sender ID'
            ];
            $description = isset($descriptions[$key]) ? $descriptions[$key] : '';
            $stmt->bind_param("sssi", $key, $value, $description, $admin_id);
            $stmt->execute();
        }
    }
    
    $success_message = "Settings updated successfully.";
}

// Get current settings
$stmt = $conn->prepare("SELECT setting_key, setting_value FROM system_settings");
$stmt->execute();
$result = $stmt->get_result();

$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

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
    <title>System Settings - ImmuCare</title>
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
        
        .settings-form {
            max-width: 800px;
        }
        
        .settings-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .settings-section h3 {
            font-size: 1.3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-group input[type="text"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group input[type="text"]:focus,
        .form-group input[type="number"]:focus,
        .form-group input[type="password"]:focus,
        .form-group select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .form-hint {
            font-size: 0.9rem;
            color: var(--light-text);
            margin-top: 5px;
        }
        
        .submit-btn {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .submit-btn:hover {
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
        
        .conditional-field {
            margin-left: 20px;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #eee;
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
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">System Settings</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-error">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form class="settings-form" method="POST" action="">
                    <div class="settings-section">
                        <h3>Notification Settings</h3>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="email_enabled" name="email_enabled" <?php echo (isset($settings['email_enabled']) && $settings['email_enabled'] === 'true') ? 'checked' : ''; ?>>
                            <label for="email_enabled">Enable Email Notifications</label>
                        </div>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="sms_enabled" name="sms_enabled" <?php echo (isset($settings['sms_enabled']) && $settings['sms_enabled'] === 'true') ? 'checked' : ''; ?>>
                            <label for="sms_enabled">Enable SMS Notifications</label>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_reminder_days">Send Appointment Reminders (days before)</label>
                            <input type="number" id="appointment_reminder_days" name="appointment_reminder_days" min="1" max="14" value="<?php echo isset($settings['appointment_reminder_days']) ? $settings['appointment_reminder_days'] : 2; ?>">
                            <div class="form-hint">Number of days before an appointment to send a reminder</div>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>SMS Provider Settings</h3>
                        
                        <div class="form-group">
                            <label for="sms_provider">SMS Provider</label>
                            <select id="sms_provider" name="sms_provider">
                                <option value="philsms" <?php echo (isset($settings['sms_provider']) && $settings['sms_provider'] === 'philsms') ? 'selected' : ''; ?>>PhilSMS</option>
                                <option value="twilio" <?php echo (isset($settings['sms_provider']) && $settings['sms_provider'] === 'twilio') ? 'selected' : ''; ?>>Twilio</option>
                            </select>
                        </div>
                        
                        <div id="philsms_settings" class="conditional-field" style="display: <?php echo (isset($settings['sms_provider']) && $settings['sms_provider'] === 'philsms') ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label for="philsms_api_key">PhilSMS API Key</label>
                                <input type="password" id="philsms_api_key" name="philsms_api_key" value="<?php echo isset($settings['philsms_api_key']) ? $settings['philsms_api_key'] : ''; ?>">
                                <div class="form-hint">Enter your PhilSMS API key</div>
                            </div>
                            
                            <div class="form-group">
                                <label for="philsms_sender_id">PhilSMS Sender ID</label>
                                <input type="text" id="philsms_sender_id" name="philsms_sender_id" value="<?php echo isset($settings['philsms_sender_id']) ? $settings['philsms_sender_id'] : 'IMMUCARE'; ?>">
                                <div class="form-hint">Your registered sender ID with PhilSMS</div>
                            </div>
                        </div>
                        
                        <div id="twilio_settings" class="conditional-field" style="display: <?php echo (isset($settings['sms_provider']) && $settings['sms_provider'] === 'twilio') ? 'block' : 'none'; ?>;">
                            <div class="form-group">
                                <label for="twilio_account_sid">Twilio Account SID</label>
                                <input type="password" id="twilio_account_sid" name="twilio_account_sid" value="<?php echo isset($settings['twilio_account_sid']) ? $settings['twilio_account_sid'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="twilio_auth_token">Twilio Auth Token</label>
                                <input type="password" id="twilio_auth_token" name="twilio_auth_token" value="<?php echo isset($settings['twilio_auth_token']) ? $settings['twilio_auth_token'] : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="twilio_phone_number">Twilio Phone Number</label>
                                <input type="text" id="twilio_phone_number" name="twilio_phone_number" value="<?php echo isset($settings['twilio_phone_number']) ? $settings['twilio_phone_number'] : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="settings-section">
                        <h3>Data Transfer Settings</h3>
                        
                        <div class="form-group checkbox-group">
                            <input type="checkbox" id="auto_sync_mhc" name="auto_sync_mhc" <?php echo (isset($settings['auto_sync_mhc']) && $settings['auto_sync_mhc'] === 'true') ? 'checked' : ''; ?>>
                            <label for="auto_sync_mhc">Enable Automatic Sync with Municipal Health Centers</label>
                            <div class="form-hint">When enabled, data will be automatically synced daily</div>
                        </div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Toggle SMS provider settings
            const smsProviderSelect = document.getElementById('sms_provider');
            const philsmsSettings = document.getElementById('philsms_settings');
            const twilioSettings = document.getElementById('twilio_settings');
            
            smsProviderSelect.addEventListener('change', function() {
                if (this.value === 'philsms') {
                    philsmsSettings.style.display = 'block';
                    twilioSettings.style.display = 'none';
                } else if (this.value === 'twilio') {
                    philsmsSettings.style.display = 'none';
                    twilioSettings.style.display = 'block';
                }
            });
            
            // Highlight the active menu item
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