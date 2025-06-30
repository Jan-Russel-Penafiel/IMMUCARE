<?php
/**
 * ImmuCare Notification System
 * 
 * This file handles sending notifications to patients via SMS and email
 * for appointment reminders, immunization alerts, and other system notifications.
 */

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'includes/sms_helper.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class NotificationSystem {
    private $conn;
    
    /**
     * Constructor - initialize database connection
     */
    public function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }
    
    /**
     * Destructor - close database connection
     */
    public function __destruct() {
        $this->conn->close();
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
                
                $email_result = $this->sendEmail($appointment['email'], $patient_name, $subject, $message, $appointment['user_id']);
                
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
                $message = $this->getImmunizationDueEmailTemplate($patient_name, $vaccine_name, $formatted_due_date);
                
                $email_result = $this->sendEmail($immunization['email'], $patient_name, $subject, $message, $immunization['user_id']);
                
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
     * @param string $type Notification type (email, sms, or both)
     * @return array Results of the notification process
     */
    public function sendCustomNotification($user_id, $title, $message, $type = 'both') {
        $results = [
            'email_sent' => false,
            'sms_sent' => false
        ];
        
        // Get user and patient information
        $stmt = $this->conn->prepare("
            SELECT 
                u.id as user_id,
                u.email,
                u.name as user_name,
                p.id as patient_id,
                p.phone_number,
                p.first_name,
                p.last_name
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
        $patient_name = !empty($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : $user['user_name'];
        
        // Send email notification
        if (($type === 'email' || $type === 'both') && !empty($user['email'])) {
            $email_result = $this->sendEmail(
                $user['email'], 
                $patient_name, 
                $title, 
                $title === "Patient Profile Created" ? 
                    $this->getPatientProfileCreatedTemplate($patient_name) : 
                    $this->getCustomEmailTemplate($patient_name, $title, $message), 
                $user['user_id'],
                'custom_notification',
                null
            );
            
            if ($email_result) {
                $results['email_sent'] = true;
            }
        }
        
        // Send SMS notification
        if (($type === 'sms' || $type === 'both') && !empty($user['phone_number'])) {
            $sms_message = "IMMUCARE: {$title} - {$message}";
            $sms_result = $this->sendSMS(
                $user['phone_number'], 
                $sms_message, 
                $user['patient_id'], 
                $user['user_id'], 
                $title,
                'custom_notification',
                null
            );
            
            if ($sms_result) {
                $results['sms_sent'] = true;
            }
        }
        
        return $results;
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
            'sms_failed' => 0
        ];
        
        foreach ($user_ids as $user_id) {
            $notification_result = $this->sendCustomNotification($user_id, $title, $message, $type);
            
            if (isset($notification_result['email_sent']) && $notification_result['email_sent']) {
                $results['email_sent']++;
            } else if ($type === 'email' || $type === 'both') {
                $results['email_failed']++;
            }
            
            if (isset($notification_result['sms_sent']) && $notification_result['sms_sent']) {
                $results['sms_sent']++;
            } else if ($type === 'sms' || $type === 'both') {
                $results['sms_failed']++;
            }
        }
        
        return $results;
    }
    
    /**
     * Send an SMS using the configured SMS provider and log it
     * 
     * @param string $phone_number Recipient phone number
     * @param string $message SMS message
     * @param int $patient_id Patient ID for logging
     * @param int $user_id User ID for notification table
     * @param string $title Notification title
     * @param string $related_to Related entity type (e.g., appointment, immunization)
     * @param int $related_id Related entity ID
     * @return bool Whether the SMS was sent successfully
     */
    private function sendSMS($phone_number, $message, $patient_id = null, $user_id = null, $title = '', $related_to = 'general', $related_id = null) {
        // Call the global sendSMS function from sms_helper.php
        $sms_result = sendSMS($phone_number, $message);
        
        // Determine status based on result
        $status = ($sms_result['status'] === 'sent') ? 'sent' : 'failed';
        $provider_response = isset($sms_result['response']) ? json_encode($sms_result['response']) : $sms_result['message'];
        
        // Log the SMS attempt
        if ($patient_id) {
            $stmt = $this->conn->prepare("
                INSERT INTO sms_logs (
                    patient_id, 
                    phone_number, 
                    message, 
                    status, 
                    provider_response,
                    related_to,
                    related_id,
                    sent_at, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->bind_param("isssssi", 
                $patient_id, 
                $phone_number, 
                $message, 
                $status, 
                $provider_response,
                $related_to,
                $related_id
            );
            $stmt->execute();
        }
        
        // Add to notifications table if user_id is provided
        if ($user_id && $sms_result['status'] === 'sent') {
            $stmt = $this->conn->prepare("
                INSERT INTO notifications (
                    user_id, 
                    title, 
                    message, 
                    type, 
                    is_read,
                    sent_at, 
                    created_at
                ) VALUES (?, ?, ?, 'sms', 0, NOW(), NOW())
            ");
            
            $stmt->bind_param("iss", $user_id, $title, $message);
            $stmt->execute();
        }
        
        return ($sms_result['status'] === 'sent');
    }
    
    /**
     * Send an email using PHPMailer and log it
     * 
     * @param string $to_email Recipient email
     * @param string $to_name Recipient name
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @param int $user_id User ID for logging
     * @param string $related_to Related entity type (e.g., appointment, immunization)
     * @param int $related_id Related entity ID
     * @return bool Whether the email was sent successfully
     */
    private function sendEmail($to_email, $to_name, $subject, $body, $user_id = null, $related_to = 'general', $related_id = null) {
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
            $mail->addAddress($to_email, $to_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $result = $mail->send();
            
            // Log the email attempt
            if ($user_id) {
                $stmt = $this->conn->prepare("
                    INSERT INTO email_logs (
                        user_id, 
                        email_address, 
                        subject, 
                        message, 
                        status, 
                        provider_response,
                        related_to,
                        related_id,
                        sent_at, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                
                $status = $result ? 'sent' : 'failed';
                $provider_response = $result ? 'Email sent successfully' : $mail->ErrorInfo;
                $stmt->bind_param("issssssi", 
                    $user_id, 
                    $to_email, 
                    $subject, 
                    $body, 
                    $status, 
                    $provider_response,
                    $related_to,
                    $related_id
                );
                $stmt->execute();
                
                // Add to notifications table
                if ($result) {
                    $stmt = $this->conn->prepare("
                        INSERT INTO notifications (
                            user_id, 
                            title, 
                            message, 
                            type, 
                            is_read,
                            sent_at, 
                            created_at
                        ) VALUES (?, ?, ?, 'email', 0, NOW(), NOW())
                    ");
                    
                    $stmt->bind_param("iss", $user_id, $subject, $body);
                    $stmt->execute();
                }
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Get appointment reminder email template
     * 
     * @param string $patient_name Patient name
     * @param string $purpose Appointment purpose
     * @param string $date Formatted appointment date
     * @param string $time Formatted appointment time
     * @param string $location Appointment location
     * @return string HTML email template
     */
    private function getAppointmentEmailTemplate($patient_name, $purpose, $date, $time, $location) {
        return '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://yourwebsite.com/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">Appointment Reminder</h2>
                <p>Hello ' . $patient_name . ',</p>
                <p>This is a friendly reminder about your upcoming appointment:</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Purpose:</strong> ' . $purpose . '</p>
                    <p><strong>Date:</strong> ' . $date . '</p>
                    <p><strong>Time:</strong> ' . $time . '</p>
                    <p><strong>Location:</strong> ' . $location . '</p>
                </div>
                <p>If you need to reschedule or have any questions, please contact us as soon as possible.</p>
                <p>Thank you,<br>ImmuCare Team</p>
            </div>
        ';
    }
    
    /**
     * Get immunization due email template
     * 
     * @param string $patient_name Patient name
     * @param string $vaccine_name Vaccine name
     * @param string $due_date Formatted due date
     * @return string HTML email template
     */
    private function getImmunizationDueEmailTemplate($patient_name, $vaccine_name, $due_date) {
        return '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://yourwebsite.com/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">Immunization Due Notice</h2>
                <p>Hello ' . $patient_name . ',</p>
                <p>This is to inform you that you have an immunization due soon:</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p><strong>Vaccine:</strong> ' . $vaccine_name . '</p>
                    <p><strong>Due Date:</strong> ' . $due_date . '</p>
                </div>
                <p>Please schedule an appointment at your earliest convenience. You can schedule online through our website or by calling our office.</p>
                <p>Staying up-to-date with vaccinations is an important part of maintaining your health.</p>
                <p>Thank you,<br>ImmuCare Team</p>
            </div>
        ';
    }
    
    /**
     * Get custom notification email template
     * 
     * @param string $patient_name Patient name
     * @param string $title Notification title
     * @param string $message Notification message
     * @return string HTML email template
     */
    private function getCustomEmailTemplate($patient_name, $title, $message) {
        return '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e1e4e8; border-radius: 8px; background-color: #ffffff;">
                <!-- Header with Logo -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px; height: auto;">
                </div>
                
                <!-- Title -->
                <h2 style="color: #4285f4; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: left;">' . htmlspecialchars($title) . '</h2>
                
                <!-- Greeting -->
                <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px 0;">Hello ' . htmlspecialchars($patient_name) . ',</p>
                
                <!-- Message Content -->
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <div style="color: #333333; font-size: 16px; line-height: 1.6;">
                        ' . nl2br(htmlspecialchars($message)) . '
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
    }
    
    /**
     * Get patient profile creation email template
     * 
     * @param string $patient_name Patient's full name
     * @return string HTML email template
     */
    private function getPatientProfileCreatedTemplate($patient_name) {
        return '
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e1e4e8; border-radius: 8px; background-color: #ffffff;">
                <!-- Header with Logo -->
                <div style="text-align: center; margin-bottom: 30px;">
                    <img src="' . APP_URL . '/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px; height: auto;">
                </div>
                
                <!-- Title -->
                <h2 style="color: #4285f4; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: left;">Patient Profile Created</h2>
                
                <!-- Greeting -->
                <p style="color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px 0;">Hello ' . htmlspecialchars($patient_name) . ',</p>
                
                <!-- Message Content -->
                <div style="background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;">
                    <div style="color: #333333; font-size: 16px; line-height: 1.6;">
                        <p style="margin: 0 0 15px 0;">Your patient profile has been created successfully in the ImmuCare system. You now have access to the following features:</p>
                        <ul style="margin: 0 0 15px 0; padding-left: 20px;">
                            <li style="margin-bottom: 8px;">View and manage your immunization records</li>
                            <li style="margin-bottom: 8px;">Schedule and track appointments</li>
                            <li style="margin-bottom: 8px;">Receive important health notifications</li>
                            <li style="margin-bottom: 8px;">Update your medical information</li>
                        </ul>
                        <p style="margin: 0;">Please log in to your account to complete your profile setup and verify your information.</p>
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
                $patient_name,
                $subject,
                $this->getCustomEmailTemplate($patient_name, $subject, $email_message),
                $appointment['user_id']
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
                $full_name,
                $subject,
                $this->getCustomEmailTemplate($full_name, $subject, $email_message),
                $user['user_id']
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
                $patient_name,
                $subject,
                $this->getCustomEmailTemplate($patient_name, $subject, $email_message),
                $immunization['user_id']
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