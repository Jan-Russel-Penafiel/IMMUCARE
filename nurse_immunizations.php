<?php
session_start();
require 'config.php';
require_once 'transaction_helper.php';

// Check if user is logged in and is a nurse
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'nurse') {
    header('Location: login.php');
    exit;
}

// Get nurse information
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Nurse';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If user name or email is missing from session, fetch from database
if (empty($user_name) || empty($user_email)) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        if (empty($user_name)) {
            $user_name = $user_data['name'];
            $_SESSION['user_name'] = $user_name;
        }
        if (empty($user_email)) {
            $user_email = $user_data['email'];
            $_SESSION['user_email'] = $user_email;
        }
    }
    $stmt->close();
}

// Handle search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%' OR v.name LIKE '%$search%')";
}

// Handle filters
$filter_date = isset($_GET['filter_date']) ? $_GET['filter_date'] : '';
$filter_vaccine = isset($_GET['filter_vaccine']) ? (int)$_GET['filter_vaccine'] : 0;

$date_condition = '';
if (!empty($filter_date)) {
    $filter_date = $conn->real_escape_string($filter_date);
    $date_condition = " AND DATE(i.administered_date) = '$filter_date'";
}

$vaccine_condition = '';
if ($filter_vaccine > 0) {
    $vaccine_condition = " AND i.vaccine_id = $filter_vaccine";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count 
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id 
                        WHERE i.administered_by = ? $search_condition $date_condition $vaccine_condition");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_immunizations = $result->fetch_assoc()['count'];
$total_pages = ceil($total_immunizations / $records_per_page);

// Get immunizations with pagination
$stmt = $conn->prepare("SELECT i.*, p.first_name, p.last_name, v.name as vaccine_name, v.doses_required,
                        i.transaction_id, i.transaction_number
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id
                        WHERE i.administered_by = ? $search_condition $date_condition $vaccine_condition
                        ORDER BY i.administered_date DESC
                        LIMIT ?, ?");
$stmt->bind_param("iii", $user_id, $offset, $records_per_page);
$stmt->execute();
$immunizations = $stmt->get_result();

// Get all vaccines for filter dropdown
$vaccines_result = $conn->query("SELECT id, name FROM vaccines WHERE is_active = 1 ORDER BY name");
$vaccines_array = [];
while ($vaccine = $vaccines_result->fetch_assoc()) {
    $vaccines_array[] = $vaccine;
}

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
    <title>Immunizations - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4285f4;
            --primary-dark: #3367d6;
            --secondary-color: #34a853;
            --accent-color: #fbbc05;
            --text-color: #333;
            --light-text: #666;
            --bg-light: #f9f9f9;
            --bg-white: #ffffff;
            --shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --transition: all 0.3s ease;
        }
        
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
        
        .btn-sm {
            padding: 8px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .btn-sm:hover {
            background-color: #5a6268;
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
        
        .immunization-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .immunization-table th, .immunization-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .immunization-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .immunization-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .patient-name {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .vaccine-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #e8f0fe;
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .dose-badge {
            display: inline-block;
            padding: 2px 6px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn, .edit-btn, .delete-btn, .print-btn {
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
        
        .delete-btn {
            background-color: #feeaed;
            color: #dc3545;
        }
        
        .delete-btn:hover {
            background-color: #fdd5db;
        }
        
        .print-btn {
            background-color: #f8f9fa;
            color: var(--text-color);
        }
        
        .print-btn:hover {
            background-color: #e9ecef;
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
            overflow-y: auto;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            width: 90%;
            max-width: 600px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            position: relative;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e1e4e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn-primary {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            padding: 10px 20px;
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
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
                    <li><a href="nurse_immunizations.php" class="active"><i class="fas fa-syringe"></i> Immunizations</a></li>
                    <li><a href="nurse_vaccine_inventory.php"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Immunization Records</h2>
                
                <div class="action-bar">
                    <form class="search-form" action="" method="GET">
                        <input type="text" name="search" placeholder="Search records..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <form class="filter-form" action="" method="GET">
                        <select name="filter_vaccine">
                            <option value="0">All Vaccines</option>
                            <?php foreach ($vaccines_array as $vaccine): ?>
                                <option value="<?php echo $vaccine['id']; ?>" <?php echo ($filter_vaccine == $vaccine['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vaccine['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
                        
                        <button type="submit">Filter</button>
                        <a href="nurse_immunizations.php" class="btn-sm">Reset</a>
                    </form>
                    
                    <a href="#" class="add-btn" onclick="openImmunizationModal()">
                        <i class="fas fa-plus"></i> Record New Immunization
                    </a>
                </div>
                
                <!-- Add Immunization Modal -->
                <div id="immunizationModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Record New Immunization</h3>
                            <span class="close" onclick="closeImmunizationModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="immunizationForm" method="POST" action="process_immunization.php">
                                <div class="form-group">
                                    <label for="patient">Patient</label>
                                    <select name="patient_id" id="patient" required>
                                        <option value="">Select Patient</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="vaccine">Vaccine</label>
                                    <select name="vaccine_id" id="vaccine" required>
                                        <option value="">Select Vaccine</option>
                                        <?php foreach ($vaccines_array as $vaccine): ?>
                                            <option value="<?php echo $vaccine['id']; ?>">
                                                <?php echo htmlspecialchars($vaccine['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="administered_date">Date Administered</label>
                                    <input type="date" name="administered_date" id="administered_date" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dose_number">Dose Number</label>
                                    <input type="number" name="dose_number" id="dose_number" min="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="batch_number">Batch Number</label>
                                    <input type="text" name="batch_number" id="batch_number" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="expiration_date">Expiration Date</label>
                                    <input type="date" name="expiration_date" id="expiration_date">
                                </div>
                                
                                <div class="form-group">
                                    <label for="next_dose_date">Next Dose Date</label>
                                    <input type="date" name="next_dose_date" id="next_dose_date">
                                </div>
                                
                                <div class="form-group">
                                    <label for="location">Location</label>
                                    <input type="text" name="location" id="location" placeholder="e.g., Health Center, Clinic">
                                </div>
                                
                                <div class="form-group">
                                    <label for="diagnosis">Diagnosis/Notes</label>
                                    <textarea name="diagnosis" id="diagnosis" rows="3" placeholder="Additional notes or diagnosis"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" onclick="closeImmunizationModal()" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary">Save Immunization</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- View Immunization Modal -->
                <div id="viewModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Immunization Details</h3>
                            <span class="close" onclick="closeViewModal()">&times;</span>
                        </div>
                        <div class="modal-body" id="viewModalContent">
                            <!-- Content will be loaded via AJAX -->
                        </div>
                    </div>
                </div>
                
                <!-- Edit Immunization Modal -->
                <div id="editModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Edit Immunization Record</h3>
                            <span class="close" onclick="closeEditModal()">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="editImmunizationForm" method="POST">
                                <input type="hidden" name="immunization_id" id="edit_immunization_id">
                                
                                <div class="form-group">
                                    <label for="edit_patient">Patient</label>
                                    <select name="patient_id" id="edit_patient" required>
                                        <option value="">Select Patient</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_vaccine">Vaccine</label>
                                    <select name="vaccine_id" id="edit_vaccine" required>
                                        <option value="">Select Vaccine</option>
                                        <?php foreach ($vaccines_array as $vaccine): ?>
                                            <option value="<?php echo $vaccine['id']; ?>">
                                                <?php echo htmlspecialchars($vaccine['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_administered_date">Date Administered</label>
                                    <input type="date" name="administered_date" id="edit_administered_date" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_dose_number">Dose Number</label>
                                    <input type="number" name="dose_number" id="edit_dose_number" min="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_batch_number">Batch Number</label>
                                    <input type="text" name="batch_number" id="edit_batch_number" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_expiration_date">Expiration Date</label>
                                    <input type="date" name="expiration_date" id="edit_expiration_date">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_next_dose_date">Next Dose Date</label>
                                    <input type="date" name="next_dose_date" id="edit_next_dose_date">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_location">Location</label>
                                    <input type="text" name="location" id="edit_location">
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_diagnosis">Diagnosis/Notes</label>
                                    <textarea name="diagnosis" id="edit_diagnosis" rows="3"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" onclick="closeEditModal()" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary">Update Immunization</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Print Certificate Modal -->
                <div id="printModal" class="modal">
                    <div class="modal-content" style="max-width: 800px;">
                        <div class="modal-header">
                            <h3>Print Immunization Certificate</h3>
                            <span class="close" onclick="closePrintModal()">&times;</span>
                        </div>
                        <div class="modal-body" id="printModalContent">
                            <!-- Certificate content will be loaded via AJAX -->
                        </div>
                        <div style="padding: 20px; text-align: center; border-top: 1px solid #e1e4e8;">
                            <button onclick="printCertificate()" class="btn-primary">
                                <i class="fas fa-print"></i> Print Certificate
                            </button>
                        </div>
                    </div>
                </div>
                
                <table class="immunization-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Date Administered</th>
                            <th>Dose</th>
                            <th>Batch #</th>
                            <th>Next Dose</th>
                            <th>Transaction Info</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($immunizations->num_rows > 0): ?>
                            <?php while ($immunization = $immunizations->fetch_assoc()): ?>
                                <tr>
                                    <td class="patient-name">
                                        <?php echo htmlspecialchars($immunization['first_name'] . ' ' . $immunization['last_name']); ?>
                                    </td>
                                    <td>
                                        <span class="vaccine-badge"><?php echo htmlspecialchars($immunization['vaccine_name']); ?></span>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($immunization['administered_date'])); ?>
                                    </td>
                                    <td>
                                        Dose <?php echo $immunization['dose_number']; ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($immunization['batch_number']); ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($immunization['next_dose_date']) {
                                            echo date('M j, Y', strtotime($immunization['next_dose_date']));
                                        } else {
                                            echo "N/A";
                                        }
                                        ?>
                                    </td>
                                    <td class="transaction-info">
                                        <div class="small">
                                            <div class="badge bg-primary mb-1"><?php echo TransactionHelper::formatTransactionNumber($immunization['transaction_number']); ?></div><br>
                                            <div class="text-muted" style="font-size: 0.65rem;"><?php echo TransactionHelper::formatTransactionId($immunization['transaction_id']); ?></div>
                                        </div>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="#" class="view-btn" onclick="openViewModal(<?php echo $immunization['id']; ?>)">View</a>
                                        <a href="#" class="edit-btn" onclick="openEditModal(<?php echo $immunization['id']; ?>)">Edit</a>
                                        <a href="#" class="print-btn" onclick="openPrintModal(<?php echo $immunization['id']; ?>)" title="Print Certificate">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center;">No immunization records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&filter_vaccine=<?php echo $filter_vaccine; ?>&filter_date=<?php echo $filter_date; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter_vaccine=<?php echo $filter_vaccine; ?>&filter_date=<?php echo $filter_date; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&filter_vaccine=<?php echo $filter_vaccine; ?>&filter_date=<?php echo $filter_date; ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
        // Function to open the immunization modal
        function openImmunizationModal() {
            document.getElementById('immunizationModal').style.display = 'block';
            // Load patients list via AJAX
            loadPatients();
        }
        
        // Function to close the immunization modal
        function closeImmunizationModal() {
            document.getElementById('immunizationModal').style.display = 'none';
            document.getElementById('immunizationForm').reset();
        }
        
        // Function to open view modal
        function openViewModal(immunizationId) {
            document.getElementById('viewModal').style.display = 'block';
            // Load immunization details via AJAX
            fetch(`get_immunization_details.php?id=${immunizationId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('viewModalContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('viewModalContent').innerHTML = '<p>Error loading immunization details.</p>';
                });
        }
        
        // Function to close view modal
        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }
        
        // Function to open edit modal
        function openEditModal(immunizationId) {
            document.getElementById('editModal').style.display = 'block';
            loadPatients('edit_patient');
            // Load immunization data for editing
            fetch(`get_immunization_data.php?id=${immunizationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const immunization = data.immunization;
                        document.getElementById('edit_immunization_id').value = immunization.id;
                        document.getElementById('edit_patient').value = immunization.patient_id;
                        document.getElementById('edit_vaccine').value = immunization.vaccine_id;
                        document.getElementById('edit_administered_date').value = immunization.administered_date.split(' ')[0];
                        document.getElementById('edit_dose_number').value = immunization.dose_number;
                        document.getElementById('edit_batch_number').value = immunization.batch_number;
                        document.getElementById('edit_expiration_date').value = immunization.expiration_date;
                        document.getElementById('edit_next_dose_date').value = immunization.next_dose_date;
                        document.getElementById('edit_location').value = immunization.location || '';
                        document.getElementById('edit_diagnosis').value = immunization.diagnosis || '';
                    } else {
                        alert('Error loading immunization data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading immunization data');
                });
        }
        
        // Function to close edit modal
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
            document.getElementById('editImmunizationForm').reset();
        }
        
        // Function to open print modal
        function openPrintModal(immunizationId) {
            document.getElementById('printModal').style.display = 'block';
            // Load certificate content via AJAX
            fetch(`get_certificate_content.php?id=${immunizationId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('printModalContent').innerHTML = data;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('printModalContent').innerHTML = '<p>Error loading certificate.</p>';
                });
        }
        
        // Function to close print modal
        function closePrintModal() {
            document.getElementById('printModal').style.display = 'none';
        }
        
        // Function to print certificate
        function printCertificate() {
            const printContent = document.getElementById('printModalContent').innerHTML;
            const originalContent = document.body.innerHTML;
            
            document.body.innerHTML = printContent;
            window.print();
            document.body.innerHTML = originalContent;
            location.reload(); // Reload to restore page functionality
        }
        
        // Function to load patients list via AJAX
        function loadPatients(selectId = 'patient') {
            fetch('get_patients.php')
                .then(response => response.json())
                .then(data => {
                    const patientSelect = document.getElementById(selectId);
                    patientSelect.innerHTML = '<option value="">Select Patient</option>';
                    data.forEach(patient => {
                        patientSelect.innerHTML += `<option value="${patient.id}">${patient.first_name} ${patient.last_name}</option>`;
                    });
                })
                .catch(error => console.error('Error loading patients:', error));
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const immunizationModal = document.getElementById('immunizationModal');
            const viewModal = document.getElementById('viewModal');
            const editModal = document.getElementById('editModal');
            const printModal = document.getElementById('printModal');
            
            if (event.target == immunizationModal) {
                closeImmunizationModal();
            } else if (event.target == viewModal) {
                closeViewModal();
            } else if (event.target == editModal) {
                closeEditModal();
            } else if (event.target == printModal) {
                closePrintModal();
            }
        }
        
        // Handle add immunization form submission
        document.getElementById('immunizationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('nurse_process_immunization.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeImmunizationModal();
                    // Reload the page to show new immunization
                    window.location.reload();
                } else {
                    alert(data.message || 'Error saving immunization record');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving immunization record');
            });
        });
        
        // Handle edit immunization form submission
        document.getElementById('editImmunizationForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('update_immunization.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    // Reload the page to show updated immunization
                    window.location.reload();
                } else {
                    alert(data.message || 'Error updating immunization record');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating immunization record');
            });
        });
    </script>
</body>
</html> 