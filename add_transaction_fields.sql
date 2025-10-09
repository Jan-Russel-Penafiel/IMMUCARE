-- Migration to add transaction_id and transaction_number to immunizations table
-- Run this file to update your database with transaction tracking fields

-- Add transaction_id column to immunizations table
ALTER TABLE `immunizations` 
ADD COLUMN `transaction_id` VARCHAR(100) DEFAULT NULL AFTER `diagnosis`,
ADD COLUMN `transaction_number` VARCHAR(50) DEFAULT NULL AFTER `transaction_id`;

-- Add index for faster lookups on transaction fields
ALTER TABLE `immunizations`
ADD INDEX `idx_transaction_id` (`transaction_id`),
ADD INDEX `idx_transaction_number` (`transaction_number`);

-- Optional: Add transaction fields to appointments table as well
ALTER TABLE `appointments` 
ADD COLUMN `transaction_id` VARCHAR(100) DEFAULT NULL AFTER `notes`,
ADD COLUMN `transaction_number` VARCHAR(50) DEFAULT NULL AFTER `transaction_id`;

-- Add index for faster lookups on appointments transaction fields
ALTER TABLE `appointments`
ADD INDEX `idx_transaction_id` (`transaction_id`),
ADD INDEX `idx_transaction_number` (`transaction_number`);

-- Add comment to document the changes
ALTER TABLE `immunizations` 
COMMENT = 'Immunization records with transaction tracking (updated with transaction_id and transaction_number)';

ALTER TABLE `appointments` 
COMMENT = 'Appointment records with transaction tracking (updated with transaction_id and transaction_number)';
