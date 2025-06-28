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
$stmt = $conn->prepare("SELECT i.*, p.first_name, p.last_name, v.name as vaccine_name 
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
$vaccines = $conn->query("SELECT id, name FROM vaccines ORDER BY name");

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
                            <?php while ($vaccine = $vaccines->fetch_assoc()): ?>
                                <option value="<?php echo $vaccine['id']; ?>" <?php echo ($filter_vaccine == $vaccine['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vaccine['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        
                        <input type="date" name="filter_date" value="<?php echo $filter_date; ?>">
                        
                        <button type="submit">Filter</button>
                        <a href="nurse_immunizations.php" class="btn-sm">Reset</a>
                    </form>
                    
                    <a href="add_immunization.php" class="add-btn">
                        <i class="fas fa-plus"></i> Record New Immunization
                    </a>
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
                                    <td class="action-buttons">
                                        <a href="view_immunization.php?id=<?php echo $immunization['id']; ?>" class="view-btn">View</a>
                                        <a href="edit_immunization.php?id=<?php echo $immunization['id']; ?>" class="edit-btn">Edit</a>
                                        <a href="print_certificate.php?id=<?php echo $immunization['id']; ?>" class="print-btn" title="Print Certificate">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">No immunization records found.</td>
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
</body>
</html> 