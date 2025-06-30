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
$result = $conn->query($query);

// Store patients in an array
$patients_array = array();
while ($patient = $result->fetch_assoc()) {
    $patients_array[] = $patient;
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
    <title>Manage Patients - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Add JavaScript before styles -->
    <script>
        // Modal Functions
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Toggle vaccine selection based on purpose
        function toggleVaccineSelect(purposeSelect, patientId) {
            const vaccineGroup = document.getElementById('vaccineGroup' + patientId);
            const vaccineSelect = document.getElementById('vaccine_id' + patientId);
            
            if (purposeSelect.value === 'vaccination') {
                vaccineGroup.style.display = 'block';
                vaccineSelect.required = true;
            } else {
                vaccineGroup.style.display = 'none';
                vaccineSelect.required = false;
                vaccineSelect.value = '';
            }
        }

        // Initialize when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                    document.body.style.overflow = 'auto';
                }
            };

            // Close modal with Escape key
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape') {
                    const modals = document.querySelectorAll('.modal');
                    modals.forEach(modal => {
                        if (modal.style.display === 'block') {
                            modal.style.display = 'none';
                            document.body.style.overflow = 'auto';
                        }
                    });
                }
            });

            // Initialize date inputs with current date
            const today = new Date().toISOString().split('T')[0];
            document.querySelectorAll('input[type="date"]').forEach(dateInput => {
                if (!dateInput.value) {
                    dateInput.min = today;
                }
            });

            // Add click handlers to all modal triggers
            document.querySelectorAll('[data-modal]').forEach(trigger => {
                trigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    const modalId = trigger.getAttribute('data-modal');
                    openModal(modalId);
                });
            });
        });
    </script>

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
        
        /* Modal Base Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 20px auto;
            width: 90%;
            max-width: 800px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }

        /* Modal Header */
        .modal-header {
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
            padding: 20px 30px;
            border-bottom: 1px solid rgba(var(--primary-rgb), 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-radius: 12px 12px 0 0;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
            font-weight: 600;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6c757d;
            transition: color 0.2s;
            padding: 5px;
            line-height: 1;
        }

        .close-modal:hover {
            color: #343a40;
        }

        /* Modal Body */
        .modal-body {
            padding: 30px;
            overflow-y: auto;
            max-height: calc(100vh - 200px);
        }

        /* Form Groups */
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            flex: 1;
            min-width: 250px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #495057;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
            outline: none;
        }

        /* View Modal Specific */
        .patient-details {
            padding: 0;
        }

        .detail-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .detail-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--primary-color);
            opacity: 0.2;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .detail-item {
            margin-bottom: 15px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--primary-color);
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .detail-value {
            color: #212529;
            font-size: 1rem;
            margin: 0;
        }

        /* Update form section titles to match */
        .form-section-title {
            color: var(--primary-color);
            font-size: 1.2rem;
            font-weight: 600;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-title::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--primary-color);
            opacity: 0.2;
        }

        /* Update modal header to use primary color */
        .modal-header {
            border-bottom-color: rgba(var(--primary-rgb), 0.2);
        }

        .modal-footer {
            border-top-color: rgba(var(--primary-rgb), 0.2);
        }

        /* Modal Footer */
        .modal-footer {
            position: sticky;
            bottom: 0;
            background: #fff;
            z-index: 1;
            padding: 20px 30px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-radius: 0 0 12px 12px;
        }

        .modal-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .modal-btn-primary {
            background-color: var(--primary-color);
            color: white;
        }

        .modal-btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .modal-btn-secondary {
            background-color: #e9ecef;
            color: #495057;
        }

        .modal-btn-secondary:hover {
            background-color: #dee2e6;
        }

        /* Scrollbar Styling */
        .modal-content::-webkit-scrollbar,
        .modal-body::-webkit-scrollbar {
            width: 8px;
        }

        .modal-content::-webkit-scrollbar-track,
        .modal-body::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb,
        .modal-body::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .modal-content::-webkit-scrollbar-thumb:hover,
        .modal-body::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .modal-content {
                margin: 10px;
                width: calc(100% - 20px);
            }

            .form-group {
                min-width: 100%;
            }

            .modal-body {
                padding: 20px;
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
                    
                    <button data-modal="addPatientModal" class="add-btn">
                        <i class="fas fa-plus"></i> Add New Patient
                    </button>
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
                        <?php if (count($patients_array) > 0): ?>
                            <?php foreach ($patients_array as $patient): ?>
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
                                        <a href="#" data-modal="viewPatientModal<?php echo $patient['id']; ?>" class="action-btn view-btn">View</a>
                                        <a href="#" data-modal="editPatientModal<?php echo $patient['id']; ?>" class="action-btn edit-btn">Edit</a>
                                        <a href="#" data-modal="scheduleModal<?php echo $patient['id']; ?>" class="action-btn view-btn">Schedule</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
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

    <!-- Add Patient Modal -->
    <div id="addPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Patient</h3>
                <button class="close-modal" onclick="closeModal('addPatientModal')">&times;</button>
            </div>
            <form action="process_add_patient.php" method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" required>
                    </div>
                    <div class="form-group">
                        <label for="purok">Purok</label>
                        <input type="text" id="purok" name="purok" required>
                    </div>
                    <div class="form-group">
                        <label for="city">City</label>
                        <input type="text" id="city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="province">Province</label>
                        <input type="text" id="province" name="province" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Add Patient</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Patient Modals for each patient -->
    <?php foreach ($patients_array as $patient): ?>
    <!-- View Patient Modal -->
    <div id="viewPatientModal<?php echo $patient['id']; ?>" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Patient Information</h3>
                <button class="close-modal" onclick="closeModal('viewPatientModal<?php echo $patient['id']; ?>')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="patient-details">
                    <div class="detail-section">
                        <h4 class="section-title">Personal Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-label">Full Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Date of Birth</div>
                                <div class="detail-value">
                                    <?php 
                                        echo date('M d, Y', strtotime($patient['date_of_birth']));
                                        $dob = new DateTime($patient['date_of_birth']);
                                        $now = new DateTime();
                                        $age = $now->diff($dob)->y;
                                        echo " ($age years old)";
                                    ?>
                                </div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Gender</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['gender']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 class="section-title">Contact Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-label">Phone Number</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['phone_number']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Address</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['purok'] . ', ' . $patient['city'] . ', ' . $patient['province']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 class="section-title">Medical Summary</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-label">Total Immunizations</div>
                                <div class="detail-value"><?php echo $patient['immunization_count']; ?> records</div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Upcoming Appointments</div>
                                <div class="detail-value"><?php echo $patient['appointment_count']; ?> scheduled</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('viewPatientModal<?php echo $patient['id']; ?>')">Close</button>
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editPatientModal<?php echo $patient['id']; ?>" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Patient Information</h3>
                <button class="close-modal" onclick="closeModal('editPatientModal<?php echo $patient['id']; ?>')">&times;</button>
            </div>
            <form action="process_edit_patient.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                    
                    <div class="detail-section">
                        <h4 class="form-section-title">Personal Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_first_name<?php echo $patient['id']; ?>">First Name</label>
                                <input type="text" id="edit_first_name<?php echo $patient['id']; ?>" name="first_name" value="<?php echo htmlspecialchars($patient['first_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_last_name<?php echo $patient['id']; ?>">Last Name</label>
                                <input type="text" id="edit_last_name<?php echo $patient['id']; ?>" name="last_name" value="<?php echo htmlspecialchars($patient['last_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_middle_name<?php echo $patient['id']; ?>">Middle Name</label>
                                <input type="text" id="edit_middle_name<?php echo $patient['id']; ?>" name="middle_name" value="<?php echo htmlspecialchars($patient['middle_name']); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_date_of_birth<?php echo $patient['id']; ?>">Date of Birth</label>
                                <input type="date" id="edit_date_of_birth<?php echo $patient['id']; ?>" name="date_of_birth" value="<?php echo $patient['date_of_birth']; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_gender<?php echo $patient['id']; ?>">Gender</label>
                                <select id="edit_gender<?php echo $patient['id']; ?>" name="gender" required>
                                    <option value="Male" <?php echo $patient['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo $patient['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 class="form-section-title">Contact Information</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_phone_number<?php echo $patient['id']; ?>">Phone Number</label>
                                <input type="tel" id="edit_phone_number<?php echo $patient['id']; ?>" name="phone_number" value="<?php echo htmlspecialchars($patient['phone_number']); ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="edit_purok<?php echo $patient['id']; ?>">Purok</label>
                                <input type="text" id="edit_purok<?php echo $patient['id']; ?>" name="purok" value="<?php echo htmlspecialchars($patient['purok']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_city<?php echo $patient['id']; ?>">City</label>
                                <input type="text" id="edit_city<?php echo $patient['id']; ?>" name="city" value="<?php echo htmlspecialchars($patient['city']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="edit_province<?php echo $patient['id']; ?>">Province</label>
                                <input type="text" id="edit_province<?php echo $patient['id']; ?>" name="province" value="<?php echo htmlspecialchars($patient['province']); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('editPatientModal<?php echo $patient['id']; ?>')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Schedule Modal -->
    <div id="scheduleModal<?php echo $patient['id']; ?>" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Schedule Appointment</h3>
                <button class="close-modal" onclick="closeModal('scheduleModal<?php echo $patient['id']; ?>')">&times;</button>
            </div>
            <form action="process_schedule_appointment.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="patient_id" value="<?php echo $patient['id']; ?>">
                    <input type="hidden" name="staff_id" value="<?php echo $_SESSION['user_id']; ?>">
                    
                    <div class="detail-section">
                        <h4 class="form-section-title">Patient Information</h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <div class="detail-label">Name</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name'] . ' ' . $patient['middle_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <div class="detail-label">Contact Number</div>
                                <div class="detail-value"><?php echo htmlspecialchars($patient['phone_number']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 class="form-section-title">Appointment Details</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="appointment_date<?php echo $patient['id']; ?>">Date</label>
                                <input type="date" 
                                    id="appointment_date<?php echo $patient['id']; ?>" 
                                    name="appointment_date" 
                                    required 
                                    min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment_time<?php echo $patient['id']; ?>">Time</label>
                                <input type="time" 
                                    id="appointment_time<?php echo $patient['id']; ?>" 
                                    name="appointment_time" 
                                    required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="purpose<?php echo $patient['id']; ?>">Purpose</label>
                                <select id="purpose<?php echo $patient['id']; ?>" 
                                        name="purpose" 
                                        required 
                                        onchange="toggleVaccineSelect(this, <?php echo $patient['id']; ?>)">
                                    <option value="">Select Purpose</option>
                                    <option value="vaccination">Vaccination</option>
                                    <option value="checkup">Regular Check-up</option>
                                    <option value="consultation">Consultation</option>
                                    <option value="follow_up">Follow-up Visit</option>
                                </select>
                            </div>
                            <div class="form-group" id="vaccineGroup<?php echo $patient['id']; ?>" style="display: none;">
                                <label for="vaccine_id<?php echo $patient['id']; ?>">Vaccine</label>
                                <select id="vaccine_id<?php echo $patient['id']; ?>" name="vaccine_id">
                                    <option value="">Select Vaccine</option>
                                    <?php
                                    // Get available vaccines
                                    $vaccine_query = "SELECT id, name, manufacturer FROM vaccines ORDER BY name";
                                    $vaccine_result = $conn->query($vaccine_query);
                                    while ($vaccine = $vaccine_result->fetch_assoc()) {
                                        echo '<option value="' . $vaccine['id'] . '">' . 
                                             htmlspecialchars($vaccine['name']) . ' (' . 
                                             htmlspecialchars($vaccine['manufacturer']) . ')</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="notes<?php echo $patient['id']; ?>">Notes</label>
                                <textarea id="notes<?php echo $patient['id']; ?>" 
                                    name="notes" 
                                    rows="3" 
                                    placeholder="Add any additional notes, symptoms, or special instructions..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h4 class="form-section-title">Appointment Status</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status<?php echo $patient['id']; ?>">Initial Status</label>
                                <select id="status<?php echo $patient['id']; ?>" name="status" required>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="requested">Requested</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('scheduleModal<?php echo $patient['id']; ?>')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-primary">Schedule Appointment</button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</body>
</html> 