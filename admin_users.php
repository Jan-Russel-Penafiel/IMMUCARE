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
$admin_name = $_SESSION['user_name'];
$admin_email = $_SESSION['user_email'];

// Initialize notification system
$notification_system = new NotificationSystem();

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process user actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Add new user
if ($action == 'add' && isset($_POST['add_user'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = !empty($_POST['password']) ? $_POST['password'] : null;
    $role_id = $_POST['role_id'];
    $phone = $_POST['phone'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role_id, phone, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssisi", $name, $email, $password, $role_id, $phone, $is_active);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        
        // Get the user_type based on role_id
        $user_type = '';
        switch ($role_id) {
            case 1: $user_type = 'admin'; break;
            case 2: $user_type = 'midwife'; break;
            case 3: $user_type = 'nurse'; break;
            case 4: $user_type = 'patient'; break;
        }
        
        // Update the user_type
        $update_stmt = $conn->prepare("UPDATE users SET user_type = ? WHERE id = ?");
        $update_stmt->bind_param("si", $user_type, $new_user_id);
        $update_stmt->execute();
        
        // Send welcome notification only if it's a patient account
        if ($role_id == 4) {
            $notification_system->sendPatientAccountNotification(
                $new_user_id,
                'created',
                ['password' => $password]
            );
        }
        
        // If the role is "patient" (role_id = 4), redirect to add new patient page
        if ($role_id == 4) {
            $_SESSION['action_message'] = "User account created successfully! Now please complete the patient profile below.";
            header("Location: admin_patients.php?action=add&user_id=$new_user_id");
            exit;
        }
        
        $action = ''; // Return to list view
    } else {
        $action_message = "Error adding user: " . $conn->error;
    }
}

// Edit user
if ($action == 'edit' && isset($_POST['edit_user'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $user_id = $_POST['user_id'];
        $name = $_POST['name'];
        $email = $_POST['email'];
        $role_id = $_POST['role_id'];
        $phone = $_POST['phone'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;

        // Update user
        $query = "UPDATE users SET name = ?, email = ?, role_id = ?, phone = ?, is_active = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssisii", $name, $email, $role_id, $phone, $is_active, $user_id);
        
        if (!$stmt->execute()) {
            throw new Exception("Error updating user: " . $conn->error);
        }
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $password, $user_id);
            if (!$stmt->execute()) {
                throw new Exception("Error updating password: " . $conn->error);
            }
        }
        
        // Check if user has associated patient record
        $check_patient = $conn->prepare("SELECT id FROM patients WHERE user_id = ?");
        $check_patient->bind_param("i", $user_id);
        $check_patient->execute();
        $patient_result = $check_patient->get_result();
        
        if ($patient_result->num_rows > 0) {
            // Extract first and last name from full name
            $name_parts = explode(' ', $name);
            $first_name = $name_parts[0];
            $last_name = count($name_parts) > 1 ? end($name_parts) : '';
            
            // Update associated patient record
            $update_patient = $conn->prepare("UPDATE patients SET first_name = ?, last_name = ?, phone_number = ? WHERE user_id = ?");
            $update_patient->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
            if (!$update_patient->execute()) {
                throw new Exception("Error updating associated patient record: " . $conn->error);
            }
        }
        
        // Send update notification
        $notification_system->sendPatientAccountNotification(
            $user_id,
            'updated',
            [
                'is_active' => $is_active,
                'password_updated' => !empty($_POST['password'])
            ]
        );
        
        // Commit transaction
        $conn->commit();
        $action_message = "User and associated records updated successfully!";
        $action = ''; // Return to list view
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $action_message = "Error: " . $e->getMessage();
    }
}

// Delete user
if ($action === 'delete' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    try {
        // Start transaction
        $conn->begin_transaction();
        
        // Get user data before deletion
        $stmt = $conn->prepare("SELECT name, email, user_type FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_data = $stmt->get_result()->fetch_assoc();
        
        // Check if user exists
        if (!$user_data) {
            throw new Exception("User not found");
        }
        
        // Send deletion notification BEFORE deleting the user
        try {
            $notification_system->sendPatientAccountNotification(
                $user_id,
                'deleted',
                []
            );
        } catch (Exception $e) {
            // Log notification error but continue with deletion
            error_log("Failed to send deletion notification: " . $e->getMessage());
        }

        // Now proceed with user deletion
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Delete associated patient record if exists
        $stmt = $conn->prepare("DELETE FROM patients WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $action_message = "User and associated records deleted successfully! A notification has been sent via email.";
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $action_message = "Error: " . $e->getMessage();
    }
    
    $action = ''; // Return to list view
}

// Fetch roles for dropdown
$stmt = $conn->prepare("SELECT id, name FROM roles ORDER BY name");
$stmt->execute();
$roles = $stmt->get_result();
$roles_array = [];
while ($role = $roles->fetch_assoc()) {
    $roles_array[$role['id']] = $role['name'];
}

// Fetch users with role names
$users_query = "SELECT u.*, r.name as role_name FROM users u JOIN roles r ON u.role_id = r.id ORDER BY u.name";
$users_result = $conn->query($users_query);

// Get user data if editing
$edit_user = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $edit_user = $stmt->get_result()->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - ImmuCare</title>
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
        
        .user-form {
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
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
        }
        
        .form-group-checkbox {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .form-group-checkbox input {
            margin-right: 10px;
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
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .users-table th,
        .users-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .users-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .user-status {
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
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit,
        .btn-delete {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
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
                    <li><a href="admin_users.php" class="active"><i class="fas fa-users"></i> User Management</a></li>
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="page-title">
                    <h2><?php echo $action == 'add' ? 'Add New User' : ($action == 'edit' ? 'Edit User' : 'User Management'); ?></h2>
                    <?php if ($action == ''): ?>
                        <a href="?action=add" class="btn-add"><i class="fas fa-user-plus"></i> Add New User</a>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $action_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'add' || $action == 'edit'): ?>
                    <div class="user-form">
                        <form method="POST" action="<?php echo $action == 'add' ? '?action=add' : '?action=edit&id='.$edit_user['id']; ?>">
                            <?php if ($action == 'edit'): ?>
                                <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                            <?php endif; ?>
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Full Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_user['name']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="password">Password <?php echo $action == 'edit' ? '(Leave blank to keep current password)' : '(Optional)'; ?></label>
                                    <input type="password" id="password" name="password">
                                </div>
                                
                                <div class="form-group">
                                    <label for="role_id">Role</label>
                                    <select id="role_id" name="role_id" required>
                                        <option value="">-- Select Role --</option>
                                        <?php foreach ($roles_array as $id => $role_name): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ($action == 'edit' && $edit_user['role_id'] == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($role_name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="text" id="phone" name="phone" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_user['phone']) : ''; ?>">
                                </div>
                                
                                <div class="form-group-checkbox">
                                    <input type="checkbox" id="is_active" name="is_active" <?php echo ($action == 'edit' && $edit_user['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <label for="is_active">Active Account</label>
                                </div>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="<?php echo $action == 'add' ? 'add_user' : 'edit_user'; ?>" class="btn-submit">
                                    <i class="fas fa-save"></i> <?php echo $action == 'add' ? 'Add User' : 'Update User'; ?>
                                </button>
                                <a href="admin_users.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="users-list">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                            <td>
                                                <span class="user-status <?php echo $user['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                                <?php if ($user['id'] != $admin_id): ?>
                                                    <a href="?action=delete&id=<?php echo $user['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this user?');"><i class="fas fa-trash"></i> Delete</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center;">No users found.</td>
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
        });
    </script>
</body>
</html> 