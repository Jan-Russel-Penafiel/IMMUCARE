-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 30, 2025 at 04:25 PM
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
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
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

INSERT INTO `email_logs` (`id`, `user_id`, `email_address`, `subject`, `message`, `status`, `provider_response`, `related_to`, `related_id`, `sent_at`, `created_at`) VALUES
(1, 5, 'stephanyartieda@sksu.edu.ph', 'Welcome to ImmuCare - Account Created', '\n                <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                    <div style=\"text-align: center; margin-bottom: 20px;\">\n                        <img src=\"http://localhost/mic_new/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                    </div>\n                    <h2 style=\"color: #4285f4;\">Welcome to ImmuCare!</h2>\n                    <p>Hello asdadad,</p>\n                    <p>Your account has been successfully created by an administrator.</p>\n                    <div style=\"background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;\">\n                        <p><strong>Email:</strong> stephanyartieda@sksu.edu.ph</p>\n                        <p><strong>Password:</strong> </p>\n                        <p><strong>Role:</strong> Patient</p>\n                    </div>\n                    <p>You can now log in to your account using the provided credentials. We recommend changing your password after your first login.</p>\n                    <p>If you have any questions, please contact our support team.</p>\n                    <div style=\"text-align: center; margin-top: 30px;\">\n                        <a href=\"http://localhost/mic_new/login.php\" style=\"background-color: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;\">Login to Your Account</a>\n                    </div>\n                    <p style=\"margin-top: 30px;\">Thank you,<br>ImmuCare Team</p>\n                </div>\n            ', 'sent', NULL, 'general', NULL, '2025-06-30 21:31:40', '2025-06-30 21:31:40'),
(2, 5, 'stephanyartieda@sksu.edu.ph', 'ImmuCare - Patient Profile Created', '\n                        <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                            <div style=\"text-align: center; margin-bottom: 20px;\">\n                                <img src=\"http://localhost/mic_new/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                            </div>\n                            <h2 style=\"color: #4285f4;\">Patient Profile Created</h2>\n                            <p>Hello asdadad,</p>\n                            <p>A patient profile has been created and linked to your account.</p>\n                            <p>You can now access your immunization records, schedule appointments, and receive vaccination reminders through your account.</p>\n                            <div style=\"text-align: center; margin-top: 30px;\">\n                                <a href=\"http://localhost/mic_new/login.php\" style=\"background-color: #4285f4; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;\">Login to Your Account</a>\n                            </div>\n                            <p style=\"margin-top: 30px;\">Thank you for choosing ImmuCare for your immunization needs.</p>\n                            <p>Best regards,<br>ImmuCare Team</p>\n                        </div>\n                    ', 'sent', NULL, 'general', NULL, '2025-06-30 21:32:19', '2025-06-30 21:32:19'),
(3, 5, 'stephanyartieda@sksu.edu.ph', 'Patient Profile Created', '\n            <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                <div style=\"text-align: center; margin-bottom: 20px;\">\n                    <img src=\"https://yourwebsite.com/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                </div>\n                <h2 style=\"color: #4285f4;\">Patient Profile Created</h2>\n                <p>Hello asdadad Peñafiel,</p>\n                <div style=\"background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;\">\n                    <p>Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.</p>\n                </div>\n                <p>Thank you,<br>ImmuCare Team</p>\n            </div>\n        ', 'sent', 'Email sent successfully', 'custom_notification', NULL, '2025-06-30 21:40:52', '2025-06-30 21:40:52'),
(4, 5, 'stephanyartieda@sksu.edu.ph', 'Patient Profile Created', '\n            <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                <div style=\"text-align: center; margin-bottom: 20px;\">\n                    <img src=\"https://yourwebsite.com/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                </div>\n                <h2 style=\"color: #4285f4;\">Patient Profile Created</h2>\n                <p>Hello asdadad Peñafiel,</p>\n                <div style=\"background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;\">\n                    <p>Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.</p>\n                </div>\n                <p>Thank you,<br>ImmuCare Team</p>\n            </div>\n        ', 'sent', 'Email sent successfully', 'custom_notification', NULL, '2025-06-30 21:43:47', '2025-06-30 21:43:47'),
(5, 5, 'stephanyartieda@sksu.edu.ph', 'Patient Profile Deleted', '\n            <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                <div style=\"text-align: center; margin-bottom: 20px;\">\n                    <img src=\"https://yourwebsite.com/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                </div>\n                <h2 style=\"color: #4285f4;\">Patient Profile Deleted</h2>\n                <p>Hello asdadad Peñafiel,</p>\n                <div style=\"background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;\">\n                    <p>Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.</p>\n                </div>\n                <p>Thank you,<br>ImmuCare Team</p>\n            </div>\n        ', 'sent', 'Email sent successfully', 'custom_notification', NULL, '2025-06-30 21:44:03', '2025-06-30 21:44:03'),
(6, 5, 'stephanyartieda@sksu.edu.ph', 'Patient Profile Deleted', '\n            <div style=\"font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e1e4e8; border-radius: 5px;\">\n                <div style=\"text-align: center; margin-bottom: 20px;\">\n                    <img src=\"https://yourwebsite.com/images/logo.svg\" alt=\"ImmuCare Logo\" style=\"max-width: 150px;\">\n                </div>\n                <h2 style=\"color: #4285f4;\">Patient Profile Deleted</h2>\n                <p>Hello asdadad Peñafiel,</p>\n                <div style=\"background-color: #f1f8ff; padding: 15px; border-radius: 5px; margin: 20px 0;\">\n                    <p>Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.</p>\n                </div>\n                <p>Thank you,<br>ImmuCare Team</p>\n            </div>\n        ', 'sent', 'Email sent successfully', 'custom_notification', NULL, '2025-06-30 21:44:15', '2025-06-30 21:44:15');

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
(1, 'Municipal Health Center', '456 Health Ave', 'Anytown', 'Province', '12345', '+1234567899', 'artiedastephany@gmail.com', 'Dr. Health Director', NULL, 1, '2025-06-30 20:52:14', '2025-06-30 12:52:14');

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
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(2, 5, 'Patient Profile Created', 'IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.', 'sms', 0, '2025-06-30 21:40:55', '2025-06-30 21:40:55', '2025-06-30 13:40:55'),
(4, 5, 'Patient Profile Created', 'IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.', 'sms', 0, '2025-06-30 21:43:49', '2025-06-30 21:43:49', '2025-06-30 13:43:49'),
(6, 5, 'Patient Profile Deleted', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sms', 0, '2025-06-30 21:44:06', '2025-06-30 21:44:06', '2025-06-30 13:44:06'),
(8, 5, 'Patient Profile Deleted', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sms', 0, '2025-06-30 21:44:18', '2025-06-30 21:44:18', '2025-06-30 13:44:18'),
(9, 5, 'Schedule Change', 'IMMUCARE: Schedule Change - dsada dad a', 'sms', 0, '2025-06-30 22:22:04', '2025-06-30 22:22:04', '2025-06-30 14:22:04');

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
  `purok` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `purok`, `city`, `province`, `postal_code`, `phone_number`, `medical_history`, `allergies`, `created_at`, `updated_at`) VALUES
(1, 4, 'Test', 'P', 'Patient', '1990-01-01', 'male', 'Purok 1', 'Anytown', 'Province', '12345', '+1234567893', NULL, NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(2, 5, 'asdadad', 'asdada', 'Peñafiel', '2003-02-02', 'female', 'asdad', 'Santo Niño', 'Provinces', '9509', '09319750668', 'asda', 'asda', '2025-06-30 21:32:15', '2025-06-30 13:32:15');

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

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `patient_id`, `phone_number`, `message`, `status`, `provider_response`, `related_to`, `related_id`, `sent_at`, `created_at`) VALUES
(1, 2, '09319750668', 'IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.', 'sent', '{\"status\":\"success\",\"message\":\"Your message was successfully delivered\",\"data\":{\"uid\":\"4907036286190\",\"user_id\":1958,\"to\":\"639319750668\",\"message\":\"IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.\",\"sms_type\":\"plain\",\"status\":\"Delivered\",\"sms_count\":2,\"cost\":0.7,\"sending_server_id\":1,\"from\":\"PhilSMS\",\"telco_id\":2,\"send_by\":\"api\",\"updated_at\":\"2025-06-30T13:40:55.000000Z\",\"created_at\":\"2025-06-30T13:40:55.000000Z\"}}', 'custom_notification', NULL, '2025-06-30 21:40:55', '2025-06-30 21:40:55'),
(2, 2, '09319750668', 'IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.', 'sent', '{\"status\":\"success\",\"message\":\"Your message was successfully delivered\",\"data\":{\"uid\":\"2975428711793\",\"user_id\":1958,\"to\":\"639319750668\",\"message\":\"IMMUCARE: Patient Profile Created - Your patient profile has been created successfully. You can now access your immunization records and schedule appointments through our system.\",\"sms_type\":\"plain\",\"status\":\"Delivered\",\"sms_count\":2,\"cost\":0.7,\"sending_server_id\":1,\"from\":\"PhilSMS\",\"telco_id\":2,\"send_by\":\"api\",\"updated_at\":\"2025-06-30T13:43:50.000000Z\",\"created_at\":\"2025-06-30T13:43:50.000000Z\"}}', 'custom_notification', NULL, '2025-06-30 21:43:49', '2025-06-30 21:43:49'),
(3, 2, '09319750668', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sent', '{\"status\":\"success\",\"message\":\"Your message was successfully delivered\",\"data\":{\"uid\":\"7963188326501\",\"user_id\":1958,\"to\":\"639319750668\",\"message\":\"IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.\",\"sms_type\":\"plain\",\"status\":\"Delivered\",\"sms_count\":2,\"cost\":0.7,\"sending_server_id\":1,\"from\":\"PhilSMS\",\"telco_id\":2,\"send_by\":\"api\",\"updated_at\":\"2025-06-30T13:44:06.000000Z\",\"created_at\":\"2025-06-30T13:44:06.000000Z\"}}', 'custom_notification', NULL, '2025-06-30 21:44:06', '2025-06-30 21:44:06'),
(4, 2, '09319750668', 'IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.', 'sent', '{\"status\":\"success\",\"message\":\"Your message was successfully delivered\",\"data\":{\"uid\":\"4731677022314\",\"user_id\":1958,\"to\":\"639319750668\",\"message\":\"IMMUCARE: Patient Profile Deleted - Your patient profile has been deleted by an administrator. If you believe this was done in error, please contact support immediately.\",\"sms_type\":\"plain\",\"status\":\"Delivered\",\"sms_count\":2,\"cost\":0.7,\"sending_server_id\":1,\"from\":\"PhilSMS\",\"telco_id\":2,\"send_by\":\"api\",\"updated_at\":\"2025-06-30T13:44:19.000000Z\",\"created_at\":\"2025-06-30T13:44:19.000000Z\"}}', 'custom_notification', NULL, '2025-06-30 21:44:18', '2025-06-30 21:44:18'),
(5, 2, '09319750668', 'IMMUCARE: Schedule Change - dsada dad a', 'sent', '{\"status\":\"success\",\"message\":\"Your message was successfully delivered\",\"data\":{\"uid\":\"8262549319871\",\"user_id\":1958,\"to\":\"639319750668\",\"message\":\"IMMUCARE: Schedule Change - dsada dad a\",\"sms_type\":\"plain\",\"status\":\"Delivered\",\"sms_count\":1,\"cost\":0.35,\"sending_server_id\":1,\"from\":\"PhilSMS\",\"telco_id\":2,\"send_by\":\"api\",\"updated_at\":\"2025-06-30T14:22:05.000000Z\",\"created_at\":\"2025-06-30T14:22:05.000000Z\"}}', 'custom_notification', NULL, '2025-06-30 22:22:04', '2025-06-30 22:22:04');

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
(1, 'sms_provider', 'philsms', 'SMS gateway provider', NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(2, 'sms_enabled', 'true', 'Enable SMS notifications', NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(3, 'email_enabled', 'true', 'Enable email notifications', NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(4, 'appointment_reminder_days', '2', 'Days before appointment to send reminder', 1, '2025-06-30 20:52:14', '2025-06-30 14:24:58'),
(5, 'auto_sync_mhc', 'true', 'Automatically sync data with Municipal Health Center', 1, '2025-06-30 20:52:14', '2025-06-30 14:24:52'),
(6, 'philsms_api_key', '2100|J9BVGEx9FFOJAbHV0xfn6SMOkKBt80HTLjHb6zZX ', 'PhilSMS API Key', 1, '2025-06-30 22:24:52', '2025-06-30 14:24:52'),
(7, 'philsms_sender_id', 'PhilSMS', 'PhilSMS Sender ID', 1, '2025-06-30 22:24:52', '2025-06-30 14:24:52');

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
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `otp` varchar(6) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role_id`, `user_type`, `name`, `email`, `phone`, `password`, `otp`, `otp_expiry`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin', 'System Admin', 'penafielliezl1122@gmail.com', '+1234567890', NULL, NULL, NULL, 1, NULL, '2025-06-30 20:52:14', '2025-06-30 13:25:08'),
(2, 2, 'midwife', 'Jane Midwife', 'penafielliezl5555@gmail.com', '+1234567891', NULL, NULL, NULL, 1, NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(3, 3, 'nurse', 'John Nurse', 'nurse@immucare.com', '+1234567892', NULL, NULL, NULL, 1, NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(4, 4, 'patient', 'Test Patient', 'penafielliezl9999@gmail.com', '+1234567893', NULL, NULL, NULL, 1, NULL, '2025-06-30 20:52:14', '2025-06-30 12:52:14'),
(5, 4, 'patient', 'asdadad', 'stephanyartieda@sksu.edu.ph', '09319750668', NULL, NULL, NULL, 1, NULL, '2025-06-30 21:31:35', '2025-06-30 13:31:35');

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
  ADD KEY `vaccine_id` (`vaccine_id`);

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
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `administered_by` (`administered_by`);

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
  ADD KEY `user_id` (`user_id`);

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
  ADD KEY `patient_id` (`patient_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_transfers`
--
ALTER TABLE `data_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `health_centers`
--
ALTER TABLE `health_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `immunizations`
--
ALTER TABLE `immunizations`
  ADD CONSTRAINT `immunizations_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `immunizations_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`id`),
  ADD CONSTRAINT `immunizations_ibfk_3` FOREIGN KEY (`administered_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sms_logs`
--
ALTER TABLE `sms_logs`
  ADD CONSTRAINT `sms_logs_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
