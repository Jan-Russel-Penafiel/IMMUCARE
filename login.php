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

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } else {
        // Connect to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Check if user exists and get all necessary user data
        $stmt = $conn->prepare("SELECT id, email, name, user_type, phone, is_active, role_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if user is active
            if ($user['is_active'] != 1) {
                $error = 'Your account is inactive. Please contact an administrator.';
            }
            else {
                // Generate OTP
                $otp = rand(100000, 999999);
                $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                
                // Store OTP in database
                $stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE id = ?");
                $stmt->bind_param("ssi", $otp, $otp_expiry, $user['id']);
                $stmt->execute();
                
                // Send OTP via email
                if (sendOTP($user['email'], $user['name'], $otp)) {
                    // Store user data in session
                    $_SESSION['temp_user_id'] = $user['id'];
                    $_SESSION['temp_user_email'] = $user['email'];
                    $_SESSION['temp_user_name'] = $user['name'];
                    $_SESSION['temp_user_type'] = $user['user_type'];
                    $_SESSION['temp_user_role_id'] = $user['role_id'];
                    $_SESSION['temp_user_phone'] = $user['phone'];
                    
                    // Redirect to OTP verification page
                    header('Location: verify_otp.php');
                    exit;
                } else {
                    $error = 'Failed to send OTP. Please try again.';
                }
            }
        } else {
            $error = 'Email not found. Please register first.';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Function to send OTP via email
function sendOTP($email, $name, $otp) {
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
        $mail->Subject = 'Your ImmuCare Login OTP';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://yourwebsite.com/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">One-Time Password (OTP) Verification</h2>
                <p>Hello ' . $name . ',</p>
                <p>Your OTP for logging into ImmuCare is:</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">
                    ' . $otp . '
                </div>
                <p>This OTP is valid for 10 minutes. If you did not request this OTP, please ignore this email.</p>
                <p>Thank you,<br>ImmuCare Team</p>
            </div>
        ';
        
        $mail->send();
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
    <title>Login - ImmuCare</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .text-muted, .small, small {
            font-weight: 500;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-weight: 700;
        }
        
        .auth-container {
            max-width: 450px;
            width: 100%;
            padding: 2rem;
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
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
        
        /* Mobile optimizations */
        @media (max-width: 576px) {
            .auth-container {
                padding: 1.5rem;
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
            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                <div class="auth-container">
                    <div class="auth-logo">
                        <img src="images/logo.svg" alt="ImmuCare Logo">
                    </div>
                    <h1 class="auth-title">Login to ImmuCare</h1>
                    <p class="auth-subtitle">Enter your email to receive a one-time password</p>
                    
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
                        <div class="mb-4">
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100">Get OTP</button>
                    </form>
                    
                    <div class="auth-links">
                        <div class="row gx-2 gy-2 mt-3">
                            <div class="col-6 text-center">
                                <a href="login_password.php" class="btn btn-outline-primary btn-sm-mobile w-100"><i class="fas fa-key me-2"></i>Use Password</a>
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
        // Add client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
            }
        });
    </script>
</body>
</html> 