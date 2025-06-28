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

// Handle filter functionality
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR a.purpose LIKE '%$search%')";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Set up the filter condition
$filter_condition = '';
switch ($filter) {
    case 'upcoming':
        $filter_condition = " AND a.appointment_date > NOW() AND a.status = 'confirmed'";
        break;
    case 'today':
        $filter_condition = " AND DATE(a.appointment_date) = CURDATE()";
        break;
    case 'past':
        $filter_condition = " AND a.appointment_date < NOW()";
        break;
    case 'cancelled':
        $filter_condition = " AND a.status = 'cancelled'";
        break;
    case 'all':
        $filter_condition = "";
        break;
    default:
        $filter_condition = " AND a.appointment_date > NOW() AND a.status = 'confirmed'";
}

// Get total number of appointments for this midwife
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments a 
                        JOIN patients p ON a.patient_id = p.id 
                        WHERE a.staff_id = ? $filter_condition $search_condition");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_appointments = $result->fetch_assoc()['count'];
$total_pages = ceil($total_appointments / $records_per_page);

// Get appointments with pagination
$stmt = $conn->prepare("SELECT a.*, p.first_name, p.last_name, p.phone_number, v.name as vaccine_name 
                        FROM appointments a 
                        JOIN patients p ON a.patient_id = p.id 
                        LEFT JOIN vaccines v ON a.vaccine_id = v.id
                        WHERE a.staff_id = ? $filter_condition $search_condition
                        ORDER BY a.appointment_date ASC
                        LIMIT ?, ?");
$stmt->bind_param("iii", $user_id, $offset, $records_per_page);
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

// Handle appointment status update
if (isset($_POST['update_status']) && isset($_POST['appointment_id']) && isset($_POST['status'])) {
    $appointment_id = $_POST['appointment_id'];
    $status = $_POST['status'];
    
    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND staff_id = ?");
    $stmt->bind_param("sii", $status, $appointment_id, $user_id);
    $stmt->execute();
    
    // Redirect to avoid form resubmission
    header("Location: midwife_appointments.php?filter=$filter&search=$search&page=$page&updated=1");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - ImmuCare</title>
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
        
        .filter-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
            overflow-x: auto;
            padding-bottom: 5px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            color: var(--light-text);
            text-decoration: none;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .filter-tab:hover {
            color: var(--text-color);
            background-color: #f8f9fa;
        }
        
        .filter-tab.active {
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            font-weight: 500;
        }
        
        .appointment-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .appointment-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            color: var(--text-color);
            border-bottom: 1px solid #e9ecef;
        }
        
        .appointment-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .appointment-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .appointment-patient {
            font-weight: 500;
            color: var(--text-color);
        }
        
        .appointment-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .appointment-actions {
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
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-confirmed {
            background-color: #e3f8e7;
            color: #28a745;
        }
        
        .status-completed {
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .status-cancelled {
            background-color: #feecef;
            color: #dc3545;
        }
        
        .status-requested {
            background-color: #fff8e6;
            color: #ffc107;
        }
        
        .status-no-show {
            background-color: #f8f9fa;
            color: #6c757d;
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
        
        .status-dropdown {
            padding: 5px 8px;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            font-family: 'Poppins', sans-serif;
            font-size: 0.8rem;
        }
        
        .status-form {
            display: flex;
            gap: 5px;
        }
        
        .status-submit {
            background-color: #e9ecef;
            border: none;
            padding: 5px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: var(--transition);
        }
        
        .status-submit:hover {
            background-color: #dee2e6;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
        }
        
        /* Section Tabs */
        .section-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #e1e4e8;
            overflow-x: auto;
        }
        
        .section-tab {
            padding: 10px 20px;
            color: var(--text-color);
            text-decoration: none;
            margin-right: 10px;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            white-space: nowrap;
        }
        
        .section-tab:hover {
            color: var(--primary-color);
        }
        
        .section-tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: 500;
        }
        
        /* Section Header */
        .section-header {
            margin-bottom: 20px;
        }
        
        .section-header h3 {
            font-size: 1.4rem;
            color: var(--primary-color);
            margin-bottom: 5px;
        }
        
        .section-header p {
            color: var(--light-text);
            margin-top: 0;
        }
        
        /* No Data Message */
        .no-data {
            padding: 30px;
            text-align: center;
            color: var(--light-text);
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
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
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Manage Appointments</h2>
                
                <?php if (isset($_GET['updated']) && $_GET['updated'] == 1): ?>
                    <div class="alert">
                        Appointment status has been updated successfully.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['appointment_message'])): ?>
                    <div class="alert">
                        <?php echo $_SESSION['appointment_message']; ?>
                        <?php unset($_SESSION['appointment_message']); unset($_SESSION['appointment_status']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="section-tabs">
                    <a href="?view=all" class="section-tab <?php echo (!isset($_GET['view']) || $_GET['view'] == 'all') ? 'active' : ''; ?>">All Appointments</a>
                    <a href="?view=requests" class="section-tab <?php echo (isset($_GET['view']) && $_GET['view'] == 'requests') ? 'active' : ''; ?>">New Requests</a>
                    <a href="?filter=upcoming" class="section-tab <?php echo (!isset($_GET['view']) && isset($_GET['filter']) && $_GET['filter'] == 'upcoming') ? 'active' : ''; ?>">Upcoming</a>
                    <a href="?filter=today" class="section-tab <?php echo (!isset($_GET['view']) && isset($_GET['filter']) && $_GET['filter'] == 'today') ? 'active' : ''; ?>">Today</a>
                    <a href="?filter=past" class="section-tab <?php echo (!isset($_GET['view']) && isset($_GET['filter']) && $_GET['filter'] == 'past') ? 'active' : ''; ?>">Past</a>
                    <a href="?filter=cancelled" class="section-tab <?php echo (!isset($_GET['view']) && isset($_GET['filter']) && $_GET['filter'] == 'cancelled') ? 'active' : ''; ?>">Cancelled</a>
                </div>
                
                <?php if (isset($_GET['view']) && $_GET['view'] == 'requests'): ?>
                    <div class="section-header">
                        <h3>New Appointment Requests</h3>
                        <p>These are appointment requests from users who are not yet registered in the system.</p>
                    </div>
                    
                    <?php
                    // Get appointment requests with status 'requested'
                    $requests_query = "SELECT a.*, 
                                     CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                                     p.phone_number, p.date_of_birth, p.gender,
                                     v.name as vaccine_name
                                     FROM appointments a 
                                     JOIN patients p ON a.patient_id = p.id 
                                     LEFT JOIN vaccines v ON a.vaccine_id = v.id 
                                     WHERE a.status = 'requested' AND a.staff_id IS NULL
                                     ORDER BY a.created_at DESC";
                    $requests_result = $conn->query($requests_query);
                    ?>
                    
                    <?php if ($requests_result && $requests_result->num_rows > 0): ?>
                        <table class="appointment-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Contact</th>
                                    <th>Requested Date</th>
                                    <th>Purpose</th>
                                    <th>Submitted On</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($request = $requests_result->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <div class="appointment-patient"><?php echo htmlspecialchars($request['patient_name']); ?></div>
                                            <div class="appointment-info">
                                                <?php echo date('M d, Y', strtotime($request['date_of_birth'])); ?> (<?php echo ucfirst($request['gender']); ?>)
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['phone_number']); ?></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($request['appointment_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($request['purpose']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($request['created_at'])); ?></td>
                                        <td class="appointment-actions">
                                            <a href="process_appointment.php?action=confirm&id=<?php echo $request['id']; ?>" class="action-btn view-btn">
                                                <i class="fas fa-check"></i> Confirm
                                            </a>
                                            <a href="process_appointment.php?action=reschedule&id=<?php echo $request['id']; ?>" class="action-btn edit-btn">
                                                <i class="fas fa-calendar-alt"></i> Reschedule
                                            </a>
                                            <a href="process_appointment.php?action=cancel&id=<?php echo $request['id']; ?>" class="action-btn" style="background-color: #feecef; color: #dc3545;" onclick="return confirm('Are you sure you want to cancel this appointment request?');">
                                                <i class="fas fa-times"></i> Cancel
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">No new appointment requests found.</div>
                    <?php endif; ?>
                
                <?php else: ?>
                
                <div class="filter-tabs">
                    <a href="?filter=upcoming" class="filter-tab <?php echo $filter == 'upcoming' ? 'active' : ''; ?>">
                        Upcoming
                    </a>
                    <a href="?filter=today" class="filter-tab <?php echo $filter == 'today' ? 'active' : ''; ?>">
                        Today
                    </a>
                    <a href="?filter=past" class="filter-tab <?php echo $filter == 'past' ? 'active' : ''; ?>">
                        Past
                    </a>
                    <a href="?filter=cancelled" class="filter-tab <?php echo $filter == 'cancelled' ? 'active' : ''; ?>">
                        Cancelled
                    </a>
                    <a href="?filter=all" class="filter-tab <?php echo $filter == 'all' ? 'active' : ''; ?>">
                        All
                    </a>
                </div>
                
                <div class="action-bar">
                    <form class="search-form" method="GET" action="">
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                        <input type="text" name="search" placeholder="Search appointments..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="schedule_appointment.php" class="add-btn">
                        <i class="fas fa-plus"></i> Schedule New Appointment
                    </a>
                </div>
                
                <table class="appointment-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($appointments->num_rows > 0): ?>
                            <?php while ($appointment = $appointments->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="appointment-patient">
                                            <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?>
                                        </div>
                                        <div class="appointment-info">
                                            <?php echo htmlspecialchars($appointment['phone_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo date('F j, Y', strtotime($appointment['appointment_date'])); ?></div>
                                        <div class="appointment-info">
                                            <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($appointment['purpose']); ?></div>
                                        <div class="appointment-info">
                                            <?php echo $appointment['vaccine_name'] ? htmlspecialchars($appointment['vaccine_name']) : 'No vaccine'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                            $status_class = '';
                                            switch ($appointment['status']) {
                                                case 'confirmed':
                                                    $status_class = 'status-confirmed';
                                                    break;
                                                case 'completed':
                                                    $status_class = 'status-completed';
                                                    break;
                                                case 'cancelled':
                                                    $status_class = 'status-cancelled';
                                                    break;
                                                case 'requested':
                                                    $status_class = 'status-requested';
                                                    break;
                                                case 'no_show':
                                                    $status_class = 'status-no-show';
                                                    break;
                                            }
                                        ?>
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td class="appointment-actions">
                                        <a href="view_appointment.php?id=<?php echo $appointment['id']; ?>" class="action-btn view-btn">View</a>
                                        
                                        <?php if ($appointment['status'] != 'completed' && $appointment['status'] != 'cancelled'): ?>
                                            <form class="status-form" method="POST" action="">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                <select name="status" class="status-dropdown">
                                                    <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                    <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    <option value="no_show" <?php echo $appointment['status'] == 'no_show' ? 'selected' : ''; ?>>No Show</option>
                                                </select>
                                                <button type="submit" name="update_status" class="status-submit">Update</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px;">No appointments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo ($page - 1); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?filter=<?php echo $filter; ?>&search=<?php echo urlencode($search); ?>&page=<?php echo ($page + 1); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html> 