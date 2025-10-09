-- Migration to add blood_type and diagnosis columns to patients table
-- Date: 2025-10-09

USE immucare_db;

-- Add blood_type column to patients table
ALTER TABLE `patients` 
ADD COLUMN `blood_type` VARCHAR(10) DEFAULT NULL COMMENT 'Patient blood type (A+, A-, B+, B-, AB+, AB-, O+, O-)' AFTER `gender`;

-- Add diagnosis column to patients table
ALTER TABLE `patients` 
ADD COLUMN `diagnosis` TEXT DEFAULT NULL COMMENT 'Patient diagnosis information' AFTER `allergies`;

-- Add index for blood_type for better query performance
ALTER TABLE `patients` 
ADD INDEX `idx_blood_type` (`blood_type`);

-- Display success message
SELECT 'Migration completed successfully! Added blood_type and diagnosis columns to patients table.' AS Status;
