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
$vaccine_filter = isset($_GET['vaccine']) ? $_GET['vaccine'] : '';

$search_condition = '';
if (!empty($search)) {
    $search = $conn->real_escape_string($search);
    $search_condition = " AND (p.first_name LIKE '%$search%' OR p.last_name LIKE '%$search%')";
}

$vaccine_condition = '';
if (!empty($vaccine_filter)) {
    $vaccine_filter = $conn->real_escape_string($vaccine_filter);
    $vaccine_condition = " AND v.id = '$vaccine_filter'";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id
                        WHERE i.administered_by = ? $search_condition $vaccine_condition");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$total_records = $result->fetch_assoc()['count'];
$total_pages = ceil($total_records / $records_per_page);

// Get immunization records with pagination
$stmt = $conn->prepare("SELECT i.*, p.first_name, p.last_name, p.date_of_birth, v.name as vaccine_name, v.doses_required 
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        JOIN vaccines v ON i.vaccine_id = v.id
                        WHERE i.administered_by = ? $search_condition $vaccine_condition
                        ORDER BY i.administered_date DESC
                        LIMIT ?, ?");
$stmt->bind_param("iii", $user_id, $offset, $records_per_page);
$stmt->execute();
$immunizations = $stmt->get_result();

// Get all vaccines for filter dropdown
$vaccines = $conn->query("SELECT id, name FROM vaccines ORDER BY name");

// Get all patients for the modal dropdown
$patients = $conn->query("SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name");

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

// Don't close the connection here as we need it for the modal dropdowns
// $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Immunization Records - ImmuCare</title>
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
            align-items: center;
            gap: 10px;
        }
        
        .filter-select {
            padding: 8px 12px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .filter-btn {
            padding: 8px 15px;
            background-color: #e9ecef;
            color: var(--text-color);
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: var(--transition);
        }
        
        .filter-btn:hover {
            background-color: #dee2e6;
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
        
        .immunization-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .immunization-table th {
            background-color: #f8f9fa;
            text-align: left;
            padding: 12px 15px;
            font-weight: 500;
            color: var(--text-color);
            border-bottom: 1px solid #e9ecef;
        }
        
        .immunization-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .immunization-table tr:hover {
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
        
        .vaccine-name {
            font-weight: 500;
        }
        
        .vaccine-info {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .dose-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            background-color: #e8f0fe;
            color: var(--primary-color);
        }
        
        .record-actions {
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

        .cancel-btn:hover {
            background-color: #dee2e6;
        }

        /* Alert Styles */
        .alert {
            padding: 12px 16px;
            margin-bottom: 20px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert i {
            font-size: 1.2em;
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
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(-10%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 3% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s ease-out;
        }

        .modal-content::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e4e8;
        }

        .modal-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
            margin: 0;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s ease;
            padding: 5px;
            line-height: 20px;
            border-radius: 50%;
        }

        .close:hover {
            color: var(--primary-color);
            background-color: #f0f0f0;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-family: inherit;
            font-size: 14px;
            transition: all 0.2s ease;
            background-color: #fff;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }

        .form-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23666' viewBox='0 0 16 16'%3E%3Cpath d='M8 11.5l-5-5h10l-5 5z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 35px;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-group input[type="date"],
        .form-group input[type="datetime-local"] {
            padding: 8px 12px;
        }

        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
        }

        .submit-btn,
        .cancel-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 100px;
        }

        .submit-btn {
            background-color: var(--primary-color);
            color: white;
        }

        .submit-btn:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
        }

        .cancel-btn {
            background-color: #e9ecef;
            color: #495057;
        }

        .cancel-btn:hover {
            background-color: #dee2e6;
            transform: translateY(-1px);
        }

        /* Required field indicator */
        .form-group label::after {
            content: "*";
            color: #dc3545;
            margin-left: 4px;
            display: inline-block;
        }

        .form-group label[for="next_dose_date"]::after,
        .form-group label[for="diagnosis"]::after {
            display: none;
        }

        /* Field validation styles */
        .form-group input:invalid,
        .form-group select:invalid {
            border-color: #dc3545;
        }

        .form-group input:invalid:focus,
        .form-group select:invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .modal-content {
                margin: 0;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
                width: 100%;
            }

            .form-actions {
                position: sticky;
                bottom: 0;
                background: #fff;
                padding: 15px 0;
                margin-bottom: 0;
            }
        }
    </style>
    <script>
        function openAddImmunizationModal() {
            document.getElementById('addImmunizationModal').style.display = 'block';
        }

        function closeAddImmunizationModal() {
            document.getElementById('addImmunizationModal').style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('addImmunizationModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }

        // Form validation and submission
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('addImmunizationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Add any additional client-side validation here
                
                // Submit the form
                this.submit();
            });
        });
    </script>
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
                    <li><a href="midwife_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php" class="active"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Immunization Records</h2>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        Immunization record added successfully!
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['error'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="action-bar">
                    <form class="search-form" method="GET" action="">
                        <?php if (!empty($vaccine_filter)): ?>
                            <input type="hidden" name="vaccine" value="<?php echo htmlspecialchars($vaccine_filter); ?>">
                        <?php endif; ?>
                        <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <form class="filter-form" method="GET" action="">
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        <select name="vaccine" class="filter-select">
                            <option value="">All Vaccines</option>
                            <?php while ($vaccine = $vaccines->fetch_assoc()): ?>
                                <option value="<?php echo $vaccine['id']; ?>" <?php echo $vaccine_filter == $vaccine['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vaccine['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <button type="submit" class="filter-btn">Filter</button>
                        <?php if (!empty($vaccine_filter) || !empty($search)): ?>
                            <a href="midwife_immunization_records.php" class="filter-btn">Clear</a>
                        <?php endif; ?>
                    </form>
                    
                    <a href="#" class="add-btn" onclick="openAddImmunizationModal()">
                        <i class="fas fa-plus"></i> Add New Record
                    </a>
                </div>
                
                <table class="immunization-table">
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Date Administered</th>
                            <th>Next Dose</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($immunizations->num_rows > 0): ?>
                            <?php while ($record = $immunizations->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="patient-name">
                                            <?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?>
                                        </div>
                                        <div class="patient-info">
                                            <?php 
                                                $dob = new DateTime($record['date_of_birth']);
                                                $now = new DateTime();
                                                $age = $now->diff($dob)->y;
                                                echo $age . ' years old';
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="vaccine-name">
                                            <?php echo htmlspecialchars($record['vaccine_name']); ?>
                                        </div>
                                        <div class="vaccine-info">
                                            Batch: <?php echo htmlspecialchars($record['batch_number']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="dose-badge">
                                            Dose <?php echo $record['dose_number']; ?> of <?php echo $record['doses_required']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($record['administered_date'])); ?>
                                        <div class="patient-info">
                                            <?php echo date('g:i A', strtotime($record['administered_date'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($record['next_dose_date']): ?>
                                            <?php echo date('M d, Y', strtotime($record['next_dose_date'])); ?>
                                            <div class="patient-info">
                                                <?php
                                                    $next_dose = new DateTime($record['next_dose_date']);
                                                    $interval = $now->diff($next_dose);
                                                    $days_remaining = $interval->invert ? 'Overdue' : 'In ' . $interval->days . ' days';
                                                    echo $days_remaining;
                                                ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="patient-info">No next dose</div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="record-actions">
                                        <a href="view_immunization.php?id=<?php echo $record['id']; ?>" class="action-btn view-btn">View</a>
                                        <a href="edit_immunization.php?id=<?php echo $record['id']; ?>" class="action-btn edit-btn">Edit</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 20px;">No immunization records found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1); ?>&search=<?php echo urlencode($search); ?>&vaccine=<?php echo urlencode($vaccine_filter); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&vaccine=<?php echo urlencode($vaccine_filter); ?>" <?php echo ($i == $page) ? 'class="active"' : ''; ?>><?php echo $i; ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1); ?>&search=<?php echo urlencode($search); ?>&vaccine=<?php echo urlencode($vaccine_filter); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Immunization Modal -->
    <div id="addImmunizationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Add New Immunization Record</h2>
                <span class="close" onclick="closeAddImmunizationModal()">&times;</span>
            </div>
            <form id="addImmunizationForm" method="POST" action="midwife_process_immunization.php">
                <div class="form-group">
                    <label for="patient">Patient</label>
                    <select name="patient_id" id="patient" required>
                        <option value="">Select Patient</option>
                        <?php while ($patient = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $patient['id']; ?>">
                                <?php echo htmlspecialchars($patient['last_name'] . ", " . $patient['first_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="vaccine">Vaccine</label>
                    <select name="vaccine_id" id="vaccine" required>
                        <option value="">Select Vaccine</option>
                        <?php
                        $vaccines->data_seek(0);
                        while ($vaccine = $vaccines->fetch_assoc()): ?>
                            <option value="<?php echo $vaccine['id']; ?>">
                                <?php echo htmlspecialchars($vaccine['name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
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
                    <input type="date" name="expiration_date" id="expiration_date" required>
                </div>
                
                <div class="form-group">
                    <label for="administered_date">Administered Date</label>
                    <input type="datetime-local" name="administered_date" id="administered_date" required>
                </div>
                
                <div class="form-group">
                    <label for="next_dose_date">Next Dose Date</label>
                    <input type="date" name="next_dose_date" id="next_dose_date">
                </div>
                
                <div class="form-group">
                    <label for="location">Location</label>
                    <input type="text" name="location" id="location" required>
                </div>
                
                <div class="form-group">
                    <label for="diagnosis">Diagnosis/Notes</label>
                    <textarea name="diagnosis" id="diagnosis" rows="3"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="submit-btn">Save Record</button>
                    <button type="button" class="cancel-btn" onclick="closeAddImmunizationModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php
// Close the database connection at the end of the file
$conn->close();
?> 