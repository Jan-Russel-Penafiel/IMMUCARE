<?php
session_start();
require 'config.php';

// Check if user is logged in and is a midwife
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'midwife') {
    header('Location: login.php');
    exit;
}

// Get midwife information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get statistics for midwife dashboard
// Count patients
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients");
$stmt->execute();
$result = $stmt->get_result();
$patient_count = $result->fetch_assoc()['count'];

// Count upcoming appointments
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE staff_id = ? AND appointment_date > NOW() AND status = 'confirmed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment_count = $result->fetch_assoc()['count'];

// Get recent appointments
$stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name FROM appointments a 
                        JOIN patients p ON a.patient_id = p.id 
                        WHERE a.staff_id = ? AND a.appointment_date > NOW() 
                        ORDER BY a.appointment_date ASC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_appointments = $stmt->get_result();

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
    <title>Midwife Dashboard - ImmuCare</title>
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
        
        .welcome-section {
            margin-bottom: 30px;
        }
        
        .welcome-section h2 {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05);
        }
        
        .stat-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .appointment-list {
            margin-top: 20px;
        }
        
        .appointment-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .appointment-item:hover {
            background-color: #f8f9fa;
        }
        
        .appointment-icon {
            width: 45px;
            height: 45px;
            background-color: #e8f0fe;
            color: var(--primary-color);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            font-size: 1.2rem;
        }
        
        .appointment-details {
            flex-grow: 1;
        }
        
        .appointment-patient {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .appointment-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .appointment-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: var(--border-radius);
            width: 80%;
            max-width: 600px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 24px;
            cursor: pointer;
            color: #666;
        }

        .close-modal:hover {
            color: #333;
        }

        .appointment-details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .detail-item {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .detail-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 5px;
        }

        .detail-value {
            color: var(--text-color);
        }

        /* Additional Modal Styles for Reschedule */
        .modal-form {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input, 
        .form-group select, 
        .form-group textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .modal-footer {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-cancel {
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-reschedule {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }

        .btn-reschedule:hover {
            opacity: 0.9;
        }

        .alert {
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            display: none;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
                    <li><a href="midwife_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="midwife_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="midwife_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p>Here's an overview of your patients and upcoming appointments.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-injured"></i>
                        </div>
                        <div class="stat-value"><?php echo $patient_count; ?></div>
                        <div class="stat-label">Total Patients</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $appointment_count; ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clipboard-list"></i>
                        </div>
                        <div class="stat-value">15</div>
                        <div class="stat-label">Pending Tasks</div>
                    </div>
                </div>
                
                <h3 class="section-title">Upcoming Appointments</h3>
                <div class="appointment-list">
                    <?php if ($recent_appointments->num_rows > 0): ?>
                        <?php while ($appointment = $recent_appointments->fetch_assoc()): ?>
                            <div class="appointment-item">
                                <div class="appointment-icon">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="appointment-details">
                                    <div class="appointment-patient">
                                        <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                    </div>
                                    <div class="appointment-info">
                                        <?php echo date('F j, Y - g:i A', strtotime($appointment['appointment_date'])); ?> | 
                                        <?php echo htmlspecialchars($appointment['purpose']); ?>
                                    </div>
                                </div>
                                <div class="appointment-actions">
                                    <button onclick="viewAppointment(
                                        <?php echo $appointment['id']; ?>,
                                        '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name'], ENT_QUOTES); ?>',
                                        '<?php echo date('F j, Y - g:i A', strtotime($appointment['appointment_date'])); ?>',
                                        '<?php echo htmlspecialchars($appointment['purpose'], ENT_QUOTES); ?>'
                                    )" class="btn btn-primary btn-sm">View</button>
                                    <button onclick="openRescheduleModal(
                                        <?php echo $appointment['id']; ?>,
                                        '<?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name'], ENT_QUOTES); ?>',
                                        '<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>'
                                    )" class="btn btn-outline btn-sm">Reschedule</button>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No upcoming appointments.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Modal Structure -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3>Appointment Details</h3>
            <div id="appointmentDetails" class="appointment-details-grid">
                <!-- Details will be populated dynamically -->
            </div>
        </div>
    </div>

    <!-- Add Reschedule Modal -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeRescheduleModal()">&times;</span>
            <h3>Reschedule Appointment</h3>
            <div id="alertMessage" class="alert"></div>
            <form id="rescheduleForm" class="modal-form" onsubmit="submitReschedule(event)">
                <input type="hidden" id="appointmentId" name="appointmentId">
                <div class="form-group">
                    <label for="patientNameDisplay">Patient</label>
                    <input type="text" id="patientNameDisplay" readonly>
                </div>
                <div class="form-group">
                    <label for="newDate">New Appointment Date & Time</label>
                    <input type="datetime-local" id="newDate" name="newDate" required>
                </div>
                <div class="form-group">
                    <label for="reason">Reason for Rescheduling</label>
                    <textarea id="reason" name="reason" rows="3" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="btn-reschedule">Confirm Reschedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Function to open modal and load appointment details
        function viewAppointment(appointmentId, patientName, appointmentDate, purpose) {
            const modal = document.getElementById('appointmentModal');
            const detailsContainer = document.getElementById('appointmentDetails');
            
            // Format the appointment details
            const details = `
                <div class="detail-item">
                    <div class="detail-label">Patient Name</div>
                    <div class="detail-value">${patientName}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Appointment Date</div>
                    <div class="detail-value">${appointmentDate}</div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Purpose</div>
                    <div class="detail-value">${purpose}</div>
                </div>
            `;
            
            detailsContainer.innerHTML = details;
            modal.style.display = 'block';
        }

        // Close modal when clicking the close button or outside the modal
        document.querySelector('.close-modal').onclick = function() {
            document.getElementById('appointmentModal').style.display = 'none';
        }

        // Function to open reschedule modal
        function openRescheduleModal(appointmentId, patientName, currentDate) {
            const modal = document.getElementById('rescheduleModal');
            document.getElementById('appointmentId').value = appointmentId;
            document.getElementById('patientNameDisplay').value = patientName;
            
            // Set minimum date to today
            const today = new Date();
            const todayStr = today.toISOString().slice(0, 16);
            document.getElementById('newDate').min = todayStr;
            
            // Set current appointment date as default
            const currentDateTime = new Date(currentDate);
            const currentDateStr = currentDateTime.toISOString().slice(0, 16);
            document.getElementById('newDate').value = currentDateStr;
            
            modal.style.display = 'block';
        }

        function closeRescheduleModal() {
            const modal = document.getElementById('rescheduleModal');
            modal.style.display = 'none';
            document.getElementById('alertMessage').style.display = 'none';
            document.getElementById('rescheduleForm').reset();
        }

        function submitReschedule(event) {
            event.preventDefault();
            const form = event.target;
            const formData = new FormData(form);

            // Send AJAX request to process_appointment.php
            fetch('process_appointment.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const alertDiv = document.getElementById('alertMessage');
                alertDiv.style.display = 'block';
                
                if (data.success) {
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = 'Appointment rescheduled successfully!';
                    // Reload the page after 2 seconds
                    setTimeout(() => {
                        window.location.reload();
                    }, 2000);
                } else {
                    alertDiv.className = 'alert alert-error';
                    alertDiv.textContent = data.message || 'Error rescheduling appointment.';
                }
            })
            .catch(error => {
                const alertDiv = document.getElementById('alertMessage');
                alertDiv.style.display = 'block';
                alertDiv.className = 'alert alert-error';
                alertDiv.textContent = 'An error occurred. Please try again.';
            });
        }

        // Update window click handler to handle both modals
        window.onclick = function(event) {
            const viewModal = document.getElementById('appointmentModal');
            const rescheduleModal = document.getElementById('rescheduleModal');
            
            if (event.target == viewModal) {
                viewModal.style.display = 'none';
            }
            if (event.target == rescheduleModal) {
                closeRescheduleModal();
            }
        }
    </script>
</body>
</html> 