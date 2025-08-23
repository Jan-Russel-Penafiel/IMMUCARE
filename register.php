<?php
session_start();
require 'vendor/autoload.php';
require 'config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// Check if the user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    // Sanitize and validate input
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $last_name = trim($_POST['last_name']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $purok = trim($_POST['purok']);
    $city = trim($_POST['city']);
    $province = trim($_POST['province']);
    $postal_code = trim($_POST['postal_code']);
    $medical_history = trim($_POST['medical_history']);
    $allergies = trim($_POST['allergies']);
    
    // Validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($phone) || 
        empty($password) || empty($date_of_birth) || empty($gender) || 
        empty($purok) || empty($city) || empty($province)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        // Connect to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'An account with this email address already exists.';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Create user account first
            $stmt = $conn->prepare("INSERT INTO users (role_id, user_type, name, email, phone, password, is_active, created_at, updated_at) VALUES (4, 'patient', ?, ?, ?, ?, 1, NOW(), NOW())");
            $full_name = $first_name . ' ' . ($middle_name ? $middle_name . ' ' : '') . $last_name;
            $stmt->bind_param("ssss", $full_name, $email, $phone, $hashed_password);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Create patient profile
                $stmt = $conn->prepare("INSERT INTO patients (user_id, first_name, middle_name, last_name, date_of_birth, gender, purok, city, province, postal_code, phone_number, medical_history, allergies, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                $stmt->bind_param("issssssssssss", $user_id, $first_name, $middle_name, $last_name, $date_of_birth, $gender, $purok, $city, $province, $postal_code, $phone, $medical_history, $allergies);
                
                if ($stmt->execute()) {
                    $patient_id = $conn->insert_id;
                    
                    // Send welcome notification
                    sendWelcomeNotification($conn, $user_id, $email, $full_name, $password);
                    
                    // Send patient profile notification
                    sendPatientProfileNotification($conn, $user_id, $patient_id, $full_name, $date_of_birth, $gender, $phone, $purok, $city, $province, $medical_history, $allergies);
                    
                    $success = 'Registration successful! Please check your email for login credentials and account details.';
                } else {
                    // Delete user if patient creation fails
                    $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                    $delete_stmt->bind_param("i", $user_id);
                    $delete_stmt->execute();
                    $error = 'Registration failed. Please try again.';
                }
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Function to send welcome notification
function sendWelcomeNotification($conn, $user_id, $email, $name, $password) {
    // Create notification
    $message = "Welcome to ImmuCare!\n\nYour ImmuCare account has been created with the following credentials:\n- Email: {$email}\n- Password: {$password}\n\nPlease keep these credentials secure and change your password after your first login.\n\nFor assistance, contact our support team:\nPhone: " . SUPPORT_PHONE . "\nEmail: " . SUPPORT_EMAIL;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, sent_at, created_at, updated_at) VALUES (?, ?, ?, 'system', 0, NOW(), NOW(), NOW())");
    $title = 'Welcome to ImmuCare - Account Created';
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
    $notification_id = $conn->insert_id;
    
    // Send email
    sendWelcomeEmail($conn, $notification_id, $user_id, $email, $name, $password);
}

// Function to send patient profile notification
function sendPatientProfileNotification($conn, $user_id, $patient_id, $name, $date_of_birth, $gender, $phone, $purok, $city, $province, $medical_history, $allergies) {
    $message = "Your patient profile has been successfully created in the ImmuCare system.\n\nProfile Details:\n- Patient ID: {$patient_id}\n- Full Name: {$name}\n- Date of Birth: " . date('F j, Y', strtotime($date_of_birth)) . "\n- Gender: " . ucfirst($gender) . "\n- Contact: {$phone}\n- Address: {$purok}, {$city}, {$province}\n\nMedical Information:\n- Medical History: {$medical_history}\n- Allergies: {$allergies}\n\nYou can now:\n- View your immunization records\n- Schedule appointments\n- Receive vaccination reminders\n- Update your medical information\n\nPlease verify all information and contact us if any corrections are needed.\nFor support, reach us at " . SUPPORT_EMAIL . " or " . SUPPORT_PHONE;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, is_read, sent_at, created_at, updated_at) VALUES (?, ?, ?, 'system', 0, NOW(), NOW(), NOW())");
    $title = 'Patient Profile Created Successfully';
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();
}

// Function to send welcome email
function sendWelcomeEmail($conn, $notification_id, $user_id, $email, $name, $password) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_USER, 'ImmuCare');
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Welcome to ImmuCare - Account Created';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e1e4e8; border-radius: 8px; background-color: #ffffff;">
                <!-- Header with Logo -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px; height: auto;">
                </div>
                
                <!-- Title -->
                <h2 style="color: #4285f4; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: left;">Welcome to ImmuCare - Account Created</h2>
                
                <!-- Greeting -->
                <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px 0;">Hello ' . $name . ',</p>
                
                <!-- Message Content -->
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <div style="color: #333333; font-size: 16px; line-height: 1.6;">
                        Welcome to ImmuCare!<br /><br />
                        Your ImmuCare account has been created with the following credentials:<br />
                        - Email: ' . $email . '<br />
                        - Password: ' . $password . '<br /><br />
                        Please keep these credentials secure and change your password after your first login.<br /><br />
                        For assistance, contact our support team:<br />
                        Phone: ' . SUPPORT_PHONE . '<br />
                        Email: ' . SUPPORT_EMAIL . '
                    </div>
                </div>
                
                <!-- Footer -->
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8;">
                    <p style="color: #666666; font-size: 14px; margin: 0;">Thank you,<br>ImmuCare Team</p>
                    
                    <!-- Contact Info -->
                    <div style="margin-top: 20px; color: #666666; font-size: 12px;">
                        <p style="margin: 5px 0;">Need help? Contact us at ' . SUPPORT_EMAIL . '</p>
                        <p style="margin: 5px 0;">Phone: ' . SUPPORT_PHONE . '</p>
                    </div>
                </div>
            </div>
        ';
        
        $mail->send();
        
        // Log email
        $stmt = $conn->prepare("INSERT INTO email_logs (notification_id, user_id, email_address, subject, message, status, related_to, sent_at, created_at) VALUES (?, ?, ?, ?, ?, 'sent', 'general', NOW(), NOW())");
        $subject = 'Welcome to ImmuCare - Account Created';
        $message = $mail->Body;
        $stmt->bind_param("iisss", $notification_id, $user_id, $email, $subject, $message);
        $stmt->execute();
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ImmuCare</title>
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
            --shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            --border-radius: 12px;
        }
        
        * {
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
        }
        
        body {
            background-color: var(--bg-light);
            font-weight: 600;
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .text-muted, .small, small {
            font-weight: 500;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
        }
        
        .auth-container {
            max-width: 800px;
            width: 100%;
            padding: 2rem;
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin: 0 auto;
        }
        
        @media (min-width: 768px) {
            .auth-container {
                padding: 2.5rem;
            }
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .auth-logo img {
            height: 60px;
        }
        
        .auth-title {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .auth-subtitle {
            text-align: center;
            margin-bottom: 2rem;
            color: var(--light-text);
        }
        
        .form-control, .form-select {
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            border-radius: 8px;
            border: 1px solid #ddd;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }
        
        .btn {
            padding: 0.75rem 1rem;
            font-weight: 600;
            border-radius: 8px;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #3367d6;
            border-color: #3367d6;
        }
        
        .alert {
            padding: 0.6rem 0.75rem;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            border-radius: 8px;
        }
        
        .auth-links {
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .btn-outline-primary, .btn-outline-secondary {
            transition: all 0.3s ease;
            border-width: 2px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .btn-outline-primary:hover {
            background-color: rgba(66, 133, 244, 0.1);
            color: #3367d6;
            border-color: #3367d6;
        }
        
        .btn-outline-secondary:hover {
            background-color: rgba(108, 117, 125, 0.1);
            color: #555;
            border-color: #555;
        }
        
        .section-title {
            font-size: 1.1rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }
        
        .required {
            color: #dc3545;
        }
        
        /* Mobile optimizations */
        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
                margin: 0 1rem;
            }
            
            .auth-logo img {
                height: 50px;
            }
            
            .auth-title {
                font-size: 1.3rem;
            }
            
            .auth-subtitle {
                font-size: 0.9rem;
                margin-bottom: 1.5rem;
            }
            
            .btn-sm-mobile {
                font-size: 0.8rem;
                padding: 0.4rem 0.6rem;
            }
            
            .form-control {
                font-size: 0.9rem;
                padding: 0.6rem 0.8rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="auth-container">
                    <div class="auth-logo">
                        <img src="images/logo.svg" alt="ImmuCare Logo">
                    </div>
                    <h1 class="auth-title">Create Your ImmuCare Account</h1>
                    <p class="auth-subtitle">Join ImmuCare to manage your immunization records and appointments</p>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <!-- Personal Information Section -->
                        <h3 class="section-title">Personal Information</h3>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label">First Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="middle_name" class="form-label">Middle Name</label>
                                <input type="text" class="form-control" id="middle_name" name="middle_name" value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="last_name" class="form-label">Last Name <span class="required">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_of_birth" class="form-label">Date of Birth <span class="required">*</span></label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" required value="<?php echo isset($_POST['date_of_birth']) ? $_POST['date_of_birth'] : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Contact Information Section -->
                        <h3 class="section-title">Contact Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email Address <span class="required">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone Number <span class="required">*</span></label>
                                <input type="tel" class="form-control" id="phone" name="phone" required placeholder="e.g., 09123456789" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Address Information Section -->
                        <h3 class="section-title">Address Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="purok" class="form-label">Purok/Street <span class="required">*</span></label>
                                <input type="text" class="form-control" id="purok" name="purok" required value="<?php echo isset($_POST['purok']) ? htmlspecialchars($_POST['purok']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City/Municipality <span class="required">*</span></label>
                                <input type="text" class="form-control" id="city" name="city" required value="<?php echo isset($_POST['city']) ? htmlspecialchars($_POST['city']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="province" class="form-label">Province <span class="required">*</span></label>
                                <input type="text" class="form-control" id="province" name="province" required value="<?php echo isset($_POST['province']) ? htmlspecialchars($_POST['province']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="postal_code" class="form-label">Postal Code</label>
                                <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?php echo isset($_POST['postal_code']) ? htmlspecialchars($_POST['postal_code']) : ''; ?>">
                            </div>
                        </div>
                        
                        <!-- Medical Information Section -->
                        <h3 class="section-title">Medical Information</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="medical_history" class="form-label">Medical History</label>
                                <textarea class="form-control" id="medical_history" name="medical_history" rows="3" placeholder="Any relevant medical history, chronic conditions, etc."><?php echo isset($_POST['medical_history']) ? htmlspecialchars($_POST['medical_history']) : ''; ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="allergies" class="form-label">Allergies</label>
                                <textarea class="form-control" id="allergies" name="allergies" rows="3" placeholder="Any known allergies (medications, foods, etc.)"><?php echo isset($_POST['allergies']) ? htmlspecialchars($_POST['allergies']) : ''; ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Account Security Section -->
                        <h3 class="section-title">Account Security</h3>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password <span class="required">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required minlength="8">
                                <small class="text-muted">Minimum 8 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password <span class="required">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    I agree to the <a href="#" class="text-primary">Terms of Service</a> and <a href="#" class="text-primary">Privacy Policy</a> <span class="required">*</span>
                                </label>
                            </div>
                        </div>
                        
                        <button type="submit" name="register" class="btn btn-primary w-100 mb-3">Create Account</button>
                    </form>
                    
                    <div class="auth-links">
                        <div class="row gx-2 gy-2">
                            <div class="col-6 text-center">
                                <a href="login.php" class="btn btn-outline-primary btn-sm-mobile w-100"><i class="fas fa-sign-in-alt me-2"></i>Already have an account?</a>
                            </div>
                            <div class="col-6 text-center">
                                <a href="index.html" class="btn btn-outline-secondary btn-sm-mobile w-100"><i class="fas fa-home me-2"></i>Back to Home</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match');
                return false;
            }
            
            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long');
                return false;
            }
        });
        
        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('63')) {
                value = '0' + value.substring(2);
            }
            e.target.value = value;
        });
    </script>
</body>
</html> 