<?php
/**
 * ImmuCare Notification System
 * 
 * This file handles sending notifications to patients via SMS and email
 * for appointment reminders, immunization alerts, and other system notifications.
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

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
                
                $email_result = $this->sendEmail($appointment['email'], $patient_name, $subject, $message);
                
                if ($email_result) {
                    $results['email_sent']++;
                    
                    // Log email
                    $stmt = $this->conn->prepare("
                        INSERT INTO email_logs (user_id, email_address, subject, message, status, sent_at, created_at)
                        VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())
                    ");
                    $stmt->bind_param("isss", $appointment['user_id'], $appointment['email'], $subject, $message);
                    $stmt->execute();
                    
                    // Add notification to notifications table
                    $stmt = $this->conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, sent_at, created_at)
                        VALUES (?, ?, ?, 'email', NOW(), NOW())
                    ");
                    $notification_title = "Appointment Reminder";
                    $notification_message = "You have an appointment for {$purpose} on {$formatted_date} at {$formatted_time}.";
                    $stmt->bind_param("iss", $appointment['user_id'], $notification_title, $notification_message);
                    $stmt->execute();
                } else {
                    $results['email_failed']++;
                }
            }
            
            // Send SMS notification if enabled
            if ($sms_enabled && !empty($appointment['phone_number'])) {
                $sms_message = "IMMUCARE REMINDER: You have an appointment for {$purpose} on {$formatted_date} at {$formatted_time}. Location: {$location}";
                
                $sms_result = $this->sendSMS($appointment['phone_number'], $sms_message);
                
                if ($sms_result) {
                    $results['sms_sent']++;
                    
                    // Log SMS
                    $stmt = $this->conn->prepare("
                        INSERT INTO sms_logs (patient_id, phone_number, message, status, sent_at, created_at)
                        VALUES (?, ?, ?, 'sent', NOW(), NOW())
                    ");
                    $stmt->bind_param("iss", $appointment['patient_id'], $appointment['phone_number'], $sms_message);
                    $stmt->execute();
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
                
                $email_result = $this->sendEmail($immunization['email'], $patient_name, $subject, $message);
                
                if ($email_result) {
                    $results['email_sent']++;
                    
                    // Log email
                    $stmt = $this->conn->prepare("
                        INSERT INTO email_logs (user_id, email_address, subject, message, status, sent_at, created_at)
                        VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())
                    ");
                    $stmt->bind_param("isss", $immunization['user_id'], $immunization['email'], $subject, $message);
                    $stmt->execute();
                    
                    // Add notification to notifications table
                    $stmt = $this->conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, sent_at, created_at)
                        VALUES (?, ?, ?, 'email', NOW(), NOW())
                    ");
                    $notification_title = "Immunization Due";
                    $notification_message = "Your {$vaccine_name} vaccination is due on {$formatted_due_date}.";
                    $stmt->bind_param("iss", $immunization['user_id'], $notification_title, $notification_message);
                    $stmt->execute();
                } else {
                    $results['email_failed']++;
                }
            }
            
            // Send SMS notification if enabled
            if ($sms_enabled && !empty($immunization['phone_number'])) {
                $sms_message = "IMMUCARE ALERT: Your {$vaccine_name} vaccination is due on {$formatted_due_date}. Please schedule an appointment soon.";
                
                $sms_result = $this->sendSMS($immunization['phone_number'], $sms_message);
                
                if ($sms_result) {
                    $results['sms_sent']++;
                    
                    // Log SMS
                    $stmt = $this->conn->prepare("
                        INSERT INTO sms_logs (patient_id, phone_number, message, status, sent_at, created_at)
                        VALUES (?, ?, ?, 'sent', NOW(), NOW())
                    ");
                    $stmt->bind_param("iss", $immunization['patient_id'], $immunization['phone_number'], $sms_message);
                    $stmt->execute();
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
            $email_result = $this->sendEmail($user['email'], $patient_name, $title, $this->getCustomEmailTemplate($patient_name, $title, $message));
            
            if ($email_result) {
                $results['email_sent'] = true;
                
                // Log email
                $stmt = $this->conn->prepare("
                    INSERT INTO email_logs (user_id, email_address, subject, message, status, sent_at, created_at)
                    VALUES (?, ?, ?, ?, 'sent', NOW(), NOW())
                ");
                $stmt->bind_param("isss", $user_id, $user['email'], $title, $message);
                $stmt->execute();
                
                // Add notification to notifications table
                $stmt = $this->conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, sent_at, created_at)
                    VALUES (?, ?, ?, 'email', NOW(), NOW())
                ");
                $stmt->bind_param("iss", $user_id, $title, $message);
                $stmt->execute();
            }
        }
        
        // Send SMS notification
        if (($type === 'sms' || $type === 'both') && !empty($user['phone_number'])) {
            $sms_message = "IMMUCARE: {$title} - {$message}";
            $sms_result = $this->sendSMS($user['phone_number'], $sms_message);
            
            if ($sms_result) {
                $results['sms_sent'] = true;
                
                // Log SMS
                $stmt = $this->conn->prepare("
                    INSERT INTO sms_logs (patient_id, phone_number, message, status, sent_at, created_at)
                    VALUES (?, ?, ?, 'sent', NOW(), NOW())
                ");
                $stmt->bind_param("iss", $user['patient_id'], $user['phone_number'], $sms_message);
                $stmt->execute();
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
     * Send an email using PHPMailer
     * 
     * @param string $to_email Recipient email
     * @param string $to_name Recipient name
     * @param string $subject Email subject
     * @param string $body Email body (HTML)
     * @return bool Whether the email was sent successfully
     */
    private function sendEmail($to_email, $to_name, $subject, $body) {
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
            
            $mail->send();
            return true;
        } catch (Exception $e) {
            // Log the error somewhere
            error_log("Email sending failed: " . $mail->ErrorInfo);
            return false;
        }
    }
    
    /**
     * Send an SMS using the configured SMS provider
     * 
     * @param string $phone_number Recipient phone number
     * @param string $message SMS message
     * @return bool Whether the SMS was sent successfully
     */
    private function sendSMS($phone_number, $message) {
        // Get SMS provider from settings
        $stmt = $this->conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'sms_provider'");
        $stmt->execute();
        $result = $stmt->get_result();
        $sms_provider = ($result->num_rows > 0) ? $result->fetch_assoc()['setting_value'] : 'philsms';
        
        // For demonstration purposes, we'll simulate a successful SMS
        // In a real application, you would integrate with an SMS gateway
        
        if ($sms_provider === 'philsms') {
         
            
           
            $api_key = 'YOUR_PHILSMS_API_KEY';
            $sender_id = 'IMMUCARE'; // Your registered sender ID
            
            $url = 'https://api.philsms.com/v1/send';
            $data = array(
                'apikey' => $api_key,
                'sender' => $sender_id,
                'recipient' => $phone_number,
                'message' => $message
            );
            
            $options = array(
                'http' => array(
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data)
                )
            );
            
            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);
            
            if ($result === FALSE) {
                error_log("PhilSMS sending failed");
                return false;
            }
            
            $response = json_decode($result, true);
            return isset($response['status']) && $response['status'] === 'success';
            
            return true;
        } else if ($sms_provider === 'twilio') {
            // Twilio integration as fallback
            // In a real application, you would use the Twilio SDK
            
            // Example Twilio integration code:
            /*
            $account_sid = 'YOUR_TWILIO_SID';
            $auth_token = 'YOUR_TWILIO_TOKEN';
            $twilio_number = 'YOUR_TWILIO_NUMBER';
            
            $client = new Twilio\Rest\Client($account_sid, $auth_token);
            $client->messages->create(
                $phone_number,
                [
                    'from' => $twilio_number,
                    'body' => $message
                ]
            );
            */
            
            // For now, just return success
            return true;
        } else {
            // Default fallback or other SMS provider integration
            return true;
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
            <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img src="https://yourwebsite.com/images/logo.svg" alt="ImmuCare Logo" style="max-width: 150px;">
                </div>
                <h2 style="color: #4285f4;">' . htmlspecialchars($title) . '</h2>
                <p>Hello ' . $patient_name . ',</p>
                <div style="background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;">
                    <p>' . nl2br(htmlspecialchars($message)) . '</p>
                </div>
                <p>Thank you,<br>ImmuCare Team</p>
            </div>
        ';
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