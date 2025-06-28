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

// Get health centers for filter
$stmt = $conn->prepare("SELECT id, name FROM health_centers WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$health_centers = $stmt->get_result();
$health_centers_array = [];
while ($center = $health_centers->fetch_assoc()) {
    $health_centers_array[$center['id']] = $center['name'];
}

// Define report data
$report_data = [];
$report_title = '';

// Generate report based on type
if (!empty($report_type)) {
    switch ($report_type) {
        case 'immunization_summary':
            $report_title = 'Immunization Summary Report';
            
            // Filter condition
            $filter_condition = " WHERE i.immunization_date BETWEEN '$date_from' AND '$date_to'";
            if (!empty($health_center_id)) {
                $filter_condition .= " AND p.health_center_id = $health_center_id";
            }
            
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
            
            // Filter condition
            $filter_condition = " WHERE a.appointment_date BETWEEN '$date_from' AND '$date_to'";
            if (!empty($health_center_id)) {
                $filter_condition .= " AND a.health_center_id = $health_center_id";
            }
            
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
            
            // Filter condition
            $filter_condition = " WHERE 1=1";
            if (!empty($health_center_id)) {
                $filter_condition .= " AND health_center_id = $health_center_id";
            }
            
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
                
                <div class="report-filter">
                    <form action="" method="GET" class="filter-form">
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select id="report_type" name="report_type" required>
                                <option value="">-- Select Report Type --</option>
                                <option value="immunization_summary" <?php echo $report_type == 'immunization_summary' ? 'selected' : ''; ?>>Immunization Summary</option>
                                <option value="appointment_status" <?php echo $report_type == 'appointment_status' ? 'selected' : ''; ?>>Appointment Status</option>
                                <option value="patient_demographics" <?php echo $report_type == 'patient_demographics' ? 'selected' : ''; ?>>Patient Demographics</option>
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
                        
                        <?php if ($report_type == 'immunization_summary'): ?>
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