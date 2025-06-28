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

// Handle search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR a.purpose LIKE '%$search%')";
}

// Add option to view unassigned appointments
$show_unassigned = isset($_GET['show_unassigned']) && $_GET['show_unassigned'] == '1';

// Handle filters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';

$date_condition = '';
if (!empty($filter_date)) {
    $filter_date = $conn->real_escape_string($filter_date);
    $date_condition = " AND DATE(a.appointment_date) = '$filter_date'";
}

$status_condition = '';
if (!empty($filter_status)) {
    $filter_status = $conn->real_escape_string($filter_status);
    $status_condition = " AND a.status = '$filter_status'";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Base query for appointments
$base_query = "FROM appointments a 
               JOIN patients p ON a.patient_id = p.id 
               LEFT JOIN vaccines v ON a.vaccine_id = v.id 
               WHERE ";

// Condition for assigned appointments
$assigned_condition = "a.staff_id = ?";

// Condition for unassigned appointments (vaccination-related)
$unassigned_condition = "a.staff_id IS NULL AND (a.purpose LIKE '%Vaccination%' OR a.purpose LIKE '%Immunization%')";

// Get total number of appointments
if ($show_unassigned) {
    $count_query = "SELECT COUNT(*) as count $base_query ($assigned_condition OR ($unassigned_condition)) $search_condition $date_condition $status_condition";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
} else {
    $count_query = "SELECT COUNT(*) as count $base_query $assigned_condition $search_condition $date_condition $status_condition";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
}

$stmt->execute();
$result = $stmt->get_result();
$total_appointments = $result->fetch_assoc()['count'];
$total_pages = ceil($total_appointments / $records_per_page);

// Get appointments with pagination
if ($show_unassigned) {
    $query = "SELECT a.*, p.first_name, p.last_name, v.name as vaccine_name 
              $base_query ($assigned_condition OR ($unassigned_condition)) $search_condition $date_condition $status_condition
              ORDER BY a.appointment_date ASC 
              LIMIT ?, ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $offset, $records_per_page);
} else {
    $query = "SELECT a.*, p.first_name, p.last_name, v.name as vaccine_name 
              $base_query $assigned_condition $search_condition $date_condition $status_condition
              ORDER BY a.appointment_date ASC 
              LIMIT ?, ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $offset, $records_per_page);
}

$stmt->execute();
$appointments = $stmt->get_result();

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

// Handle appointment claim
if (isset($_POST['claim_appointment']) && isset($_POST['appointment_id'])) {
    $appointment_id = $_POST['appointment_id'];
    
    $stmt = $conn->prepare("UPDATE appointments SET staff_id = ? WHERE id = ? AND staff_id IS NULL");
    $stmt->bind_param("ii", $user_id, $appointment_id);
    $stmt->execute();
    
    // Redirect to avoid form resubmission
    header("Location: nurse_appointments.php?search=$search&filter_status=$filter_status&filter_date=$filter_date&page=$page&claimed=1");
    exit;
}

$conn->close();
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
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
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
        
        .filter-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .filter-form select, .filter-form input[type="date"] {
            padding: 8px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .filter-form button {
            padding: 8px 15px;
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-form button:hover {
            background-color: #e9ecef;
        }
        
        .add-btn {
            padding: 10px 15px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .add-btn i {
            margin-right: 5px;
        }
        
        .add-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .appointment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .appointment-table th, .appointment-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .appointment-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .appointment-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .patient-name {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .appointment-time {
            white-space: nowrap;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-requested {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-confirmed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-completed {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-no_show {
            background-color: #e2e3e5;
            color: #383d41;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn, .edit-btn, .complete-btn, .cancel-btn {
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.8rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .view-btn {
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .view-btn:hover {
            background-color: #d0e3ff;
        }
        
        .edit-btn {
            background-color: #e9f9ef;
            color: #28a745;
        }
        
        .edit-btn:hover {
            background-color: #d1f2e0;
        }
        
        .complete-btn {
            background-color: #cce5ff;
            color: #004085;
        }
        
        .complete-btn:hover {
            background-color: #b8daff;
        }
        
        .cancel-btn {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .cancel-btn:hover {
            background-color: #f5c6cb;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 30px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            margin: 0 5px;
            border-radius: 4px;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .pagination a {
            background-color: #f8f9fa;
            color: var(--text-color);
        }
        
        .pagination a:hover {
            background-color: #e9ecef;
        }
        
        .pagination span {
            background-color: var(--primary-color);
            color: white;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form, .filter-form {
                width: 100%;
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
                    <li><a href="nurse_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="nurse_immunizations.php"><i class="fas fa-syringe"></i> Immunizations</a></li>
                    <li><a href="nurse_vaccine_inventory.php"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Manage Appointments</h2>
                
                <?php if (isset($_GET['claimed']) && $_GET['claimed'] == 1): ?>
                    <div class="alert" style="padding: 15px; margin-bottom: 20px; border-radius: 5px; color: #0c5460; background-color: #d1ecf1; border: 1px solid #bee5eb;">
                        Appointment has been claimed successfully.
                    </div>
                <?php endif; ?>
                
                <div class="action-bar">
                    <form class="search-form" action="" method="GET">
                        <input type="text" name="search" placeholder="Search appointments..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <form class="filter-form" action="" method="GET">
                        <select name="filter_status">
                            <option value="">All Statuses</option>
                            <option value="requested" <?php echo ($filter_status == 'requested') ? 'selected' : ''; ?>>Requested</option>
                            <option value="confirmed" <?php echo ($filter_status == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo ($filter_status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($filter_status == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="no_show" <?php echo ($filter_status == 'no_show') ? 'selected' : ''; ?>>No Show</option>
                        </select>
                        
                        <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
                        
                        <div style="display: flex; align-items: center; margin-right: 10px;">
                            <input type="checkbox" id="show_unassigned" name="show_unassigned" value="1" <?php echo $show_unassigned ? 'checked' : ''; ?> style="margin-right: 5px;">
                            <label for="show_unassigned">Show Unassigned</label>
                        </div>
                        
                        <button type="submit">Filter</button>
                        <a href="nurse_appointments.php" class="btn-sm">Reset</a>
                    </form>
                    
                    <a href="schedule_appointment.php" class="add-btn">
                        <i class="fas fa-plus"></i> Schedule Appointment
                    </a>
                </div>
                
                <table class="appointment-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Vaccine</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments->num_rows > 0): ?>
                            <?php while ($appointment = $appointments->fetch_assoc()): 
                                $status_class = 'status-' . $appointment['status'];
                                $status_text = ucfirst($appointment['status']);
                                if ($appointment['status'] == 'no_show') {
                                    $status_text = 'No Show';
                                }
                            ?>
                                <tr>
                                    <td class="patient-name">
                                        <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                    </td>
                                    <td class="appointment-time">
                                        <?php echo date('M j, Y - g:i A', strtotime($appointment['appointment_date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($appointment['purpose']); ?>
                                    </td>
                                    <td>
                                        <?php echo !empty($appointment['vaccine_name']) ? htmlspecialchars($appointment['vaccine_name']) : 'N/A'; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="view-btn">View</a>
                                        
                                        <?php if ($appointment['staff_id'] == $user_id): ?>
                                            <?php if ($appointment['status'] == 'requested' || $appointment['status'] == 'confirmed'): ?>
                                                <a href="edit_appointment.php?id=<?php echo $appointment['id']; ?>" class="edit-btn">Edit</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] == 'confirmed'): ?>
                                                <a href="administer_vaccine.php?appointment_id=<?php echo $appointment['id']; ?>" class="complete-btn">Record</a>
                                            <?php endif; ?>
                                            
                                            <?php if ($appointment['status'] == 'requested' || $appointment['status'] == 'confirmed'): ?>
                                                <a href="update_appointment_status.php?id=<?php echo $appointment['id']; ?>&status=cancelled" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                            <?php endif; ?>
                                        <?php elseif (empty($appointment['staff_id'])): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <button type="submit" name="claim_appointment" class="complete-btn">Claim Appointment</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&filter_status=<?php echo $filter_status; ?>&filter_date=<?php echo $filter_date; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 