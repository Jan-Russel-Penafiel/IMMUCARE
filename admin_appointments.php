<?php
session_start();
require 'config.php';
require_once 'notification_system.php';

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
    
    // Get appointment and patient details
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name, 
               p.last_name, 
               p.phone_number,
               u.email,
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
    
    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointments SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $notes, $appointment_id);
    
    if ($stmt->execute()) {
        // Send notification using the notification system
        $patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
        $appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
        $purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
        
        $status_message = "Your appointment status has been updated.\n\n" .
                         "Appointment Details:\n" .
                         "- Purpose: " . $purpose . "\n" .
                         "- Date: " . $appointment_date . "\n" .
                         "- Time: " . $appointment_time . "\n" .
                         "- New Status: " . ucfirst($status) . "\n\n" .
                         $this->getStatusSpecificMessage($status) . "\n" .
                         (!empty($notes) ? "\nAdditional Notes: " . $notes . "\n" : "") .
                         "\nIf you have any questions or need to make changes, please contact us.";
        
        $notification_system->sendCustomNotification(
            $appointment_data['user_id'],
            "Appointment Status Update: " . ucfirst($status),
            $status_message,
            'both'
        );
        
        $_SESSION['action_message'] = "Appointment status updated successfully! Notifications sent via Email and SMS.";
        
        // Redirect to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    } else {
        $_SESSION['action_message'] = "Error updating appointment status: " . $conn->error;
        header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
        exit;
    }
}

// Assign staff to appointment
if (isset($_POST['assign_staff'])) {
    $appointment_id = $_POST['appointment_id'];
    $staff_id = $_POST['staff_id'];
    
    // Get appointment details before update
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name, 
               p.last_name,
               u.id as user_id,
               s.name as staff_name,
               v.name as vaccine_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users u ON p.user_id = u.id
        LEFT JOIN users s ON s.id = ?
        LEFT JOIN vaccines v ON a.vaccine_id = v.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("ii", $staff_id, $appointment_id);
    $stmt->execute();
    $appointment_data = $stmt->get_result()->fetch_assoc();
    
    $stmt = $conn->prepare("UPDATE appointments SET staff_id = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ii", $staff_id, $appointment_id);
    
    if ($stmt->execute()) {
        // Send notification about staff assignment
        $patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
        $appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
        $purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
        
        $assign_message = "A healthcare provider has been assigned to your appointment.\n\n" .
                         "Appointment Details:\n" .
                         "- Purpose: " . $purpose . "\n" .
                         "- Date: " . $appointment_date . "\n" .
                         "- Time: " . $appointment_time . "\n" .
                         "- Healthcare Provider: " . $appointment_data['staff_name'] . "\n" .
                         "- Provider Role: " . ucfirst($appointment_data['staff_type']) . "\n\n" .
                         "Your provider has been notified and will be prepared for your visit. " .
                         "If you need to reschedule or have any questions, please contact us.";
        
        $notification_system->sendCustomNotification(
            $appointment_data['user_id'],
            "Healthcare Provider Assigned to Your Appointment",
            $assign_message,
            'both'
        );
        
        $_SESSION['action_message'] = "Staff assigned successfully! Notifications sent via Email and SMS.";
    } else {
        $_SESSION['action_message'] = "Error assigning staff: " . $conn->error;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

// Delete appointment
if ($action == 'delete' && isset($_GET['id'])) {
    $appointment_id = $_GET['id'];
    
    // Get appointment details before deletion
    $stmt = $conn->prepare("
        SELECT a.*, 
               p.first_name, 
               p.last_name,
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
    $appointment_data = $stmt->get_result()->fetch_assoc();
    
    // Send cancellation notification before deleting
    if ($appointment_data) {
        $patient_name = $appointment_data['first_name'] . ' ' . $appointment_data['last_name'];
        $appointment_date = date('l, F j, Y', strtotime($appointment_data['appointment_date']));
        $appointment_time = date('h:i A', strtotime($appointment_data['appointment_date']));
        $purpose = !empty($appointment_data['vaccine_name']) ? $appointment_data['vaccine_name'] . ' vaccination' : $appointment_data['purpose'];
        
        $cancel_message = "Important Notice: Your appointment has been cancelled.\n\n" .
                         "Cancelled Appointment Details:\n" .
                         "- Purpose: " . $purpose . "\n" .
                         "- Date: " . $appointment_date . "\n" .
                         "- Time: " . $appointment_time . "\n\n" .
                         "This means:\n" .
                         "- Your scheduled slot has been released\n" .
                         "- Any preparations for this appointment should be discontinued\n" .
                         "- You will need to schedule a new appointment if needed\n\n" .
                         "If you need to reschedule or believe this was done in error, " .
                         "please contact our scheduling team at " . SCHEDULING_PHONE . " " .
                         "or visit our online scheduling portal.";
        
        $notification_system->sendCustomNotification(
            $appointment_data['user_id'],
            "Appointment Cancellation Notice",
            $cancel_message,
            'both'
        );
    }
    
    $stmt = $conn->prepare("DELETE FROM appointments WHERE id = ?");
    $stmt->bind_param("i", $appointment_id);
    
    if ($stmt->execute()) {
        if ($appointment_data) {
            $_SESSION['action_message'] = "Appointment deleted successfully! Notifications sent via Email and SMS.";
        } else {
            $_SESSION['action_message'] = "Appointment deleted successfully! (Notifications failed)";
        }
    } else {
        $_SESSION['action_message'] = "Error deleting appointment: " . $conn->error;
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($_GET));
    exit;
}

// Filter appointments
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$staff_filter = isset($_GET['staff']) ? $_GET['staff'] : '';

// Initialize params array for prepared statement
$params = array();

$query = "SELECT a.*, 
         CONCAT(p.first_name, ' ', p.last_name) as patient_name,
         p.phone_number as patient_phone,
         u.name as staff_name,
         u.user_type as staff_type,
         v.name as vaccine_name 
         FROM appointments a 
         LEFT JOIN patients p ON a.patient_id = p.id 
         LEFT JOIN users u ON a.staff_id = u.id
         LEFT JOIN vaccines v ON a.vaccine_id = v.id 
         WHERE 1=1";

// Apply filters
if (!empty($status_filter)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
}

if (!empty($date_filter)) {
    $query .= " AND DATE(a.appointment_date) = ?";
    $params[] = $date_filter;
}

if (!empty($staff_filter)) {
    if ($staff_filter === 'unassigned') {
        $query .= " AND a.staff_id IS NULL";
    } else {
        $query .= " AND a.staff_id = ?";
        $params[] = $staff_filter;
    }
}

$query .= " ORDER BY a.appointment_date DESC";

// Prepare and execute the query with parameters
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$appointments_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e1e4e8; }
        .dashboard-logo { display: flex; align-items: center; }
        .dashboard-logo img { height: 40px; margin-right: 10px; }
        .dashboard-logo h1 { font-size: 1.8rem; color: var(--primary-color); margin: 0; }
        .user-menu { display: flex; align-items: center; }
        .user-info { margin-right: 20px; text-align: right; }
        .user-name { font-weight: 600; color: var(--text-color); }
        .user-role { font-size: 0.8rem; color: var(--primary-color); font-weight: 500; text-transform: uppercase; }
        .user-email { font-size: 0.9rem; color: var(--light-text); }
        .logout-btn { padding: 8px 15px; background-color: #f1f3f5; color: var(--text-color); border-radius: 5px; font-size: 0.9rem; transition: var(--transition); }
        .logout-btn:hover { background-color: #e9ecef; }
        .dashboard-content { display: grid; grid-template-columns: 1fr 4fr; gap: 30px; }
        .sidebar { background-color: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 20px; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 15px; border-radius: var(--border-radius); color: var(--text-color); transition: var(--transition); text-decoration: none; }
        .sidebar-menu a:hover { background-color: #f1f8ff; color: var(--primary-color); }
        .sidebar-menu a.active { background-color: #e8f0fe; color: var(--primary-color); font-weight: 500; }
        .sidebar-menu i { margin-right: 10px; font-size: 1.1rem; width: 20px; text-align: center; }
        .main-content { background-color: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 30px; }
        .page-title { font-size: 1.8rem; color: var(--primary-color); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .btn-add { background-color: var(--primary-color); color: white; padding: 8px 15px; border-radius: 5px; text-decoration: none; font-size: 0.9rem; transition: var(--transition); }
        .btn-add:hover { background-color: #3367d6; }
        .alert { padding: 15px; border-radius: var(--border-radius); margin-bottom: 20px; font-size: 0.95rem; }
        .alert-success { background-color: #e8f5e9; color: #2e7d32; border: 1px solid #a5d6a7; }
        .alert-error { background-color: #ffebee; color: #c62828; border: 1px solid #ef9a9a; }
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
        .status-no-show { background-color: #fafafa; color: #757575; }
        .action-buttons { display: flex; gap: 10px; }
        .btn-view, .btn-edit, .btn-delete { padding: 5px 10px; border-radius: 5px; font-size: 0.8rem; text-decoration: none; transition: var(--transition); }
        .btn-view { background-color: #e8f5e9; color: #2e7d32; }
        .btn-view:hover { background-color: #c8e6c9; }
        .btn-edit { background-color: #e3f2fd; color: #1976d2; }
        .btn-edit:hover { background-color: #bbdefb; }
        .btn-delete { background-color: #ffebee; color: #c62828; }
        .btn-delete:hover { background-color: #ffcdd2; }
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
        @media screen and (max-width: 992px) { .dashboard-content { grid-template-columns: 1fr; } .sidebar { margin-bottom: 20px; } .modal-content { width: 70%; } }
        @media screen and (max-width: 768px) { .dashboard-header { flex-direction: column; align-items: flex-start; } .user-menu { margin-top: 20px; align-self: flex-end; } .filter-bar { flex-direction: column; align-items: flex-start; } .filter-group { width: 100%; } .action-buttons { flex-direction: column; } .modal-content { width: 90%; } }
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
                    <li><a href="admin_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="page-title">
                    <h2>Appointment Management</h2>
                </div>
                
                <?php if (!empty($action_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $action_message; ?>
                    </div>
                <?php endif; ?>
                
                <form action="" method="GET" class="filter-bar">
                    <div class="filter-group">
                        <label for="status">Status:</label>
                        <select id="status" name="status">
                            <option value="">All Statuses</option>
                            <option value="requested" <?php echo $status_filter == 'requested' ? 'selected' : ''; ?>>Requested</option>
                            <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo $status_filter == 'no_show' ? 'selected' : ''; ?>>No-Show</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="date">Date:</label>
                        <input type="date" id="date" name="date" value="<?php echo $date_filter; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="staff">Staff:</label>
                        <select id="staff" name="staff">
                            <option value="">All Staff</option>
                            <option value="unassigned">Unassigned</option>
                            <?php
                            // Fetch staff options
                            $staff_query = "SELECT id, name FROM users WHERE user_type IN ('midwife', 'nurse') ORDER BY name";
                            $staff_result = $conn->query($staff_query);
                            if ($staff_result && $staff_result->num_rows > 0) {
                                while ($staff = $staff_result->fetch_assoc()) {
                                    $selected = ($staff_filter == $staff['id']) ? 'selected' : '';
                                    echo "<option value=\"{$staff['id']}\" {$selected}>" . htmlspecialchars($staff['name']) . "</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="filter-btn"><i class="fas fa-filter"></i> Filter</button>
                    <a href="admin_appointments.php" class="reset-btn"><i class="fas fa-redo"></i> Reset</a>
                </form>
                
                <div class="appointments-list">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Staff</th>
                                <th>Date & Time</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
                                <?php while ($appointment = $appointments_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $appointment['id']; ?></td>
                                        <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['staff_name'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($appointment['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                        <td>
                                            <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <button type="button" class="btn-edit" onclick="openStatusModal(<?php echo $appointment['id']; ?>, '<?php echo $appointment['status']; ?>', '<?php echo htmlspecialchars($appointment['notes'] ?? '', ENT_QUOTES); ?>')">
                                                <i class="fas fa-edit"></i> Update Status
                                            </button>
                                            <a href="?action=delete&id=<?php echo $appointment['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this appointment?');">
                                                <i class="fas fa-trash"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center;">No appointments found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
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
    <?php $conn->close(); ?>
</body>
</html> 