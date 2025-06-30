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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = $_POST['patient_id'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $purpose = $_POST['purpose'];
    $vaccine_id = !empty($_POST['vaccine_id']) ? $_POST['vaccine_id'] : null;
    $notes = $_POST['notes'];
    
    // Combine date and time
    $appointment_datetime = date('Y-m-d H:i:s', strtotime("$appointment_date $appointment_time"));
    
    // Insert the appointment
    $stmt = $conn->prepare("INSERT INTO appointments (patient_id, staff_id, appointment_date, vaccine_id, purpose, notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', NOW())");
    $stmt->bind_param("iisiss", $patient_id, $user_id, $appointment_datetime, $vaccine_id, $purpose, $notes);
    
    if ($stmt->execute()) {
        // Get the patient's contact information for notification
        $patient_query = "SELECT p.first_name, p.last_name, p.phone_number, u.email 
                         FROM patients p 
                         LEFT JOIN users u ON p.user_id = u.id 
                         WHERE p.id = ?";
        $stmt = $conn->prepare($patient_query);
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $patient_result = $stmt->get_result()->fetch_assoc();
        
        // TODO: Send notification to patient (SMS/Email)
        
        header('Location: nurse_appointments.php?scheduled=1');
        exit;
    } else {
        $error = "Error scheduling appointment: " . $conn->error;
    }
}

// Get list of patients
$patients_query = "SELECT id, first_name, last_name FROM patients ORDER BY last_name, first_name";
$patients_result = $conn->query($patients_query);

// Get list of vaccines
$vaccines_query = "SELECT id, name, manufacturer FROM vaccines ORDER BY name";
$vaccines_result = $conn->query($vaccines_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment - ImmuCare</title>
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
        
        .form-container {
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .form-group select,
        .form-group input[type="date"],
        .form-group input[type="time"],
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e1e4e8;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
            font-size: 0.9rem;
            background-color: #f8f9fa;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 30px;
        }
        
        .submit-btn {
            padding: 10px 20px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .submit-btn:hover {
            background-color: var(--primary-dark);
        }
        
        .cancel-btn {
            padding: 10px 20px;
            background-color: #f1f3f5;
            color: var(--text-color);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .cancel-btn:hover {
            background-color: #e9ecef;
        }
        
        @media screen and (max-width: 992px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                margin-bottom: 20px;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .submit-btn,
            .cancel-btn {
                width: 100%;
                text-align: center;
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
                    <li><a href="nurse_appointments.php" class="active"><i class="fas fa-calendar-check"></i> Appointments</a></li>
                    <li><a href="nurse_patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
                    <li><a href="nurse_reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
                    <li><a href="nurse_profile.php"><i class="fas fa-user"></i> Profile</a></li>
                </ul>
            </div>
            
            <div class="main-content">
                <h2 class="page-title">Schedule New Appointment</h2>
                
                <?php if (isset($error)): ?>
                    <div class="error-message"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="form-container">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="patient_id">Patient</label>
                            <select name="patient_id" id="patient_id" required>
                                <option value="">Select Patient</option>
                                <?php while ($patient = $patients_result->fetch_assoc()): ?>
                                    <option value="<?php echo $patient['id']; ?>">
                                        <?php echo htmlspecialchars($patient['last_name'] . ', ' . $patient['first_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_date">Appointment Date</label>
                            <input type="date" name="appointment_date" id="appointment_date" required 
                                   min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="appointment_time">Appointment Time</label>
                            <input type="time" name="appointment_time" id="appointment_time" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Purpose</label>
                            <select name="purpose" id="purpose" required>
                                <option value="">Select Purpose</option>
                                <option value="Vaccination">Vaccination</option>
                                <option value="Follow-up">Follow-up</option>
                                <option value="Consultation">Consultation</option>
                                <option value="General Checkup">General Checkup</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="vaccine_id">Vaccine (Optional)</label>
                            <select name="vaccine_id" id="vaccine_id">
                                <option value="">Select Vaccine</option>
                                <?php while ($vaccine = $vaccines_result->fetch_assoc()): ?>
                                    <option value="<?php echo $vaccine['id']; ?>">
                                        <?php echo htmlspecialchars($vaccine['name'] . ' (' . $vaccine['manufacturer'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" id="notes" placeholder="Enter any additional notes or instructions"></textarea>
                        </div>
                        
                        <div class="button-group">
                            <a href="nurse_appointments.php" class="cancel-btn">Cancel</a>
                            <button type="submit" class="submit-btn">Schedule Appointment</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Add client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const appointmentDate = new Date(document.getElementById('appointment_date').value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (appointmentDate < today) {
                e.preventDefault();
                alert('Please select a future date for the appointment.');
            }
        });
        
        // Show/hide vaccine selection based on purpose
        document.getElementById('purpose').addEventListener('change', function() {
            const vaccineGroup = document.getElementById('vaccine_id').closest('.form-group');
            if (this.value === 'Vaccination') {
                vaccineGroup.style.display = 'block';
                document.getElementById('vaccine_id').required = true;
            } else {
                vaccineGroup.style.display = 'none';
                document.getElementById('vaccine_id').required = false;
            }
        });
    </script>
</body>
</html> 