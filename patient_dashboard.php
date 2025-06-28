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
                    <li><a href="patient_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="patient_profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="patient_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="patient_immunizations.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="patient_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($patient['first_name']); ?>!</h2>
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
                
                <div class="section">
                    <div class="section-header">
                        <h3>Upcoming Appointments</h3>
                        <a href="patient_appointments.php" class="view-all">View All</a>
                    </div>
                    
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
                                    <span>
                                        <span class="badge badge-<?php echo strtolower($appointment['status']); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p>No upcoming appointments scheduled. <a href="patient_appointments.php?action=new">Schedule an appointment</a>.</p>
                    <?php endif; ?>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h3>Recent Immunizations</h3>
                        <a href="patient_immunizations.php" class="view-all">View All</a>
                    </div>
                    
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
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h3>Due Immunizations</h3>
                    </div>
                    
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
                                            <a href="patient_appointments.php?action=new&vaccine_id=<?php echo $vaccine['id']; ?>" class="view-all">
                                                Schedule
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>You are up to date with all recommended immunizations.</p>
                    <?php endif; ?>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <h3>Recent Notifications</h3>
                        <a href="patient_notifications.php" class="view-all">View All</a>
                    </div>
                    
                    <?php if ($notifications->num_rows > 0): ?>
                        <ul class="notification-list">
                            <?php while ($notification = $notifications->fetch_assoc()): ?>
                                <li class="notification-item">
                                    <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-time"><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></div>
                                </li>
                            <?php endwhile; ?>
                        </ul>
                    <?php else: ?>
                        <p>No unread notifications.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script src="script.js"></script>
</body>
</html> 