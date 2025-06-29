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

// Handle search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR p.phone_number LIKE '%$search%')";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of patients
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM patients p");
$stmt->execute();
$result = $stmt->get_result();
$total_patients = $result->fetch_assoc()['count'];
$total_pages = ceil($total_patients / $records_per_page);

// Get patients with pagination
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM immunizations i WHERE i.patient_id = p.id) as immunization_count,
          (SELECT COUNT(*) FROM appointments a WHERE a.patient_id = p.id AND a.status = 'confirmed') as appointment_count
          FROM patients p
          WHERE 1=1 $search_condition
          ORDER BY p.last_name ASC
          LIMIT $offset, $records_per_page";
$patients = $conn->query($query);

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
    <title>Manage Patients - ImmuCare</title>
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
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .search-form {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 5px 15px;
            width: 300px;
        }
        
        .search-form input {
            border: none;
            background: transparent;
            padding: 8px;
            width: 100%;
            font-family: 'Poppins', sans-serif;
        }
        
        .search-form input:focus {
            outline: none;
        }
        
        .search-form button {
            background: transparent;
            border: none;
            color: var(--light-text);
            cursor: pointer;
        }
        
        .add-btn {
            padding: 8px 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 5px;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .add-btn i {
            margin-right: 5px;
        }
        
        .add-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patient-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            color: var(--text-color);
            border-bottom: 1px solid #e9ecef;
        }
        
        .patient-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .patient-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .patient-name {
            font-weight: 500;
            color: var(--text-color);
        }
        
        .patient-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .patient-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 5px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .view-btn {
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .edit-btn {
            background-color: #e9ecef;
            color: var(--text-color);
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
            background-color: #f8f9fa;
            transition: var(--transition);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination a:hover:not(.active) {
            background-color: #e9ecef;
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
                    <li><a href="midwife_patients.php" class="active"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="midwife_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Manage Patients</h2>
                
                <div class="action-bar">
                    <form class="search-form" method="GET" action="">
                        <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="add_patient.php" class="add-btn">
                        <i class="fas fa-plus"></i> Add New Patient
                    </a>
                </div>
                
                <table class="patient-table">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Date of Birth</th>
                            <th>Contact</th>
                            <th>Records</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($patients->num_rows > 0): ?>
                            <?php while ($patient = $patients->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="patient-name">
                                            <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name']); ?>
                                        </div>
                                        <div class="patient-info">
                                            <?php echo htmlspecialchars($patient['gender']); ?> | ID: <?php echo $patient['id']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($patient['date_of_birth'])); ?>
                                        <div class="patient-info">
                                            <?php 
                                                $dob = new DateTime($patient['date_of_birth']);
                                                $now = new DateTime();
                                                $age = $now->diff($dob)->y;
                                                echo $age . ' years old';
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($patient['phone_number']); ?></div>
                                        <div class="patient-info">
                                            <?php echo htmlspecialchars($patient['purok'] . ', ' . $patient['city'] . ', ' . $patient['province']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo $patient['immunization_count']; ?> immunizations</div>
                                        <div class="patient-info">
                                            <?php echo $patient['appointment_count']; ?> upcoming appointments
                                        </div>
                                    </td>
                                    <td class="patient-actions">
                                        <a href="view_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn view-btn">View</a>
                                        <a href="edit_patient.php?id=<?php echo $patient['id']; ?>" class="action-btn edit-btn">Edit</a>
                                        <a href="schedule_appointment.php?patient_id=<?php echo $patient['id']; ?>" class="action-btn view-btn">Schedule</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">No patients found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 