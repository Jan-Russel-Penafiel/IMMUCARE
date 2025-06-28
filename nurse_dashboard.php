<?php
session_start();
require 'config.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Location: login.php');
    exit;
}

// Get nurse information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get statistics for nurse dashboard
// Count total immunizations administered by this nurse
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE administered_by = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$immunization_count = $result->fetch_assoc()['count'];

// Count today's immunizations
$today = date('Y-m-d');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE administered_by = ? AND DATE(administered_date) = ?");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result = $stmt->get_result();
$today_immunization_count = $result->fetch_assoc()['count'];

// Count upcoming appointments for immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE staff_id = ? AND appointment_date > NOW() AND status = 'confirmed'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment_count = $result->fetch_assoc()['count'];

// Get recent immunizations administered
$stmt = $conn->prepare("SELECT i.*, p.first_name, p.last_name, v.name as vaccine_name 
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id
                        WHERE i.administered_by = ? 
                        ORDER BY i.administered_date DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_immunizations = $stmt->get_result();

// Get upcoming appointments
$stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name, v.name as vaccine_name 
                        FROM appointments a 
                        JOIN patients p ON a.patient_id = p.id 
                        LEFT JOIN vaccines v ON a.vaccine_id = v.id
                        WHERE a.staff_id = ? AND a.appointment_date > NOW() AND a.status = 'confirmed'
                        ORDER BY a.appointment_date ASC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();

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
    <title>Nurse Dashboard - ImmuCare</title>
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
        
        .dashboard-sections {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .section-card {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 20px;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .record-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #e9ecef;
            transition: var(--transition);
        }
        
        .record-item:hover {
            background-color: #f8f9fa;
        }
        
        .record-item:last-child {
            border-bottom: none;
        }
        
        .record-icon {
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
        
        .record-details {
            flex-grow: 1;
        }
        
        .record-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .record-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .record-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8rem;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .dashboard-sections {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
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
                    <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="user-role">Nurse</div>
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
                    <li><a href="nurse_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="nurse_immunizations.php"><i class="fas fa-syringe"></i> Immunizations</a></li>
                    <li><a href="nurse_vaccine_inventory.php"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <div class="welcome-section">
                    <h2>Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
                    <p>Here's an overview of your immunization activities and upcoming appointments.</p>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <div class="stat-value"><?php echo $immunization_count; ?></div>
                        <div class="stat-label">Total Immunizations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-value"><?php echo $today_immunization_count; ?></div>
                        <div class="stat-label">Today's Immunizations</div>
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
                            <i class="fas fa-vial"></i>
                        </div>
                        <div class="stat-value">24</div>
                        <div class="stat-label">Vaccines Available</div>
                    </div>
                </div>
                
                <div class="dashboard-sections">
                    <div class="section-card">
                        <h3 class="section-title">Recent Immunizations</h3>
                        <?php if ($recent_immunizations->num_rows > 0): ?>
                            <?php while ($immunization = $recent_immunizations->fetch_assoc()): ?>
                                <div class="record-item">
                                    <div class="record-icon">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                    <div class="record-details">
                                        <div class="record-title">
                                            <?php echo htmlspecialchars($immunization['first_name'] . ' ' . $immunization['last_name']); ?>
                                        </div>
                                        <div class="record-info">
                                            <?php echo htmlspecialchars($immunization['vaccine_name']); ?> | 
                                            Dose #<?php echo $immunization['dose_number']; ?> | 
                                            <?php echo date('M j, Y', strtotime($immunization['administered_date'])); ?>
                                        </div>
                                    </div>
                                    <div class="record-actions">
                                        <a href="view_immunization.php?id=<?php echo $immunization['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p>No recent immunizations.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="section-card">
                        <h3 class="section-title">Upcoming Appointments</h3>
                        <?php if ($upcoming_appointments->num_rows > 0): ?>
                            <?php while ($appointment = $upcoming_appointments->fetch_assoc()): ?>
                                <div class="record-item">
                                    <div class="record-icon">
                                        <i class="fas fa-calendar"></i>
                                    </div>
                                    <div class="record-details">
                                        <div class="record-title">
                                            <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                        </div>
                                        <div class="record-info">
                                            <?php echo date('M j, Y - g:i A', strtotime($appointment['appointment_date'])); ?> | 
                                            <?php echo !empty($appointment['vaccine_name']) ? htmlspecialchars($appointment['vaccine_name']) : htmlspecialchars($appointment['purpose']); ?>
                                        </div>
                                    </div>
                                    <div class="record-actions">
                                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="btn btn-primary btn-sm">View</a>
                                        <a href="administer_vaccine.php?appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-outline btn-sm">Record</a>
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
    </div>
</body>
</html> 