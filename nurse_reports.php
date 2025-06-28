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

// Get immunization statistics
// Get total immunizations by month for the current year
$current_year = date('Y');
$stmt = $conn->prepare("SELECT MONTH(administered_date) as month, COUNT(*) as count 
                        FROM immunizations 
                        WHERE administered_by = ? AND YEAR(administered_date) = ? 
                        GROUP BY MONTH(administered_date)");
$stmt->bind_param("ii", $user_id, $current_year);
$stmt->execute();
$monthly_immunizations = $stmt->get_result();

$months = array(
    1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
    7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
);

$monthly_data = array_fill(1, 12, 0);
while ($row = $monthly_immunizations->fetch_assoc()) {
    $monthly_data[$row['month']] = $row['count'];
}

// Get immunizations by vaccine type
$stmt = $conn->prepare("SELECT v.name, COUNT(*) as count 
                        FROM immunizations i 
                        JOIN vaccines v ON i.vaccine_id = v.id 
                        WHERE i.administered_by = ? 
                        GROUP BY i.vaccine_id 
                        ORDER BY count DESC 
                        LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$vaccine_distribution_result = $stmt->get_result();

// Store vaccine distribution data for later use
$vaccine_labels = [];
$vaccine_data = [];
while ($vaccine = $vaccine_distribution_result->fetch_assoc()) {
    $vaccine_labels[] = $vaccine['name'];
    $vaccine_data[] = $vaccine['count'];
}

// Get age group distribution
$stmt = $conn->prepare("SELECT 
                        CASE 
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) < 1 THEN 'Under 1'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 1 AND 4 THEN '1-4'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 5 AND 11 THEN '5-11'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 12 AND 17 THEN '12-17'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 18 AND 49 THEN '18-49'
                            WHEN TIMESTAMPDIFF(YEAR, p.date_of_birth, CURDATE()) BETWEEN 50 AND 64 THEN '50-64'
                            ELSE '65+' 
                        END as age_group,
                        COUNT(*) as count
                        FROM immunizations i 
                        JOIN patients p ON i.patient_id = p.id 
                        WHERE i.administered_by = ? 
                        GROUP BY age_group 
                        ORDER BY 
                        CASE age_group
                            WHEN 'Under 1' THEN 1
                            WHEN '1-4' THEN 2
                            WHEN '5-11' THEN 3
                            WHEN '12-17' THEN 4
                            WHEN '18-49' THEN 5
                            WHEN '50-64' THEN 6
                            ELSE 7
                        END");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$age_distribution_result = $stmt->get_result();

// Store age distribution data for later use
$age_labels = [];
$age_data = [];
while ($age = $age_distribution_result->fetch_assoc()) {
    $age_labels[] = $age['age_group'];
    $age_data[] = $age['count'];
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
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Immunization Reports</h2>
                
                <div class="report-actions">
                    <div class="report-filter">
                        <select id="year-filter">
                            <option value="2023">2023</option>
                            <option value="2022">2022</option>
                            <option value="<?php echo $current_year; ?>" selected><?php echo $current_year; ?></option>
                        </select>
                    </div>
                    
                    <div>
                        <a href="export_report.php?type=immunizations" class="export-btn">
                            <i class="fas fa-file-export"></i> Export Report
                        </a>
                    </div>
                </div>
                
                <div class="report-grid">
                    <div class="chart-container full-width">
                        <h3 class="chart-title">Monthly Immunizations (<?php echo $current_year; ?>)</h3>
                        <canvas id="monthlyChart" class="chart-canvas"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3 class="chart-title">Vaccine Distribution</h3>
                        <canvas id="vaccineChart" class="chart-canvas"></canvas>
                    </div>
                    
                    <div class="chart-container">
                        <h3 class="chart-title">Age Group Distribution</h3>
                        <canvas id="ageChart" class="chart-canvas"></canvas>
                    </div>
                </div>
                
                <h3 class="page-title">Detailed Reports</h3>
                
                <div class="report-actions">
                    <div class="report-filter">
                        <select id="report-type">
                            <option value="monthly">Monthly Immunizations</option>
                            <option value="vaccine">Vaccine Distribution</option>
                            <option value="age">Age Group Distribution</option>
                        </select>
                    </div>
                </div>
                
                <div id="monthly-report">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Number of Immunizations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($months as $month_num => $month_name): ?>
                                <tr>
                                    <td><?php echo $month_name; ?></td>
                                    <td><?php echo $monthly_data[$month_num]; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="vaccine-report" style="display: none;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Vaccine</th>
                                <th>Number of Immunizations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Reset the result pointer for vaccine distribution
                            foreach ($vaccine_labels as $index => $name): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($name); ?></td>
                                    <td><?php echo $vaccine_data[$index]; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div id="age-report" style="display: none;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Age Group</th>
                                <th>Number of Immunizations</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Use the stored age distribution data
                            foreach ($age_labels as $index => $age_group): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($age_group); ?></td>
                                    <td><?php echo $age_data[$index]; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Monthly Immunizations Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [{
                    label: 'Number of Immunizations',
                    data: [
                        <?php echo implode(', ', $monthly_data); ?>
                    ],
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
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
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            boxWidth: 12
                        }
                    }
                }
            }
        });
        
        // Vaccine Distribution Chart
        const vaccineCtx = document.getElementById('vaccineChart').getContext('2d');
        const vaccineChart = new Chart(vaccineCtx, {
            type: 'pie',
            data: {
                labels: [
                    <?php echo "'" . implode("', '", array_map('addslashes', $vaccine_labels)) . "'"; ?>
                ],
                datasets: [{
                    data: [
                        <?php echo implode(', ', $vaccine_data); ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
        
        // Age Group Distribution Chart
        const ageCtx = document.getElementById('ageChart').getContext('2d');
        const ageChart = new Chart(ageCtx, {
            type: 'doughnut',
            data: {
                labels: [
                    <?php echo "'" . implode("', '", array_map('addslashes', $age_labels)) . "'"; ?>
                ],
                datasets: [{
                    data: [
                        <?php echo implode(', ', $age_data); ?>
                    ],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.5)',
                        'rgba(54, 162, 235, 0.5)',
                        'rgba(255, 206, 86, 0.5)',
                        'rgba(75, 192, 192, 0.5)',
                        'rgba(153, 102, 255, 0.5)',
                        'rgba(255, 159, 64, 0.5)',
                        'rgba(201, 203, 207, 0.5)'
                    ],
                    borderColor: [
                        'rgba(255, 99, 132, 1)',
                        'rgba(54, 162, 235, 1)',
                        'rgba(255, 206, 86, 1)',
                        'rgba(75, 192, 192, 1)',
                        'rgba(153, 102, 255, 1)',
                        'rgba(255, 159, 64, 1)',
                        'rgba(201, 203, 207, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            boxWidth: 12,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });
        
        // Toggle between report tables
        document.getElementById('report-type').addEventListener('change', function() {
            const reportType = this.value;
            
            document.getElementById('monthly-report').style.display = 'none';
            document.getElementById('vaccine-report').style.display = 'none';
            document.getElementById('age-report').style.display = 'none';
            
            document.getElementById(reportType + '-report').style.display = 'block';
        });
    </script>
</body>
</html> 