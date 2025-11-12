-- Update SMS provider settings to use iProgTech SMS API
-- Run this script to update your database settings

-- First, remove old PhilSMS settings if they exist
DELETE FROM system_settings WHERE setting_key IN ('philsms_api_key', 'philsms_sender_id');

-- Insert or update iProgSMS settings
INSERT INTO system_settings (setting_key, setting_value, description, created_at, updated_at) 
VALUES 
    ('iprogsms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'iProgTech SMS API Token for sending SMS notifications', NOW(), NOW()),
    ('iprogsms_sender_id', 'IMMUCARE', 'Sender ID for iProgTech SMS', NOW(), NOW()),
    ('sms_provider', 'iprogsms', 'Current SMS provider (iProgTech)', NOW(), NOW())
ON DUPLICATE KEY UPDATE 
    setting_value = VALUES(setting_value),
    updated_at = NOW();

-- Verify the settings were updated
SELECT * FROM system_settings WHERE setting_key LIKE '%iprogsms%' OR setting_key = 'sms_provider';