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

// Get vaccines with pagination
$query = "SELECT * FROM vaccines $search_condition ORDER BY name ASC LIMIT $offset, $records_per_page";
$vaccines = $conn->query($query);

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
                    <li><a href="nurse_vaccine_inventory.php" class="active"><i class="fas fa-vials"></i> Vaccine Inventory</a></li>
                    <li><a href="nurse_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
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
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-value">18</div>
                        <div class="stat-label">In Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-value">3</div>
                        <div class="stat-label">Low Stock</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-value">2</div>
                        <div class="stat-label">Out of Stock</div>
                    </div>
                </div>
                
                <div class="action-bar">
                    <form class="search-form" action="" method="GET">
                        <input type="text" name="search" placeholder="Search vaccines..." value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <a href="request_vaccine.php" class="add-btn">
                        <i class="fas fa-plus"></i> Request Vaccine
                    </a>
                </div>
                
                <table class="vaccine-table">
                    <thead>
                        <tr>
                            <th>Vaccine Name</th>
                            <th>Manufacturer</th>
                            <th>Doses Required</th>
                            <th>Recommended Age</th>
                            <th>Stock Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($vaccines->num_rows > 0): ?>
                            <?php while ($vaccine = $vaccines->fetch_assoc()): 
                                // Mock stock status for display purposes
                                $stock_status = rand(0, 2);
                                $status_class = '';
                                $status_text = '';
                                
                                switch ($stock_status) {
                                    case 0:
                                        $status_class = 'text-danger';
                                        $status_text = 'Out of Stock';
                                        break;
                                    case 1:
                                        $status_class = 'text-warning';
                                        $status_text = 'Low Stock';
                                        break;
                                    case 2:
                                        $status_class = 'text-success';
                                        $status_text = 'In Stock';
                                        break;
                                }
                            ?>
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
                                    <td class="<?php echo $status_class; ?>">
                                        <?php echo $status_text; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="view_vaccine.php?id=<?php echo $vaccine['id']; ?>" class="view-btn">View</a>
                                        <a href="update_stock.php?id=<?php echo $vaccine['id']; ?>" class="edit-btn">Update Stock</a>
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