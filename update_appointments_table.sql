-- Add location column to appointments table
ALTER TABLE appointments
ADD COLUMN location varchar(255) DEFAULT 'Municipal Health Center' AFTER purpose; 