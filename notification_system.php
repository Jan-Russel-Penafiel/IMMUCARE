<?php
/**
 * ImmuCare Notification System
 * 
 * This file handles sending notifications to patients via SMS and email
 * for appointment reminders, immunization alerts, and other system notifications.
 */

// First include config.php to get the existing constants
require_once 'config.php';
require_once 'vendor/autoload.php';

// Only define constants that aren't in config.php
if (!defined('SMTP_FROM_EMAIL')) {
    define('SMTP_FROM_EMAIL', 'noreply@immucare.com');
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'IMMUCARE');
}

require_once 'includes/sms_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class NotificationSystem {
    private $conn;
    private $max_retries = 3;
    private $retry_delay = 1; // seconds
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            throw new Exception("Connection failed: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8mb4");
    }
    
    /**
     * Destructor - close database connection
     */
    public function __destruct() {
        if ($this->conn) {
        $this->conn->close();
        }
    }
    
    /**
     * Send appointment reminder notifications
     * 
     * @return array Results of the notification process
     */
    public function sendAppointmentReminders() {
        // Get system setting for reminder days
        $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'appointment_reminder_days'");
        $stmt->execute();
        $result = $stmt->get_result();
        $reminder_days = ($result->num_rows > 0) ? (int)$result->fetch_assoc()['setting_value'] : 2;
        
        // Calculate the date for appointments to remind
        $reminder_date = date('Y-m-d', strtotime("+{$reminder_days} days"));
        
        // Get appointments scheduled for the reminder date
        $stmt = $this->conn->prepare("
            SELECT 
                a.id as appointment_id,
                a.appointment_date,
                a.purpose,
                a.location,
                p.id as patient_id,
                p.first_name,
                p.last_name,
                p.phone_number,
                u.email,
                u.id as user_id,
                v.name as vaccine_name
            FROM 
                appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                LEFT JOIN vaccines v ON a.vaccine_id = v.id
            WHERE 
                DATE(a.appointment_date) = ?
                AND a.status = 'confirmed'
        ");
        
        $stmt->bind_param("s", $reminder_date);
        $stmt->execute();
        $appointments = $stmt->get_result();
        
        $results = [
            'total' => 0,
            'email_sent' => 0,
            'email_failed' => 0,
            'sms_sent' => 0,
            'sms_failed' => 0
        ];
        
        // Check if SMS and email are enabled
        $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_enabled', 'email_enabled')");
        $stmt->execute();
        $settings_result = $stmt->get_result();
        
        $settings = [];
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $sms_enabled = isset($settings['sms_enabled']) && $settings['sms_enabled'] === 'true';
        $email_enabled = isset($settings['email_enabled']) && $settings['email_enabled'] === 'true';
        
        while ($appointment = $appointments->fetch_assoc()) {
            $results['total']++;
            
            // Format appointment date and time
            $appointment_datetime = new DateTime($appointment['appointment_date']);
            $formatted_date = $appointment_datetime->format('l, F j, Y');
            $formatted_time = $appointment_datetime->format('h:i A');
            
            // Prepare notification content
            $purpose = !empty($appointment['vaccine_name']) ? $appointment['vaccine_name'] . ' vaccination' : $appointment['purpose'];
            $location = $appointment['location'];
            $patient_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
            
            // Send email notification if enabled
            if ($email_enabled && !empty($appointment['email'])) {
                $subject = "Appointment Reminder: " . $formatted_date;
                $message = $this->getAppointmentEmailTemplate($patient_name, $purpose, $formatted_date, $formatted_time, $location);
                
                $email_result = $this->sendEmail($appointment['email'], $subject, $message);
                
                if ($email_result) {
                    $results['email_sent']++;
                } else {
                    $results['email_failed']++;
                }
            }
            
            // Send SMS notification if enabled
            if ($sms_enabled && !empty($appointment['phone_number'])) {
                $sms_message = "IMMUCARE REMINDER: You have an appointment for {$purpose} on {$formatted_date} at {$formatted_time}. Location: {$location}";
                
                $sms_result = $this->sendSMS(
                    $appointment['phone_number'], 
                    $sms_message, 
                    $appointment['patient_id'], 
                    $appointment['user_id'], 
                    $subject,
                    'appointment',
                    $appointment['appointment_id']
                );
                
                if ($sms_result) {
                    $results['sms_sent']++;
                } else {
                    $results['sms_failed']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Send immunization due notifications
     * 
     * @return array Results of the notification process
     */
    public function sendImmunizationDueNotifications() {
        // Get patients with immunizations due in the next 7 days
        $stmt = $this->conn->prepare("
            SELECT 
                i.id as immunization_id,
                i.next_dose_date,
                p.id as patient_id,
                p.first_name,
                p.last_name,
                p.phone_number,
                u.id as user_id,
                u.email,
                v.name as vaccine_name,
                v.id as vaccine_id
            FROM 
                immunizations i
                JOIN patients p ON i.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                JOIN vaccines v ON i.vaccine_id = v.id
            WHERE 
                i.next_dose_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                AND NOT EXISTS (
                    SELECT 1 FROM appointments a 
                    WHERE a.patient_id = p.id 
                    AND a.vaccine_id = v.id 
                    AND a.status IN ('requested', 'confirmed')
                )
        ");
        
        $stmt->execute();
        $due_immunizations = $stmt->get_result();
        
        $results = [
            'total' => 0,
            'email_sent' => 0,
            'email_failed' => 0,
            'sms_sent' => 0,
            'sms_failed' => 0
        ];
        
        // Check if SMS and email are enabled
        $stmt = $this->conn->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('sms_enabled', 'email_enabled')");
        $stmt->execute();
        $settings_result = $stmt->get_result();
        
        $settings = [];
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $sms_enabled = isset($settings['sms_enabled']) && $settings['sms_enabled'] === 'true';
        $email_enabled = isset($settings['email_enabled']) && $settings['email_enabled'] === 'true';
        
        while ($immunization = $due_immunizations->fetch_assoc()) {
            $results['total']++;
            
            // Format due date
            $due_date = new DateTime($immunization['next_dose_date']);
            $formatted_due_date = $due_date->format('l, F j, Y');
            
            // Prepare notification content
            $vaccine_name = $immunization['vaccine_name'];
            $patient_name = $immunization['first_name'] . ' ' . $immunization['last_name'];
            
            // Send email notification if enabled
            if ($email_enabled && !empty($immunization['email'])) {
                $subject = "Immunization Due: " . $vaccine_name;
                $message = $this->getImmunizationEmailTemplate($patient_name, $vaccine_name, $formatted_due_date);
                
                $email_result = $this->sendEmail($immunization['email'], $subject, $message);
                
                if ($email_result) {
                    $results['email_sent']++;
                } else {
                    $results['email_failed']++;
                }
            }
            
            // Send SMS notification if enabled
            if ($sms_enabled && !empty($immunization['phone_number'])) {
                $sms_message = "IMMUCARE ALERT: Your {$vaccine_name} vaccination is due on {$formatted_due_date}. Please schedule an appointment soon.";
                
                $sms_result = $this->sendSMS(
                    $immunization['phone_number'], 
                    $sms_message, 
                    $immunization['patient_id'], 
                    $immunization['user_id'], 
                    $subject,
                    'immunization',
                    $immunization['immunization_id']
                );
                
                if ($sms_result) {
                    $results['sms_sent']++;
                } else {
                    $results['sms_failed']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Send a custom notification to a specific user
     * 
     * @param int $user_id User ID to send notification to
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $channel Notification type (email, sms, or both)
     * @return bool Whether the notification was sent successfully
     */
    public function sendCustomNotification($user_id, $title, $message, $channel = 'both') {
        try {
            // Start transaction
            $this->conn->begin_transaction();
        
            // Create notification record first
            $notification_stmt = $this->conn->prepare("
                INSERT INTO notifications (
                    user_id, 
                    title, 
                    message, 
                    type,
                    is_read,
                    sent_at,
                    created_at
                ) VALUES (?, ?, ?, 'system', 0, NOW(), NOW())
            ");
            
            $notification_stmt->bind_param("iss", $user_id, $title, $message);
            
            if (!$notification_stmt->execute()) {
                throw new Exception("Failed to create notification record");
            }
            
            $notification_id = $this->conn->insert_id;
            
            // Get user and patient details
            $user_stmt = $this->conn->prepare("
                SELECT 
                    u.email as user_email,
                    u.phone as user_phone,
                    u.name as user_name,
                    p.id as patient_id,
                    p.phone_number as patient_phone,
                    CONCAT(p.first_name, ' ', p.last_name) as patient_name
                FROM users u
                LEFT JOIN patients p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();
        
            if (!$user) {
                throw new Exception("User not found");
            }
        
            $success = true;
            $name_to_use = $user['patient_name'] ?? $user['user_name'];
        
            // Send email if requested
            if ($channel === 'both' || $channel === 'email') {
                if (!empty($user['user_email'])) {
                    $email_message = $this->getCustomEmailTemplate($name_to_use, $title, $message);
                    if (!$this->sendEmail($user['user_email'], $title, $email_message)) {
                        $success = false;
                    }
                }
            }
        
            // Send SMS if requested
            if ($channel === 'both' || $channel === 'sms') {
                // Use patient phone number if available, otherwise use user phone
                $phone_to_use = !empty($user['patient_phone']) ? $user['patient_phone'] : $user['user_phone'];
                
                if (!empty($phone_to_use)) {
                    $sms_message = "IMMUCARE: $title - $message";
                    if (!$this->sendSMS(
                        $phone_to_use, 
                        $sms_message, 
                        $user['patient_id'] ?? null, 
                        $user_id, 
                        $notification_id, 
                        $title, 
                        'custom_notification',
                        null
                    )) {
                        $success = false;
                    }
                }
            }
            
            if ($success) {
                $this->conn->commit();
                return true;
            } else {
                $this->conn->rollback();
                return false;
            }
            
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Error in sendCustomNotification: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Send bulk notifications to multiple users
     * 
     * @param array $user_ids Array of user IDs to notify
     * @param string $title Notification title
     * @param string $message Notification message
     * @param string $type Notification type (email, sms, or both)
     * @return array Results of the notification process
     */
    public function sendBulkNotifications($user_ids, $title, $message, $type = 'both') {
        $results = [
            'total' => count($user_ids),
            'email_sent' => 0,
            'email_failed' => 0,
            'sms_sent' => 0,
            'sms_failed' => 0,
            'successful' => 0,
            'failed' => 0
        ];
        
        foreach ($user_ids as $user_id) {
            $notification_result = $this->sendCustomNotification($user_id, $title, $message, $type);
            
            if ($notification_result) {
                $results['successful']++;
                // Since sendCustomNotification returns true on success, 
                // we assume both email and SMS were attempted based on the type
                if ($type === 'email' || $type === 'both') {
                    $results['email_sent']++;
                }
                if ($type === 'sms' || $type === 'both') {
                    $results['sms_sent']++;
                }
            } else {
                $results['failed']++;
                // Count failures based on notification type
                if ($type === 'email' || $type === 'both') {
                    $results['email_failed']++;
                }
                if ($type === 'sms' || $type === 'both') {
                    $results['sms_failed']++;
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Send an SMS using the configured SMS provider and log it
     * 
     * @param string $phone_number Recipient phone number
     * @param string $message SMS message
     * @param int $patient_id Patient ID
     * @param int $user_id User ID for notification table
     * @param string $title Notification title
     * @param string $related_to Related entity type (e.g., appointment, immunization)
     * @param int $related_id Related entity ID
     * @return bool Whether the SMS was sent successfully
     */
    private function sendSMS($phone_number, $message, $patient_id = NULL, $user_id, $notification_id = NULL, $title = '', $related_to = 'general', $related_id = NULL) {
        try {
            // Sanitize message to remove special characters that iProg API might flag
            $message = str_replace("ñ", "n", $message);
            $message = str_replace("Ñ", "N", $message);
            
            // Replace other special characters
            $message = str_replace("á", "a", $message);
            $message = str_replace("é", "e", $message);
            $message = str_replace("í", "i", $message);
            $message = str_replace("ó", "o", $message);
            $message = str_replace("ú", "u", $message);
            $message = str_replace("Á", "A", $message);
            $message = str_replace("É", "E", $message);
            $message = str_replace("Í", "I", $message);
            $message = str_replace("Ó", "O", $message);
            $message = str_replace("Ú", "U", $message);
            
            // Use the working SMS helper function from includes/sms_helper.php
            $result = sendSMS($phone_number, $message);
            
            // Determine success based on the result
            $success = ($result['status'] === 'sent');
            $status = $success ? 'sent' : 'failed';
            $provider_response = isset($result['response']) ? json_encode($result['response']) : $result['message'];
            
            if ($success) {
                // Log the successful SMS
                $log_stmt = $this->conn->prepare("
                    INSERT INTO sms_logs (
                        notification_id,
                        patient_id,
                        user_id,
                        phone_number,
                        message,
                        status,
                        provider_response,
                        related_to,
                        related_id,
                        sent_at,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 'sent', ?, ?, ?, NOW(), NOW())
                ");
                
                $log_stmt->bind_param(
                    "iiissssi",
                    $notification_id,
                    $patient_id,
                    $user_id,
                    $phone_number,
                    $message,
                    $provider_response,
                    $related_to,
                    $related_id
                );
                
                if (!$log_stmt->execute()) {
                    error_log("Failed to log SMS: " . $this->conn->error);
                }
                
                return true;
            } else {
                // Log the failed SMS attempt
                $log_stmt = $this->conn->prepare("
                    INSERT INTO sms_logs (
                        notification_id,
                        patient_id,
                        user_id,
                        phone_number,
                        message,
                        status,
                        provider_response,
                        related_to,
                        related_id,
                        sent_at,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, 'failed', ?, ?, ?, NOW(), NOW())
                ");
                
                $log_stmt->bind_param(
                    "iiissssi",
                    $notification_id,
                    $patient_id,
                    $user_id,
                    $phone_number,
                    $message,
                    $provider_response,
                    $related_to,
                    $related_id
                );
                
                if (!$log_stmt->execute()) {
                    error_log("Failed to log SMS: " . $this->conn->error);
                }
                
                return false;
            }
            
        } catch (Exception $e) {
            error_log("Error sending SMS: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send email notification
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject
     * @param string $body Email body (plain text)
     * @return bool Whether the email was sent successfully
     */
    private function sendEmail($to, $subject, $body) {
        try {
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            $mail->isHTML(false);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("Error sending email: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate appointment reminder email template
     * 
     * @param string $patient_name Patient's name
     * @param string $purpose Appointment purpose
     * @param string $date Appointment date
     * @param string $time Appointment time
     * @param string $location Appointment location
     * @return string Plain text email template
     */
    private function getAppointmentEmailTemplate($patient_name, $purpose, $date, $time, $location) {
        return "IMMUCARE: Appointment Reminder\n\n" .
               "Hello " . $patient_name . ",\n\n" .
               "This is a friendly reminder about your upcoming appointment:\n\n" .
               "Purpose: " . $purpose . "\n" .
               "Date: " . $date . "\n" .
               "Time: " . $time . "\n" .
               "Location: " . $location . "\n\n" .
               "If you need to reschedule or have any questions, please contact us as soon as possible.\n\n" .
               "Thank you,\n" .
               "IMMUCARE Team";
    }

    /**
     * Generate immunization due email template
     * 
     * @param string $patient_name Patient's name
     * @param string $vaccine_name Vaccine name
     * @param string $due_date Due date
     * @return string Plain text email template
     */
    private function getImmunizationEmailTemplate($patient_name, $vaccine_name, $due_date) {
        return "IMMUCARE: Immunization Due Notice\n\n" .
               "Hello " . $patient_name . ",\n\n" .
               "This is to inform you that you have an immunization due soon:\n\n" .
               "Vaccine: " . $vaccine_name . "\n" .
               "Due Date: " . $due_date . "\n\n" .
               "Please schedule an appointment at your earliest convenience. " .
               "You can schedule online through our website or by calling our office.\n\n" .
               "Staying up-to-date with vaccinations is an important part of maintaining your health.\n\n" .
               "Thank you,\n" .
               "IMMUCARE Team";
    }

    /**
     * Generate custom email template
     * 
     * @param string $patient_name Patient's name
     * @param string $title Email title
     * @param string $message Email message
     * @return string Plain text email template
     */
    private function getCustomEmailTemplate($patient_name, $title, $message) {
        return $title . "\n\n" .
               "Hello " . $patient_name . ",\n\n" .
               $message . "\n\n" .
               "Thank you,\n" .
               "IMMUCARE Team\n\n" .
               "Need help? Contact us:\n" .
               "Email: " . SUPPORT_EMAIL . "\n" .
               "Phone: " . SUPPORT_PHONE;
    }
    
    /**
     * Send appointment status update notification
     * 
     * @param int $appointment_id Appointment ID
     * @param string $new_status New appointment status
     * @return array Results of the notification process
     */
    public function sendAppointmentStatusNotification($appointment_id, $new_status) {
        // Get appointment details
        $stmt = $this->conn->prepare("
            SELECT 
                a.appointment_date,
                a.purpose,
                a.location,
                p.id as patient_id,
                p.first_name,
                p.last_name,
                p.phone_number,
                u.id as user_id,
                u.email,
                v.name as vaccine_name
            FROM 
                appointments a
                JOIN patients p ON a.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                LEFT JOIN vaccines v ON a.vaccine_id = v.id
            WHERE 
                a.id = ?
        ");
        
        $stmt->bind_param("i", $appointment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['error' => 'Appointment not found'];
        }
        
        $appointment = $result->fetch_assoc();
        $patient_name = $appointment['first_name'] . ' ' . $appointment['last_name'];
        $purpose = !empty($appointment['vaccine_name']) ? $appointment['vaccine_name'] . ' vaccination' : $appointment['purpose'];
        
        // Format appointment date
        $appointment_datetime = new DateTime($appointment['appointment_date']);
        $formatted_date = $appointment_datetime->format('l, F j, Y');
        $formatted_time = $appointment_datetime->format('h:i A');
        
        // Prepare status message
        $status_messages = [
            'confirmed' => 'has been confirmed',
            'completed' => 'has been marked as completed',
            'cancelled' => 'has been cancelled',
            'no_show' => 'has been marked as no-show'
        ];
        
        $status_message = isset($status_messages[$new_status]) ? $status_messages[$new_status] : 'has been updated';
        
        // Send notifications
        $results = [
            'email_sent' => false,
            'sms_sent' => false
        ];
        
        // Email notification
        $subject = "Appointment Status Update";
        $email_message = "Your appointment for {$purpose} on {$formatted_date} at {$formatted_time} {$status_message}.";
        
        if (!empty($appointment['email'])) {
            $email_result = $this->sendEmail(
                $appointment['email'],
                $subject,
                $email_message
            );
            
            if ($email_result) {
                $results['email_sent'] = true;
            }
        }
        
        // SMS notification
        if (!empty($appointment['phone_number'])) {
            $sms_message = "IMMUCARE: Your appointment for {$purpose} on {$formatted_date} at {$formatted_time} {$status_message}.";
            $sms_result = $this->sendSMS(
                $appointment['phone_number'], 
                $sms_message, 
                $appointment['patient_id'], 
                $appointment['user_id'], 
                $subject,
                'appointment_status',
                $appointment_id
            );
            
            if ($sms_result) {
                $results['sms_sent'] = true;
            }
        }
        
        return $results;
    }
    
    /**
     * Send welcome notification for new user/patient registration
     * 
     * @param int $user_id User ID
     * @return array Results of the notification process
     */
    public function sendWelcomeNotification($user_id) {
        // Get user details
        $stmt = $this->conn->prepare("
            SELECT 
                u.id as user_id,
                u.email,
                u.user_type,
                COALESCE(p.first_name, u.name) as first_name,
                COALESCE(p.last_name, '') as last_name,
                p.phone_number
            FROM 
                users u
                LEFT JOIN patients p ON u.id = p.user_id
            WHERE 
                u.id = ?
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['error' => 'User not found'];
        }
        
        $user = $result->fetch_assoc();
        $full_name = trim($user['first_name'] . ' ' . $user['last_name']);
        
        $results = [
            'email_sent' => false,
            'sms_sent' => false
        ];
        
        // Email notification
        $subject = "Welcome to ImmuCare";
        $email_message = "Welcome to ImmuCare! Your account has been successfully created. ";
        
        if ($user['user_type'] === 'patient') {
            $email_message .= "You can now schedule appointments, view your immunization records, and receive important health notifications through our platform.";
        } else {
            $email_message .= "You can now access the system based on your assigned role and manage patient records, appointments, and immunizations.";
        }
        
        if (!empty($user['email'])) {
            $email_result = $this->sendEmail(
                $user['email'],
                $subject,
                $email_message
            );
            
            if ($email_result) {
                $results['email_sent'] = true;
            }
        }
        
        // SMS notification for patients only
        if ($user['user_type'] === 'patient' && !empty($user['phone_number'])) {
            $sms_message = "Welcome to ImmuCare! Your account has been created successfully. You can now manage your immunization records and appointments through our platform.";
            $sms_result = $this->sendSMS(
                $user['phone_number'], 
                $sms_message, 
                $user['user_id'], 
                $user['user_id'], 
                $subject,
                'welcome'
            );
            
            if ($sms_result) {
                $results['sms_sent'] = true;
            }
        }
        
        return $results;
    }
    
    /**
     * Send notification for new immunization record
     * 
     * @param int $immunization_id Immunization record ID
     * @return array Results of the notification process
     */
    public function sendImmunizationRecordNotification($immunization_id) {
        // Get immunization details
        $stmt = $this->conn->prepare("
            SELECT 
                i.administered_date,
                i.next_dose_date,
                i.dose_number,
                i.location,
                p.id as patient_id,
                p.first_name,
                p.last_name,
                p.phone_number,
                u.id as user_id,
                u.email,
                v.name as vaccine_name,
                v.doses_required
            FROM 
                immunizations i
                JOIN patients p ON i.patient_id = p.id
                JOIN users u ON p.user_id = u.id
                JOIN vaccines v ON i.vaccine_id = v.id
            WHERE 
                i.id = ?
        ");
        
        $stmt->bind_param("i", $immunization_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return ['error' => 'Immunization record not found'];
        }
        
        $immunization = $result->fetch_assoc();
        $patient_name = $immunization['first_name'] . ' ' . $immunization['last_name'];
        
        // Format dates
        $administered_date = new DateTime($immunization['administered_date']);
        $formatted_admin_date = $administered_date->format('l, F j, Y');
        
        $results = [
            'email_sent' => false,
            'sms_sent' => false
        ];
        
        // Prepare notification message
        $dose_info = ($immunization['doses_required'] > 1) 
            ? " (Dose {$immunization['dose_number']} of {$immunization['doses_required']})"
            : "";
            
        $next_dose_info = "";
        if (!empty($immunization['next_dose_date'])) {
            $next_dose_date = new DateTime($immunization['next_dose_date']);
            $next_dose_info = "\n\nYour next dose is scheduled for " . $next_dose_date->format('l, F j, Y') . ".";
        }
        
        // Email notification
        $subject = "Immunization Record Updated";
        $email_message = "Your immunization record has been updated. {$immunization['vaccine_name']}{$dose_info} was administered on {$formatted_admin_date} at {$immunization['location']}.{$next_dose_info}";
        
        if (!empty($immunization['email'])) {
            $email_result = $this->sendEmail(
                $immunization['email'],
                $subject,
                $email_message
            );
            
            if ($email_result) {
                $results['email_sent'] = true;
            }
        }
        
        // Send SMS notification
        if (!empty($immunization['phone_number'])) {
            $sms_message = "IMMUCARE: {$immunization['vaccine_name']}{$dose_info} was administered on {$formatted_admin_date}.";
            if (!empty($immunization['next_dose_date'])) {
                $sms_message .= " Next dose: " . $next_dose_date->format('M j, Y');
            }
            
            $sms_result = $this->sendSMS(
                $immunization['phone_number'], 
                $sms_message, 
                $immunization['patient_id'], 
                $immunization['user_id'], 
                $subject,
                'immunization_record',
                $immunization_id
            );
            
            if ($sms_result) {
                $results['sms_sent'] = true;
            }
        }
        
        return $results;
    }
    
    /**
     * Get unread notifications for a user
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notifications
     */
    public function getUnreadNotifications($user_id, $limit = 10) {
        $stmt = $this->conn->prepare("
            SELECT 
                id,
                title,
                message,
                type,
                sent_at
            FROM 
                notifications
            WHERE 
                user_id = ? 
                AND is_read = 0
            ORDER BY 
                sent_at DESC
            LIMIT ?
        ");
        
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        return $notifications;
    }
    
    /**
     * Mark notifications as read
     * 
     * @param int $user_id User ID
     * @param array $notification_ids Array of notification IDs to mark as read
     * @return bool Whether the operation was successful
     */
    public function markNotificationsAsRead($user_id, $notification_ids) {
        if (empty($notification_ids)) {
            return true;
        }
        
        $placeholders = str_repeat('?,', count($notification_ids) - 1) . '?';
        $types = str_repeat('i', count($notification_ids) + 1);
        
        $stmt = $this->conn->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE user_id = ? 
            AND id IN ($placeholders)
        ");
        
        $params = array_merge([$user_id], $notification_ids);
        $stmt->bind_param($types, ...$params);
        
        return $stmt->execute();
    }
    
    /**
     * Delete old notifications
     * 
     * @param int $days Number of days to keep notifications
     * @return bool Whether the operation was successful
     */
    public function cleanupOldNotifications($days = 30) {
        $stmt = $this->conn->prepare("
            DELETE FROM notifications 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->bind_param("i", $days);
        return $stmt->execute();
    }

    private function formatEmailMessage($message) {
        return '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="' . APP_URL . '/images/logo.svg" alt="' . APP_NAME . ' Logo" style="max-width: 150px;">
            </div>
            <div style="line-height: 1.6; color: #24292e;">
                ' . nl2br(htmlspecialchars($message)) . '
            </div>
            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8; font-size: 0.9em; color: #6a737d;">
                <p>This is an automated message from ' . APP_NAME . '. Please do not reply to this email.</p>
            </div>
        </div>';
    }

    /**
     * Send notification for patient account-related actions
     * 
     * @param int $user_id User ID
     * @param string $action Action type ('created', 'linked', 'updated', 'deleted')
     * @param array $data Additional data for the notification
     * @return bool Whether the notification was sent successfully
     */
    public function sendPatientAccountNotification($user_id, $action, $data = []) {
        try {
            // Get user and patient details
            $user_stmt = $this->conn->prepare("
                SELECT 
                    u.email as user_email,
                    u.phone as user_phone,
                    u.name as user_name,
                    u.user_type,
                    p.id as patient_id,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    p.date_of_birth,
                    p.gender,
                    p.phone_number as patient_phone,
                    p.purok,
                    p.city,
                    p.province
                FROM users u
                LEFT JOIN patients p ON u.id = p.user_id
                WHERE u.id = ?
            ");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user = $user_stmt->get_result()->fetch_assoc();

            if (!$user) {
                throw new Exception("User not found");
            }

            switch ($action) {
                case 'created':
                    $is_linking = isset($data['is_linking']) && $data['is_linking'];
                    $patient_details = isset($data['patient_details']) ? $data['patient_details'] : null;
                    
                    // Send account credentials via email only
                    if (!$is_linking && isset($data['password'])) {
                        $account_title = "IMMUCARE: Account Created Successfully";
                        $account_message = "Dear " . $user['user_name'] . ",\n\n" .
                                        "Your IMMUCARE account has been successfully created.\n\n" .
                                        "Account Details:\n" .
                                        "Email: " . $user['user_email'] . "\n" .
                                        "Phone: " . $user['user_phone'] . "\n" .
                                        "Password: " . $data['password'] . "\n\n" .
                                        "For security reasons, please change your password after logging in.\n\n" .
                                        "Need help? Contact us:\n" .
                                        "Phone: " . SUPPORT_PHONE . "\n" .
                                        "Email: " . SUPPORT_EMAIL . "\n\n" .
                                        "Best regards,\n" .
                                        "IMMUCARE Team";
                        
                        // Send account credentials via email only
                        $this->sendCustomNotification($user_id, $account_title, $account_message, 'email');
                    }

                    // Send patient profile information via both SMS and email
                    if ($patient_details) {
                        $profile_title = "IMMUCARE: Patient Profile Created";
                        $profile_message = "Dear " . $patient_details['first_name'] . ",\n\n" .
                                        "Your patient profile has been created in IMMUCARE.\n\n" .
                                        "Profile Details:\n" .
                                        "Name: " . $patient_details['first_name'] . 
                                        ($patient_details['middle_name'] ? " " . $patient_details['middle_name'] . " " : " ") . 
                                        $patient_details['last_name'] . "\n" .
                                        "Birth Date: " . date('F j, Y', strtotime($patient_details['date_of_birth'])) . "\n" .
                                        "Gender: " . ucfirst($patient_details['gender']) . "\n" .
                                        "Contact: " . $patient_details['phone_number'] . "\n" .
                                        "Address: Purok " . $patient_details['purok'] . ", " . 
                                        $patient_details['city'] . ", " . $patient_details['province'] . "\n";

                        if (!empty($patient_details['medical_history'])) {
                            $profile_message .= "\nMedical History:\n" . $patient_details['medical_history'] . "\n";
                        }
                        if (!empty($patient_details['allergies'])) {
                            $profile_message .= "\nAllergies:\n" . $patient_details['allergies'] . "\n";
                        }

                        $profile_message .= "\nYou can now:\n" .
                                        "1. View immunization records\n" .
                                        "2. Schedule vaccinations\n" .
                                        "3. Receive health notifications\n" .
                                        "4. Update your information\n\n" .
                                        "Best regards,\n" .
                                        "IMMUCARE Team";

                        // Send patient profile information via both SMS and email
                        $this->sendCustomNotification($user_id, $profile_title, $profile_message, 'both');
                    }
                    return true;

                case 'updated':
                    $title = "IMMUCARE: Account Updated";
                    $message = "Dear " . $user['user_name'] . ",\n\n" .
                             "Your IMMUCARE account has been updated.\n\n" .
                             "Update Details:\n" .
                             "Account Status: " . ($data['is_active'] ? "Active" : "Inactive") . "\n" .
                             (!empty($data['password_updated']) ? "Password: Updated\n" : "") . "\n" .
                             "If you did not request these changes, please contact us immediately:\n" .
                             "Phone: " . SUPPORT_PHONE . "\n" .
                             "Email: " . SUPPORT_EMAIL . "\n\n" .
                             "Best regards,\n" .
                             "IMMUCARE Team";
                    return $this->sendCustomNotification($user_id, $title, $message, 'both');

                case 'deleted':
                    $title = "IMMUCARE: Account Deleted";
                    $message = "Dear " . $user['user_name'] . ",\n\n" .
                             "Your IMMUCARE account and patient profile have been deleted.\n\n" .
                             "Account Details:\n" .
                             "Patient ID: " . $user['patient_id'] . "\n" .
                             "Name: " . $user['first_name'] . ' ' . $user['last_name'] . "\n" .
                             "Email: " . $user['user_email'] . "\n\n" .
                             "This means:\n" .
                             "1. Patient records removed\n" .
                             "2. User account deactivated\n" .
                             "3. Appointments cancelled\n" .
                             "4. Vaccination reminders stopped\n\n" .
                             "If this was done in error, contact us:\n" .
                             "Phone: " . SUPPORT_PHONE . "\n" .
                             "Email: " . SUPPORT_EMAIL . "\n\n" .
                             "Best regards,\n" .
                             "IMMUCARE Team";
                    
                    // Send deletion notification via email only
                    return $this->sendCustomNotification($user_id, $title, $message, 'email');
            }

            return true;

        } catch (Exception $e) {
            error_log("Error in sendPatientAccountNotification: " . $e->getMessage());
            throw $e;
        }
    }
}

// Usage example:
/*
$notification = new NotificationSystem();

// Send appointment reminders
$appointment_results = $notification->sendAppointmentReminders();
print_r($appointment_results);

// Send immunization due notifications
$immunization_results = $notification->sendImmunizationDueNotifications();
print_r($immunization_results);

// Send custom notification to a specific user
$custom_result = $notification->sendCustomNotification(1, 'Important Update', 'Your health record has been updated.');
print_r($custom_result);

// Send bulk notifications
$bulk_results = $notification->sendBulkNotifications([1, 2, 3], 'System Maintenance', 'The system will be down for maintenance on Saturday.');
print_r($bulk_results);
*/ 