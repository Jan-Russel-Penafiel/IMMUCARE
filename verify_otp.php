<?php
session_start();
require 'config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$error = '';
$success = '';

// Check if user has initiated login
if (!isset($_SESSION['temp_user_id']) || !isset($_SESSION['temp_user_email'])) {
    header('Location: login.php');
    exit;
}

// Process OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = filter_var($_POST['otp'], FILTER_SANITIZE_NUMBER_INT);
    
    if (empty($otp)) {
        $error = 'Please enter the OTP';
    } else {
        // Connect to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
        
        // Check if OTP is valid and not expired
        $user_id = $_SESSION['temp_user_id'];
        $stmt = $conn->prepare("SELECT id, name, email, user_type, otp, otp_expiry FROM users WHERE id = ? AND otp = ?");
        $stmt->bind_param("is", $user_id, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Check if OTP is expired
            $current_time = date('Y-m-d H:i:s');
            if ($current_time <= $user['otp_expiry']) {
                // Clear OTP
                $stmt = $conn->prepare("UPDATE users SET otp = NULL, otp_expiry = NULL WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                
                // Set session variables for logged in user
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                
                // Remove temporary session variables
                unset($_SESSION['temp_user_id']);
                unset($_SESSION['temp_user_email']);
                unset($_SESSION['temp_user_name']);
                unset($_SESSION['temp_user_type']);
                
                // Redirect to dashboard
                header('Location: dashboard.php');
                exit;
            } else {
                $error = 'OTP has expired. Please request a new one.';
            }
        } else {
            $error = 'Invalid OTP. Please try again.';
        }
        
        $stmt->close();
        $conn->close();
    }
}

// Process resend OTP request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resend_otp'])) {
    // Connect to database
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Generate new OTP
    $otp = rand(100000, 999999);
    $otp_expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $user_id = $_SESSION['temp_user_id'];
    
    // Update OTP in database
    $stmt = $conn->prepare("UPDATE users SET otp = ?, otp_expiry = ? WHERE id = ?");
    $stmt->bind_param("ssi", $otp, $otp_expiry, $user_id);
    $stmt->execute();
    
    // Send OTP via email
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
        $mail->addAddress($_SESSION['temp_user_email'], $_SESSION['temp_user_name']);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your ImmuCare Login OTP';
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://yourwebsite.com/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">One-Time Password (OTP) Verification</h2>
                <p>Hello ' . $_SESSION['temp_user_name'] . ',</p>
                <p>Your new OTP for logging into ImmuCare is:</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 5px; margin: 20px 0;">
                    ' . $otp . '
                </div>
                <p>This OTP is valid for 10 minutes. If you did not request this OTP, please ignore this email.</p>
                <p>Thank you,<br>ImmuCare Team</p>
            </div>
        ';
        
        $mail->send();
        $success = 'A new OTP has been sent to your email address.';
    } catch (Exception $e) {
        $error = 'Failed to send OTP. Please try again.';
    }
    
    $stmt->close();
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP - ImmuCare</title>
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
            margin-bottom: 20px;
            color: var(--primary-color);
        }
        
        .auth-subtitle {
            text-align: center;
            margin-bottom: 30px;
            color: var(--light-text);
        }
        
        .otp-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            font-size: 24px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .otp-input:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(66, 133, 244, 0.2);
        }
        
        .auth-form button {
            width: 100%;
            padding: 12px;
            margin-top: 10px;
        }
        
        .resend-container {
            text-align: center;
            margin-top: 20px;
        }
        
        .resend-link {
            color: var(--primary-color);
            text-decoration: none;
            cursor: pointer;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .timer {
            display: inline-block;
            margin-left: 10px;
            color: var(--light-text);
            font-size: 0.9rem;
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
        <h1 class="auth-title">Verify Your Identity</h1>
        <p class="auth-subtitle">We've sent a 6-digit OTP to <strong><?php echo htmlspecialchars($_SESSION['temp_user_email']); ?></strong></p>
        
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
            <div class="otp-container">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" required>
                <input type="hidden" id="otp" name="otp" required>
            </div>
            <button type="submit" name="verify_otp" class="btn btn-primary">Verify OTP</button>
        </form>
        
        <div class="resend-container">
            <span>Didn't receive the OTP?</span>
            <form method="POST" action="" id="resendForm" style="display: inline;">
                <button type="submit" name="resend_otp" class="resend-link" id="resendBtn" disabled>Resend OTP</button>
            </form>
            <span class="timer" id="timer">in <span id="countdown">60</span>s</span>
        </div>
    </div>
    
    <script>
        // OTP input handling
        const otpInputs = document.querySelectorAll('.otp-input');
        const otpHiddenInput = document.getElementById('otp');
        
        otpInputs.forEach((input, index) => {
            // Auto focus next input
            input.addEventListener('input', (e) => {
                if (e.target.value.length === 1) {
                    if (index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }
                }
                
                // Update hidden input with complete OTP
                updateOtpValue();
            });
            
            // Handle backspace
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && e.target.value === '') {
                    if (index > 0) {
                        otpInputs[index - 1].focus();
                    }
                }
            });
            
            // Only allow numbers
            input.addEventListener('keypress', (e) => {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
            
            // Handle paste
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const pasteData = e.clipboardData.getData('text').trim();
                
                if (/^\d+$/.test(pasteData) && pasteData.length === otpInputs.length) {
                    for (let i = 0; i < otpInputs.length; i++) {
                        otpInputs[i].value = pasteData[i];
                    }
                    updateOtpValue();
                }
            });
        });
        
        function updateOtpValue() {
            let otp = '';
            otpInputs.forEach(input => {
                otp += input.value;
            });
            otpHiddenInput.value = otp;
        }
        
        // Countdown timer for resend OTP
        let countdown = 60;
        const countdownElement = document.getElementById('countdown');
        const resendBtn = document.getElementById('resendBtn');
        const timer = document.getElementById('timer');
        
        function updateCountdown() {
            countdownElement.textContent = countdown;
            
            if (countdown <= 0) {
                clearInterval(countdownInterval);
                resendBtn.disabled = false;
                timer.style.display = 'none';
            } else {
                countdown--;
            }
        }
        
        const countdownInterval = setInterval(updateCountdown, 1000);
    </script>
</body>
</html> 