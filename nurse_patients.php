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
        
        .patient-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .patient-table th, .patient-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .patient-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .patient-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .patient-name {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn, .edit-btn, .delete-btn, .immunization-btn {
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
        
        .immunization-btn {
            background-color: #e6f2ff;
            color: #0066cc;
            cursor: pointer;
        }
        
        .immunization-btn:hover {
            background-color: #cce5ff;
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
                gap: 15px;
            }
            
            .search-form {
                width: 100%;
            }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
        }

        .modal-content {
            position: relative;
            background-color: #fff;
            margin: 50px auto;
            padding: 20px;
            width: 80%;
            max-width: 800px;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e1e4e8;
        }

        .modal-title {
            font-size: 1.5rem;
            color: var(--primary-color);
            margin: 0;
        }

        .close-modal {
            font-size: 1.5rem;
            color: var(--light-text);
            cursor: pointer;
            background: none;
            border: none;
            padding: 0;
        }

        .close-modal:hover {
            color: var(--text-color);
        }

        .modal-body {
            max-height: calc(100vh - 200px);
            overflow-y: auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
            font-family: 'Poppins', sans-serif;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .modal-footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .modal-btn {
            padding: 8px 15px;
            border-radius: 4px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .modal-btn-primary {
            background-color: var(--primary-color);
            color: white;
            border: none;
        }

        .modal-btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .modal-btn-secondary {
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
        }

        .modal-btn-secondary:hover {
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
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php" class="active"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Manage Patients</h2>
                
                <div class="action-bar">
                    <form class="search-form" action="" method="GET">
                        <input type="text" name="search" placeholder="Search patients..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="javascript:void(0)" class="add-btn" onclick="openModal('addPatientModal')">
                        <i class="fas fa-plus"></i> Add New Patient
                    </a>
                </div>
                
                <table class="patient-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Contact</th>
                            <th>Immunizations</th>
                            <th>Appointments</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($patients->num_rows > 0): ?>
                            <?php while ($patient = $patients->fetch_assoc()): ?>
                                <tr>
                                    <td class="patient-name">
                                        <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?>
                                    </td>
                                    <td>
                                        <?php echo ucfirst(htmlspecialchars($patient['gender'])); ?>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($patient['phone_number']); ?></div>
                                        <div class="patient-info">
                                            <?php echo htmlspecialchars($patient['purok'] . ', ' . $patient['city'] . ', ' . $patient['province']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo $patient['immunization_count']; ?>
                                    </td>
                                    <td>
                                        <?php echo $patient['appointment_count']; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="javascript:void(0)" class="view-btn" data-id="<?php echo $patient['id']; ?>" onclick="loadPatientDetails(this.getAttribute('data-id'))">View</a>
                                        <a href="javascript:void(0)" class="edit-btn" data-id="<?php echo $patient['id']; ?>" onclick="loadPatientForEdit(this.getAttribute('data-id'))">Edit</a>
                                        <a href="javascript:void(0)" class="immunization-btn" data-id="<?php echo $patient['id']; ?>" onclick="loadImmunizationHistory(this.getAttribute('data-id'))">
                                            <i class="fas fa-syringe"></i> Immunization
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No patients found.</td>
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
                            <?php if ($i == $page): ?>
                                <span><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
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
            <div class="modal-body">
                <form id="addPatientForm" method="POST" action="api/add_patient.php">
                    <h4>Account Information</h4>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" required>
                    </div>

                    <h4>Personal Information</h4>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone_number">Phone Number</label>
                        <input type="tel" id="phone_number" name="phone_number" required>
                    </div>

                    <h4>Address Information</h4>
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
                    <div class="form-group">
                        <label for="postal_code">Postal Code</label>
                        <input type="text" id="postal_code" name="postal_code">
                    </div>

                    <h4>Medical Information</h4>
                    <div class="form-group">
                        <label for="medical_history">Medical History</label>
                        <textarea id="medical_history" name="medical_history" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="allergies">Allergies</label>
                        <textarea id="allergies" name="allergies" rows="3"></textarea>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('addPatientModal')">Cancel</button>
                        <button type="submit" class="modal-btn modal-btn-primary">Add Patient</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Patient Modal -->
    <div id="viewPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Patient Details</h3>
                <button class="close-modal" onclick="closeModal('viewPatientModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewPatientContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <!-- Edit Patient Modal -->
    <div id="editPatientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Patient</h3>
                <button class="close-modal" onclick="closeModal('editPatientModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editPatientForm" method="POST" action="api/update_patient.php">
                    <input type="hidden" id="edit_patient_id" name="patient_id">
                    <div class="form-group">
                        <label for="edit_first_name">First Name</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_last_name">Last Name</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_date_of_birth">Date of Birth</label>
                        <input type="date" id="edit_date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_gender">Gender</label>
                        <select id="edit_gender" name="gender" required>
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_phone_number">Phone Number</label>
                        <input type="tel" id="edit_phone_number" name="phone_number" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_purok">Purok</label>
                        <input type="text" id="edit_purok" name="purok" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_city">City</label>
                        <input type="text" id="edit_city" name="city" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_province">Province</label>
                        <input type="text" id="edit_province" name="province" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="modal-btn modal-btn-secondary" onclick="closeModal('editPatientModal')">Cancel</button>
                        <button type="submit" class="modal-btn modal-btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Immunization History Modal -->
    <div id="immunizationHistoryModal" class="modal">
        <div class="modal-content" style="width: 90%; max-width: 1000px;">
            <div class="modal-header">
                <h3 class="modal-title">Immunization History</h3>
                <button class="close-modal" onclick="closeModal('immunizationHistoryModal')">&times;</button>
            </div>
            <div class="modal-body" id="immunizationHistoryContent">
                <!-- Content will be loaded dynamically -->
            </div>
        </div>
    </div>

    <script>
        // Function to open modal
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
            document.body.style.overflow = 'hidden';
        }

        // Function to close modal
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Function to load patient details
        async function loadPatientDetails(patientId) {
            try {
                const response = await fetch(`api/get_patient.php?id=${patientId}`);
                const data = await response.json();
                
                if (data.success) {
                    document.getElementById('viewPatientContent').innerHTML = `
                        <div class="patient-details">
                            <p><strong>Name:</strong> ${data.patient.first_name} ${data.patient.last_name}</p>
                            <p><strong>Date of Birth:</strong> ${data.patient.date_of_birth}</p>
                            <p><strong>Gender:</strong> ${data.patient.gender}</p>
                            <p><strong>Phone:</strong> ${data.patient.phone_number}</p>
                            <p><strong>Address:</strong> ${data.patient.purok}, ${data.patient.city}, ${data.patient.province}</p>
                        </div>
                    `;
                    openModal('viewPatientModal');
                }
            } catch (error) {
                console.error('Error loading patient details:', error);
            }
        }

        // Function to load patient data for editing
        async function loadPatientForEdit(patientId) {
            try {
                const response = await fetch(`api/get_patient.php?id=${patientId}`);
                const data = await response.json();
                
                if (data.success) {
                    const patient = data.patient;
                    document.getElementById('edit_patient_id').value = patient.id;
                    document.getElementById('edit_first_name').value = patient.first_name;
                    document.getElementById('edit_last_name').value = patient.last_name;
                    document.getElementById('edit_date_of_birth').value = patient.date_of_birth;
                    document.getElementById('edit_gender').value = patient.gender;
                    document.getElementById('edit_phone_number').value = patient.phone_number;
                    document.getElementById('edit_purok').value = patient.purok;
                    document.getElementById('edit_city').value = patient.city;
                    document.getElementById('edit_province').value = patient.province;
                    
                    openModal('editPatientModal');
                }
            } catch (error) {
                console.error('Error loading patient for edit:', error);
            }
        }

        // Function to load immunization history
        async function loadImmunizationHistory(patientId) {
            try {
                // First get the patient's name
                const patientResponse = await fetch(`api/get_patient.php?id=${patientId}`);
                const patientData = await patientResponse.json();
                let patientName = '';
                
                if (patientData.success) {
                    patientName = `${patientData.patient.first_name} ${patientData.patient.last_name}`;
                }
                
                const response = await fetch(`api/get_immunization_history.php?patient_id=${patientId}`);
                const data = await response.json();
                
                if (data.success) {
                    let historyHtml = '<div class="immunization-history">';
                    
                    // Add patient info at the top
                    if (patientName) {
                        historyHtml += `<h4>Immunization Records for ${patientName}</h4>`;
                    }
                    
                    // Add button to record new immunization
                    historyHtml += `<div style="margin-bottom: 20px;">
                        <a href="nurse_process_immunization.php?patient_id=${patientId}" class="add-btn" style="display: inline-block;">
                            <i class="fas fa-plus"></i> Record New Immunization
                        </a>
                    </div>`;
                    
                    if (data.immunizations.length > 0) {
                        historyHtml += '<table class="patient-table"><thead><tr>' +
                            '<th>Vaccine</th>' +
                            '<th>Dose #</th>' +
                            '<th>Date Administered</th>' +
                            '<th>Administrator</th>' +
                            '<th>Batch #</th>' +
                            '<th>Next Dose</th>' +
                            '</tr></thead><tbody>';
                        
                        data.immunizations.forEach(immunization => {
                            historyHtml += `<tr>
                                <td>${immunization.vaccine_name}</td>
                                <td>${immunization.dose_number}</td>
                                <td>${immunization.date}</td>
                                <td>${immunization.administrator}</td>
                                <td>${immunization.batch_number || 'N/A'}</td>
                                <td>${immunization.next_dose_date}</td>
                            </tr>`;
                        });
                        
                        historyHtml += '</tbody></table>';
                    } else {
                        historyHtml += '<p>No immunization records found for this patient.</p>';
                    }
                    historyHtml += '</div>';
                    
                    document.getElementById('immunizationHistoryContent').innerHTML = historyHtml;
                    openModal('immunizationHistoryModal');
                }
            } catch (error) {
                console.error('Error loading immunization history:', error);
            }
        }

        // Update the action buttons to use modal functions
        document.addEventListener('DOMContentLoaded', function() {
            // Update Add Patient button
            const addPatientBtn = document.querySelector('.add-btn');
            addPatientBtn.href = 'javascript:void(0)';
            addPatientBtn.onclick = () => openModal('addPatientModal');
            
            // Update table action buttons
            document.querySelectorAll('.patient-table').forEach(table => {
                table.addEventListener('click', function(e) {
                    const target = e.target;
                    if (target.classList.contains('view-btn')) {
                        e.preventDefault();
                        const patientId = target.getAttribute('data-id');
                        loadPatientDetails(patientId);
                    } else if (target.classList.contains('edit-btn')) {
                        e.preventDefault();
                        const patientId = target.getAttribute('data-id');
                        loadPatientForEdit(patientId);
                    } else if (target.classList.contains('immunization-btn')) {
                        e.preventDefault();
                        const patientId = target.getAttribute('data-id');
                        loadImmunizationHistory(patientId);
                    }
                });
            });
        });
    </script>
</body>
</html> 