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
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Midwife';
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

// Get List of Patients
$stmt = $conn->prepare("
    SELECT 
        p.*,
        COUNT(DISTINCT i.id) as immunization_count,
        COUNT(DISTINCT a.id) as appointment_count
    FROM patients p
    LEFT JOIN immunizations i ON p.id = i.patient_id
    LEFT JOIN appointments a ON p.id = a.patient_id
    GROUP BY p.id
    ORDER BY p.last_name, p.first_name
");
$stmt->execute();
$patients_result = $stmt->get_result();
$patients_data = [];
while ($row = $patients_result->fetch_assoc()) {
    $patients_data[] = $row;
}

// Get List of Appointments
$stmt = $conn->prepare("
    SELECT 
        a.*,
        p.first_name,
        p.last_name,
        v.name as vaccine_name
    FROM appointments a
    JOIN patients p ON a.patient_id = p.id
    LEFT JOIN vaccines v ON a.vaccine_id = v.id
    WHERE a.staff_id = ?
    ORDER BY a.appointment_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$appointments_result = $stmt->get_result();
$appointments_data = [];
while ($row = $appointments_result->fetch_assoc()) {
    $appointments_data[] = $row;
}

// Get Patient Information and Diagnosis
$stmt = $conn->prepare("
    SELECT 
        p.*,
        i.administered_date,
        i.diagnosis,
        v.name as vaccine_name,
        i.batch_number,
        i.dose_number
    FROM patients p
    JOIN immunizations i ON p.id = i.patient_id
    JOIN vaccines v ON i.vaccine_id = v.id
    WHERE i.administered_by = ?
    ORDER BY i.administered_date DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$diagnoses_result = $stmt->get_result();
$diagnoses_data = [];
while ($row = $diagnoses_result->fetch_assoc()) {
    $diagnoses_data[] = $row;
}

// Process logout
if (isset($_GET['logout'])) {
    $_SESSION = array();
    session_destroy();
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
    <title>Reports - ImmuCare</title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .report-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .report-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .report-filter select {
            padding: 8px 12px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
        }
        
        .export-btn {
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
        
        .export-btn i {
            margin-right: 5px;
        }
        
        .export-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-container {
            background-color: #f8f9fa;
            border-radius: var(--border-radius);
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            height: 350px;
            position: relative;
        }
        
        .chart-title {
            font-size: 1.2rem;
            color: var(--text-color);
            margin-bottom: 15px;
            text-align: center;
        }
        
        .chart-canvas {
            width: 100%;
            height: 280px !important;
            max-height: 280px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .report-table th, .report-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .report-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .report-table tr:hover {
            background-color: #f8f9fa;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .report-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .chart-canvas {
                height: 230px !important;
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
                    <li><a href="midwife_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="midwife_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="midwife_immunization_records.php"><i class="fas fa-syringe"></i> Immunization Records</a></li>
                    <li><a href="midwife_reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="midwife_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Reports</h2>
                
                <div class="report-actions">
                    <div class="report-filter">
                        <select id="report-type">
                            <option value="patients">List of Patients</option>
                            <option value="appointments">List of Appointments</option>
                            <option value="diagnoses">Patient Information and Diagnosis</option>
                        </select>
                    </div>
                    
                    <div>
                        <a href="#" class="export-btn" onclick="exportReport()">
                            <i class="fas fa-file-export"></i> Export Report
                        </a>
                    </div>
                </div>
                
                <!-- List of Patients Report -->
                <div id="patients-report">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Date of Birth</th>
                                <th>Gender</th>
                                <th>Blood Type</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>Immunizations</th>
                                <th>Appointments</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($patients_data as $patient): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?></td>
                                    <td><?php echo ucfirst($patient['gender']); ?></td>
                                    <td><?php echo !empty($patient['blood_type']) ? htmlspecialchars($patient['blood_type']) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($patient['phone_number']); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($patient['purok'] . ', ' . 
                                            $patient['city'] . ', ' . 
                                            $patient['province']); ?>
                                    </td>
                                    <td><?php echo $patient['immunization_count']; ?></td>
                                    <td><?php echo $patient['appointment_count']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- List of Appointments Report -->
                <div id="appointments-report" style="display: none;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date & Time</th>
                                <th>Patient Name</th>
                                <th>Purpose</th>
                                <th>Vaccine</th>
                                <th>Status</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments_data as $appointment): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['purpose']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['vaccine_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo ucfirst($appointment['status']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Patient Information and Diagnosis Report -->
                <div id="diagnoses-report" style="display: none;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Patient Name</th>
                                <th>Vaccine</th>
                                <th>Dose</th>
                                <th>Batch Number</th>
                                <th>Diagnosis</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($diagnoses_data as $diagnosis): ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($diagnosis['administered_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($diagnosis['first_name'] . ' ' . $diagnosis['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($diagnosis['vaccine_name']); ?></td>
                                    <td><?php echo $diagnosis['dose_number']; ?></td>
                                    <td><?php echo htmlspecialchars($diagnosis['batch_number']); ?></td>
                                    <td><?php echo htmlspecialchars($diagnosis['diagnosis'] ?? 'No diagnosis recorded'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle between report tables
        document.getElementById('report-type').addEventListener('change', function() {
            const reportType = this.value;
            
            document.getElementById('patients-report').style.display = 'none';
            document.getElementById('appointments-report').style.display = 'none';
            document.getElementById('diagnoses-report').style.display = 'none';
            
            document.getElementById(reportType + '-report').style.display = 'block';
        });

        // Export report function
        function exportReport() {
            const reportType = document.getElementById('report-type').value;
            window.location.href = `export_report.php?type=${reportType}&role=midwife`;
        }
    </script>
</body>
</html>
