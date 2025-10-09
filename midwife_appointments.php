<?php
session_start();
require_once 'config.php';

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'midwife') {
    header('Location: login.php');
    exit();
}

// Get the logged-in midwife's ID and info
$midwife_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Handle appointment status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';

    $update_query = "UPDATE appointments SET 
                    status = ?, 
                    notes = ?,
                    staff_id = ?,
                    updated_at = NOW() 
                    WHERE id = ?";
    
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param('ssii', $new_status, $notes, $midwife_id, $appointment_id);
    
    if ($stmt->execute()) {
        // Send notification to patient
        $notification_query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                             SELECT p.user_id,
                                    'Appointment Update',
                                    CONCAT('Your appointment status has been updated to: ', ?, ' Notes: ', ?),
                                    'system',
                                    NOW()
                             FROM appointments a
                             JOIN patients p ON a.patient_id = p.id
                             WHERE a.id = ?";
        
        $stmt_notif = $conn->prepare($notification_query);
        $stmt_notif->bind_param('ssi', $new_status, $notes, $appointment_id);
        $stmt_notif->execute();
        
        $_SESSION['success_message'] = "Appointment updated successfully!";
    } else {
        $_SESSION['error_message'] = "Error updating appointment.";
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get all appointments for the midwife with patient details
$query = "SELECT 
            a.*,
            p.first_name,
            p.middle_name,
            p.last_name,
            p.date_of_birth,
            p.phone_number,
            v.name as vaccine_name
          FROM appointments a
          JOIN patients p ON a.patient_id = p.id
          LEFT JOIN vaccines v ON a.vaccine_id = v.id
          WHERE (a.staff_id = ? OR a.staff_id IS NULL)
          ORDER BY a.appointment_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $midwife_id);
$stmt->execute();
$result = $stmt->get_result();
$appointments = $result->fetch_all(MYSQLI_ASSOC);

// Process logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
    header('Location: login.php');
    exit;
}

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

        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
        }

        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border-color: #c3e6cb;
        }

        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
        }

        .appointment-list {
            margin-top: 20px;
        }

        .appointment-item {
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: var(--transition);
        }

        .appointment-item:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .appointment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .appointment-date {
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--primary-color);
        }

        .appointment-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-requested { background-color: #fff3cd; color: #856404; }
        .status-confirmed { background-color: #cce5ff; color: #004085; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-cancelled { background-color: #f8d7da; color: #721c24; }
        .status-no_show { background-color: #e2e3e5; color: #383d41; }

        .patient-info {
            margin: 15px 0;
        }

        .patient-name {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 5px;
        }

        .patient-details {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        .appointment-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: #0056b3;
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

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: hidden;
        }

        .modal.show {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            padding-top: 2rem;
        }

        .modal-dialog {
            position: relative;
            width: 100%;
            max-width: 500px;
            margin: 0 1rem;
        }

        .modal-content {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background-color: #4285f4;
            padding: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border: none;
        }

        .modal-title {
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-body {
            padding: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 4px;
        }

        .form-group p {
            font-size: 1rem;
            color: #333;
            padding: 0.25rem 0;
            margin: 0;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem;
            font-size: 0.95rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.2s;
            background-color: #fff;
        }

        .form-control:focus {
            border-color: #4285f4;
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.1);
            outline: none;
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%234285f4'%3e%3cpath d='M7 10l5 5 5-5z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.5rem center;
            background-size: 1.2rem;
            padding-right: 2rem;
        }

        textarea.form-control {
            min-height: 80px;
            resize: vertical;
        }

        .modal-footer {
            padding: 1rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 0.5rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-secondary {
            background-color: #f5f5f5;
            color: #333;
        }

        .btn-secondary:hover {
            background-color: #e9e9e9;
        }

        .btn-primary {
            background-color: #4285f4;
            color: white;
        }

        .btn-primary:hover {
            background-color: #3b78e7;
        }

        .close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            opacity: 0.8;
            cursor: pointer;
            padding: 0;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            opacity: 1;
        }

        /* Status Colors in Modal */
        #modalStatus {
            padding: 0.5rem;
        }
        #modalStatus option {
            padding: 4px 8px;
            font-size: 0.95rem;
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
                    <li><a href="midwife_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Appointment Management</h2>

                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success_message'];
                        unset($_SESSION['success_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error_message'];
                        unset($_SESSION['error_message']);
                        ?>
                    </div>
                <?php endif; ?>

                <div class="appointment-list">
                    <?php if ($result->num_rows > 0): ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <div class="appointment-item">
                                <div class="appointment-header">
                                    <div class="appointment-date">
                                        <i class="far fa-calendar-alt"></i>
                                        <?php echo date('F j, Y - g:i A', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    <span class="appointment-status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                <div class="patient-info">
                                    <div class="patient-name">
                                        <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['middle_name'] . ' ' . $appointment['last_name']); ?>
                                    </div>
                                    <div class="patient-details">
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['phone_number']); ?>
                                        <br>
                                        <i class="fas fa-notes-medical"></i> Purpose: <?php echo htmlspecialchars($appointment['purpose']); ?>
                                        <?php if ($appointment['vaccine_name']): ?>
                                            <br>
                                            <i class="fas fa-syringe"></i> Vaccine: <?php echo htmlspecialchars($appointment['vaccine_name']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <button class="btn btn-primary" onclick="openUpdateModal(<?php echo htmlspecialchars(json_encode($appointment)); ?>)">
                                        <i class="fas fa-edit"></i> Update Status
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="far fa-calendar-times"></i>
                            <p>No appointments found</p>
                            <span>There are no appointments scheduled at this time.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Appointment Modal -->
    <div class="modal fade" id="updateAppointmentModal" tabindex="-1" role="dialog" aria-labelledby="updateAppointmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateAppointmentModalLabel">
                        <i class="fas fa-calendar-check"></i> Update Appointment
                    </h5>
                    <button type="button" class="close" onclick="closeModal()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="appointment_id" id="modalAppointmentId">
                        
                        <div class="form-group">
                            <label>
                                <i class="fas fa-user"></i> Patient Name
                            </label>
                            <p id="modalPatientName"></p>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <i class="far fa-calendar-alt"></i> Appointment Date
                            </label>
                            <p id="modalAppointmentDate"></p>
                        </div>
                        
                        <div class="form-group">
                            <label for="modalStatus">
                                <i class="fas fa-tag"></i> Status
                            </label>
                            <select class="form-control" name="status" id="modalStatus" required>
                                <option value="requested">Requested</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no_show">No Show</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="modalNotes">
                                <i class="fas fa-notes-medical"></i> Notes
                            </label>
                            <textarea class="form-control" name="notes" id="modalNotes" rows="3" 
                                    placeholder="Enter any additional notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">
                            Cancel
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script>
        // Function to open modal
        function openUpdateModal(appointment) {
            document.getElementById('modalAppointmentId').value = appointment.id;
            document.getElementById('modalPatientName').textContent = 
                `${appointment.first_name} ${appointment.middle_name} ${appointment.last_name}`;
            document.getElementById('modalAppointmentDate').textContent = 
                new Date(appointment.appointment_date).toLocaleString();
            document.getElementById('modalStatus').value = appointment.status;
            document.getElementById('modalNotes').value = appointment.notes || '';
            
            const modal = document.getElementById('updateAppointmentModal');
            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        // Function to close modal
        function closeModal() {
            const modal = document.getElementById('updateAppointmentModal');
            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('updateAppointmentModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>