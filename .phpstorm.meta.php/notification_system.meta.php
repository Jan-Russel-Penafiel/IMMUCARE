<?php
/**
 * PHPDoc stubs for notification_system.php
 * This file helps IDEs understand the NotificationSystem class better
 * 
 * @package ImmuCare
 */

/**
 * NotificationSystem Class
 * 
 * Handles all notification sending (SMS and Email) with logging
 */
class NotificationSystem {
    /**
     * Send an email notification
     * 
     * @param string $to Recipient email address
     * @param string $subject Email subject line
     * @param string $body Email body content
     * @return bool Success status
     */
    private function sendEmail($to, $subject, $body) {}
    
    /**
     * Generate appointment email template
     * 
     * @param string $patient_name Patient's full name
     * @param string $purpose Appointment purpose
     * @param string $date Formatted appointment date
     * @param string $time Formatted appointment time
     * @param string $location Appointment location
     * @return string Email template
     */
    private function getAppointmentEmailTemplate($patient_name, $purpose, $date, $time, $location) {}
    
    /**
     * Generate immunization email template
     * 
     * @param string $patient_name Patient's full name
     * @param string $vaccine_name Vaccine name
     * @param string $due_date Formatted due date
     * @return string Email template
     */
    private function getImmunizationEmailTemplate($patient_name, $vaccine_name, $due_date) {}
    
    /**
     * Generate custom email template
     * 
     * @param string $patient_name Patient's full name
     * @param string $title Email title
     * @param string $message Email message content
     * @return string Email template
     */
    private function getCustomEmailTemplate($patient_name, $title, $message) {}
    
    /**
     * Send appointment status notification
     * 
     * @param int $appointment_id Appointment ID
     * @param string $new_status New status value
     * @return array Notification results
     */
    public function sendAppointmentStatusNotification($appointment_id, $new_status) {}
    
    /**
     * Send welcome notification
     * 
     * @param int $user_id User ID
     * @return array Notification results
     */
    public function sendWelcomeNotification($user_id) {}
    
    /**
     * Send immunization record notification
     * 
     * @param int $immunization_id Immunization record ID
     * @return array Notification results
     */
    public function sendImmunizationRecordNotification($immunization_id) {}
    
    /**
     * Get unread notifications
     * 
     * @param int $user_id User ID
     * @param int $limit Maximum results
     * @return array Notifications
     */
    public function getUnreadNotifications($user_id, $limit = 10) {}
    
    /**
     * Mark notifications as read
     * 
     * @param int $user_id User ID
     * @param array $notification_ids Notification IDs
     * @return bool Success status
     */
    public function markNotificationsAsRead($user_id, $notification_ids) {}
    
    /**
     * Clean up old notifications
     * 
     * @param int $days Number of days to keep
     * @return bool Success status
     */
    public function cleanupOldNotifications($days = 30) {}
    
    /**
     * Format email with HTML template
     * 
     * @param string $message Message content
     * @return string Formatted HTML
     */
    private function formatEmailMessage($message) {}
    
    /**
     * Send patient account notification
     * 
     * @param int $user_id User ID
     * @param string $action Action type ('created', 'updated', 'deleted')
     * @param array $data Additional data
     * @return bool Success status
     */
    public function sendPatientAccountNotification($user_id, $action, $data = []) {}
}
