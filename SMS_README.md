# ImmuCare Enhanced SMS Notification System

This enhanced SMS notification system integrates the robust SMS functionality from the smart attendance system into the ImmuCare project. It provides reliable SMS notifications using the IPROG SMS API with comprehensive logging, error handling, and scheduling capabilities.

## Features

- **IPROG SMS Integration**: Uses IPROG SMS API for reliable message delivery
- **Enhanced Error Handling**: Comprehensive error logging and retry mechanisms  
- **Message Scheduling**: Support for scheduling SMS messages for future delivery
- **Database Logging**: Complete SMS history with delivery status tracking
- **Multiple Notification Types**: Support for appointment reminders, immunization alerts, custom notifications
- **Rate Limiting**: Built-in delays to prevent API rate limiting
- **Automatic Processing**: Cron job support for processing scheduled messages
- **Statistics and Reporting**: SMS usage statistics and delivery reports

## Installation

### 1. Database Migration

Run the migration script to set up the required database tables:

```bash
php sms_migration.php
```

This will:
- Create/update the `sms_logs` table
- Create/update the `system_settings` table  
- Create/update the `notifications` table
- Add default SMS configuration settings
- Create performance indexes

### 2. Configuration

Update your system settings in the `system_settings` table:

```sql
-- Enable SMS notifications
UPDATE system_settings SET setting_value = 'true' WHERE setting_key = 'sms_enabled';

-- Set your IPROG SMS API key
UPDATE system_settings SET setting_value = '1ef3b27ea753780a90cbdf07d027fb7b52791004' WHERE setting_key = 'sms_api_key';

-- Set SMS provider (should be 'iprog')
UPDATE system_settings SET setting_value = 'iprog' WHERE setting_key = 'sms_provider';
```

### 3. Set Up Cron Job

Add this to your crontab to process scheduled SMS messages every minute:

```bash
* * * * * php /path/to/your/mic_new/process_scheduled_sms.php
```

## Usage

### Using the NotificationSystem Class

```php
<?php
require_once 'notification_system.php';

// Initialize the notification system
$notification = new NotificationSystem();

// Send SMS to a patient
$result = $notification->sendPatientSMS(
    $patient_id = 1,
    $message = "Your appointment is tomorrow at 2 PM",
    $notification_type = 'appointment',
    $scheduled_at = null, // Send immediately
    $related_to = 'appointment',
    $related_id = 123
);

if ($result['success']) {
    echo "SMS sent successfully!";
} else {
    echo "Failed to send SMS: " . $result['message'];
}
?>
```

### Direct Function Usage

```php
<?php
require_once 'notification_system.php';

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Send SMS directly
$result = sendSMSNotificationToPatientEnhanced(
    $patient_id = 1,
    $message = "Test message",
    $conn,
    $notification_type = 'test',
    $scheduled_at = null,
    $user_id = null,
    $related_to = 'system',
    $related_id = 1
);

$conn->close();
?>
```

### Scheduling SMS Messages

```php
// Schedule SMS for future delivery
$scheduled_time = date('Y-m-d H:i:s', strtotime('+24 hours'));

$result = $notification->sendPatientSMS(
    $patient_id = 1,
    $message = "Reminder: Your appointment is tomorrow",
    $notification_type = 'reminder',
    $scheduled_at = $scheduled_time,
    $related_to = 'appointment',
    $related_id = 123
);
```

## SMS Message Types

The system supports various notification types:

- `appointment` - Appointment confirmations and reminders
- `immunization` - Vaccination due dates and records
- `reminder` - General reminders
- `welcome` - New patient/user welcome messages
- `system` - System notifications and alerts
- `custom` - Custom administrative messages

## Database Tables

### sms_logs

Stores all SMS message history and delivery status:

```sql
CREATE TABLE sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NULL,
    user_id INT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    notification_type VARCHAR(50) DEFAULT 'general',
    provider_response TEXT,
    reference_id VARCHAR(100) NULL,
    related_to VARCHAR(50) DEFAULT 'general',
    related_id INT NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### system_settings

Configuration settings for SMS and other system features:

```sql
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    setting_type ENUM('text', 'boolean', 'number', 'json') DEFAULT 'text',
    is_public BOOLEAN DEFAULT FALSE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

## API Methods

### NotificationSystem Class Methods

- `sendPatientSMS($patient_id, $message, $type, $scheduled_at, $related_to, $related_id)` - Send SMS to patient
- `sendAppointmentReminders()` - Send appointment reminders
- `sendImmunizationDueNotifications()` - Send immunization due alerts
- `sendCustomNotification($user_id, $title, $message, $channel)` - Send custom notification
- `processScheduledMessages()` - Process pending scheduled messages
- `getSMSLogs($patient_id, $limit, $offset)` - Get SMS history
- `getSMSStatistics($date_from, $date_to)` - Get SMS usage statistics

### Standalone Functions

- `sendSMSUsingIPROGEnhanced($phone, $message, $api_key)` - Direct IPROG SMS API call
- `sendSMSNotificationToPatientEnhanced($patient_id, $message, $conn, ...)` - Send SMS with database logging
- `getSMSConfigEnhanced($conn)` - Get SMS configuration from database
- `processScheduledSMSEnhanced($conn)` - Process scheduled messages

## Error Handling

The system includes comprehensive error handling:

- **Connection Errors**: Handles network and API connectivity issues
- **Invalid Phone Numbers**: Validates Philippine mobile number format
- **API Errors**: Logs and handles IPROG SMS API error responses
- **Database Errors**: Catches and logs database operation failures
- **Rate Limiting**: Includes delays to prevent API rate limiting
- **Retry Logic**: Automatic retry for failed messages with configurable limits

## Monitoring and Logging

### Log Files

All SMS operations are logged to PHP error logs with detailed information:

```
[SMS-CRON] 2024-01-15 10:30:15 - Starting scheduled SMS processing...
[SMS-CRON] 2024-01-15 10:30:15 - Found 5 pending SMS messages to process.
[SMS-CRON] 2024-01-15 10:30:16 - SMS ID 123 sent successfully.
[SMS-CRON] 2024-01-15 10:30:17 - Processing completed. Processed: 5, Sent: 4, Failed: 1
```

### SMS Statistics

Get delivery statistics:

```php
$stats = $notification->getSMSStatistics();
echo "Total SMS: " . $stats['total'];
echo "Sent: " . $stats['sent'];  
echo "Failed: " . $stats['failed'];
echo "Pending: " . $stats['pending'];
```

### SMS History

View SMS message history:

```php
$logs = $notification->getSMSLogs($patient_id = null, $limit = 50);
foreach ($logs as $log) {
    echo $log['phone_number'] . " - " . $log['status'] . " - " . $log['sent_at'];
}
```

## Phone Number Format

The system automatically handles Philippine mobile number formatting:

- Input: `09171234567` → Output: `639171234567`
- Input: `+639171234567` → Output: `639171234567`  
- Input: `639171234567` → Output: `639171234567`

## Security Features

- **Input Validation**: All phone numbers and messages are validated
- **SQL Injection Prevention**: All database queries use prepared statements
- **API Key Protection**: API keys are stored securely in database
- **Access Control**: Web access to cron scripts is prevented
- **Error Logging**: Sensitive information is not exposed in error messages

## Troubleshooting

### Common Issues

1. **SMS Not Sending**
   - Check if `sms_enabled` is set to `true` in system_settings
   - Verify IPROG SMS API key is correct
   - Check error logs for API response details

2. **Scheduled Messages Not Processing**
   - Ensure cron job is set up correctly
   - Check if `process_scheduled_sms.php` has execution permissions
   - Review cron job logs

3. **Database Errors**
   - Ensure all required tables exist (run migration script)
   - Check database user permissions
   - Verify table structure matches requirements

### Debug Mode

Enable debug logging by adding this to your PHP configuration:

```php
error_reporting(E_ALL);
log_errors = On
error_log = /path/to/your/error.log
```

## License

This enhanced SMS system is integrated into the ImmuCare project and inherits the same licensing terms as the main application.

## Support

For issues related to:
- **IPROG SMS API**: Contact IPROG Support
- **ImmuCare Integration**: Check project documentation
- **Database Issues**: Review migration scripts and table structures

## Changelog

### Version 1.0 (2024-01-15)
- Initial integration of enhanced SMS system from smart attendance project
- IPROG SMS API implementation
- Comprehensive database logging
- Message scheduling support
- Cron job processing
- Error handling and retry logic
- Statistics and reporting features