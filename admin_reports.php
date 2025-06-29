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
$admin_name = $_SESSION['user_name'];
$admin_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
            
        case 'gender_distribution':
            $report_title = 'Gender Distribution Report';
            $base_query = "SELECT p.gender, COUNT(DISTINCT p.id) as count FROM patients p";
            
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
            
            $query = $base_query . $filter_condition . " GROUP BY p.gender";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
            }
            break;
            
        case 'purok_distribution':
            $report_title = 'Purok Distribution Report';
            $base_query = "SELECT p.purok, COUNT(DISTINCT p.id) as count FROM patients p";
            
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
            
            $query = $base_query . $filter_condition . " GROUP BY p.purok ORDER BY count DESC";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $report_data[] = $row;
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
            
        case 'age_distribution':
            $report_title = 'Age Distribution Report';
            $base_query = "SELECT 
                     CASE 
                        WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 1 THEN 'Under 1'
                        WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 1 AND 5 THEN '1-5'
                        WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 6 AND 12 THEN '6-12'
                        ELSE 'Over 12'
                     END as age_group,
                     COUNT(DISTINCT p.id) as count
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
            
            $query = $base_query . $filter_condition . " 
                     GROUP BY age_group
                     ORDER BY 
                        CASE age_group
                            WHEN 'Under 1' THEN 1
                            WHEN '1-5' THEN 2
                            WHEN '6-12' THEN 3
                            ELSE 4
                        END";
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
        .sidebar { background-color: var(--bg-white); border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 20px; }
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
        .report-chart-container { height: 400px; margin-bottom: 30px; }
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
                                <option value="appointment_list" <?php echo $report_type == 'appointment_list' ? 'selected' : ''; ?>>List of Appointments</option>
                                <option value="patient_diagnosis" <?php echo $report_type == 'patient_diagnosis' ? 'selected' : ''; ?>>Patient Information and Diagnosis</option>
                                <option value="gender_distribution" <?php echo $report_type == 'gender_distribution' ? 'selected' : ''; ?>>Gender Distribution</option>
                                <option value="purok_distribution" <?php echo $report_type == 'purok_distribution' ? 'selected' : ''; ?>>Purok Distribution</option>
                                <option value="diagnosis_distribution" <?php echo $report_type == 'diagnosis_distribution' ? 'selected' : ''; ?>>Diagnosis Distribution</option>
                                <option value="age_distribution" <?php echo $report_type == 'age_distribution' ? 'selected' : ''; ?>>Age Distribution</option>
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
                        <?php elseif ($report_type == 'gender_distribution' || $report_type == 'purok_distribution' || $report_type == 'diagnosis_distribution' || $report_type == 'age_distribution'): ?>
                            <table class="report-table">
                                <thead>
                                    <tr>
                                        <th><?php echo ucfirst(str_replace('_distribution', '', $report_type)); ?></th>
                                        <th>Number of Patients</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row[array_key_first($row)]); ?></td>
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
                            <a href="#" class="btn-print" onclick="window.print()">
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
            
            <?php if (!empty($report_type)): ?>
            // Set up Chart.js for report visualization
            const ctx = document.getElementById('reportChart').getContext('2d');
            
            <?php if ($report_type == 'immunization_summary'): ?>
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
            <?php elseif ($report_type == 'gender_distribution' || $report_type == 'purok_distribution' || $report_type == 'diagnosis_distribution' || $report_type == 'age_distribution'): ?>
                const labels = [<?php echo implode(', ', array_map(function($item) { return '"' . addslashes($item[array_key_first($item)]) . '"'; }, $report_data)); ?>];
                const data = [<?php echo implode(', ', array_map(function($item) { return $item['count']; }, $report_data)); ?>];
                
                new Chart(ctx, {
                    type: '<?php echo $report_type == "purok_distribution" || $report_type == "diagnosis_distribution" ? "bar" : "pie"; ?>',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Number of Patients',
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
    </script>
</body>
</html> 