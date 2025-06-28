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
        
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, email, name, user_type FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
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
                
                // Redirect to OTP verification page
                header('Location: verify_otp.php');
                exit;
            } else {
                $error = 'Failed to send OTP. Please try again.';
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
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .auth-container {
            max-width: 450px;
            margin: 80px auto;
            padding: 30px;
            background-color: var(--bg-white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }
        
        .auth-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo img {
            height: 60px;
        }
        
        .auth-title {
            font-size: 1.8rem;
            text-align: center;
            margin-bottom: 30px;
            color: var(--primary-color);
        }
        
        .auth-subtitle {
            text-align: center;
            margin-bottom: 30px;
            color: var(--light-text);
        }
        
        .auth-form .form-group {
            margin-bottom: 20px;
        }
        
        .auth-form .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .auth-form .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .auth-form .form-group input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        
        .auth-form button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
        }
        
        .auth-links {
            margin-top: 20px;
            text-align: center;
        }
        
        .auth-links a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .alert-error {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <img src="images/logo.svg" alt="ImmuCare Logo">
        </div>
        <h1 class="auth-title">Login to ImmuCare</h1>
        <p class="auth-subtitle">Enter your email to receive a one-time password</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form class="auth-form" method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit" name="login" class="btn btn-primary">Send OTP</button>
        </form>
        
        <div class="auth-links">
            <a href="register.php">Don't have an account? Register</a>
        </div>
    </div>
    
    <script>
        // Add any client-side validation if needed
        document.querySelector('.auth-form').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address');
            }
        });
    </script>
</body>
</html> 