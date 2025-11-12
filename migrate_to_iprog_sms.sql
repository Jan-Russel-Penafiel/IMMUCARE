-- Migration from PhilSMS to IProg SMS
-- Run this script to update your database from PhilSMS to IProg SMS configuration

-- First, update the SMS provider to 'iprog'
UPDATE system_settings 
SET setting_value = 'iprog' 
WHERE setting_key = 'sms_provider';

-- Insert iprog SMS settings if they don't exist
INSERT IGNORE INTO system_settings (setting_key, setting_value, description, created_at, updated_at)
VALUES 
('iprog_sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'IProg SMS API Key', NOW(), NOW()),
('iprog_sms_sender_id', 'IMMUCARE', 'IProg SMS Sender ID', NOW(), NOW());

-- Clean up old PhilSMS settings (optional - you may want to keep these as backup)
-- Uncomment the following lines if you want to remove old PhilSMS settings
-- DELETE FROM system_settings WHERE setting_key = 'philsms_api_key';
-- DELETE FROM system_settings WHERE setting_key = 'philsms_sender_id';

-- Verify the changes
SELECT setting_key, setting_value 
FROM system_settings 
WHERE setting_key IN ('sms_provider', 'iprog_sms_api_key', 'iprog_sms_sender_id')
ORDER BY setting_key;