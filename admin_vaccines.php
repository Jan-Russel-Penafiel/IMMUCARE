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
$admin_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin';
$admin_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

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

// Process vaccine actions
$action_message = '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

// Add new vaccine
if ($action == 'add' && isset($_POST['add_vaccine'])) {
    $name = $_POST['name'];
    $manufacturer = $_POST['manufacturer'];
    $description = $_POST['description'];
    $recommended_age = $_POST['recommended_age'];
    $doses_required = $_POST['doses_required'];
    $days_between_doses = isset($_POST['days_between_doses']) ? $_POST['days_between_doses'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO vaccines (name, manufacturer, description, recommended_age, doses_required, days_between_doses, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssssiii", $name, $manufacturer, $description, $recommended_age, $doses_required, $days_between_doses, $is_active);
    
    if ($stmt->execute()) {
        $action_message = "Vaccine added successfully!";
        $action = ''; // Return to list view
    } else {
        $action_message = "Error adding vaccine: " . $conn->error;
    }
}

// Edit vaccine
if ($action == 'edit' && isset($_POST['edit_vaccine'])) {
    $vaccine_id = $_POST['vaccine_id'];
    $name = $_POST['name'];
    $manufacturer = $_POST['manufacturer'];
    $description = $_POST['description'];
    $recommended_age = $_POST['recommended_age'];
    $doses_required = $_POST['doses_required'];
    $days_between_doses = isset($_POST['days_between_doses']) ? $_POST['days_between_doses'] : null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    $query = "UPDATE vaccines SET name = ?, manufacturer = ?, description = ?, recommended_age = ?, doses_required = ?, days_between_doses = ?, is_active = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssiiii", $name, $manufacturer, $description, $recommended_age, $doses_required, $days_between_doses, $is_active, $vaccine_id);
    
    if ($stmt->execute()) {
        $action_message = "Vaccine updated successfully!";
        $action = ''; // Return to list view
    } else {
        $action_message = "Error updating vaccine: " . $conn->error;
    }
}

// Delete/deactivate vaccine
if ($action == 'delete' && isset($_GET['id'])) {
    $vaccine_id = $_GET['id'];
    
    // Check if vaccine has immunization records
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE vaccine_id = ?");
    $stmt->bind_param("i", $vaccine_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $immunization_count = $result->fetch_assoc()['count'];
    
    if ($immunization_count > 0) {
        // Don't delete, just deactivate
        $stmt = $conn->prepare("UPDATE vaccines SET is_active = 0 WHERE id = ?");
        $stmt->bind_param("i", $vaccine_id);
        
        if ($stmt->execute()) {
            $action_message = "Vaccine deactivated. Cannot delete vaccine with existing immunization records.";
        } else {
            $action_message = "Error deactivating vaccine: " . $conn->error;
        }
    } else {
        // Delete the vaccine
        $stmt = $conn->prepare("DELETE FROM vaccines WHERE id = ?");
        $stmt->bind_param("i", $vaccine_id);
        
        if ($stmt->execute()) {
            $action_message = "Vaccine deleted successfully!";
        } else {
            $action_message = "Error deleting vaccine: " . $conn->error;
        }
    }
    
    $action = ''; // Return to list view
}

// Fetch vaccines
$vaccines_query = "SELECT * FROM vaccines ORDER BY name";
$vaccines_result = $conn->query($vaccines_query);

// Get vaccine data if editing
$edit_vaccine = null;
if ($action == 'edit' && isset($_GET['id'])) {
    $vaccine_id = $_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM vaccines WHERE id = ?");
    $stmt->bind_param("i", $vaccine_id);
    $stmt->execute();
    $edit_vaccine = $stmt->get_result()->fetch_assoc();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Management - ImmuCare</title>
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
        
        .vaccine-form {
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
        
        .vaccines-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vaccines-table th,
        .vaccines-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .vaccines-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .vaccine-status {
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
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php" class="active"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="page-title">
                    <h2><?php echo $action == 'edit' ? 'Edit Vaccine' : 'Vaccine Management'; ?></h2>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $action_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($action == 'edit'): ?>
                    <div class="vaccine-form">
                        <form method="POST" action="?action=edit&id=<?php echo $edit_vaccine['id']; ?>">
                            <input type="hidden" name="vaccine_id" value="<?php echo $edit_vaccine['id']; ?>">
                            
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="name">Vaccine Name</label>
                                    <input type="text" id="name" name="name" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['name']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="manufacturer">Manufacturer</label>
                                    <input type="text" id="manufacturer" name="manufacturer" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['manufacturer']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="recommended_age">Recommended Age</label>
                                    <input type="text" id="recommended_age" name="recommended_age" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['recommended_age']) : ''; ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="doses_required">Doses Required</label>
                                    <input type="number" id="doses_required" name="doses_required" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['doses_required']) : '1'; ?>" min="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="days_between_doses">Days Between Doses</label>
                                    <input type="number" id="days_between_doses" name="days_between_doses" value="<?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['days_between_doses']) : '0'; ?>" min="0">
                                </div>
                                
                                <div class="form-group-checkbox">
                                    <input type="checkbox" id="is_active" name="is_active" <?php echo ($action == 'edit' && isset($edit_vaccine['is_active']) && $edit_vaccine['is_active'] == 1) ? 'checked' : ''; ?>>
                                    <label for="is_active">Active</label>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description"><?php echo $action == 'edit' ? htmlspecialchars($edit_vaccine['description']) : ''; ?></textarea>
                            </div>
                            
                            <div class="form-buttons">
                                <button type="submit" name="edit_vaccine" class="btn-submit">
                                    <i class="fas fa-save"></i> Update Vaccine
                                </button>
                                <a href="admin_vaccines.php" class="btn-cancel">Cancel</a>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="search-bar">
                        <input type="text" id="vaccine-search" placeholder="Search vaccines by name or disease...">
                        <button type="button"><i class="fas fa-search"></i></button>
                    </div>
                    
                    <div class="vaccines-list">
                        <table class="vaccines-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Recommended Age</th>
                                    <th>Doses</th>
                                    <th>Days Between</th>
                                    <th>Manufacturer</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($vaccines_result->num_rows > 0): ?>
                                    <?php while ($vaccine = $vaccines_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($vaccine['name']); ?></td>
                                            <td><?php echo htmlspecialchars($vaccine['recommended_age']); ?></td>
                                            <td><?php echo htmlspecialchars($vaccine['doses_required']); ?></td>
                                            <td><?php echo isset($vaccine['days_between_doses']) ? htmlspecialchars($vaccine['days_between_doses']) : 'N/A'; ?></td>
                                            <td><?php echo htmlspecialchars($vaccine['manufacturer']); ?></td>
                                            <td>
                                                <span class="vaccine-status <?php echo isset($vaccine['is_active']) && $vaccine['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                                    <?php echo isset($vaccine['is_active']) && $vaccine['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td class="action-buttons">
                                                <a href="?action=edit&id=<?php echo $vaccine['id']; ?>" class="btn-edit"><i class="fas fa-edit"></i> Edit</a>
                                                <a href="?action=delete&id=<?php echo $vaccine['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this vaccine?');"><i class="fas fa-trash"></i> Delete</a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center;">No vaccines found.</td>
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
            
            // Simple vaccine search functionality
            const searchInput = document.getElementById('vaccine-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function() {
                    const searchValue = this.value.toLowerCase();
                    const rows = document.querySelectorAll('.vaccines-table tbody tr');
                    
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
        });
    </script>
</body>
</html> 