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

// Ensure patient ID is provided
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    header('Location: nurse_patients.php');
    exit;
}

$patient_id = (int)$_GET['patient_id'];

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patient details
$stmt = $conn->prepare("SELECT first_name, middle_name, last_name, date_of_birth, gender FROM patients WHERE id = ?");
$stmt->bind_param("i", $patient_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Patient not found, redirect back
    $conn->close();
    header('Location: nurse_patients.php');
    exit;
}

$patient = $result->fetch_assoc();
$patient_name = $patient['first_name'] . ' ' . ($patient['middle_name'] ? $patient['middle_name'] . ' ' : '') . $patient['last_name'];
$patient_age = date_diff(date_create($patient['date_of_birth']), date_create('now'))->y;

// Get available vaccines
$vaccines = $conn->query("SELECT id, name, manufacturer, doses_required FROM vaccines WHERE is_active = 1 ORDER BY name");

// Process form submission
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $vaccine_id = isset($_POST['vaccine_id']) ? (int)$_POST['vaccine_id'] : 0;
    $dose_number = isset($_POST['dose_number']) ? (int)$_POST['dose_number'] : 1;
    $administered_date = isset($_POST['administered_date']) ? $_POST['administered_date'] : '';
    $batch_number = isset($_POST['batch_number']) ? $_POST['batch_number'] : '';
    $expiration_date = isset($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
    $location = isset($_POST['location']) ? $_POST['location'] : '';
    $next_dose_date = isset($_POST['next_dose_date']) ? $_POST['next_dose_date'] : null;
    $diagnosis = isset($_POST['diagnosis']) ? $_POST['diagnosis'] : '';
    
    if (empty($vaccine_id) || empty($administered_date)) {
        $message = 'Please fill in all required fields.';
        $message_type = 'error';
    } else {
        // Get current datetime for created_at
        $now = date('Y-m-d H:i:s');
        
        // Insert immunization record
        $stmt = $conn->prepare("INSERT INTO immunizations 
                               (patient_id, vaccine_id, administered_by, dose_number, 
                               batch_number, expiration_date, administered_date, 
                               next_dose_date, location, diagnosis, created_at) 
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->bind_param("iiiisssssss", 
                         $patient_id, $vaccine_id, $user_id, $dose_number, 
                         $batch_number, $expiration_date, $administered_date, 
                         $next_dose_date, $location, $diagnosis, $now);
        
        if ($stmt->execute()) {
            $message = 'Immunization record added successfully.';
            $message_type = 'success';
            
            // Add notification for the patient
            $patient_user_id_query = $conn->query("SELECT user_id FROM patients WHERE id = $patient_id");
            if ($patient_user_id_query && $patient_user_id_query->num_rows > 0) {
                $patient_user_id_row = $patient_user_id_query->fetch_assoc();
                $patient_user_id = $patient_user_id_row['user_id'];
                
                // Only create notification if patient has a valid user_id
                if (!empty($patient_user_id)) {
                    // Get vaccine name
                    $vaccine_query = $conn->query("SELECT name FROM vaccines WHERE id = $vaccine_id");
                    $vaccine_name = 'Unknown vaccine';
                    if ($vaccine_query && $vaccine_query->num_rows > 0) {
                        $vaccine_row = $vaccine_query->fetch_assoc();
                        $vaccine_name = $vaccine_row['name'];
                    }
                    
                    // Create notification
                    $notification_title = "Immunization Record Added";
                    $notification_message = "An immunization record has been added to your health record. 
                                          Vaccine: $vaccine_name, 
                                          Date: " . date('M j, Y', strtotime($administered_date)) . ", 
                                          Dose: $dose_number";
                    
                    $stmt = $conn->prepare("INSERT INTO notifications 
                                           (user_id, title, message, type, created_at) 
                                           VALUES (?, ?, ?, 'system', ?)");
                    $stmt->bind_param("isss", $patient_user_id, $notification_title, $notification_message, $now);
                    $stmt->execute();
                }
            }
            
            // Redirect to patient's immunization history
            header("Location: nurse_patients.php");
            exit;
        } else {
            $message = 'Error adding immunization record: ' . $conn->error;
            $message_type = 'error';
        }
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Immunization - ImmuCare</title>
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
        
        .patient-info-box {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .patient-info-box h3 {
            margin-top: 0;
            margin-bottom: 10px;
            color: var(--text-color);
            font-size: 1.1rem;
        }
        
        .patient-info-box p {
            margin: 5px 0;
            color: var(--text-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
        }
        
        .btn-secondary {
            background-color: #f1f3f5;
            color: var(--text-color);
        }
        
        .btn-secondary:hover {
            background-color: #e9ecef;
        }
        
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #842029;
        }
        
        .full-width {
            grid-column: span 2;
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
                <h2 class="page-title">Record Immunization</h2>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="patient-info-box">
                    <h3>Patient Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($patient_name); ?></p>
                    <p><strong>Date of Birth:</strong> <?php echo date('M j, Y', strtotime($patient['date_of_birth'])); ?> (<?php echo $patient_age; ?> years)</p>
                    <p><strong>Gender:</strong> <?php echo ucfirst(htmlspecialchars($patient['gender'])); ?></p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="vaccine_id">Vaccine *</label>
                            <select id="vaccine_id" name="vaccine_id" required>
                                <option value="">Select Vaccine</option>
                                <?php while ($vaccine = $vaccines->fetch_assoc()): ?>
                                    <option value="<?php echo $vaccine['id']; ?>" data-doses="<?php echo $vaccine['doses_required']; ?>">
                                        <?php echo htmlspecialchars($vaccine['name'] . ' (' . $vaccine['manufacturer'] . ')'); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="dose_number">Dose Number *</label>
                            <input type="number" id="dose_number" name="dose_number" min="1" value="1" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="administered_date">Date Administered *</label>
                            <input type="datetime-local" id="administered_date" name="administered_date" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="batch_number">Batch Number</label>
                            <input type="text" id="batch_number" name="batch_number">
                        </div>
                        
                        <div class="form-group">
                            <label for="expiration_date">Expiration Date</label>
                            <input type="date" id="expiration_date" name="expiration_date">
                        </div>
                        
                        <div class="form-group">
                            <label for="location">Location</label>
                            <input type="text" id="location" name="location" placeholder="e.g., Left Arm, Right Thigh">
                        </div>
                        
                        <div class="form-group">
                            <label for="next_dose_date">Next Dose Date (if applicable)</label>
                            <input type="date" id="next_dose_date" name="next_dose_date">
                        </div>
                        
                        <div class="form-group full-width">
                            <label for="diagnosis">Diagnosis/Notes</label>
                            <textarea id="diagnosis" name="diagnosis" rows="4"></textarea>
                        </div>
                    </div>
                    
                    <div class="form-buttons">
                        <a href="nurse_patients.php" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Set the current datetime as default for administered_date
        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const formattedDate = now.toISOString().slice(0, 16); // Format: YYYY-MM-DDTHH:MM
            document.getElementById('administered_date').value = formattedDate;
            
            // Update the next dose date based on vaccine selection
            document.getElementById('vaccine_id').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const dosesRequired = selectedOption.getAttribute('data-doses');
                
                // If doses required is greater than 1, suggest a next dose date (30 days from now)
                if (dosesRequired > 1 && parseInt(document.getElementById('dose_number').value) < parseInt(dosesRequired)) {
                    const nextDoseDate = new Date();
                    nextDoseDate.setDate(nextDoseDate.getDate() + 30); // Default to 30 days later
                    document.getElementById('next_dose_date').value = nextDoseDate.toISOString().slice(0, 10);
                } else {
                    document.getElementById('next_dose_date').value = '';
                }
            });
            
            // Update dose number limits based on vaccine
            document.getElementById('vaccine_id').addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                if (selectedOption.value) {
                    const dosesRequired = parseInt(selectedOption.getAttribute('data-doses')) || 1;
                    const doseInput = document.getElementById('dose_number');
                    doseInput.max = dosesRequired;
                    doseInput.min = 1;
                    
                    // If current value exceeds max, reset to 1
                    if (parseInt(doseInput.value) > dosesRequired) {
                        doseInput.value = 1;
                    }
                }
            });
        });
    </script>
</body>
</html>
