# IProg SMS Integration Migration Guide

## Overview
This document outlines the migration from PhilSMS to IProg SMS API integration in the IMMUCARE system.

## Changes Made

### 1. Files Updated

#### a. `includes/sms_helper.php`
- Updated `sendSMS()` function to use IProg SMS API
- Changed endpoint from PhilSMS to IProg SMS
- Updated phone number formatting for IProg SMS API
- Changed authentication method (api_token instead of Bearer)
- Updated request payload format

#### b. `notification_system.php`
- Updated `sendSMS()` method in NotificationSystem class
- Changed API endpoint to `https://sms.iprogtech.com/api/v1/sms_messages`
- Updated authentication and request format
- Added fallback to config constants if database settings are not found

#### c. `admin_settings.php`
- Updated SMS provider settings form
- Changed from PhilSMS to IProg SMS in the interface
- Updated field names from `philsms_*` to `iprog_sms_*`
- Updated JavaScript to handle IProg settings

#### d. `config.php`
- Updated SMS configuration constants
- Set API key to your provided token: `1ef3b27ea753780a90cbdf07d027fb7b52791004`

### 2. New Files Created

#### a. `migrate_to_iprog_sms.sql`
- Database migration script
- Updates system settings from PhilSMS to IProg SMS
- Inserts new IProg SMS configuration

#### b. `test_iprog_sms_integration.php`
- Test file to verify IProg SMS integration
- Includes configuration display and test functions
- Manual testing instructions

## API Configuration

### IProg SMS API Details
- **Endpoint**: `https://sms.iprogtech.com/api/v1/sms_messages`
- **Method**: POST
- **Content-Type**: application/json
- **Authentication**: API token in request body

### Request Format
```json
{
    "api_token": "1ef3b27ea753780a90cbdf07d027fb7b52791004",
    "phone_number": "639123456789",
    "message": "Your message here"
}
```

### Phone Number Format
- Numbers are formatted to Philippine format (63XXXXXXXXX)
- Automatic conversion from local format (09XXXXXXXXX) to international
- Removes special characters and handles +63 prefix

## Migration Steps

### Step 1: Backup Current Settings
```sql
-- Backup current SMS settings
SELECT * FROM system_settings WHERE setting_key LIKE '%sms%' OR setting_key LIKE '%philsms%';
```

### Step 2: Run Migration Script
```bash
# Navigate to your project directory
cd /path/to/your/project

# Run the migration SQL script
mysql -u your_username -p your_database < migrate_to_iprog_sms.sql
```

### Step 3: Update Admin Settings
1. Log in to admin panel
2. Go to System Settings
3. Verify SMS Provider is set to "IProg SMS"
4. Verify API Key is correctly set
5. Test SMS functionality

### Step 4: Test Integration
1. Open `test_iprog_sms_integration.php` in your browser
2. Update the test phone number to your own
3. Uncomment the test function call
4. Run the test to verify SMS sending

## Configuration Options

### Database Settings
The system now looks for these settings in the `system_settings` table:
- `sms_provider`: 'iprog'
- `iprog_sms_api_key`: Your IProg SMS API key
- `iprog_sms_sender_id`: Your sender ID (default: 'IMMUCARE')

### Config File Constants
Fallback constants in `config.php`:
- `SMS_PROVIDER`: 'iprog'
- `IPROG_SMS_API_KEY`: '1ef3b27ea753780a90cbdf07d027fb7b52791004'
- `SMS_SENDER_ID`: 'IMMUCARE'

## Testing

### Unit Testing
```php
// Test SMS helper function
require_once 'includes/sms_helper.php';
$result = sendSMS('09123456789', 'Test message');
print_r($result);
```

### Integration Testing
```php
// Test NotificationSystem class
require_once 'notification_system.php';
$notification = new NotificationSystem();
$result = $notification->sendCustomNotification(1, 'Test', 'Test message', 'sms');
```

## Troubleshooting

### Common Issues

1. **API Key Not Working**
   - Verify the API key is correctly configured
   - Check if the key has proper permissions
   - Ensure no extra spaces or characters

2. **Phone Number Format Issues**
   - Verify phone numbers are in correct format
   - Check conversion from local to international format
   - Test with known working numbers

3. **SSL/TLS Issues**
   - Ensure cURL is properly configured
   - Verify SSL certificates are valid
   - Check firewall settings

### Debug Information
The system logs detailed information for troubleshooting:
- Request data is logged before sending
- API responses are logged
- HTTP status codes are recorded
- Error messages are captured

### Log Files
Check these locations for debug information:
- PHP error log
- SMS logs table in database
- Browser developer console (for admin interface)

## Security Considerations

1. **API Key Protection**
   - Store API key securely in database or config
   - Use environment variables in production
   - Regularly rotate API keys

2. **Input Validation**
   - Phone numbers are sanitized
   - Message content is validated
   - SQL injection prevention

3. **Rate Limiting**
   - Monitor API usage
   - Implement retry logic with delays
   - Handle API rate limits gracefully

## Support

For issues with this migration:
1. Check the test file output for detailed error messages
2. Review the database migration results
3. Verify all file updates are applied correctly
4. Contact IProg SMS support for API-related issues

## API Documentation Reference

For more details on the IProg SMS API, refer to their official documentation or contact their support team.
