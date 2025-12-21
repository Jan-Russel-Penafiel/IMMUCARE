<?php
session_start();
require 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: login.php');
    exit;
}

// Get admin information
$admin_id = $_SESSION['user_id'];
$admin_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Admin';
$admin_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// If admin name or email is missing from session, fetch from database
if (empty($admin_name) || empty($admin_email)) {
    $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        if (empty($admin_name)) {
            $admin_name = $user_data['name'];
            $_SESSION['user_name'] = $admin_name; // Update session
        }
        if (empty($admin_email)) {
            $admin_email = $user_data['email'];
            $_SESSION['user_email'] = $admin_email; // Update session
        }
    }
    $stmt->close();
}

// Process report type selection
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-t'); // Last day of current month
$health_center_id = isset($_GET['health_center_id']) ? $_GET['health_center_id'] : '';
$gender_filter = isset($_GET['gender']) ? $_GET['gender'] : '';
$purok_filter = isset($_GET['purok']) ? $_GET['purok'] : '';
$diagnosis_filter = isset($_GET['diagnosis']) ? $_GET['diagnosis'] : '';
$age_filter = isset($_GET['age_group']) ? $_GET['age_group'] : '';
$view_type = isset($_GET['view_type']) ? $_GET['view_type'] : 'monthly';

// Process search
$search_query = isset($_GET['search_query']) ? trim($_GET['search_query']) : '';
$search_category = isset($_GET['search_category']) ? $_GET['search_category'] : '';
$search_time_frame = isset($_GET['search_time_frame']) ? $_GET['search_time_frame'] : 'monthly';
$search_results = [];

if (!empty($search_query) && !empty($search_category)) {
    $date_condition = "";
    if ($search_time_frame === 'daily') {
        $date_condition = " AND DATE(p.created_at) = CURDATE()";
    } else { // monthly
        $date_condition = " AND DATE_FORMAT(p.created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')";
    }

    switch ($search_category) {
        case 'gender':
            $stmt = $conn->prepare("SELECT 
                p.gender,
                COUNT(*) as total_count,
                GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as patients,
                GROUP_CONCAT(DISTINCT p.purok SEPARATOR ', ') as puroks
            FROM patients p 
            WHERE p.gender = ? " . $date_condition . "
            GROUP BY p.gender
            LIMIT 50");
            $stmt->bind_param("s", $search_query);
            break;

        case 'purok':
            $stmt = $conn->prepare("SELECT 
                p.purok,
                COUNT(*) as total_count,
                GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as patients,
                GROUP_CONCAT(DISTINCT p.gender SEPARATOR ', ') as genders
            FROM patients p 
            WHERE p.purok = ? " . $date_condition . "
            GROUP BY p.purok
            LIMIT 50");
            $stmt->bind_param("s", $search_query);
            break;

        case 'diagnosis':
            $stmt = $conn->prepare("SELECT 
                i.diagnosis,
                COUNT(*) as total_count,
                GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as patients,
                GROUP_CONCAT(DISTINCT v.name SEPARATOR ', ') as vaccines
            FROM immunizations i
            JOIN patients p ON p.id = i.patient_id
            JOIN vaccines v ON v.id = i.vaccine_id
            WHERE i.diagnosis LIKE ? " . $date_condition . "
            GROUP BY i.diagnosis
            LIMIT 50");
            $search_param = "%$search_query%";
            $stmt->bind_param("s", $search_param);
            break;

        case 'age':
            // Convert search query to age range
            $age_ranges = [
                'under_1' => 'Under 1 year',
                '1_5' => '1-5 years',
                '6_12' => '6-12 years',
                'over_12' => 'Over 12 years'
            ];
            
            $age_condition = "";
            if (is_numeric($search_query)) {
                $age_condition = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) = ?";
            } else {
                foreach ($age_ranges as $key => $range) {
                    if (stripos($range, $search_query) !== false) {
                        switch($key) {
                            case 'under_1':
                                $age_condition = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 1";
                                break;
                            case '1_5':
                                $age_condition = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 1 AND 5";
                                break;
                            case '6_12':
                                $age_condition = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 6 AND 12";
                                break;
                            case 'over_12':
                                $age_condition = "TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) > 12";
                                break;
                        }
                        break;
                    }
                }
            }

            if (!empty($age_condition)) {
                if (is_numeric($search_query)) {
                    $stmt = $conn->prepare("SELECT 
                        CONCAT(TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()), ' years') as age_group,
                        COUNT(*) as total_count,
                        GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as patients,
                        GROUP_CONCAT(DISTINCT p.gender SEPARATOR ', ') as genders
                    FROM patients p 
                    WHERE " . $age_condition . $date_condition . "
                    GROUP BY age_group
                    LIMIT 50");
                    $stmt->bind_param("i", $search_query);
                } else {
                    $stmt = $conn->prepare("SELECT 
                        CASE 
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 1 THEN 'Under 1 year'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 1 AND 5 THEN '1-5 years'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN '6-12 years'
                            ELSE 'Over 12 years'
                        END as age_group,
                        COUNT(*) as total_count,
                        GROUP_CONCAT(DISTINCT CONCAT(p.first_name, ' ', p.last_name) SEPARATOR ', ') as patients,
                        GROUP_CONCAT(DISTINCT p.gender SEPARATOR ', ') as genders
                    FROM patients p 
                    WHERE " . $age_condition . $date_condition . "
                    GROUP BY age_group
                    LIMIT 50");
                }
            }
            break;
    }

    if (isset($stmt)) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $search_results[] = $row;
        }
        $stmt->close();
    }
}

// Get health centers for filter
$stmt = $conn->prepare("SELECT id, name FROM health_centers WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$health_centers = $stmt->get_result();
$health_centers_array = [];
while ($center = $health_centers->fetch_assoc()) {
    $health_centers_array[$center['id']] = $center['name'];
}

// Get puroks for filter
$stmt = $conn->prepare("SELECT DISTINCT purok FROM patients WHERE purok IS NOT NULL ORDER BY purok");
$stmt->execute();
$puroks = $stmt->get_result();
$purok_array = [];
while ($purok = $puroks->fetch_assoc()) {
    $purok_array[] = $purok['purok'];
}

// Get diagnoses for filter
$stmt = $conn->prepare("SELECT DISTINCT diagnosis FROM immunizations WHERE diagnosis IS NOT NULL ORDER BY diagnosis");
$stmt->execute();
$diagnoses = $stmt->get_result();
$diagnosis_array = [];
while ($diagnosis = $diagnoses->fetch_assoc()) {
    $diagnosis_array[] = $diagnosis['diagnosis'];
}

// Define report data
$report_data = [];
$report_title = '';

// Generate report based on type
if (!empty($report_type)) {
    // Base filter condition for patients table
    $filter_condition = " WHERE 1=1";
    
    // Health center filter
    if (!empty($health_center_id)) {
        $filter_condition .= " AND p.health_center_id = $health_center_id";
    }
    
    // Gender filter
    if (!empty($gender_filter)) {
        $filter_condition .= " AND p.gender = '$gender_filter'";
    }
    
    // Purok filter
    if (!empty($purok_filter)) {
        $filter_condition .= " AND p.purok = '$purok_filter'";
    }
    
    // Age group filter
    if (!empty($age_filter)) {
        switch($age_filter) {
            case 'under_1':
                $filter_condition .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 1";
                break;
            case '1_5':
                $filter_condition .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 1 AND 5";
                break;
            case '6_12':
                $filter_condition .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 6 AND 12";
                break;
            case 'over_12':
                $filter_condition .= " AND TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) > 12";
                break;
        }
    }

    switch ($report_type) {
        case 'patient_list':
            $report_title = 'List of Patients';
            $base_query = "SELECT 
                            p.id,
                            p.first_name,
                            p.last_name,
                            p.date_of_birth,
                            p.gender,
                            p.purok,
                            p.city,
                            p.phone_number,
                            TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age
                         FROM patients p";
            
            // Add joins and date conditions based on filters
            if (!empty($diagnosis_filter)) {
                $base_query .= " JOIN immunizations i ON i.patient_id = p.id";
                $filter_condition .= " AND i.diagnosis = '$diagnosis_filter'";
                
                // Add date filter on immunizations
                if ($view_type === 'daily') {
                    $filter_condition .= " AND DATE(i.administered_date) BETWEEN '$date_from' AND '$date_to'";
                } else {
                    $filter_condition .= " AND DATE_FORMAT(i.administered_date, '%Y-%m') BETWEEN DATE_FORMAT('$date_from', '%Y-%m') AND DATE_FORMAT('$date_to', '%Y-%m')";
                }
            } else {
                // Date filter on patients if no diagnosis filter
                if ($view_type === 'daily') {
                    $filter_condition .= " AND DATE(p.created_at) BETWEEN '$date_from' AND '$date_to'";
                } else {
                    $filter_condition .= " AND DATE_FORMAT(p.created_at, '%Y-%m') BETWEEN DATE_FORMAT('$date_from', '%Y-%m') AND DATE_FORMAT('$date_to', '%Y-%m')";
                }
            }
            
            $query = $base_query . $filter_condition . " ORDER BY p.last_name, p.first_name";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'appointment_list':
            $report_title = 'List of Appointments';
            $base_query = "SELECT 
                            a.id,
                            a.appointment_date,
                            CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                            p.phone_number,
                            v.name as vaccine_name,
                            a.purpose,
                            a.status,
                            a.notes
                         FROM appointments a
                         JOIN patients p ON p.id = a.patient_id
                         LEFT JOIN vaccines v ON v.id = a.vaccine_id";
            
            // Add date filter
            if ($view_type === 'daily') {
                $filter_condition .= " AND DATE(a.appointment_date) BETWEEN '$date_from' AND '$date_to'";
            } else {
                $filter_condition .= " AND DATE_FORMAT(a.appointment_date, '%Y-%m') BETWEEN DATE_FORMAT('$date_from', '%Y-%m') AND DATE_FORMAT('$date_to', '%Y-%m')";
            }
            
            // Add diagnosis filter if needed
            if (!empty($diagnosis_filter)) {
                $base_query .= " JOIN immunizations i ON i.patient_id = p.id";
                $filter_condition .= " AND i.diagnosis = '$diagnosis_filter'";
            }
            
            $query = $base_query . $filter_condition . " ORDER BY a.appointment_date DESC";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'patient_diagnosis':
            $report_title = 'Patient Information and Diagnosis';
            $base_query = "SELECT 
                            p.id,
                            p.first_name,
                            p.last_name,
                            p.date_of_birth,
                            TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) as age,
                            p.gender,
                            p.purok,
                            p.city,
                            p.phone_number,
                            i.administered_date,
                            i.diagnosis,
                            v.name as vaccine_name
                         FROM patients p
                         JOIN immunizations i ON i.patient_id = p.id
                         JOIN vaccines v ON v.id = i.vaccine_id";
            
            // Add date filter on immunizations
            if ($view_type === 'daily') {
                $filter_condition .= " AND DATE(i.administered_date) BETWEEN '$date_from' AND '$date_to'";
            } else {
                $filter_condition .= " AND DATE_FORMAT(i.administered_date, '%Y-%m') BETWEEN DATE_FORMAT('$date_from', '%Y-%m') AND DATE_FORMAT('$date_to', '%Y-%m')";
            }
            
            // Add diagnosis filter
            if (!empty($diagnosis_filter)) {
                $filter_condition .= " AND i.diagnosis = '$diagnosis_filter'";
            }
            
            $query = $base_query . $filter_condition . " ORDER BY p.last_name, p.first_name, i.administered_date DESC";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'immunization_summary':
            $report_title = 'Immunization Summary Report';
            
            // Get immunization data grouped by vaccine
            $query = "SELECT v.name as vaccine_name, COUNT(*) as count 
                     FROM immunizations i 
                     JOIN vaccines v ON i.vaccine_id = v.id 
                     JOIN patients p ON i.patient_id = p.id
                     $filter_condition
                     GROUP BY v.id 
                     ORDER BY count DESC";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'appointment_status':
            $report_title = 'Appointment Status Report';
            
            // Get appointment data grouped by status
            $query = "SELECT a.status, COUNT(*) as count 
                     FROM appointments a
                     $filter_condition
                     GROUP BY a.status 
                     ORDER BY count DESC";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'patient_demographics':
            $report_title = 'Patient Demographics Report';
            
            // Get patient data grouped by gender
            $query = "SELECT gender, COUNT(*) as count 
                     FROM patients
                     $filter_condition
                     GROUP BY gender";
            
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            
            // Get patient data grouped by age range
            $query = "SELECT 
                     CASE 
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 1 THEN 'Under 1'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 1 AND 5 THEN '1-5'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) BETWEEN 6 AND 12 THEN '6-12'
                        ELSE 'Over 12'
                     END as age_group,
                     COUNT(*) as count
                     FROM patients
                     $filter_condition
                     GROUP BY age_group
                     ORDER BY 
                        CASE age_group
                            WHEN 'Under 1' THEN 1
                            WHEN '1-5' THEN 2
                            WHEN '6-12' THEN 3
                            ELSE 4
                        END";
            
            $age_result = $conn->query($query);
            $age_data = [];
            while ($row = $age_result->fetch_assoc()) {
                $age_data[] = $row;
            }
            break;
            
        case 'diagnosis_distribution':
            $report_title = 'Diagnosis Distribution Report';
            $base_query = "SELECT i.diagnosis, COUNT(DISTINCT p.id) as count 
                          FROM patients p
                          JOIN immunizations i ON i.patient_id = p.id";
            
            // Add date filter on immunizations
            if ($view_type === 'daily') {
                $filter_condition .= " AND DATE(i.administered_date) BETWEEN '$date_from' AND '$date_to'";
            } else {
                $filter_condition .= " AND DATE_FORMAT(i.administered_date, '%Y-%m') BETWEEN DATE_FORMAT('$date_from', '%Y-%m') AND DATE_FORMAT('$date_to', '%Y-%m')";
            }
            
            $query = $base_query . $filter_condition . " 
                     AND i.diagnosis IS NOT NULL
                     GROUP BY i.diagnosis
                     ORDER BY count DESC";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <style>
        .dashboard-container { max-width: 1400px; margin: 0 auto; padding: 20px; }
        .dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e1e4e8; }
        .dashboard-logo { display: flex; align-items: center; }
        .dashboard-logo img { height: 40px; margin-right: 10px; }
        .dashboard-logo h1 { font-size: 1.8rem; color: var(--primary-color); margin: 0; }
        .user-menu { display: flex; align-items: center; }
        .user-info { margin-right: 20px; text-align: right; }
        .user-name { font-weight: 600; color: var(--text-color); }
        .user-role { font-size: 0.8rem; color: var(--primary-color); font-weight: 500; text-transform: uppercase; }
        .user-email { font-size: 0.9rem; color: var(--light-text); }
        .logout-btn { padding: 8px 15px; background-color: #f1f3f5; color: var(--text-color); border-radius: 5px; font-size: 0.9rem; transition: var(--transition); }
        .logout-btn:hover { background-color: #e9ecef; }
        .dashboard-content { display: grid; grid-template-columns: 1fr 4fr; gap: 30px; }
        .sidebar {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 20px;
            height: fit-content;
        }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 15px; border-radius: var(--border-radius); color: var(--text-color); transition: var(--transition); text-decoration: none; }
        .sidebar-menu a:hover { background-color: #f1f8ff; color: var(--primary-color); }
        .sidebar-menu a.active { background-color: #e8f0fe; color: var(--primary-color); font-weight: 500; }
        .sidebar-menu i { margin-right: 10px; font-size: 1.1rem; width: 20px; text-align: center; }
        .main-content { background-color: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 30px; }
        .page-title { font-size: 1.8rem; color: var(--primary-color); margin-bottom: 20px; }
        .report-filter { background-color: #f8f9fa; padding: 20px; border-radius: var(--border-radius); margin-bottom: 30px; }
        .filter-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group select, .form-group input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: var(--border-radius); font-family: inherit; }
        .btn-generate { background-color: var(--primary-color); color: white; border: none; padding: 10px 15px; border-radius: var(--border-radius); cursor: pointer; font-family: inherit; transition: var(--transition); }
        .btn-generate:hover { background-color: #3367d6; }
        .report-content { margin-top: 30px; }
        .report-header { margin-bottom: 20px; }
        .report-title { font-size: 1.5rem; color: var(--primary-color); margin-bottom: 10px; }
        .report-meta { font-size: 0.9rem; color: var(--light-text); margin-bottom: 20px; }
        .report-chart-container { height: 400px; margin-bottom: 30px; background-color: #ffffff; padding: 20px; border-radius: var(--border-radius); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stats-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: var(--border-radius); color: white; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .stat-card.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-card.green { background: linear-gradient(135deg, #48c6ef 0%, #6f86d6 100%); }
        .stat-card.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-card.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stat-card h4 { margin: 0 0 10px 0; font-size: 0.9rem; opacity: 0.9; }
        .stat-card .stat-value { font-size: 2rem; font-weight: 700; margin: 0; }
        .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .report-table th, .report-table td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        .report-table th { font-weight: 600; color: var(--primary-color); background-color: #f8f9fa; }
        .btn-export { background-color: #4caf50; color: white; border: none; padding: 8px 15px; border-radius: var(--border-radius); cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; text-decoration: none; margin-top: 20px; transition: var(--transition); }
        .btn-export:hover { background-color: #43a047; }
        .btn-print { background-color: #2196f3; color: white; border: none; padding: 8px 15px; border-radius: var(--border-radius); cursor: pointer; font-family: inherit; display: inline-flex; align-items: center; gap: 5px; margin-left: 10px; text-decoration: none; transition: var(--transition); }
        .btn-print:hover { background-color: #1e88e5; }
        .report-actions { display: flex; margin-top: 20px; }
        @media screen and (max-width: 992px) { .dashboard-content { grid-template-columns: 1fr; } .sidebar { margin-bottom: 20px; } .filter-form { grid-template-columns: 1fr; } }
        @media screen and (max-width: 768px) { .dashboard-header { flex-direction: column; align-items: flex-start; } .user-menu { margin-top: 20px; align-self: flex-end; } }
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
                    <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                    <div class="user-role">Administrator</div>
                    <div class="user-email"><?php echo htmlspecialchars($admin_email); ?></div>
                </div>
                <a href="admin_dashboard.php?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-content">
            <div class="sidebar">
                <ul class="sidebar-menu">
                    <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="admin_users.php"><i class="fas fa-users"></i> User Management</a></li>
                    <li><a href="admin_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="admin_vaccines.php"><i class="fas fa-syringe"></i> Vaccines</a></li>
                    <li><a href="admin_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="admin_reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Reports</h2>

                <!-- Add Search Section -->
                <div class="search-section" style="margin-bottom: 30px;">
                    <h3>Quick Search</h3>
                    <form action="" method="GET" class="search-form" style="display: flex; gap: 15px; margin-bottom: 20px;">
                        <div class="form-group" style="flex: 1;">
                            <?php if ($search_category === 'gender'): ?>
                                <select name="search_query" class="form-control" style="width: 100%; padding: 8px;">
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo $search_query === 'male' ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo $search_query === 'female' ? 'selected' : ''; ?>>Female</option>
                                </select>
                            <?php elseif ($search_category === 'purok'): ?>
                                <select name="search_query" class="form-control" style="width: 100%; padding: 8px;">
                                    <option value="">Select Purok</option>
                                    <?php foreach ($purok_array as $purok): ?>
                                        <option value="<?php echo htmlspecialchars($purok); ?>" <?php echo $search_query === $purok ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($purok); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="text" name="search_query" placeholder="Enter search term..." value="<?php echo htmlspecialchars($search_query); ?>" class="form-control" style="width: 100%; padding: 8px;">
                            <?php endif; ?>
                        </div>
                        <div class="form-group" style="width: 200px;">
                            <select name="search_category" class="form-control" style="width: 100%; padding: 8px;" onchange="this.form.submit()">
                                <option value="">Select Category</option>
                                <option value="gender" <?php echo $search_category == 'gender' ? 'selected' : ''; ?>>Gender</option>
                                <option value="purok" <?php echo $search_category == 'purok' ? 'selected' : ''; ?>>Purok</option>
                                <option value="diagnosis" <?php echo $search_category == 'diagnosis' ? 'selected' : ''; ?>>Diagnosis</option>
                                <option value="age" <?php echo $search_category == 'age' ? 'selected' : ''; ?>>Age</option>
                            </select>
                        </div>
                        <div class="form-group" style="width: 150px;">
                            <select name="search_time_frame" class="form-control" style="width: 100%; padding: 8px;">
                                <option value="monthly" <?php echo $search_time_frame == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="daily" <?php echo $search_time_frame == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <button type="submit" class="btn-generate" style="height: 100%;">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </form>

                    <script>
                        // Auto-submit form when category changes to update the search input type
                        document.querySelector('select[name="search_category"]').addEventListener('change', function() {
                            this.form.submit();
                        });
                    </script>

                    <?php if (!empty($search_query) && !empty($search_category)): ?>
                        <div class="search-results">
                            <h4>Search Results (<?php echo $search_time_frame == 'daily' ? 'Today' : 'This Month'; ?>)</h4>
                            <?php if (empty($search_results)): ?>
                                <p>No results found.</p>
                            <?php else: ?>
                                <table class="report-table">
                                    <thead>
                                        <tr>
                                            <?php
                                            // Display appropriate headers based on search category
                                            $first_row = reset($search_results);
                                            foreach (array_keys($first_row) as $header) {
                                                echo '<th>' . ucwords(str_replace('_', ' ', $header)) . '</th>';
                                            }
                                            ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($search_results as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $key => $value): ?>
                                                    <td>
                                                        <?php
                                                        if ($key == 'gender') {
                                                            echo ucfirst(htmlspecialchars($value));
                                                        } else {
                                                            echo htmlspecialchars($value);
                                                        }
                                                        ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="report-filter">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" required>
                                <option value="">-- Select Report Type --</option>
                                <option value="patient_list" <?php echo $report_type == 'patient_list' ? 'selected' : ''; ?>>List of Patients</option>
                                <option value="patient_diagnosis" <?php echo $report_type == 'patient_diagnosis' ? 'selected' : ''; ?>>Patient Information</option>
                                <option value="appointment_list" <?php echo $report_type == 'appointment_list' ? 'selected' : ''; ?>>List of Appointments</option>
                                <option value="diagnosis_distribution" <?php echo $report_type == 'diagnosis_distribution' ? 'selected' : ''; ?>>Diagnosis</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="view_type">View Type</label>
                            <select id="view_type" name="view_type">
                                <option value="monthly" <?php echo $view_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                                <option value="daily" <?php echo $view_type == 'daily' ? 'selected' : ''; ?>>Daily</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="date_from">From Date</label>
                            <input type="date" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_to">To Date</label>
                            <input type="date" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="health_center_id">Health Center</label>
                            <select id="health_center_id" name="health_center_id">
                                <option value="">All Health Centers</option>
                                <?php foreach ($health_centers_array as $id => $center_name): ?>
                                    <option value="<?php echo $id; ?>" <?php echo $health_center_id == $id ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($center_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">All Genders</option>
                                <option value="male" <?php echo $gender_filter == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $gender_filter == 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="purok">Purok</label>
                            <select id="purok" name="purok">
                                <option value="">All Puroks</option>
                                <?php foreach ($purok_array as $purok): ?>
                                    <option value="<?php echo htmlspecialchars($purok); ?>" <?php echo $purok_filter == $purok ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($purok); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="diagnosis">Diagnosis</label>
                            <select id="diagnosis" name="diagnosis">
                                <option value="">All Diagnoses</option>
                                <?php foreach ($diagnosis_array as $diagnosis): ?>
                                    <option value="<?php echo htmlspecialchars($diagnosis); ?>" <?php echo $diagnosis_filter == $diagnosis ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($diagnosis); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="age_group">Age Group</label>
                            <select id="age_group" name="age_group">
                                <option value="">All Age Groups</option>
                                <option value="under_1" <?php echo $age_filter == 'under_1' ? 'selected' : ''; ?>>Under 1 year</option>
                                <option value="1_5" <?php echo $age_filter == '1_5' ? 'selected' : ''; ?>>1-5 years</option>
                                <option value="6_12" <?php echo $age_filter == '6_12' ? 'selected' : ''; ?>>6-12 years</option>
                                <option value="over_12" <?php echo $age_filter == 'over_12' ? 'selected' : ''; ?>>Over 12 years</option>
                            </select>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: flex-end;">
                            <button type="submit" class="btn-generate">
                                <i class="fas fa-chart-line"></i> Generate Report
                            </button>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($report_type)): ?>
                    <div class="report-content">
                        <div class="report-header">
                            <h3 class="report-title"><?php echo $report_title; ?></h3>
                            <div class="report-meta">
                                <div>Date Range: <?php echo date('M d, Y', strtotime($date_from)); ?> - <?php echo date('M d, Y', strtotime($date_to)); ?></div>
                                <div>Health Center: <?php echo !empty($health_center_id) ? htmlspecialchars($health_centers_array[$health_center_id]) : 'All Health Centers'; ?></div>
                                <div>Generated on: <?php echo date('M d, Y h:i A'); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($report_data)): ?>
                        <!-- Summary Statistics -->
                        <div class="stats-summary">
                            <?php if ($report_type == 'patient_list'): ?>
                                <?php
                                $total_patients = count($report_data);
                                $puroks = array_filter(array_column($report_data, 'purok'));
                                $unique_puroks = count(array_unique($puroks));
                                $purok_counts = array_count_values($puroks);
                                arsort($purok_counts);
                                $most_common_purok = !empty($purok_counts) ? array_key_first($purok_counts) : 'N/A';
                                $most_common_count = !empty($purok_counts) ? $purok_counts[$most_common_purok] : 0;
                                ?>
                                <div class="stat-card blue">
                                    <h4>Total Patients</h4>
                                    <p class="stat-value"><?php echo $total_patients; ?></p>
                                </div>
                                <div class="stat-card green">
                                    <h4>Total Puroks</h4>
                                    <p class="stat-value"><?php echo $unique_puroks; ?></p>
                                </div>
                                <div class="stat-card orange" style="grid-column: span 2;">
                                    <h4>Most Populated Purok</h4>
                                    <p class="stat-value" style="font-size: 1.3rem;"><?php echo htmlspecialchars($most_common_purok); ?> (<?php echo $most_common_count; ?> patients)</p>
                                </div>
                            <?php elseif ($report_type == 'appointment_list'): ?>
                                <?php
                                $total_appointments = count($report_data);
                                $pending_count = count(array_filter($report_data, function($a) { return strtolower($a['status']) == 'pending'; }));
                                $confirmed_count = count(array_filter($report_data, function($a) { return strtolower($a['status']) == 'confirmed'; }));
                                $completed_count = count(array_filter($report_data, function($a) { return strtolower($a['status']) == 'completed'; }));
                                ?>
                                <div class="stat-card blue">
                                    <h4>Total Appointments</h4>
                                    <p class="stat-value"><?php echo $total_appointments; ?></p>
                                </div>
                                <div class="stat-card green">
                                    <h4>Confirmed</h4>
                                    <p class="stat-value"><?php echo $confirmed_count; ?></p>
                                </div>
                                <div class="stat-card orange">
                                    <h4>Pending</h4>
                                    <p class="stat-value"><?php echo $pending_count; ?></p>
                                </div>
                                <div class="stat-card purple">
                                    <h4>Completed</h4>
                                    <p class="stat-value"><?php echo $completed_count; ?></p>
                                </div>
                            <?php elseif ($report_type == 'patient_diagnosis'): ?>
                                <?php
                                $total_records = count($report_data);
                                $unique_patients = count(array_unique(array_map(function($r) { return $r['id']; }, $report_data)));
                                $unique_vaccines = count(array_unique(array_column($report_data, 'vaccine_name')));
                                $unique_diagnoses = count(array_unique(array_filter(array_column($report_data, 'diagnosis'))));
                                ?>
                                <div class="stat-card blue">
                                    <h4>Total Records</h4>
                                    <p class="stat-value"><?php echo $total_records; ?></p>
                                </div>
                                <div class="stat-card green">
                                    <h4>Unique Patients</h4>
                                    <p class="stat-value"><?php echo $unique_patients; ?></p>
                                </div>
                                <div class="stat-card orange">
                                    <h4>Vaccine Types</h4>
                                    <p class="stat-value"><?php echo $unique_vaccines; ?></p>
                                </div>
                                <div class="stat-card purple">
                                    <h4>Diagnoses Recorded</h4>
                                    <p class="stat-value"><?php echo $unique_diagnoses; ?></p>
                                </div>
                            <?php elseif ($report_type == 'diagnosis_distribution'): ?>
                                <?php
                                $total_cases = array_sum(array_column($report_data, 'count'));
                                $unique_diagnoses = count($report_data);
                                $most_common = !empty($report_data) ? $report_data[0] : ['diagnosis' => 'N/A', 'count' => 0];
                                ?>
                                <div class="stat-card blue">
                                    <h4>Total Cases</h4>
                                    <p class="stat-value"><?php echo $total_cases; ?></p>
                                </div>
                                <div class="stat-card green">
                                    <h4>Unique Diagnoses</h4>
                                    <p class="stat-value"><?php echo $unique_diagnoses; ?></p>
                                </div>
                                <div class="stat-card orange" style="grid-column: span 2;">
                                    <h4>Most Common Diagnosis</h4>
                                    <p class="stat-value" style="font-size: 1.3rem;"><?php echo htmlspecialchars($most_common['diagnosis']); ?> (<?php echo $most_common['count']; ?>)</p>
                                </div>
                            <?php else: ?>
                                <?php
                                $total_count = array_sum(array_column($report_data, 'count'));
                                $categories = count($report_data);
                                ?>
                                <div class="stat-card blue">
                                    <h4>Total Count</h4>
                                    <p class="stat-value"><?php echo $total_count; ?></p>
                                </div>
                                <div class="stat-card green">
                                    <h4>Categories</h4>
                                    <p class="stat-value"><?php echo $categories; ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="report-chart-container">
                            <canvas id="reportChart"></canvas>
                        </div>
                        
                        <?php if ($report_type == 'patient_list'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Purok</th>
                                        <th>City</th>
                                        <th>Phone Number</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo $row['age']; ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($row['gender'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['purok']); ?></td>
                                            <td><?php echo htmlspecialchars($row['city']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'appointment_list'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Patient Name</th>
                                        <th>Phone Number</th>
                                        <th>Vaccine</th>
                                        <th>Purpose</th>
                                        <th>Status</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y h:i A', strtotime($row['appointment_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($row['status'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'patient_diagnosis'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Age</th>
                                        <th>Gender</th>
                                        <th>Purok</th>
                                        <th>Phone Number</th>
                                        <th>Vaccine</th>
                                        <th>Date</th>
                                        <th>Diagnosis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                                            <td><?php echo $row['age']; ?></td>
                                            <td><?php echo ucfirst(htmlspecialchars($row['gender'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['purok']); ?></td>
                                            <td><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                            <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($row['administered_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'immunization_summary'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Vaccine</th>
                                        <th>Number of Immunizations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                                            <td><?php echo $row['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'appointment_status'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th>Number of Appointments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($row['status'])); ?></td>
                                            <td><?php echo $row['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'patient_demographics'): ?>
                            <h4>Gender Distribution</h4>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Gender</th>
                                        <th>Number of Patients</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo ucfirst(htmlspecialchars($row['gender'])); ?></td>
                                            <td><?php echo $row['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            
                            <h4 style="margin-top: 30px;">Age Distribution</h4>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Age Group</th>
                                        <th>Number of Patients</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($age_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['age_group']); ?></td>
                                            <td><?php echo $row['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php elseif ($report_type == 'diagnosis_distribution'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th>Diagnosis</th>
                                        <th>Number of Cases</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['diagnosis']); ?></td>
                                            <td><?php echo $row['count']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        
                        <div class="report-actions">
                            <a href="#" class="btn-export" onclick="exportTableToCSV('<?php echo $report_title; ?>')">
                                <i class="fas fa-file-csv"></i> Export to CSV
                            </a>
                            <a href="#" class="btn-print" onclick="printReportPDF(); return false;">
                                <i class="fas fa-print"></i> Print Report
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight active menu item
            const currentPath = window.location.pathname;
            const menuItems = document.querySelectorAll('.sidebar-menu a');
            
            menuItems.forEach(item => {
                if (item.getAttribute('href') === currentPath) {
                    item.classList.add('active');
                } else if (item.classList.contains('active') && item.getAttribute('href') !== '#') {
                    item.classList.remove('active');
                }
            });
            
            <?php if (!empty($report_type) && !empty($report_data)): ?>
            // Set up Chart.js for report visualization
            const ctx = document.getElementById('reportChart').getContext('2d');
            
            <?php if ($report_type == 'patient_list'): ?>
                // Chart for patient list - Purok Distribution
                const purokCounts = {};
                
                <?php foreach ($report_data as $patient): ?>
                    (function() {
                        const purok = '<?php echo addslashes($patient['purok']); ?>';
                        
                        // Count purok
                        if (!purokCounts[purok]) purokCounts[purok] = 0;
                        purokCounts[purok]++;
                    })();
                <?php endforeach; ?>
                
                const purokLabels = Object.keys(purokCounts).sort();
                const purokData = purokLabels.map(label => purokCounts[label]);
                
                // Generate dynamic colors
                const purokColors = [];
                const purokBorderColors = [];
                const colorPalette = [
                    [54, 162, 235], [255, 99, 132], [255, 206, 86], [75, 192, 192],
                    [153, 102, 255], [255, 159, 64], [199, 199, 199], [83, 102, 255],
                    [255, 99, 71], [50, 205, 50], [255, 140, 0], [138, 43, 226]
                ];
                
                for (let i = 0; i < purokData.length; i++) {
                    const [r, g, b] = colorPalette[i % colorPalette.length];
                    purokColors.push(`rgba(${r}, ${g}, ${b}, 0.6)`);
                    purokBorderColors.push(`rgba(${r}, ${g}, ${b}, 1)`);
                }
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: purokLabels,
                        datasets: [{
                            label: 'Number of Patients',
                            data: purokData,
                            backgroundColor: purokColors,
                            borderColor: purokBorderColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Purok Distribution'
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'appointment_list'): ?>
                // Chart for appointments - Status distribution
                const statusCounts = {};
                <?php foreach ($report_data as $appointment): ?>
                    (function() {
                        const status = '<?php echo ucfirst($appointment['status']); ?>';
                        if (!statusCounts[status]) statusCounts[status] = 0;
                        statusCounts[status]++;
                    })();
                <?php endforeach; ?>
                
                const statusLabels = Object.keys(statusCounts);
                const statusData = Object.values(statusCounts);
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: statusLabels,
                        datasets: [{
                            data: statusData,
                            backgroundColor: [
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 206, 86, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(153, 102, 255, 0.6)'
                            ],
                            borderColor: [
                                'rgba(75, 192, 192, 1)',
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 206, 86, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(153, 102, 255, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Appointment Status Distribution'
                            },
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'patient_diagnosis'): ?>
                // Chart for patient diagnosis - Vaccine and diagnosis distribution
                const vaccineCounts = {};
                <?php foreach ($report_data as $record): ?>
                    (function() {
                        const vaccine = '<?php echo addslashes($record['vaccine_name']); ?>';
                        if (!vaccineCounts[vaccine]) vaccineCounts[vaccine] = 0;
                        vaccineCounts[vaccine]++;
                    })();
                <?php endforeach; ?>
                
                const vaccineLabels = Object.keys(vaccineCounts);
                const vaccineData = Object.values(vaccineCounts);
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: vaccineLabels,
                        datasets: [{
                            label: 'Number of Immunizations',
                            data: vaccineData,
                            backgroundColor: 'rgba(153, 102, 255, 0.6)',
                            borderColor: 'rgba(153, 102, 255, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Vaccine Distribution'
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'immunization_summary'): ?>
                const labels = [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['vaccine_name']) . '"'; }, $report_data)); ?>];
                const data = [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $report_data)); ?>];
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Immunizations',
                            data: data,
                            backgroundColor: 'rgba(54, 162, 235, 0.6)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            <?php elseif ($report_type == 'appointment_status'): ?>
                const labels = [<?php echo implode(', ', array_map(function($item) { return '"' . ucfirst(addslashes($item['status'])) . '"'; }, $report_data)); ?>];
                const data = [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $report_data)); ?>];
                
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: labels,
                        datasets: [{
                            data: data,
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(75, 192, 192, 0.6)',
                                'rgba(153, 102, 255, 0.6)',
                                'rgba(255, 99, 132, 0.6)',
                                'rgba(255, 159, 64, 0.6)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(75, 192, 192, 1)',
                                'rgba(153, 102, 255, 1)',
                                'rgba(255, 99, 132, 1)',
                                'rgba(255, 159, 64, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            <?php elseif ($report_type == 'patient_demographics'): ?>
                const genderLabels = [<?php echo implode(', ', array_map(function($item) { return '"' . ucfirst(addslashes($item['gender'])) . '"'; }, $report_data)); ?>];
                const genderData = [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $report_data)); ?>];
                
                new Chart(ctx, {
                    type: 'doughnut',
                    data: {
                        labels: genderLabels,
                        datasets: [{
                            data: genderData,
                            backgroundColor: [
                                'rgba(54, 162, 235, 0.6)',
                                'rgba(255, 99, 132, 0.6)'
                            ],
                            borderColor: [
                                'rgba(54, 162, 235, 1)',
                                'rgba(255, 99, 132, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
            <?php elseif ($report_type == 'diagnosis_distribution'): ?>
                const labels = [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item['diagnosis']) . '"'; }, $report_data)); ?>];
                const data = [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $report_data)); ?>];
                
                // Generate dynamic colors based on number of items
                const colors = [];
                const borderColors = [];
                const diagnosisColorPalette = [
                    [54, 162, 235], [255, 99, 132], [255, 206, 86], [75, 192, 192],
                    [153, 102, 255], [255, 159, 64], [199, 199, 199], [83, 102, 255],
                    [255, 99, 71], [50, 205, 50]
                ];
                
                for (let i = 0; i < data.length; i++) {
                    const [r, g, b] = diagnosisColorPalette[i % diagnosisColorPalette.length];
                    colors.push(`rgba(${r}, ${g}, ${b}, 0.6)`);
                    borderColors.push(`rgba(${r}, ${g}, ${b}, 1)`);
                }
                
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Cases',
                            data: data,
                            backgroundColor: colors,
                            borderColor: borderColors,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: 'Diagnosis Distribution'
                            },
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            <?php endif; ?>
            <?php endif; ?>
        });
        
        // Function to export table to CSV
        function exportTableToCSV(filename) {
            const tables = document.querySelectorAll('.report-table');
            let csv = [];
            
            tables.forEach(table => {
                const rows = table.querySelectorAll('tr');
                
                rows.forEach(row => {
                    const cols = row.querySelectorAll('td,th');
                    const rowData = Array.from(cols)
                        .map(col => '"' + col.innerText.replace(/"/g, '""') + '"')
                        .join(',');
                    
                    csv.push(rowData);
                });
                
                csv.push(''); // Add a blank line between tables
            });
            
            const csvString = csv.join('\n');
            const link = document.createElement('a');
            link.style.display = 'none';
            link.setAttribute('href', 'data:text/csv;charset=utf-8,' + encodeURIComponent(csvString));
            link.setAttribute('download', filename + '_' + new Date().toISOString().split('T')[0] + '.csv');
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // Function to print report as PDF using jsPDF
        function printReportPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF('p', 'mm', 'a4');
            
            // Get report title and metadata
            const reportTitle = document.querySelector('.report-title')?.innerText || 'Report';
            const reportMeta = document.querySelector('.report-meta')?.innerText || '';
            
            // Add header
            doc.setFontSize(20);
            doc.setTextColor(50, 100, 200);
            doc.text('ImmuCare', 14, 20);
            
            doc.setFontSize(16);
            doc.setTextColor(0, 0, 0);
            doc.text(reportTitle, 14, 30);
            
            // Add metadata
            doc.setFontSize(9);
            doc.setTextColor(100, 100, 100);
            const metaLines = doc.splitTextToSize(reportMeta, 180);
            doc.text(metaLines, 14, 38);
            
            let yPosition = 38 + (metaLines.length * 5) + 5;
            
            // Add statistics cards if present
            const statCards = document.querySelectorAll('.stat-card');
            if (statCards.length > 0) {
                doc.setFontSize(11);
                doc.setTextColor(0, 0, 0);
                doc.text('Summary Statistics:', 14, yPosition);
                yPosition += 7;
                
                statCards.forEach((card, index) => {
                    const title = card.querySelector('h4')?.innerText || '';
                    const value = card.querySelector('.stat-value')?.innerText || '';
                    
                    doc.setFontSize(9);
                    doc.setTextColor(50, 50, 50);
                    doc.text(`${title}: ${value}`, 14, yPosition);
                    yPosition += 5;
                });
                
                yPosition += 5;
            }
            
            // Process all tables
            const tables = document.querySelectorAll('.report-table');
            tables.forEach((table, tableIndex) => {
                // Get table headers
                const headers = [];
                const headerCells = table.querySelectorAll('thead tr th, tr:first-child th');
                headerCells.forEach(cell => {
                    headers.push(cell.innerText.trim());
                });
                
                // Get table data
                const data = [];
                const rows = table.querySelectorAll('tbody tr, tr:not(:first-child)');
                rows.forEach(row => {
                    const rowData = [];
                    const cells = row.querySelectorAll('td');
                    if (cells.length > 0) {
                        cells.forEach(cell => {
                            rowData.push(cell.innerText.trim());
                        });
                        data.push(rowData);
                    }
                });
                
                // If no separate header row, use first data row as header
                if (headers.length === 0 && data.length > 0) {
                    headers.push(...data.shift());
                }
                
                // Add table title if there's an h4 before the table
                const previousElement = table.previousElementSibling;
                if (previousElement && previousElement.tagName === 'H4') {
                    if (yPosition > 250) {
                        doc.addPage();
                        yPosition = 20;
                    }
                    doc.setFontSize(12);
                    doc.setTextColor(0, 0, 0);
                    doc.text(previousElement.innerText, 14, yPosition);
                    yPosition += 8;
                }
                
                // Add table to PDF
                if (headers.length > 0 && data.length > 0) {
                    doc.autoTable({
                        head: [headers],
                        body: data,
                        startY: yPosition,
                        margin: { left: 14, right: 14 },
                        styles: {
                            fontSize: 8,
                            cellPadding: 2,
                            overflow: 'linebreak'
                        },
                        headStyles: {
                            fillColor: [50, 100, 200],
                            textColor: 255,
                            fontStyle: 'bold'
                        },
                        alternateRowStyles: {
                            fillColor: [245, 245, 245]
                        },
                        didDrawPage: function(data) {
                            // Footer
                            doc.setFontSize(8);
                            doc.setTextColor(150);
                            doc.text(
                                `Generated: ${new Date().toLocaleString()} | Page ${doc.internal.getNumberOfPages()}`,
                                14,
                                doc.internal.pageSize.height - 10
                            );
                        }
                    });
                    
                    yPosition = doc.lastAutoTable.finalY + 10;
                }
            });
            
            // Save/Print the PDF
            const filename = reportTitle.replace(/\s+/g, '_') + '_' + new Date().toISOString().split('T')[0] + '.pdf';
            doc.save(filename);
        }
    </script>
</body>
</html> 