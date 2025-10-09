-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 09, 2025 at 10:12 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `immucare_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `appointment_date` datetime NOT NULL,
  `vaccine_id` int(11) DEFAULT NULL,
  `purpose` varchar(255) NOT NULL,
  `status` enum('requested','confirmed','completed','cancelled','no_show') DEFAULT 'requested',
  `notes` text DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `transaction_number` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Appointment records with transaction tracking (updated with transaction_id and transaction_number)';

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `staff_id`, `appointment_date`, `vaccine_id`, `purpose`, `status`, `notes`, `transaction_id`, `transaction_number`, `created_at`, `updated_at`) VALUES
(1, 12, 2, '2025-07-03 11:41:00', NULL, 'bjhb', 'requested', 'knbkbkj', NULL, NULL, '2025-07-01 11:40:20', '2025-08-02 06:23:08'),
(2, 12, 3, '2025-08-08 19:31:00', 2, 'sadaass', 'requested', 'asda', NULL, NULL, '2025-08-02 19:29:23', '2025-08-02 11:44:22');

-- --------------------------------------------------------

--
-- Table structure for table `data_transfers`
--

CREATE TABLE `data_transfers` (
  `id` int(11) NOT NULL,
  `initiated_by` int(11) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `record_count` int(11) DEFAULT NULL,
  `transfer_type` enum('manual','scheduled','api') NOT NULL,
  `status` enum('pending','completed','failed') NOT NULL,
  `status_message` text DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `data_transfers`
--

INSERT INTO `data_transfers` (`id`, `initiated_by`, `destination`, `file_name`, `file_size`, `record_count`, `transfer_type`, `status`, `status_message`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(11, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_110206.xlsx, immucare_health_data_2025-08-02_110206.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:02:15', '2025-08-02 17:02:15', '2025-08-02 17:02:15', '2025-08-02 09:02:15'),
(12, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_114626.xlsx, immucare_health_data_2025-08-02_114626.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:46:35', '2025-08-02 17:46:35', '2025-08-02 17:46:35', '2025-08-02 09:46:35'),
(13, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_115631.xlsx, immucare_health_data_2025-08-02_115632.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:56:43', '2025-08-02 17:56:43', '2025-08-02 17:56:43', '2025-08-02 09:56:43');

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `email_address` varchar(100) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `provider_response` text DEFAULT NULL,
  `related_to` varchar(50) DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `notification_id`, `user_id`, `email_address`, `subject`, `message`, `status`, `provider_response`, `related_to`, `related_id`, `sent_at`, `created_at`) VALUES
(39, 40, 15, 'stephanyartieda@sksu.edu.ph', 'Welcome to ImmuCare - Account Created', '\n            <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px; border: 1px solid #e1e4e8; border-radius: 8px; background-color: #ffffff;\">\n                <!-- Header with Logo -->\n                <div style=\"text-align: center; margin-bottom: 30px;\">\n                    <img src=\"http://localhost/mic_new/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px; height: auto;\">\n                </div>\n                \n                <!-- Title -->\n                <h2 style=\"color: #4285f4; font-size: 24px; font-weight: 600; margin: 0 0 20px 0; text-align: left;\">Welcome to ImmuCare - Account Created</h2>\n                \n                <!-- Greeting -->\n                <p style=\"color: #333333; font-size: 16px; line-height: 1.5; margin: 0 0 20px 0;\">Hello Stephany lablab,</p>\n                \n                <!-- Message Content -->\n                <div style=\"background-color: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0;\">\n                    <div style=\"color: #333333; font-size: 16px; line-height: 1.6;\">\n                        Welcome to ImmuCare!<br />\n<br />\nYour ImmuCare account has been created with the following credentials:<br />\n- Email: stephanyartieda@sksu.edu.ph<br />\n- Phone: 09920157536<br />\n- Password: 12345678<br />\n<br />\nPlease keep these credentials secure and change your password after your first login.<br />\n<br />\nFor assistance, contact our support team:<br />\nPhone: +1-800-IMMUCARE<br />\nEmail: support@immucare.com\n                    </div>\n                </div>\n                \n                <!-- Footer -->\n                <div style=\"margin-top: 30px; padding-top: 20px; border-top: 1px solid #e1e4e8;\">\n                    <p style=\"color: #666666; font-size: 14px; margin: 0;\">Thank you,<br>ImmuCare Team</p>\n                    \n                    <!-- Contact Info -->\n                    <div style=\"margin-top: 20px; color: #666666; font-size: 12px;\">\n                        <p style=\"margin: 5px 0;\">Need help? Contact us at support@immucare.com</p>\n                        <p style=\"margin: 5px 0;\">Phone: +1-800-IMMUCARE</p>\n                    </div>\n                </div>\n            </div>\n        ', 'sent', NULL, 'general', NULL, '2025-07-01 11:06:19', '2025-07-01 11:06:19');

-- --------------------------------------------------------

--
-- Table structure for table `health_centers`
--

CREATE TABLE `health_centers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `api_key` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `health_centers`
--

INSERT INTO `health_centers` (`id`, `name`, `address`, `city`, `province`, `postal_code`, `phone`, `email`, `contact_person`, `api_key`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Municipal Health Center', '456 Health Ave', 'Anytown', 'Province', '12345', '+1234567899', 'artiedastephany@gmail.com', 'Dr. Health Director', NULL, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(2, 'Regional Medical Center', '789 Medical Drive', 'MedCity', 'Province', '67890', '+1234567891', 'regional.medical@gmail.com', 'Dr. Regional Director', NULL, 1, '2025-08-02 16:27:46', '2025-08-02 08:27:46'),
(3, 'Community Health Clinic', '321 Wellness Street', 'Wellness', 'Province', '54321', '+1234567892', 'community.health@gmail.com', 'Dr. Community Director', NULL, 1, '2025-08-02 16:27:46', '2025-08-02 08:27:46'),
(4, 'Rural Health Unit', '654 Village Road', 'Village', 'Province', '98765', '+1234567893', 'rural.health@gmail.com', 'Dr. Rural Director', NULL, 1, '2025-08-02 16:27:46', '2025-08-02 08:27:46');

-- --------------------------------------------------------

--
-- Table structure for table `immunizations`
--

CREATE TABLE `immunizations` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `administered_by` int(11) NOT NULL,
  `dose_number` int(11) DEFAULT 1,
  `batch_number` varchar(50) DEFAULT NULL,
  `expiration_date` date DEFAULT NULL,
  `administered_date` datetime NOT NULL,
  `next_dose_date` date DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `transaction_number` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Immunization records with transaction tracking (updated with transaction_id and transaction_number)';

--
-- Dumping data for table `immunizations`
--

INSERT INTO `immunizations` (`id`, `patient_id`, `vaccine_id`, `administered_by`, `dose_number`, `batch_number`, `expiration_date`, `administered_date`, `next_dose_date`, `location`, `diagnosis`, `transaction_id`, `transaction_number`, `created_at`, `updated_at`) VALUES
(1, 12, 3, 2, 1, '20', '2025-08-09', '2025-08-07 14:57:00', '0025-10-21', 'asda', 'as', NULL, NULL, '2025-08-02 14:57:24', '2025-08-02 06:57:24'),
(2, 1, 1, 1, 1, 'BCG001', NULL, '2025-08-02 15:35:00', NULL, NULL, NULL, NULL, NULL, '2025-08-02 15:35:00', '2025-08-02 07:35:00'),
(3, 1, 2, 1, 1, 'HEPB001', NULL, '2025-08-02 15:35:00', '2025-09-01', NULL, NULL, NULL, NULL, '2025-08-02 15:35:00', '2025-08-02 07:35:00'),
(4, 12, 1, 1, 1, 'BCG002', NULL, '2025-08-02 15:35:00', NULL, NULL, NULL, NULL, NULL, '2025-08-02 15:35:00', '2025-08-02 07:35:00'),
(5, 1, 1, 3, 1, '1', '2025-08-22', '2025-08-02 18:02:00', '2025-08-09', 'asda', 'dada', NULL, NULL, '2025-08-02 18:02:28', '2025-08-02 10:02:28');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('email','sms','system') NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `sent_at`, `created_at`, `updated_at`) VALUES
(4, 5, 'Patient Profile Created', 'IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.', 'sms', 0, '2025-06-30 21:43:49', '2025-06-30 21:43:49', '2025-06-30 13:43:49'),
(6, 5, 'Patient Profile Deleted', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sms', 0, '2025-06-30 21:44:06', '2025-06-30 21:44:06', '2025-06-30 13:44:06'),
(8, 5, 'Patient Profile Deleted', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sms', 0, '2025-06-30 21:44:18', '2025-06-30 21:44:18', '2025-06-30 13:44:18'),
(9, 5, 'Schedule Change', 'IMMUCARE: Schedule Change - dsada dad a', 'sms', 0, '2025-06-30 22:22:04', '2025-06-30 22:22:04', '2025-06-30 14:22:04'),
(14, 8, 'Welcome to ImmuCare - Account Created', 'Welcome to ImmuCare! Your account has been created as a Patient. You can now access our system using your email (stephanyartieda@sksu.edu.ph) and the provided password. For security reasons, please change your password after your first login. If you need any assistance, please contact our support team.', 'system', 0, '2025-07-01 09:06:48', '2025-07-01 09:06:48', '2025-07-01 01:06:48'),
(15, 8, 'Patient Profile Linked to Your Account', 'Your existing ImmuCare account has been linked to a new patient profile.\n\nAccount Details:\n- Name: Stephany lablab\n- Email: stephanyartieda@sksu.edu.ph\n- Phone: 09319750668\n\nPatient Profile Details:\n- Full Name: Stephany asdadgd lablab\n- Date of Birth: September 8, 2004\n- Gender: Female\n- Contact: 09319750668\n- Address: Purok sada, Santo Niño, Provinces\n\nYou can now access your health records and schedule appointments through your existing account.\n\nIf you did not expect this change, please contact our support team immediately at support@immucare.com', 'system', 0, '2025-07-01 09:07:27', '2025-07-01 09:07:27', '2025-07-01 01:07:27'),
(16, 8, 'Patient Profile Created Successfully', 'Your patient profile has been successfully created in the ImmuCare system.\n\nProfile Details:\n- Patient ID: 5\n- Full Name: Stephany asdadgd lablab\n- Date of Birth: September 8, 2004\n- Gender: Female\n- Contact: 09319750668\n- Address: Purok sada, Santo Niño, Provinces\n\nMedical Information:\n- Medical History: sadada\n- Allergies: dada\n\nYou can now:\n- View your immunization records\n- Schedule appointments\n- Receive vaccination reminders\n- Update your medical information\n\nPlease verify all information and contact us if any corrections are needed.\nFor support, reach us at support@immucare.com or +1-800-IMMUCARE', 'system', 0, '2025-07-01 09:07:34', '2025-07-01 09:07:34', '2025-07-01 01:07:34'),
(29, 11, 'Patient Profile and Account Deletion Notice', 'Important Notice: Your ImmuCare patient profile and user account have been deleted.\n\nProfile Details:\n- Patient ID: 8\n- Name: Stephany asdadgd lablab\n- Email: stephanyartieda@sksu.edu.ph\n\nThis means:\n- Your patient records have been removed\n- Your user account has been deactivated\n- Any scheduled appointments have been cancelled\n- You will no longer receive vaccination reminders\n\nIf you believe this was done in error, please contact our support team immediately.\nYou can reach us at +1-800-IMMUCARE or support@immucare.com', 'system', 0, '2025-07-01 10:38:19', '2025-07-01 10:38:19', '2025-07-01 02:38:19'),
(33, 12, 'Patient Profile and Account Deletion Notice', 'Important Notice: Your ImmuCare patient profile and user account have been deleted.\n\nProfile Details:\n- Patient ID: 9\n- Name: Stephany sadada lablab\n- Email: stephanyartieda@sksu.edu.ph\n\nThis means:\n- Your patient records have been removed\n- Your user account has been deactivated\n- Any scheduled appointments have been cancelled\n- You will no longer receive vaccination reminders\n\nIf you believe this was done in error, please contact our support team immediately.\nYou can reach us at +1-800-IMMUCARE or support@immucare.com', 'system', 0, '2025-07-01 10:54:06', '2025-07-01 10:54:06', '2025-07-01 02:54:06'),
(36, 13, 'Patient Profile and Account Deletion Notice', 'Important Notice: Your ImmuCare patient profile and user account have been deleted.\n\nProfile Details:\n- Patient ID: 10\n- Name: Stephany asdadgd lablab\n- Email: stephanyartieda@sksu.edu.ph\n\nThis means:\n- Your patient records have been removed\n- Your user account has been deactivated\n- Any scheduled appointments have been cancelled\n- You will no longer receive vaccination reminders\n\nIf you believe this was done in error, please contact our support team immediately.\nYou can reach us at +1-800-IMMUCARE or support@immucare.com', 'system', 0, '2025-07-01 10:59:16', '2025-07-01 10:59:16', '2025-07-01 02:59:16'),
(39, 14, 'Patient Profile and Account Deletion Notice', 'Important Notice: Your ImmuCare patient profile and user account have been deleted.\n\nProfile Details:\n- Patient ID: 11\n- Name: Stephany asdadgd lablab\n- Email: stephanyartieda@sksu.edu.ph\n\nThis means:\n- Your patient records have been removed\n- Your user account has been deactivated\n- Any scheduled appointments have been cancelled\n- You will no longer receive vaccination reminders\n\nIf you believe this was done in error, please contact our support team immediately.\nYou can reach us at +1-800-IMMUCARE or support@immucare.com', 'system', 0, '2025-07-01 11:05:13', '2025-07-01 11:05:13', '2025-07-01 03:05:13'),
(40, 15, 'Welcome to ImmuCare - Account Created', 'Welcome to ImmuCare!\n\nYour ImmuCare account has been created with the following credentials:\n- Email: stephanyartieda@sksu.edu.ph\n- Phone: 09920157536\n- Password: 12345678\n\nPlease keep these credentials secure and change your password after your first login.\n\nFor assistance, contact our support team:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com', 'system', 0, '2025-07-01 11:06:19', '2025-07-01 11:06:19', '2025-07-01 03:06:19'),
(42, 2, 'New Appointment Request', 'New appointment request from patient #12 for July 3, 2025 - 11:41 AM', '', 0, NULL, '2025-07-01 11:40:20', '2025-07-01 03:40:20'),
(43, 3, 'New Appointment Request', 'New appointment request from patient #12 for July 3, 2025 - 11:41 AM', '', 0, NULL, '2025-07-01 11:40:20', '2025-07-01 03:40:20'),
(47, 2, 'New Appointment Request', 'New appointment request from patient #12 for August 8, 2025 - 7:31 PM', '', 0, NULL, '2025-08-02 19:29:23', '2025-08-02 11:29:23'),
(48, 3, 'New Appointment Request', 'New appointment request from patient #12 for August 8, 2025 - 7:31 PM', '', 0, NULL, '2025-08-02 19:29:23', '2025-08-02 11:29:23');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('male','female','other') NOT NULL,
  `blood_type` varchar(10) DEFAULT NULL COMMENT 'Patient blood type (A+, A-, B+, B-, AB+, AB-, O+, O-)',
  `purok` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL COMMENT 'Patient diagnosis information',
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `blood_type`, `purok`, `city`, `province`, `postal_code`, `phone_number`, `medical_history`, `allergies`, `diagnosis`, `created_at`, `updated_at`) VALUES
(1, 4, 'Test', 'P', 'Patient', '1990-01-01', 'male', NULL, 'Purok 1', 'Anytown', 'Province', '12345', '+1234567893', NULL, NULL, NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(12, 15, 'Stephany', 'asdada', 'lablab', '2002-01-20', 'female', NULL, 'asda', 'dada', 'asda', '9509as', '09920157536', 'dada', 'dada', NULL, '2025-07-01 11:06:58', '2025-07-01 03:06:58');

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'admin', 'System administrator with full access', '2025-06-30 20:52:14'),
(2, 'midwife', 'Midwife with patient management access', '2025-06-30 20:52:14'),
(3, 'nurse', 'Nurse with immunization management access', '2025-06-30 20:52:14'),
(4, 'patient', 'Regular patient user', '2025-06-30 20:52:14');

-- --------------------------------------------------------

--
-- Table structure for table `sms_logs`
--

CREATE TABLE `sms_logs` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(50) DEFAULT NULL,
  `provider_response` text DEFAULT NULL,
  `related_to` varchar(50) DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'sms_provider', 'philsms', 'SMS gateway provider', NULL, '2025-06-30 20:52:14', '2025-06-30 04:52:14'),
(2, 'sms_enabled', 'true', 'Enable SMS notifications', NULL, '2025-06-30 20:52:14', '2025-06-30 04:52:14'),
(3, 'email_enabled', 'true', 'Enable email notifications', NULL, '2025-06-30 20:52:14', '2025-06-30 04:52:14'),
(4, 'appointment_reminder_days', '2', 'Days before appointment to send reminder', 1, '2025-06-30 20:52:14', '2025-08-02 07:17:30'),
(5, 'auto_sync_mhc', 'true', 'Automatically sync data with Municipal Health Center', 1, '2025-06-30 20:52:14', '2025-08-02 06:56:29'),
(6, 'philsms_api_key', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX ', 'PhilSMS API Key', 1, '2025-06-30 22:24:52', '2025-06-30 06:24:52'),
(7, 'philsms_sender_id', 'PhilSMS', 'PhilSMS Sender ID', 1, '2025-06-30 22:24:52', '2025-06-30 06:24:52'),
(8, 'smtp_host', 'smtp.gmail.com', 'SMTP Server Host', 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(9, 'smtp_port', '587', 'SMTP Server Port', 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(10, 'smtp_user', 'vmctaccollege@gmail.com', 'SMTP Username', 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(11, 'smtp_pass', 'tqqs fkkh lbuz jbeg', 'SMTP Password', 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(12, 'smtp_secure', 'tls', 'SMTP Security Type (tls/ssl)', 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 4,
  `user_type` enum('admin','midwife','nurse','patient') NOT NULL DEFAULT 'patient',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `user_type`, `name`, `email`, `phone`, `password`, `otp`, `otp_expiry`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'System Admin', 'penafielliezl1122@gmail.com', '+1234567890', '12345678', NULL, NULL, 1, '2025-10-09 15:49:13', '2025-06-30 20:52:14', '2025-10-09 07:49:13'),
(2, 2, 'midwife', 'Jane Midwife', 'penafielliezl5555@gmail.com', '+1234567891', '12345678', NULL, NULL, 1, '2025-10-09 16:07:02', '2025-06-30 20:52:14', '2025-10-09 08:07:02'),
(3, 3, 'nurse', 'John Nurse', 'penafielliezl3322@gmail.com', '+1234567892', '12345678', NULL, NULL, 1, '2025-10-09 16:04:49', '2025-06-30 20:52:14', '2025-10-09 08:04:49'),
(4, 4, 'patient', 'Test Patient', 'penafielliezl9999@gmail.com', '+1234567893', '$2y$10$i4nrJwhdt1o6A.LGZrcWLOAwws.oIQzkAKqI/H9sOglnCZ0xQt1CS', NULL, NULL, 1, '2025-07-21 20:08:04', '2025-06-30 20:52:14', '2025-07-21 12:08:04'),
(15, 4, 'patient', 'Stephany lablab', 'stephanyartieda@sksu.edu.ph', '09920157536', '12345678', '293049', '2025-07-01 08:04:34', 1, '2025-08-02 19:14:24', '2025-07-01 11:06:19', '2025-08-02 11:14:24');

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `recommended_age` varchar(100) DEFAULT NULL,
  `doses_required` int(11) DEFAULT 1,
  `days_between_doses` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`id`, `name`, `manufacturer`, `description`, `recommended_age`, `doses_required`, `days_between_doses`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BCG', 'BioPharm', 'Bacillus Calmette???Gu??rin vaccine', 'At birth', 1, 0, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(2, 'Hepatitis B', 'VaxCorp', 'Hepatitis B vaccine', 'At birth, 1-2 months, 6 months', 3, 30, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(3, 'DTaP', 'ImmuneTech', 'Diphtheria, Tetanus, Pertussis vaccine', '2 months, 4 months, 6 months, 15-18 months, 4-6 years', 5, 60, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(4, 'IPV', 'GlobalVax', 'Inactivated Polio Vaccine', '2 months, 4 months, 6-18 months, 4-6 years', 4, 60, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(5, 'MMR', 'BioShield', 'Measles, Mumps, Rubella vaccine', '12-15 months, 4-6 years', 2, 1095, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `staff_id` (`staff_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_transaction_number` (`transaction_number`);

--
-- Indexes for table `data_transfers`
--
ALTER TABLE `data_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `initiated_by` (`initiated_by`);

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `health_centers`
--
ALTER TABLE `health_centers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `administered_by` (`administered_by`),
  ADD KEY `idx_transaction_id` (`transaction_id`),
  ADD KEY `idx_transaction_number` (`transaction_number`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_blood_type` (`blood_type`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `notification_id` (`notification_id`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `data_transfers`
--
ALTER TABLE `data_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `health_centers`
--
ALTER TABLE `health_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `data_transfers`
--
ALTER TABLE `data_transfers`
  ADD CONSTRAINT `data_transfers_ibfk_1` FOREIGN KEY (`initiated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `email_logs_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD CONSTRAINT `immunizations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `immunizations_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `immunizations_ibfk_3` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sms_logs_ibfk_2` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
