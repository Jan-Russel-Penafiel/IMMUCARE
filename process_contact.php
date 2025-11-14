<?php
session_start();
require 'config.php';
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.html#contact');
    exit;
}

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get and sanitize form data
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// Validate inputs
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    header('Location: index.html#contact?error=missing_fields');
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: index.html#contact?error=invalid_email');
    exit;
}

try {
    // Save contact message to database (optional)
    $stmt = $conn->prepare("INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
        $stmt->bind_param("ssss", $name, $email, $subject, $message);
        $stmt->execute();
        $stmt->close();
    }

    // Send email to admin
    $mail = new PHPMailer(true);
    
    // Server settings
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = SMTP_SECURE;
    $mail->Port = SMTP_PORT;
    
    // Recipients
    $mail->setFrom($email, $name);
    $mail->addAddress(SMTP_USER, APP_NAME . ' Support'); // Send to admin email
    $mail->addReplyTo($email, $name);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = '[' . APP_NAME . ' Contact] ' . $subject;
    $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <h2 style="color: #4285f4;">New Contact Message</h2>
            </div>
            
            <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <p><strong>From:</strong> ' . htmlspecialchars($name) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($email) . '</p>
                <p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
            </div>
            
            <div style="padding: 20px; background-color: #ffffff; border-left: 4px solid #4285f4;">
                <h3 style="color: #333; margin-top: 0;">Message:</h3>
                <p style="line-height: 1.6; color: #555;">' . nl2br(htmlspecialchars($message)) . '</p>
            </div>
            
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8;">
                <p style="font-size: 12px; color: #666; text-align: center;">
                    This message was sent from the ' . APP_NAME . ' website contact form.<br>
                    Sent on: ' . date('F j, Y \a\t g:i A') . '
                </p>
            </div>
        </div>
    ';
    
    // Plain text version
    $mail->AltBody = "New Contact Message from " . APP_NAME . "\n\n" .
                    "From: " . $name . "\n" .
                    "Email: " . $email . "\n" .
                    "Subject: " . $subject . "\n\n" .
                    "Message:\n" . $message . "\n\n" .
                    "Sent on: " . date('F j, Y \a\t g:i A');
    
    // Send the email
    if ($mail->send()) {
        // Send confirmation email to sender
        sendConfirmationEmail($email, $name, $subject, $conn);
        
        // Log the email if we have contact messages table
        if ($stmt) {
            $log_stmt = $conn->prepare("INSERT INTO email_logs (email_address, subject, message, status, sent_at, created_at) VALUES (?, ?, ?, 'sent', NOW(), NOW())");
            if ($log_stmt) {
                $email_subject = '[' . APP_NAME . ' Contact] ' . $subject;
                $log_stmt->bind_param("sss", SMTP_USER, $email_subject, $mail->Body);
                $log_stmt->execute();
                $log_stmt->close();
            }
        }
        
        header('Location: index.html#contact?success=1');
    } else {
        header('Location: index.html#contact?error=send_failed');
    }
    
} catch (Exception $e) {
    error_log("Contact form email failed: " . $e->getMessage());
    header('Location: index.html#contact?error=send_failed');
}

$conn->close();

function sendConfirmationEmail($email, $name, $subject, $conn) {
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
        $mail->setFrom(SMTP_USER, APP_NAME);
        $mail->addAddress($email, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Thank you for contacting ' . APP_NAME;
        $mail->Body = '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="' . APP_NAME . ' Logo" style="max-width: 150px;">
                </div>
                
                <h2 style="color: #4285f4;">Thank You for Contacting Us!</h2>
                
                <p>Hello ' . htmlspecialchars($name) . ',</p>
                
                <p>Thank you for reaching out to ' . APP_NAME . '. We have received your message and will get back to you as soon as possible.</p>
                
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Your Message Details:</strong></p>
                    <p><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
                    <p><strong>Submitted:</strong> ' . date('F j, Y \a\t g:i A') . '</p>
                </div>
                
                <p>Our team typically responds within 24-48 hours during business days. If your inquiry is urgent, please call us at ' . (defined('SUPPORT_PHONE') ? SUPPORT_PHONE : '+1-800-IMMUCARE') . '.</p>
                
                <div style="text-align: center; margin-top: 30px;">
                    <a href="' . APP_URL . '" style="background-color: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;">Visit Our Website</a>
                </div>
                
                <p style="margin-top: 30px;">Best regards,<br>' . APP_NAME . ' Team</p>
                
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8; font-size: 12px; color: #666; text-align: center;">
                    <p>This is an automated confirmation email. Please do not reply to this message.</p>
                </div>
            </div>
        ';
        
        $mail->send();
        
        // Log confirmation email
        $log_stmt = $conn->prepare("INSERT INTO email_logs (email_address, subject, message, status, sent_at, created_at) VALUES (?, ?, ?, 'sent', NOW(), NOW())");
        if ($log_stmt) {
            $confirmation_subject = 'Thank you for contacting ' . APP_NAME;
            $log_stmt->bind_param("sss", $email, $confirmation_subject, $mail->Body);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
    } catch (Exception $e) {
        error_log("Confirmation email failed: " . $e->getMessage());
    }
}
?>