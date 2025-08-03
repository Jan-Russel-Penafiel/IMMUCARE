<?php
session_start();
require 'config.php';

// Check if user is logged in and is a patient
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'patient') {
    header('Location: login.php');
    exit;
}

// Get patient information
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize count variables
$immunization_count = 0;
$appointment_count = 0;
$notification_count = 0;

// Get patient details
$stmt = $conn->prepare("SELECT * FROM patients WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();
$patient_id = $patient ? $patient['id'] : null;

// If patient exists, get counts
if ($patient_id) {
    // Get immunization count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM immunizations WHERE patient_id = ?");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $immunization_count = $result->fetch_assoc()['count'];
    
    // Get appointment count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM appointments WHERE patient_id = ? AND status = 'confirmed' AND appointment_date >= CURDATE()");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointment_count = $result->fetch_assoc()['count'];
}

// Get notification count
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notification_count = $result->fetch_assoc()['count'];

// Get upcoming appointments
$stmt = $conn->prepare("
    SELECT a.*, v.name as vaccine_name, u.name as staff_name 
    FROM appointments a 
    LEFT JOIN vaccines v ON a.vaccine_id = v.id 
    LEFT JOIN users u ON a.staff_id = u.id 
    WHERE a.patient_id = ? AND a.status IN ('requested', 'confirmed') AND a.appointment_date >= CURDATE()
    ORDER BY a.appointment_date ASC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$upcoming_appointments = $stmt->get_result();

// Get recent immunizations
$stmt = $conn->prepare("
    SELECT i.*, v.name as vaccine_name, u.name as administered_by_name
    FROM immunizations i
    JOIN vaccines v ON i.vaccine_id = v.id
    JOIN users u ON i.administered_by = u.id
    WHERE i.patient_id = ?
    ORDER BY i.administered_date DESC
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$recent_immunizations = $stmt->get_result();

// Get due immunizations
$stmt = $conn->prepare("
    SELECT v.id, v.name, v.recommended_age, v.doses_required,
    (SELECT COUNT(*) FROM immunizations i WHERE i.patient_id = ? AND i.vaccine_id = v.id) as doses_received
    FROM vaccines v
    HAVING doses_received < doses_required
    ORDER BY v.id
    LIMIT 5
");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$due_immunizations = $stmt->get_result();

// Get notifications
$stmt = $conn->prepare("
    SELECT * FROM notifications
    WHERE user_id = ? AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();

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
    <title>Patient Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="icon" href="images/favicon.svg" type="image/svg+xml">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4285f4;
            --secondary-color: #34a853;
            --accent-color: #fbbc05;
            --text-color: #333;
            --light-text: #666;
            --bg-light: #f8fafc;
            --bg-white: #ffffff;
            --shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--bg-light);
            font-size: 14px;
            line-height: 1.6;
            font-weight: 600;
        }

        * {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }

        .text-muted, .small, small {
            font-weight: 500;
        }

        .fw-medium {
            font-weight: 600;
        }

        h1, h2, h3, h4, h5, h6, .h1, .h2, .h3, .h4, .h5, .h6 {
            font-weight: 700;
        }

        @media (max-width: 768px) {
            body {
                font-size: 13px;
            }
        }

        /* Card styles */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            margin-bottom: 1.5rem;
            background: var(--bg-white);
            overflow: hidden;
        }

        .card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }

        .card-body {
            padding: 1.5rem;
        }
        
        .card-header {
            background-color: rgba(66, 133, 244, 0.05);
            border-bottom: 1px solid rgba(66, 133, 244, 0.1);
            padding: 1rem 1.5rem;
        }
        
        .card-header h3, .card-header h5 {
            margin-bottom: 0;
            color: var(--primary-color);
        }
        
        .border-card {
            border: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: none;
        }
        
        .border-card:hover {
            border-color: rgba(66, 133, 244, 0.2);
            box-shadow: 0 2px 8px rgba(66, 133, 244, 0.1);
        }

        /* Sidebar styles */
        .sidebar {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1.25rem;
            position: sticky;
            top: 1rem;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            color: var(--text-color);
            text-decoration: none;
            transition: var(--transition);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            justify-content: space-between;
        }

        .sidebar-menu a:hover {
            background-color: #edf2ff;
            color: var(--primary-color);
        }

        .sidebar-menu a.active {
            background-color: var(--primary-color);
            color: white;
        }

        .sidebar-menu a i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
            font-size: 1rem;
        }
        
        .sidebar-menu a div {
            display: flex;
            align-items: center;
        }
        
        .sidebar-menu .badge {
            font-size: 0.7rem;
            padding: 0.35em 0.65em;
            font-weight: 500;
        }
        
        .sidebar-menu a.active .badge {
            background-color: white !important;
            color: var(--primary-color);
        }

        /* Header styles */
        .dashboard-header {
            background: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        /* Stats card styles */
        .stat-card {
            background: linear-gradient(135deg, var(--bg-white) 0%, #f8f9fa 100%);
            border-radius: var(--border-radius);
            padding: 1.5rem;
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(0, 0, 0, 0.05);
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-card .icon {
            width: 48px;
            height: 48px;
            background: rgba(66, 133, 244, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .stat-card .icon i {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .stat-card .number {
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .stat-card .label {
            color: var(--light-text);
            font-size: 0.9rem;
        }

        /* Button styles */
        .btn {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
        }

        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-primary:hover {
            background-color: #3367d6;
            border-color: #3367d6;
        }

        /* Table styles */
        .table {
            font-size: 0.9rem;
        }

        .table th {
            font-weight: 600;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }

        /* Badge styles */
        .badge {
            font-size: 0.75rem;
            padding: 0.5em 0.75em;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-requested {
            background-color: #fef3c7;
            color: #92400e;
        }

        /* Modal styles */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #eee;
            padding: 1.25rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .form-label {
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            font-size: 0.9rem;
            border-radius: 8px;
            padding: 0.5rem 0.75rem;
        }

        /* Responsive adjustments */
        @media (max-width: 991.98px) {
            .sidebar {
                margin-bottom: 1.5rem;
                position: static;
            }

            .content-section {
                padding: 1rem;
            }
        }

        @media (max-width: 767.98px) {
            .stat-card {
                margin-bottom: 1rem;
            }

            .table-responsive {
                margin-bottom: 1rem;
            }

            .modal-dialog {
                margin: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="dashboard-header mb-4">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center">
                <div class="d-flex align-items-center mb-3 mb-md-0">
                    <img src="images/logo.svg" alt="<?php echo APP_NAME; ?> Logo" height="40" class="me-2">
                    <h1 class="h4 mb-0"><?php echo APP_NAME; ?></h1>
                </div>
                <div class="d-flex flex-column flex-md-row align-items-center">
                    <div class="text-center text-md-end me-md-3">
                        <div class="fw-medium"><?php echo htmlspecialchars($user_name); ?></div>
                        <div class="text-muted small"><?php echo htmlspecialchars($user_email); ?></div>
                    </div>
                    <a href="?logout=1" class="btn btn-light btn-sm mt-2 mt-md-0">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-12 col-lg-3">
                <div class="sidebar">
                    <ul class="sidebar-menu">
                        <li><a href="#dashboard" class="nav-link active" data-section="dashboard">
                            <div><i class="fas fa-home"></i> Dashboard</div>
                        </a></li>
                        <li><a href="#profile" class="nav-link" data-section="profile">
                            <div><i class="fas fa-user"></i> My Profile</div>
                        </a></li>
                        <li><a href="#appointments" class="nav-link" data-section="appointments">
                            <div><i class="fas fa-calendar-check"></i> Appointments</div>
                            <?php if ($appointment_count > 0): ?>
                                <span class="badge rounded-pill bg-primary"><?php echo $appointment_count; ?></span>
                            <?php endif; ?>
                        </a></li>
                        <li><a href="#immunizations" class="nav-link" data-section="immunizations">
                            <div><i class="fas fa-syringe"></i> Immunization Records</div>
                            <?php if ($immunization_count > 0): ?>
                                <span class="badge rounded-pill bg-primary"><?php echo $immunization_count; ?></span>
                            <?php endif; ?>
                        </a></li>
                        <li><a href="#notifications" class="nav-link" data-section="notifications">
                            <div><i class="fas fa-bell"></i> Notifications</div>
                            <?php if ($notification_count > 0): ?>
                                <span class="badge rounded-pill bg-danger"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a></li>
                    </ul>
                </div>
            </div>

            <!-- Main Content Area -->
            <div class="col-12 col-lg-9">
                <!-- Dashboard Section -->
                <div class="content-section active" id="dashboard-section">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="h5 text-primary">Welcome, <?php echo htmlspecialchars($patient ? $patient['first_name'] : $user_name); ?>!</h3>
                        </div>
                        <div class="card-body">
                            <p class="text-muted small">Here's an overview of your health information and upcoming appointments.</p>
                            
                            <div class="row g-4">
                                <!-- Stats Cards -->
                                <div class="col-12 col-md-4">
                                    <div class="stat-card">
                                        <div class="icon">
                                            <i class="fas fa-syringe"></i>
                                        </div>
                                        <div class="number"><?php echo $immunization_count; ?></div>
                                        <div class="label">Immunizations</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="stat-card">
                                        <div class="icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div class="number"><?php echo $appointment_count; ?></div>
                                        <div class="label">Upcoming Appointments</div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-4">
                                    <div class="stat-card">
                                        <div class="icon">
                                            <i class="fas fa-bell"></i>
                                        </div>
                                        <div class="number"><?php echo $notification_count; ?></div>
                                        <div class="label">Notifications</div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($upcoming_appointments->num_rows > 0): ?>
                            <div class="mt-4">
                                <h5 class="text-primary mb-3">Next Appointment</h5>
                                <?php 
                                $appointment = $upcoming_appointments->fetch_assoc();
                                $upcoming_appointments->data_seek(0); // Reset pointer for later use
                                ?>
                                <div class="card border-card">
                                    <div class="card-body">
                                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
                                            <div class="mb-2 mb-md-0">
                                                <div class="text-primary fw-medium mb-1">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                </div>
                                                <div class="text-muted small">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                                </div>
                                            </div>
                                            <span class="badge badge-<?php echo strtolower($appointment['status']); ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                        <h6 class="mb-2">
                                            <?php echo htmlspecialchars($appointment['purpose']); ?>
                                            <?php if ($appointment['vaccine_id']): ?>
                                                <span class="text-primary small">
                                                    - <?php echo htmlspecialchars($appointment['vaccine_name']); ?>
                                                </span>
                                            <?php endif; ?>
                                        </h6>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Profile Section -->
                <div class="content-section" id="profile-section">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="h5">My Profile</h3>
                            <?php if (!$patient): ?>
                                <button id="create-profile-btn" class="btn btn-primary btn-sm">
                                    <i class="fas fa-plus me-1"></i> Create Profile
                                </button>
                            <?php endif; ?>
                        </div>

                        <?php if ($patient): ?>
                        <div class="card-body">
                            <div class="row g-4">
                                <div class="col-12 col-md-6">
                                    <div class="card border-card h-100">
                                        <div class="card-body">
                                            <h6 class="text-primary mb-3">Personal Information</h6>
                                            <div class="mb-3">
                                                <div class="text-muted small">Full Name</div>
                                                <div class="fw-medium">
                                                    <?php 
                                                        echo htmlspecialchars($patient['first_name'] . ' ' . 
                                                            ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . 
                                                            $patient['last_name']); 
                                                    ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="text-muted small">Date of Birth</div>
                                                <div class="fw-medium">
                                                    <?php echo date('F j, Y', strtotime($patient['date_of_birth'])); ?>
                                                    (<?php 
                                                        $dob = new DateTime($patient['date_of_birth']);
                                                        $now = new DateTime();
                                                        echo $now->diff($dob)->y . ' years old';
                                                    ?>)
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="text-muted small">Gender</div>
                                                <div class="fw-medium"><?php echo ucfirst($patient['gender']); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <div class="card border-card h-100">
                                        <div class="card-body">
                                            <h6 class="text-primary mb-3">Contact Information</h6>
                                            <div class="mb-3">
                                                <div class="text-muted small">Phone</div>
                                                <div class="fw-medium">
                                                    <?php echo htmlspecialchars($patient['phone_number']); ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="text-muted small">Email</div>
                                                <div class="fw-medium">
                                                    <?php echo htmlspecialchars($user_email); ?>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <div class="text-muted small">Address</div>
                                                <div class="fw-medium">
                                                    <?php 
                                                        $address_parts = array_filter([
                                                            $patient['purok'],
                                                            $patient['city'],
                                                            $patient['province'],
                                                            $patient['postal_code']
                                                        ]);
                                                        echo htmlspecialchars(implode(', ', $address_parts));
                                                    ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($patient['medical_history']) || !empty($patient['allergies'])): ?>
                            <div class="row mt-4">
                                <?php if (!empty($patient['medical_history'])): ?>
                                <div class="col-12 col-md-6 mb-4">
                                    <div class="card border-card h-100">
                                        <div class="card-body">
                                            <h6 class="text-primary mb-3">Medical History</h6>
                                            <div class="fw-medium small"><?php echo nl2br(htmlspecialchars($patient['medical_history'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                                <?php if (!empty($patient['allergies'])): ?>
                                <div class="col-12 col-md-6 mb-4">
                                    <div class="card border-card h-100">
                                        <div class="card-body">
                                            <h6 class="text-primary mb-3">Allergies</h6>
                                            <div class="fw-medium small"><?php echo nl2br(htmlspecialchars($patient['allergies'])); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <p>Your patient profile has not been created yet. Please click the "Create Profile" button above to set up your profile.</p>
                                <p>This will allow you to:</p>
                                <ul class="mb-0">
                                    <li>Schedule appointments</li>
                                    <li>Track your immunizations</li>
                                    <li>Receive important health notifications</li>
                                </ul>
                            </div>
                            <div class="card border-card mt-4">
                                <div class="card-body">
                                    <h6 class="text-primary mb-3">Account Information</h6>
                                    <div class="mb-2">
                                        <div class="text-muted small">Name</div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($user_name); ?></div>
                                    </div>
                                    <div>
                                        <div class="text-muted small">Email</div>
                                        <div class="fw-medium"><?php echo htmlspecialchars($user_email); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Appointments Section -->
                <div class="content-section" id="appointments-section">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="h5">My Appointments</h3>
                            <button id="new-appointment-btn" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i> New Appointment
                            </button>
                        </div>
                        <div class="card-body">
                            <?php if ($upcoming_appointments->num_rows > 0): ?>
                                <div class="row g-4">
                                <?php while ($appointment = $upcoming_appointments->fetch_assoc()): ?>
                                    <div class="col-12">
                                        <div class="card border-card">
                                            <div class="card-body">
                                                <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-3">
                                                    <div class="mb-2 mb-md-0">
                                                        <div class="text-primary fw-medium mb-1">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-clock me-1"></i>
                                                            <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                                        </div>
                                                    </div>
                                                    <span class="badge badge-<?php echo strtolower($appointment['status']); ?>">
                                                        <?php echo ucfirst($appointment['status']); ?>
                                                    </span>
                                                </div>
                                                <h6 class="card-title mb-3">
                                                    <?php echo htmlspecialchars($appointment['purpose']); ?>
                                                    <?php if ($appointment['vaccine_id']): ?>
                                                        <span class="text-primary small">
                                                            - <?php echo htmlspecialchars($appointment['vaccine_name']); ?>
                                                        </span>
                                                    <?php endif; ?>
                                                </h6>
                                                <div class="d-flex flex-wrap gap-3">
                                                    <span class="text-muted small">
                                                        <i class="fas fa-map-marker-alt me-1"></i> 
                                                        Barangay Health Center
                                                    </span>
                                                    <span class="text-muted small">
                                                        <i class="fas fa-user-md me-1"></i> 
                                                        <?php echo $appointment['staff_name'] ? htmlspecialchars($appointment['staff_name']) : 'Not assigned'; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No upcoming appointments scheduled.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Immunizations Section -->
                <div class="content-section" id="immunizations-section">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h3 class="h5">Recent Immunizations</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($recent_immunizations->num_rows > 0): ?>
                                <div class="row g-3">
                                    <?php while ($immunization = $recent_immunizations->fetch_assoc()): ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="card border-card h-100">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="text-primary mb-0"><?php echo htmlspecialchars($immunization['vaccine_name']); ?></h6>
                                                        <span class="badge bg-light text-dark small">Dose <?php echo $immunization['dose_number']; ?></span>
                                                    </div>
                                                    <div class="mb-2">
                                                        <div class="text-muted small mb-1">
                                                            <i class="fas fa-calendar me-1"></i>
                                                            <?php echo date('M j, Y', strtotime($immunization['administered_date'])); ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-user-md me-1"></i>
                                                            <?php echo htmlspecialchars($immunization['administered_by_name']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No immunization records found.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3 class="h5">Due Immunizations</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($due_immunizations->num_rows > 0): ?>
                                <div class="row g-3">
                                    <?php while ($vaccine = $due_immunizations->fetch_assoc()): ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="card border-card h-100">
                                                <div class="card-body p-3">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="text-primary mb-0"><?php echo htmlspecialchars($vaccine['name']); ?></h6>
                                                        <span class="badge bg-warning text-dark small">Due</span>
                                                    </div>
                                                    <div class="mb-3">
                                                        <div class="text-muted small mb-1">
                                                            <i class="fas fa-calendar-alt me-1"></i>
                                                            Recommended: <?php echo htmlspecialchars($vaccine['recommended_age']); ?>
                                                        </div>
                                                        <div class="text-muted small mb-1">
                                                            <i class="fas fa-syringe me-1"></i>
                                                            Required doses: <?php echo $vaccine['doses_required']; ?>
                                                        </div>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-check-circle me-1"></i>
                                                            Received: <?php echo $vaccine['doses_received']; ?>
                                                        </div>
                                                    </div>
                                                    <button class="btn btn-primary btn-sm w-100 schedule-btn" data-vaccine-id="<?php echo $vaccine['id']; ?>">
                                                        <i class="fas fa-calendar-plus me-1"></i> Schedule
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-success small">
                                    <i class="fas fa-check-circle me-1"></i>
                                    You are up to date with all recommended immunizations.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications Section -->
                <div class="content-section" id="notifications-section">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="h5">Notifications</h3>
                        </div>
                        <div class="card-body">
                            <?php if ($notifications->num_rows > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php while ($notification = $notifications->fetch_assoc()): ?>
                                        <div class="list-group-item border-start-0 border-end-0 py-3 <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h6 class="mb-0 text-primary">
                                                    <i class="fas fa-bell me-2 small"></i>
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                </small>
                                            </div>
                                            <p class="mb-0 small text-muted">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info small">
                                    <i class="fas fa-info-circle me-1"></i>
                                    No notifications to display.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Profile Modal -->
    <div class="modal fade" id="createProfileModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-primary">Create Patient Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="createProfileForm" method="post" action="process_profile.php">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">First Name *</label>
                                <input type="text" class="form-control form-control-sm" name="first_name" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Middle Name</label>
                                <input type="text" class="form-control form-control-sm" name="middle_name">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Last Name *</label>
                                <input type="text" class="form-control form-control-sm" name="last_name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Date of Birth *</label>
                                <input type="date" class="form-control form-control-sm" name="date_of_birth" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Gender *</label>
                                <select class="form-select form-select-sm" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Blood Type</label>
                                <select class="form-select form-select-sm" name="blood_type">
                                    <option value="">Select Blood Type</option>
                                    <option value="A+">A+</option>
                                    <option value="A-">A-</option>
                                    <option value="B+">B+</option>
                                    <option value="B-">B-</option>
                                    <option value="AB+">AB+</option>
                                    <option value="AB-">AB-</option>
                                    <option value="O+">O+</option>
                                    <option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Phone Number *</label>
                                <input type="tel" class="form-control form-control-sm" name="phone_number" required>
                            </div>
                            
                            <div class="col-12">
                                <hr class="my-2">
                                <h6 class="text-primary mb-3">Address Information</h6>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small fw-medium">Purok/Street Address *</label>
                                <input type="text" class="form-control form-control-sm" name="purok" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">City *</label>
                                <input type="text" class="form-control form-control-sm" name="city" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Province *</label>
                                <input type="text" class="form-control form-control-sm" name="province" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-medium">Postal Code</label>
                                <input type="text" class="form-control form-control-sm" name="postal_code">
                            </div>
                            
                            <div class="col-12">
                                <hr class="my-2">
                                <h6 class="text-primary mb-3">Medical Information</h6>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small fw-medium">Medical History</label>
                                <textarea class="form-control form-control-sm" name="medical_history" rows="3" placeholder="Enter any pre-existing medical conditions"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Allergies</label>
                                <textarea class="form-control form-control-sm" name="allergies" rows="3" placeholder="Enter any known allergies"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Create Profile</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule Appointment Modal -->
    <div class="modal fade" id="scheduleAppointmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-primary">Schedule New Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="scheduleAppointmentForm" method="post" action="process_patient_appointment.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Preferred Date *</label>
                                <input type="date" class="form-control form-control-sm" name="appointment_date" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-medium">Preferred Time *</label>
                                <input type="time" class="form-control form-control-sm" name="appointment_time" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Vaccine (Optional)</label>
                                <select class="form-select form-select-sm" name="vaccine_id" id="vaccine_id">
                                    <option value="">Select Vaccine</option>
                                    <?php
                                    $stmt = $conn->prepare("SELECT id, name FROM vaccines");
                                    $stmt->execute();
                                    $vaccines = $stmt->get_result();
                                    while ($vaccine = $vaccines->fetch_assoc()) {
                                        echo "<option value='" . $vaccine['id'] . "'>" . htmlspecialchars($vaccine['name']) . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Purpose of Visit *</label>
                                <textarea class="form-control form-control-sm" name="purpose" rows="3" required placeholder="Describe the reason for your appointment"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-medium">Additional Notes</label>
                                <textarea class="form-control form-control-sm" name="notes" rows="3" placeholder="Any additional information for the healthcare provider"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top-0">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary btn-sm">Schedule Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Bootstrap modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                new bootstrap.Modal(modal);
            });

            // Get all section elements
            const sections = document.querySelectorAll('.content-section');
            
            // Get all navigation links
            const navLinks = document.querySelectorAll('.nav-link');
            
            // Function to show a specific section
            function showSection(sectionId) {
                // Hide all sections
                sections.forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show the selected section
                const targetSection = document.getElementById(sectionId + '-section');
                if (targetSection) {
                    targetSection.style.display = 'block';
                }
                
                // Update active state of navigation links
                navLinks.forEach(link => {
                    if (link.getAttribute('data-section') === sectionId) {
                        link.classList.add('active');
                    } else {
                        link.classList.remove('active');
                    }
                });
                
                // Store the active section in sessionStorage
                sessionStorage.setItem('activeSection', sectionId);
            }
            
            // Add click event listeners to navigation links
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const sectionId = this.getAttribute('data-section');
                    showSection(sectionId);
                });
            });
            
            // Show the last active section or default to dashboard
            const activeSection = sessionStorage.getItem('activeSection') || 'dashboard';
            showSection(activeSection);

            // Schedule Appointment Button
            const scheduleButtons = document.querySelectorAll('.schedule-btn');
            scheduleButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const vaccineId = this.getAttribute('data-vaccine-id');
                    const vaccineSelect = document.getElementById('vaccine_id');
                    
                    if (vaccineId && vaccineSelect) {
                        vaccineSelect.value = vaccineId;
                    }
                    
                    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
                    scheduleModal.show();
                });
            });

            // New Appointment Button
            const newAppointmentBtn = document.getElementById('new-appointment-btn');
            if (newAppointmentBtn) {
                newAppointmentBtn.addEventListener('click', function() {
                    const scheduleModal = new bootstrap.Modal(document.getElementById('scheduleAppointmentModal'));
                    scheduleModal.show();
                });
            }

            // Create Profile Button
            const createProfileBtn = document.getElementById('create-profile-btn');
            if (createProfileBtn) {
                createProfileBtn.addEventListener('click', function() {
                    const modal = new bootstrap.Modal(document.getElementById('createProfileModal'));
                    modal.show();
                });
            }

            // Handle form submission
            const scheduleAppointmentForm = document.getElementById('scheduleAppointmentForm');
            if (scheduleAppointmentForm) {
                scheduleAppointmentForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    // Get form data
                    const formData = new FormData(this);
                    
                    // Submit form using fetch
                    fetch('process_patient_appointment.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Hide modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('scheduleAppointmentModal'));
                            modal.hide();
                            
                            // Optionally show success message
                            alert('Appointment scheduled successfully!');
                            
                            // Reload page to show new appointment
                            window.location.reload();
                        } else {
                            alert(data.message || 'Error scheduling appointment. Please try again.');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error scheduling appointment. Please try again.');
                    });
                });
            }
        });
    </script>
</body>
</html> 