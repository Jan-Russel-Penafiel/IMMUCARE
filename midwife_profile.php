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

// Get full user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get unread notification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread_count = $result->fetch_assoc()['count'];

// Process profile update
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error_message = "Name and email are required fields.";
    } else {
        // Check if email is already used by another user
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_message = "Email address is already in use by another account.";
        } else {
            // Update user profile
            $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $name, $email, $phone, $user_id);
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $user_name = $name;
                $user_email = $email;
                
                $success_message = "Profile updated successfully.";
                
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error_message = "Error updating profile. Please try again.";
            }
        }
    }
}

// Process password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "New password must be at least 8 characters long.";
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success_message = "Password changed successfully.";
            } else {
                $error_message = "Error changing password. Please try again.";
            }
        } else {
            $error_message = "Current password is incorrect.";
        }
    }
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
    <title>My Profile - ImmuCare</title>
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
        
        .profile-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.4rem;
            color: var(--text-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-info {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: start;
        }
        
        .profile-avatar {
            text-align: center;
        }
        
        .avatar-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background-color: #e8f0fe;
            color: var(--primary-color);
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 4rem;
            margin: 0 auto 15px;
        }
        
        .avatar-initials {
            text-transform: uppercase;
            font-weight: 500;
        }
        
        .avatar-role {
            display: inline-block;
            padding: 5px 15px;
            background-color: var(--primary-color);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }
        
        .avatar-joined {
            color: var(--light-text);
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.1);
        }
        
        .form-submit {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: var(--transition);
        }
        
        .form-submit:hover {
            background-color: var(--primary-dark);
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
        }
        
        .alert-success {
            color: #155724;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            color: #721c24;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
        }
        
        .unread-badge {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary-color);
            margin-right: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
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
                    <li><a href="midwife_notifications.php"><i class="fas fa-bell"></i> Notifications <?php if ($unread_count > 0): ?><span class="unread-badge"></span><?php endif; ?></a></li>
                    <li><a href="midwife_profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">My Profile</h2>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <div class="profile-section">
                    <h3 class="section-title">Personal Information</h3>
                    
                    <div class="profile-info">
                        <div class="profile-avatar">
                            <div class="avatar-circle">
                                <span class="avatar-initials">
                                    <?php 
                                        $initials = '';
                                        $name_parts = explode(' ', $user_name);
                                        foreach ($name_parts as $part) {
                                            $initials .= strtoupper(substr($part, 0, 1));
                                            if (strlen($initials) >= 2) break;
                                        }
                                        echo $initials;
                                    ?>
                                </span>
                            </div>
                            <div class="avatar-role">Midwife</div>
                            <div class="avatar-joined">
                                Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                            </div>
                        </div>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="name" class="form-label">Full Name</label>
                                <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="text" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                            </div>
                            
                            <div class="form-group">
                                <button type="submit" name="update_profile" class="form-submit">Update Profile</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="profile-section">
                    <h3 class="section-title">Change Password</h3>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" name="change_password" class="form-submit">Change Password</button>
                        </div>
                    </form>
                </div>
                
                <div class="profile-section">
                    <h3 class="section-title">Activity Summary</h3>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-injured"></i>
                            </div>
                            <div class="stat-value">--</div>
                            <div class="stat-label">Patients Managed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <div class="stat-value">--</div>
                            <div class="stat-label">Appointments Completed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <div class="stat-value">--</div>
                            <div class="stat-label">Immunizations Recorded</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 