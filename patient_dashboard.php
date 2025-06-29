<?php
session_start();
require 'config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: login.php');
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patient details
$stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$patient_id = $patient ? $patient['id'] : null;



// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT a.*, v.name as vaccine_name, u.name as staff_name 
    FROM appointments a 
    LEFT JOIN vaccines v ON a.vaccine_id = v.id 
    LEFT JOIN users u ON a.staff_id = u.id 
    WHERE a.patient_id = ? AND a.status IN ('requested', 'confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();

// Get recent immunizations
$stmt = $conn->prepare("
    SELECT i.*, v.name as vaccine_name, u.name as administered_by_name
    FROM immunizations i
    JOIN vaccines v ON i.vaccine_id = v.id
    JOIN users u ON i.administered_by = u.id
    WHERE i.patient_id = ?
    ORDER BY i.administered_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$recent_immunizations = $stmt->get_result();

// Get due immunizations
$stmt = $conn->prepare("
    SELECT v.id, v.name, v.recommended_age, v.doses_required,
    (SELECT COUNT(*) FROM immunizations i WHERE i.patient_id = ? AND i.vaccine_id = v.id) as doses_received
    FROM vaccines v
    HAVING doses_received < doses_required
    ORDER BY v.id
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$due_immunizations = $stmt->get_result();

// Get notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

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
    <title>Patient Dashboard - <?php echo APP_NAME; ?></title>
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
            grid-template-columns: 1fr 3fr;
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
        
        .section {
            margin-bottom: 30px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .section h3 {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin: 0;
        }
        
        .view-all {
            color: var(--primary-color);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .appointment-card {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            transition: var(--transition);
        }
        
        .appointment-card:hover {
            background-color: #f1f8ff;
        }
        
        .appointment-date {
            font-size: 0.9rem;
            color: var(--primary-color);
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .appointment-purpose {
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .appointment-details {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
            color: var(--light-text);
        }
        
        .immunization-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .immunization-table th, .immunization-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e1e4e8;
        }
        
        .immunization-table th {
            font-weight: 600;
            color: var(--primary-color);
            background-color: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-confirmed {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .badge-requested {
            background-color: #fff8e1;
            color: #ff8f00;
        }
        
        .badge-completed {
            background-color: #e8f5e9;
            color: #388e3c;
        }
        
        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #e1e4e8;
            transition: var(--transition);
        }
        
        .notification-item:hover {
            background-color: #f1f8ff;
        }
        
        .notification-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-message {
            font-size: 0.9rem;
            color: var(--light-text);
            margin-bottom: 5px;
        }
        
        .notification-time {
            font-size: 0.8rem;
            color: var(--light-text);
        }
        
        .profile-section {
            display: flex;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .info-group {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--light-text);
            margin-bottom: 5px;
        }
        
        .info-value {
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .profile-section {
                flex-direction: column;
            }
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        
        .alert-info {
            color: #0c5460;
            background-color: #d1ecf1;
            border-color: #bee5eb;
        }
        
        .alert ul {
            margin-top: 10px;
            margin-bottom: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="dashboard-logo">
                <img src="images/logo.svg" alt="<?php echo APP_NAME; ?> Logo">
                <h1><?php echo APP_NAME; ?></h1>
            </div>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
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
                    <li><a href="#dashboard" class="nav-link active" data-section="dashboard"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="#profile" class="nav-link" data-section="profile"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="#appointments" class="nav-link" data-section="appointments"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="#immunizations" class="nav-link" data-section="immunizations"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="#notifications" class="nav-link" data-section="notifications"><i class="fas fa-bell"></i> Notifications</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <!-- Dashboard Section -->
                <div class="content-section active" id="dashboard-section">
                <div class="welcome-section">
                        <h2>Welcome, <?php echo htmlspecialchars($patient ? $patient['first_name'] : $user_name); ?>!</h2>
                    <p>Here's an overview of your health information and upcoming appointments.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE patient_id = ?");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $immunization_count = $result->fetch_assoc()['count'];
                        ?>
                        <div class="stat-value"><?php echo $immunization_count; ?></div>
                        <div class="stat-label">Immunizations</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'confirmed'");
                        $stmt->bind_param("i", $patient_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $appointment_count = $result->fetch_assoc()['count'];
                        ?>
                        <div class="stat-value"><?php echo $appointment_count; ?></div>
                        <div class="stat-label">Upcoming Appointments</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-bell"></i>
                        </div>
                        <?php
                        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                        $stmt->bind_param("i", $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $notification_count = $result->fetch_assoc()['count'];
                        ?>
                        <div class="stat-value"><?php echo $notification_count; ?></div>
                        <div class="stat-label">Unread Notifications</div>
                    </div>
                    </div>
                </div>

                <!-- Profile Section -->
                <div class="content-section" id="profile-section">
                    <div class="section-header">
                        <h3>My Profile</h3>
                        <?php if (!$patient): ?>
                            <button id="create-profile-btn" class="btn btn-primary">Create Profile</button>
                        <?php else: ?>
                            <button id="edit-profile-btn" class="btn btn-primary">Edit Profile</button>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($patient): ?>
                    <div class="profile-info">
                        <div class="info-group">
                            <div class="info-label">Full Name</div>
                            <div class="info-value">
                                <?php 
                                    echo htmlspecialchars($patient['first_name'] . ' ' . 
                                        ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . 
                                        $patient['last_name']); 
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Date of Birth</div>
                            <div class="info-value">
                                <?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?>
                                (<?php 
                                    $dob = new DateTime($patient['date_of_birth']);
                                    $now = new DateTime();
                                    echo $now->diff($dob)->y . ' years old';
                                ?>)
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Gender</div>
                            <div class="info-value"><?php echo ucfirst($patient['gender']); ?></div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Contact Information</div>
                            <div class="info-value">
                                <div><?php echo htmlspecialchars($patient['phone_number']); ?></div>
                                <div><?php echo htmlspecialchars($user_email); ?></div>
                            </div>
                        </div>
                        
                        <div class="info-group">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?php 
                                    $address_parts = array_filter([
                                        $patient['purok'],
                                        $patient['city'],
                                        $patient['province'],
                                        $patient['postal_code']
                                    ]);
                                    echo htmlspecialchars(implode(', ', $address_parts));
                                ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($patient['medical_history'])): ?>
                        <div class="info-group">
                            <div class="info-label">Medical History</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($patient['allergies'])): ?>
                        <div class="info-group">
                            <div class="info-label">Allergies</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="profile-info">
                        <div class="alert alert-info">
                            <p>Your patient profile has not been created yet. Please click the "Create Profile" button above to set up your profile.</p>
                            <p>This will allow you to:</p>
                            <ul>
                                <li>Schedule appointments</li>
                                <li>Track your immunizations</li>
                                <li>Receive important health notifications</li>
                            </ul>
                        </div>
                        <div class="info-group">
                            <div class="info-label">Account Information</div>
                            <div class="info-value">
                                <div>Name: <?php echo htmlspecialchars($user_name); ?></div>
                                <div>Email: <?php echo htmlspecialchars($user_email); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Appointments Section -->
                <div class="content-section" id="appointments-section">
                    <div class="section-header">
                        <h3>My Appointments</h3>
                        <button id="new-appointment-btn" class="btn btn-primary">Schedule New Appointment</button>
                    </div>
                    
                    <div class="appointments-list">
                    <?php if ($upcoming_appointments->num_rows > 0): ?>
                        <?php while ($appointment = $upcoming_appointments->fetch_assoc()): ?>
                            <div class="appointment-card">
                                <div class="appointment-date">
                                    <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?> at 
                                    <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                </div>
                                <div class="appointment-purpose">
                                    <?php echo htmlspecialchars($appointment['purpose']); ?>
                                    <?php if ($appointment['vaccine_id']): ?>
                                        - <?php echo htmlspecialchars($appointment['vaccine_name']); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="appointment-details">
                                    <span>
                                        <i class="fas fa-map-marker-alt"></i> 
                                        <?php echo htmlspecialchars($appointment['location']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-user-md"></i> 
                                        <?php echo $appointment['staff_name'] ? htmlspecialchars($appointment['staff_name']) : 'Not assigned'; ?>
                                    </span>
                                        <span class="badge badge-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                            <p>No upcoming appointments scheduled.</p>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- Immunizations Section -->
                <div class="content-section" id="immunizations-section">
                    <div class="section-header">
                        <h3>Immunization Records</h3>
                    </div>
                    
                    <div class="immunizations-content">
                        <h4>Recent Immunizations</h4>
                    <?php if ($recent_immunizations->num_rows > 0): ?>
                        <table class="immunization-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Vaccine</th>
                                    <th>Dose</th>
                                    <th>Administered By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($immunization = $recent_immunizations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('M j, Y', strtotime($immunization['administered_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($immunization['vaccine_name']); ?></td>
                                        <td><?php echo $immunization['dose_number']; ?></td>
                                        <td><?php echo htmlspecialchars($immunization['administered_by_name']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No immunization records found.</p>
                    <?php endif; ?>

                        <h4>Due Immunizations</h4>
                    <?php if ($due_immunizations->num_rows > 0): ?>
                        <table class="immunization-table">
                            <thead>
                                <tr>
                                    <th>Vaccine</th>
                                    <th>Recommended Age</th>
                                    <th>Doses Required</th>
                                    <th>Doses Received</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($vaccine = $due_immunizations->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($vaccine['name']); ?></td>
                                        <td><?php echo htmlspecialchars($vaccine['recommended_age']); ?></td>
                                        <td><?php echo $vaccine['doses_required']; ?></td>
                                        <td><?php echo $vaccine['doses_received']; ?></td>
                                        <td>
                                                <button class="schedule-btn" data-vaccine-id="<?php echo $vaccine['id']; ?>">
                                                Schedule
                                                </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>You are up to date with all recommended immunizations.</p>
                    <?php endif; ?>
                    </div>
                </div>
                
                <!-- Notifications Section -->
                <div class="content-section" id="notifications-section">
                    <div class="section-header">
                        <h3>Notifications</h3>
                        <button id="mark-all-read-btn" class="btn btn-secondary">Mark All as Read</button>
                    </div>
                    
                    <?php if ($notifications->num_rows > 0): ?>
                        <ul class="notification-list">
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <li class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time">
                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                    </div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No notifications to display.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Create Profile Modal -->
    <div id="createProfileModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create Patient Profile</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <form id="createProfileForm" method="post" action="process_profile.php">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>

                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>

                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>

                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth *</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender *</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="blood_type">Blood Type</label>
                        <select id="blood_type" name="blood_type">
                            <option value="">Select Blood Type</option>
                            <option value="A+">A+</option>
                            <option value="A-">A-</option>
                            <option value="B+">B+</option>
                            <option value="B-">B-</option>
                            <option value="AB+">AB+</option>
                            <option value="AB-">AB-</option>
                            <option value="O+">O+</option>
                            <option value="O-">O-</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="purok">Purok/Street Address *</label>
                        <input type="text" id="purok" name="purok" required>
                    </div>

                    <div class="form-group">
                        <label for="city">City *</label>
                        <input type="text" id="city" name="city" required>
                    </div>

                    <div class="form-group">
                        <label for="province">Province *</label>
                        <input type="text" id="province" name="province" required>
                    </div>

                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>

                    <div class="form-group">
                        <label for="phone_number">Phone Number *</label>
                        <input type="tel" id="phone_number" name="phone_number" required>
                    </div>

                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea id="medical_history" name="medical_history" rows="4"></textarea>
                    </div>

                    <div class="form-group">
                        <label for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="4"></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Create Profile</button>
                        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        /* Existing styles ... */
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            overflow-y: auto;
        }

        .modal-content {
            background-color: #fefefe;
            margin: 30px auto;
            padding: 0;
            border-radius: 8px;
            max-width: 700px;
            width: 90%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }

        .modal-body {
            padding: 20px;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }

        .close:hover {
            color: #555;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }

        .form-actions {
            margin-top: 30px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        /* Make modal scrollable on mobile */
        @media (max-width: 768px) {
            .modal-content {
                margin: 0;
                min-height: 100vh;
                border-radius: 0;
            }
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Existing script...

            // Modal functionality
            const modal = document.getElementById('createProfileModal');
            const createProfileBtn = document.getElementById('create-profile-btn');
            const closeBtn = document.querySelector('.close');
            const createProfileForm = document.getElementById('createProfileForm');

            function openModal() {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            }

            function closeModal() {
                modal.style.display = 'none';
                document.body.style.overflow = ''; // Restore scrolling
            }

            if (createProfileBtn) {
                createProfileBtn.addEventListener('click', openModal);
            }

            if (closeBtn) {
                closeBtn.addEventListener('click', closeModal);
            }

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });

            // Handle form submission
            if (createProfileForm) {
                createProfileForm.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    try {
                        const formData = new FormData(this);
                        const response = await fetch('process_profile.php', {
                            method: 'POST',
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            // Reload the page to show the new profile
                            window.location.reload();
                        } else {
                            alert(result.message || 'Error creating profile. Please try again.');
                        }
                    } catch (error) {
                        console.error('Error:', error);
                        alert('An error occurred. Please try again.');
                    }
                });
            }

            // Form validation
            const requiredFields = createProfileForm.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                field.addEventListener('invalid', function(e) {
                    e.preventDefault();
                    this.classList.add('invalid');
                });

                field.addEventListener('input', function() {
                    this.classList.remove('invalid');
                });
            });
        });

        // Add this to your existing script
        function closeModal() {
            const modal = document.getElementById('createProfileModal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    </script>
</body>
</html> 