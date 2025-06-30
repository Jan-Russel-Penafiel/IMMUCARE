-- Create the database
CREATE DATABASE IF NOT EXISTS immucare_db;
USE immucare_db;

-- Create user roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Insert default roles
INSERT INTO roles (name, description, created_at) VALUES
('admin', 'System administrator with full access', NOW()),
('midwife', 'Midwife with patient management access', NOW()),
('nurse', 'Nurse with immunization management access', NOW()),
('patient', 'Regular patient user', NOW());

-- Create users table with role support
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL DEFAULT 4,
    user_type ENUM('admin', 'midwife', 'nurse', 'patient') NOT NULL DEFAULT 'patient',
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20),
    password VARCHAR(255),
    otp VARCHAR(6),
    otp_expiry DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Create patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,

    purok TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20),
    phone_number VARCHAR(20),

    medical_history TEXT,
    allergies TEXT,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create vaccines table
CREATE TABLE IF NOT EXISTS vaccines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100),
    description TEXT,
    recommended_age VARCHAR(100),
    doses_required INT DEFAULT 1,
    days_between_doses INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create immunizations table
CREATE TABLE IF NOT EXISTS immunizations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    vaccine_id INT NOT NULL,
    administered_by INT NOT NULL,
    dose_number INT DEFAULT 1,
    batch_number VARCHAR(50),
    expiration_date DATE,
    administered_date DATETIME NOT NULL,
    next_dose_date DATE,
    location VARCHAR(100),
    diagnosis TEXT,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE RESTRICT,
    FOREIGN KEY (administered_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Create appointments table
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    staff_id INT,
    appointment_date DATETIME NOT NULL,
    vaccine_id INT,
    purpose VARCHAR(255) NOT NULL,
    status ENUM('requested', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'requested',
    notes TEXT,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (vaccine_id) REFERENCES vaccines(id) ON DELETE SET NULL
);

-- Create notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('email', 'sms', 'system') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    sent_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create SMS log table
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50),
    provider_response TEXT,
    related_to VARCHAR(50) DEFAULT 'general',
    related_id INT,
    sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);

-- Create email log table
CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email_address VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status VARCHAR(50),
    provider_response TEXT,
    related_to VARCHAR(50) DEFAULT 'general',
    related_id INT,
    sent_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Create data transfer logs table
CREATE TABLE IF NOT EXISTS data_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    initiated_by INT NOT NULL,
    destination VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    record_count INT,
    transfer_type ENUM('manual', 'scheduled', 'api') NOT NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL,
    status_message TEXT,
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (initiated_by) REFERENCES users(id) ON DELETE RESTRICT
);

-- Create health centers table
CREATE TABLE IF NOT EXISTS health_centers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    address TEXT NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    postal_code VARCHAR(20),
    phone VARCHAR(20),
    email VARCHAR(100),
    contact_person VARCHAR(100),
    api_key VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create system settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    description TEXT,
    updated_by INT,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert sample data for testing

-- Insert admin user
INSERT INTO users (role_id, user_type, name, email, phone, created_at) VALUES 
(1, 'admin', 'System Admin', 'penafielliezl1122@gmail.com', '+1234567890', NOW());

-- Insert sample staff
INSERT INTO users (role_id, user_type, name, email, phone, created_at) VALUES 
(2, 'midwife', 'Jane Midwife', 'penafielliezl5555@gmail.com', '+1234567891', NOW()),
(3, 'nurse', 'John Nurse', 'nurse@immucare.com', '+1234567892', NOW());

-- Insert sample patient user
INSERT INTO users (role_id, user_type, name, email, phone, created_at) VALUES 
(4, 'patient', 'Test Patient', 'penafielliezl9999@gmail.com', '+1234567893', NOW());

-- Insert sample patient record
INSERT INTO patients (user_id, first_name, middle_name, last_name, date_of_birth, gender, purok, city, province, postal_code, phone_number, created_at) VALUES
(4, 'Test', 'P', 'Patient', '1990-01-01', 'male', 'Purok 1', 'Anytown', 'Province', '12345', '+1234567893', NOW());

-- Insert sample vaccines
INSERT INTO vaccines (name, manufacturer, description, recommended_age, doses_required, days_between_doses, created_at) VALUES
('BCG', 'BioPharm', 'Bacillus Calmette–Guérin vaccine', 'At birth', 1, 0, NOW()),
('Hepatitis B', 'VaxCorp', 'Hepatitis B vaccine', 'At birth, 1-2 months, 6 months', 3, 30, NOW()),
('DTaP', 'ImmuneTech', 'Diphtheria, Tetanus, Pertussis vaccine', '2 months, 4 months, 6 months, 15-18 months, 4-6 years', 5, 60, NOW()),
('IPV', 'GlobalVax', 'Inactivated Polio Vaccine', '2 months, 4 months, 6-18 months, 4-6 years', 4, 60, NOW()),
('MMR', 'BioShield', 'Measles, Mumps, Rubella vaccine', '12-15 months, 4-6 years', 2, 1095, NOW());

-- Insert sample health center
INSERT INTO health_centers (name, address, city, province, postal_code, phone, email, contact_person, created_at) VALUES
('Municipal Health Center', '456 Health Ave', 'Anytown', 'Province', '12345', '+1234567899', 'artiedastephany@gmail.com', 'Dr. Health Director', NOW());

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, description, created_at) VALUES
('sms_provider', 'philsms', 'SMS gateway provider', NOW()),
('sms_enabled', 'true', 'Enable SMS notifications', NOW()),
('email_enabled', 'true', 'Enable email notifications', NOW()),
('appointment_reminder_days', '2', 'Days before appointment to send reminder', NOW()),
('auto_sync_mhc', 'false', 'Automatically sync data with Municipal Health Center', NOW());

-- Add is_active column to vaccines table
ALTER TABLE vaccines ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER days_between_doses; 