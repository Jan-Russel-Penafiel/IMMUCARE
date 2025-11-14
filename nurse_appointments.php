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
                    <li><a href="nurse_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
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
                    
                    <?php // Removed Schedule Appointment button ?>
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
                                        <button type="button" class="view-btn" onclick="viewAppointment(<?php echo $appointment['id']; ?>)">View</button>
                                        
                                        <?php if ($appointment['staff_id'] == $user_id): ?>
                                            <?php if ($appointment['status'] == 'requested' || $appointment['status'] == 'confirmed'): ?>
                                                <button type="button" class="edit-btn" onclick="editAppointment(<?php echo $appointment['id']; ?>)">Edit</button>
                                            <?php endif; ?>
                                            
                                            <?php // Removed administer vaccine button ?>
                                            
                                            <?php if ($appointment['status'] == 'requested' || $appointment['status'] == 'confirmed'): ?>
                                                <a href="update_appointment_status.php?id=<?php echo $appointment['id']; ?>&status=cancelled" class="cancel-btn" onclick="return confirm('Are you sure you want to cancel this appointment?')">Cancel</a>
                                            <?php endif; ?>
                                        <?php elseif (empty($appointment['staff_id'])): ?>
                                            <form method="POST" action="" style="display: inline;">
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

    <!-- View Appointment Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Appointment Details</h2>
                <span class="close" onclick="closeModal('viewModal')">&times;</span>
            </div>
            <div class="modal-body" id="viewModalBody">
                <div class="loading">Loading appointment details...</div>
            </div>
        </div>
    </div>

    <!-- Edit Appointment Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Appointment</h2>
                <span class="close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body" id="editModalBody">
                <div class="loading">Loading appointment details...</div>
            </div>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 8px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            padding: 20px 30px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 1.5rem;
        }

        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .close:hover,
        .close:focus {
            opacity: 0.7;
            text-decoration: none;
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .loading {
            text-align: center;
            padding: 20px;
            color: var(--light-text);
        }

        .appointment-detail {
            margin-bottom: 15px;
        }

        .appointment-detail label {
            font-weight: 600;
            color: var(--text-color);
            display: block;
            margin-bottom: 5px;
        }

        .appointment-detail .value {
            color: var(--light-text);
            padding: 8px 12px;
            background-color: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #e9ecef;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: var(--text-color);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            box-sizing: border-box;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(66, 133, 244, 0.1);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
        }

        .status-badge-large {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .modal-header,
            .modal-body {
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .modal-body div[style*="grid-template-columns"] {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
            }
        }
    </style>

    <script>
        function viewAppointment(appointmentId) {
            const modal = document.getElementById('viewModal');
            const modalBody = document.getElementById('viewModalBody');
            
            modal.style.display = 'block';
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading appointment details...</div>';
            
            // Fetch appointment details via AJAX
            fetch('get_appointment_details.php?id=' + appointmentId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        modalBody.innerHTML = buildViewContent(data.appointment);
                    } else {
                        modalBody.innerHTML = `
                            <div style="text-align: center; color: #dc3545; padding: 20px;">
                                <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                <p>Error loading appointment details: ${data.message}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    modalBody.innerHTML = `
                        <div style="text-align: center; color: #dc3545; padding: 20px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading appointment details. Please try again.</p>
                        </div>
                    `;
                });
        }

        function editAppointment(appointmentId) {
            const modal = document.getElementById('editModal');
            const modalBody = document.getElementById('editModalBody');
            
            modal.style.display = 'block';
            modalBody.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading appointment details...</div>';
            
            // Fetch appointment details and vaccines for editing
            Promise.all([
                fetch('get_appointment_details.php?id=' + appointmentId),
                fetch('get_vaccines.php')
            ])
            .then(responses => {
                // Check if all responses are ok
                responses.forEach(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                });
                return Promise.all(responses.map(r => r.json()));
            })
            .then(([appointmentData, vaccinesData]) => {
                if (appointmentData.success && vaccinesData.success) {
                    modalBody.innerHTML = buildEditContent(appointmentData.appointment, vaccinesData.vaccines);
                } else {
                    modalBody.innerHTML = `
                        <div style="text-align: center; color: #dc3545; padding: 20px;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                            <p>Error loading appointment details.</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                modalBody.innerHTML = `
                    <div style="text-align: center; color: #dc3545; padding: 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 2rem; margin-bottom: 10px;"></i>
                        <p>Error loading appointment details. Please try again.</p>
                    </div>
                `;
            });
        }

        function buildViewContent(appointment) {
            const statusClass = 'status-' + appointment.status;
            const statusText = appointment.status === 'no_show' ? 'No Show' : appointment.status.charAt(0).toUpperCase() + appointment.status.slice(1);
            
            return `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 5px;">Patient Information</h3>
                        <div class="appointment-detail">
                            <label>Full Name:</label>
                            <div class="value">${appointment.patient_name}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Date of Birth:</label>
                            <div class="value">${appointment.date_of_birth ? new Date(appointment.date_of_birth).toLocaleDateString() : 'N/A'}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Gender:</label>
                            <div class="value">${appointment.gender || 'N/A'}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Blood Type:</label>
                            <div class="value">${appointment.blood_type || 'Not specified'}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Phone Number:</label>
                            <div class="value">${appointment.patient_phone || 'N/A'}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Address:</label>
                            <div class="value">${appointment.patient_address}</div>
                        </div>
                        ${appointment.medical_history ? `
                        <div class="appointment-detail">
                            <label>Medical History:</label>
                            <div class="value">${appointment.medical_history}</div>
                        </div>
                        ` : ''}
                        ${appointment.allergies ? `
                        <div class="appointment-detail">
                            <label>Allergies:</label>
                            <div class="value" style="color: #dc3545; font-weight: 500;">${appointment.allergies}</div>
                        </div>
                        ` : ''}
                    </div>
                    <div>
                        <h3 style="color: var(--primary-color); margin-bottom: 15px; border-bottom: 2px solid #e9ecef; padding-bottom: 5px;">Appointment Details</h3>
                        <div class="appointment-detail">
                            <label>Date & Time:</label>
                            <div class="value">${new Date(appointment.appointment_date).toLocaleString()}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Purpose:</label>
                            <div class="value">${appointment.purpose}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Status:</label>
                            <div class="value">
                                <span class="status-badge-large ${statusClass}">${statusText}</span>
                            </div>
                        </div>
                        ${appointment.staff_name ? `
                        <div class="appointment-detail">
                            <label>Assigned Staff:</label>
                            <div class="value">${appointment.staff_name}</div>
                        </div>
                        ` : ''}
                        ${appointment.vaccine_name ? `
                        <div class="appointment-detail">
                            <label>Vaccine:</label>
                            <div class="value">${appointment.vaccine_name}</div>
                        </div>
                        ${appointment.vaccine_manufacturer ? `
                        <div class="appointment-detail">
                            <label>Manufacturer:</label>
                            <div class="value">${appointment.vaccine_manufacturer}</div>
                        </div>
                        ` : ''}
                        ${appointment.vaccine_recommended_age ? `
                        <div class="appointment-detail">
                            <label>Recommended Age:</label>
                            <div class="value">${appointment.vaccine_recommended_age}</div>
                        </div>
                        ` : ''}
                        ${appointment.vaccine_doses_required ? `
                        <div class="appointment-detail">
                            <label>Doses Required:</label>
                            <div class="value">${appointment.vaccine_doses_required}</div>
                        </div>
                        ` : ''}
                        ${appointment.vaccine_description ? `
                        <div class="appointment-detail">
                            <label>Vaccine Description:</label>
                            <div class="value">${appointment.vaccine_description}</div>
                        </div>
                        ` : ''}
                        ` : ''}
                        ${appointment.notes ? `
                        <div class="appointment-detail">
                            <label>Notes:</label>
                            <div class="value">${appointment.notes}</div>
                        </div>
                        ` : ''}
                    </div>
                </div>
                <div style="border-top: 1px solid #e9ecef; padding-top: 15px; margin-top: 15px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="appointment-detail">
                            <label>Created:</label>
                            <div class="value">${new Date(appointment.created_at).toLocaleString()}</div>
                        </div>
                        <div class="appointment-detail">
                            <label>Last Updated:</label>
                            <div class="value">${new Date(appointment.updated_at).toLocaleString()}</div>
                        </div>
                    </div>
                </div>
            `;
        }

        function buildEditContent(appointment, vaccines) {
            const vaccineOptions = vaccines.map(vaccine => 
                `<option value="${vaccine.id}" ${appointment.vaccine_id == vaccine.id ? 'selected' : ''}>${vaccine.name}</option>`
            ).join('');
            
            return `
                <form id="editAppointmentForm">
                    <input type="hidden" name="appointment_id" value="${appointment.id}">
                    
                    <div class="form-group">
                        <label>Patient Name:</label>
                        <input type="text" value="${appointment.patient_name}" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="appointment_date">Date & Time:</label>
                        <input type="datetime-local" name="appointment_date" id="appointment_date" 
                               value="${appointment.appointment_date.replace(' ', 'T')}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose:</label>
                        <input type="text" name="purpose" id="purpose" value="${appointment.purpose}" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="vaccine_id">Vaccine:</label>
                        <select name="vaccine_id" id="vaccine_id">
                            <option value="">Select Vaccine (Optional)</option>
                            ${vaccineOptions}
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status" required>
                            <option value="requested" ${appointment.status === 'requested' ? 'selected' : ''}>Requested</option>
                            <option value="confirmed" ${appointment.status === 'confirmed' ? 'selected' : ''}>Confirmed</option>
                            <option value="completed" ${appointment.status === 'completed' ? 'selected' : ''}>Completed</option>
                            <option value="cancelled" ${appointment.status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                            <option value="no_show" ${appointment.status === 'no_show' ? 'selected' : ''}>No Show</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes:</label>
                        <textarea name="notes" id="notes" placeholder="Additional notes about the appointment...">${appointment.notes || ''}</textarea>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Appointment</button>
                    </div>
                </form>
            `;
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target === viewModal) {
                viewModal.style.display = 'none';
            }
            if (event.target === editModal) {
                editModal.style.display = 'none';
            }
        }

        // Handle edit form submission
        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('submit', function(e) {
                if (e.target.id === 'editAppointmentForm') {
                    e.preventDefault();
                    
                    const submitButton = e.target.querySelector('button[type="submit"]');
                    const originalText = submitButton.innerHTML;
                    
                    // Show loading state
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
                    submitButton.disabled = true;
                    
                    const formData = new FormData(e.target);
                    
                    fetch('update_appointment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Show success message
                            const modalBody = document.getElementById('editModalBody');
                            modalBody.innerHTML = `
                                <div style="text-align: center; color: #28a745; padding: 40px;">
                                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                    <h3>Success!</h3>
                                    <p>Appointment updated successfully.</p>
                                    <button type="button" class="btn btn-primary" onclick="location.reload()">Close & Refresh</button>
                                </div>
                            `;
                        } else {
                            // Show error message
                            alert('Error updating appointment: ' + data.message);
                            submitButton.innerHTML = originalText;
                            submitButton.disabled = false;
                        }
                    })
                    .catch(error => {
                        alert('Error updating appointment. Please try again.');
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    });
                }
            });
        });
    </script>
</body>
</html> 