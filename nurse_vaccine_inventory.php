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
    $search_condition = " WHERE name LIKE '%$search%' OR manufacturer LIKE '%$search%'";
}

// Handle pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get total number of vaccines
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM vaccines" . $search_condition);
$stmt->execute();
$result = $stmt->get_result();
$total_vaccines = $result->fetch_assoc()['count'];
$total_pages = ceil($total_vaccines / $records_per_page);

// Get total immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations");
$stmt->execute();
$result = $stmt->get_result();
$total_immunizations = $result->fetch_assoc()['count'];

// Get total patients vaccinated
$stmt = $conn->prepare("SELECT COUNT(DISTINCT patient_id) as count FROM immunizations WHERE patient_id > 0");
$stmt->execute();
$result = $stmt->get_result();
$total_patients_vaccinated = $result->fetch_assoc()['count'];

// Get today's immunizations
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE DATE(administered_date) = CURDATE()");
$stmt->execute();
$result = $stmt->get_result();
$todays_immunizations = $result->fetch_assoc()['count'];

// Get vaccines with pagination
$query = "SELECT * FROM vaccines $search_condition ORDER BY name ASC LIMIT $offset, $records_per_page";
$vaccines = $conn->query($query);

// Get immunization counts for all vaccines in the current page
$immunization_counts = array();
$vaccine_ids = array();
while ($vaccine = $vaccines->fetch_assoc()) {
    $vaccine_ids[] = $vaccine['id'];
}
$vaccines->data_seek(0); // Reset the result pointer

if (!empty($vaccine_ids)) {
    $ids_string = implode(',', $vaccine_ids);
    $query = "SELECT vaccine_id, COUNT(*) as count 
              FROM immunizations 
              WHERE vaccine_id IN ($ids_string) 
              GROUP BY vaccine_id";
    $counts_result = $conn->query($query);
    while ($row = $counts_result->fetch_assoc()) {
        $immunization_counts[$row['vaccine_id']] = $row['count'];
    }
}

$conn->close();

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
    <title>Vaccine Inventory - ImmuCare</title>
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
        
        .vaccine-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .vaccine-table th, .vaccine-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .vaccine-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .vaccine-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .vaccine-name {
            font-weight: 500;
            color: var(--primary-color);
        }
        
        .doses-badge {
            display: inline-block;
            padding: 4px 8px;
            background-color: #e8f0fe;
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn, .edit-btn, .delete-btn {
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
        
        .inventory-stats {
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
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #e1e4e8;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.4rem;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            color: #666;
            cursor: pointer;
            transition: var(--transition);
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .close:hover {
            color: #333;
            background-color: #e9ecef;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        /* Form Styles */
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
            padding: 10px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(var(--primary-rgb), 0.1);
        }
        
        .form-group input[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e1e4e8;
        }
        
        .btn-primary,
        .btn-secondary {
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: #f1f3f5;
            color: var(--text-color);
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
        
        /* Vaccine Details Styles */
        .vaccine-details {
            padding: 10px;
        }
        
        .vaccine-details h4 {
            color: var(--primary-color);
            margin: 0 0 20px 0;
            font-size: 1.2rem;
        }
        
        .vaccine-details p {
            margin: 10px 0;
            line-height: 1.6;
        }
        
        .vaccine-details strong {
            color: var(--text-color);
            font-weight: 500;
            min-width: 150px;
            display: inline-block;
        }
        
        /* Form Validation Styles */
        .form-group input:invalid,
        .form-group select:invalid {
            border-color: #dc3545;
        }
        
        .form-group input:invalid:focus,
        .form-group select:invalid:focus {
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.1);
        }
        
        /* Loading State */
        .loading {
            position: relative;
            pointer-events: none;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 20px;
            height: 20px;
            margin: -10px 0 0 -10px;
            border: 2px solid transparent;
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
        
        /* Responsive Styles */
        @media screen and (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 20px auto;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions button {
                width: 100%;
            }
            
            .vaccine-details strong {
                display: block;
                margin-bottom: 5px;
            }
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
        
        .loading-spinner {
            text-align: center;
            padding: 20px;
            color: var(--primary-color);
        }
        
        .error-message {
            color: #dc3545;
            text-align: center;
            padding: 20px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 1px solid #f0f0f0;
            padding-bottom: 10px;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-row strong {
            flex: 0 0 200px;
            color: var(--primary-color);
        }
        
        .detail-row span {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .detail-row {
                flex-direction: column;
            }
            
            .detail-row strong {
                margin-bottom: 5px;
            }
        }
    </style>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Function to open/close modals
            function openModal(modalId) {
                document.getElementById(modalId).style.display = 'block';
            }
            
            function closeModal(modalId) {
                document.getElementById(modalId).style.display = 'none';
                const form = document.getElementById(modalId).querySelector('form');
                if (form) {
                    form.reset();
                }
            }
            
            // Make these functions globally available
            window.openViewModal = function(vaccineId) {
                const modal = document.getElementById('viewVaccineModal');
                const content = document.getElementById('viewVaccineContent');
                
                // Show loading state
                content.innerHTML = '<div class="loading-spinner">Loading...</div>';
                modal.style.display = 'block';
                
                // Load vaccine details via AJAX
                fetch(`get_vaccine_details.php?id=${vaccineId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const vaccine = data.vaccine;
                            content.innerHTML = `
                                <div class="vaccine-details">
                                    <div class="detail-row">
                                        <strong>Name:</strong>
                                        <span>${vaccine.name}</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Manufacturer:</strong>
                                        <span>${vaccine.manufacturer}</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Doses Required:</strong>
                                        <span>${vaccine.doses_required} dose${vaccine.doses_required > 1 ? 's' : ''}</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Recommended Age:</strong>
                                        <span>${vaccine.recommended_age || 'N/A'}</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Times Administered:</strong>
                                        <span>${vaccine.times_administered || 0} times</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Patients Vaccinated:</strong>
                                        <span>${vaccine.patients_vaccinated || 0} patients</span>
                                    </div>
                                    <div class="detail-row">
                                        <strong>Description:</strong>
                                        <span>${vaccine.description || 'N/A'}</span>
                                    </div>
                                </div>
                            `;
                        } else {
                            content.innerHTML = '<div class="error-message">Error loading vaccine details. Please try again.</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        content.innerHTML = '<div class="error-message">Error loading vaccine details. Please try again.</div>';
                    });
            };

            window.openRequestModal = function() {
                const modal = document.getElementById('requestVaccineModal');
                const form = document.getElementById('requestVaccineForm');
                if (form) form.reset();
                modal.style.display = 'block';
            };

            window.closeModal = function(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) {
                    modal.style.display = 'none';
                    const form = modal.querySelector('form');
                    if (form) form.reset();
                }
            };
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                if (event.target.classList.contains('modal')) {
                    event.target.style.display = 'none';
                    const form = event.target.querySelector('form');
                    if (form) form.reset();
                }
            };
            
            // Handle form submissions
            const requestForm = document.getElementById('requestVaccineForm');
            if (requestForm) {
                requestForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    
                    fetch('process_vaccine_request.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            closeModal('requestVaccineModal');
                            alert('Vaccine request submitted successfully');
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error submitting vaccine request');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error submitting vaccine request');
                    });
                });
            }
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
                    <li><a href="nurse_vaccine_inventory.php" class="active"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Vaccine Inventory</h2>
                
                <div class="inventory-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-vials"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_vaccines; ?></div>
                        <div class="stat-label">Total Vaccines</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_immunizations; ?></div>
                        <div class="stat-label">Total Immunizations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_patients_vaccinated; ?></div>
                        <div class="stat-label">Patients Vaccinated</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-value"><?php echo $todays_immunizations; ?></div>
                        <div class="stat-label">Today's Immunizations</div>
                    </div>
                </div>
                
                <div class="action-bar">
                    <form class="search-form" action="" method="GET">
                        <input type="text" name="search" placeholder="Search vaccines..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="#" class="add-btn" onclick="openRequestModal()">
                        <i class="fas fa-plus"></i> Add Vaccine
                    </a>
                </div>
                
                <!-- Add Vaccine Modal -->
                <div id="requestVaccineModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Add Vaccine</h3>
                            <span class="close" onclick="closeModal('requestVaccineModal')">&times;</span>
                        </div>
                        <div class="modal-body">
                            <form id="requestVaccineForm" method="POST" action="process_vaccine_request.php">
                                <div class="form-group">
                                    <label for="vaccine_name">Vaccine Name</label>
                                    <input type="text" id="vaccine_name" name="vaccine_name" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="manufacturer">Manufacturer</label>
                                    <input type="text" id="manufacturer" name="manufacturer" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="doses_required">Doses Required</label>
                                    <input type="number" id="doses_required" name="doses_required" min="1" value="1" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="days_between_doses">Days Between Doses</label>
                                    <input type="number" id="days_between_doses" name="days_between_doses" min="0">
                                </div>
                                
                                <div class="form-group">
                                    <label for="recommended_age">Recommended Age</label>
                                    <input type="text" id="recommended_age" name="recommended_age">
                                </div>
                                
                                <div class="form-group">
                                    <label for="notes">Description</label>
                                    <textarea id="notes" name="notes" rows="3"></textarea>
                                </div>
                                
                                <div class="form-actions">
                                    <button type="button" onclick="closeModal('requestVaccineModal')" class="btn-secondary">Cancel</button>
                                    <button type="submit" class="btn-primary">Add Vaccine</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Vaccine Modal -->
                <div id="viewVaccineModal" class="modal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>Vaccine Details</h3>
                            <span class="close" onclick="closeModal('viewVaccineModal')">&times;</span>
                        </div>
                        <div class="modal-body" id="viewVaccineContent">
                            <!-- Content will be loaded dynamically -->
                        </div>
                    </div>
                </div>
                
                <table class="vaccine-table">
                    <thead>
                        <tr>
                            <th>Vaccine Name</th>
                            <th>Manufacturer</th>
                            <th>Doses Required</th>
                            <th>Recommended Age</th>
                            <th>Times Administered</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($vaccines->num_rows > 0): ?>
                            <?php while ($vaccine = $vaccines->fetch_assoc()): ?>
                                <tr>
                                    <td class="vaccine-name">
                                        <?php echo htmlspecialchars($vaccine['name']); ?>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
                                    </td>
                                    <td>
                                        <span class="doses-badge"><?php echo $vaccine['doses_required']; ?> dose<?php echo $vaccine['doses_required'] > 1 ? 's' : ''; ?></span>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($vaccine['recommended_age']); ?>
                                    </td>
                                    <td>
                                        <?php echo isset($immunization_counts[$vaccine['id']]) ? $immunization_counts[$vaccine['id']] : 0; ?> times
                                    </td>
                                    <td class="action-buttons">
                                        <a href="#" class="view-btn" onclick="openViewModal(<?php echo $vaccine['id']; ?>)">View</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">No vaccines found.</td>
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
</body>
</html> 