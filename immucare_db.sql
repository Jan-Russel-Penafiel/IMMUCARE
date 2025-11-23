-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 19, 2025 at 05:02 PM
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
  `location` varchar(255) DEFAULT 'Main Clinic',
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

INSERT INTO `appointments` (`id`, `patient_id`, `staff_id`, `appointment_date`, `vaccine_id`, `purpose`, `location`, `status`, `notes`, `transaction_id`, `transaction_number`, `created_at`, `updated_at`) VALUES
(1, 12, 2, '2025-07-03 11:41:00', NULL, 'bjhb', 'Main Clinic', 'confirmed', 'knbkbkj', NULL, NULL, '2025-07-01 11:40:20', '2025-11-12 19:35:23'),
(2, 12, 3, '2025-08-08 19:31:00', 2, 'sadaass', 'Main Clinic', 'requested', 'asda', 'TX251114200254', 'TX0007', '2025-08-02 19:29:23', '2025-11-14 19:02:54'),
(5, 13, 3, '2025-11-05 01:58:00', 2, 'sada', 'Main Clinic', 'requested', 'dasda', 'TX251114200408', 'TX0008', '2025-11-15 01:55:50', '2025-11-14 19:04:08'),
(6, 13, 3, '2025-10-29 01:56:00', 1, 'asd', 'Main Clinic', 'cancelled', 'adada', NULL, NULL, '2025-11-15 01:56:08', '2025-11-14 18:36:23'),
(9, 13, 2, '2025-11-14 02:37:00', 2, 'sdf', 'Main Clinic', 'confirmed', 'assa', 'TX251114193802', 'TX0002', '2025-11-15 02:34:21', '2025-11-14 18:38:02'),
(10, 13, 3, '2025-11-21 02:52:00', 2, 'asd', 'Main Clinic', 'confirmed', 'ada', 'TX251114195051', 'TX0003', '2025-11-15 02:50:51', '2025-11-14 18:51:12'),
(11, 13, 3, '2025-11-20 02:54:00', 2, 'sad', 'Main Clinic', 'confirmed', 'ada', 'TX251114195231', 'TX0004', '2025-11-15 02:52:31', '2025-11-14 18:56:12'),
(12, 13, 2, '2025-11-28 02:00:00', 1, 'asda', 'Main Clinic', 'requested', 'dada', 'TX251114200715', 'TX0009', '2025-11-15 02:57:58', '2025-11-14 19:07:15');

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(500) NOT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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

--
-- Dumping data for table `data_transfers`
--

INSERT INTO `data_transfers` (`id`, `initiated_by`, `destination`, `file_name`, `file_size`, `record_count`, `transfer_type`, `status`, `status_message`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(11, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_110206.xlsx, immucare_health_data_2025-08-02_110206.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:02:15', '2025-08-02 17:02:15', '2025-08-02 17:02:15', '2025-08-02 09:02:15'),
(12, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_114626.xlsx, immucare_health_data_2025-08-02_114626.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:46:35', '2025-08-02 17:46:35', '2025-08-02 17:46:35', '2025-08-02 09:46:35'),
(13, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-08-02_115631.xlsx, immucare_health_data_2025-08-02_115632.pdf', NULL, NULL, 'manual', 'completed', '', '2025-08-02 17:56:43', '2025-08-02 17:56:43', '2025-08-02 17:56:43', '2025-08-02 09:56:43'),
(14, 1, 'artiedastephany@gmail.com', 'immucare_health_data_2025-11-12_222101.xlsx, immucare_health_data_2025-11-12_222102.pdf', NULL, NULL, 'manual', 'completed', '', '2025-11-13 05:21:07', '2025-11-13 05:21:07', '2025-11-13 05:21:07', '2025-11-12 21:21:07');

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
(4, 12, 1, 1, 1, 'BCG002', NULL, '2025-08-02 15:35:00', NULL, NULL, NULL, NULL, NULL, '2025-08-02 15:35:00', '2025-08-02 07:35:00'),
(6, 14, 3, 2, 1, '1', '2025-11-13', '2025-11-15 00:40:00', '2025-11-22', 'sdasd', 'asda', NULL, NULL, '2025-11-15 00:40:59', '2025-11-14 16:40:59'),
(7, 12, 1, 3, 1, '1', '2025-11-20', '2025-11-14 16:50:00', '2025-11-29', 'asda', 'sadad', NULL, NULL, '2025-11-14 17:50:34', '2025-11-14 16:50:34'),
(8, 14, 3, 3, 1, '1', '2025-11-21', '2025-11-14 16:52:00', '2025-12-18', 'sada', 'sadada', NULL, NULL, '2025-11-14 17:53:05', '2025-11-14 16:53:05'),
(9, 12, 2, 3, 1, '2', '2025-11-28', '2025-11-14 17:05:00', '2025-11-28', 'sadada', 'sadada', NULL, NULL, '2025-11-14 18:06:05', '2025-11-14 17:06:05'),
(10, 14, 1, 2, 1, '1', '2025-11-18', '2025-11-20 05:06:00', '2025-11-23', 'kk', 'asd', 'TX251115220703', 'TX0010', '2025-11-16 05:07:03', '2025-11-15 21:07:03');

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
(55, 15, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: bjhb\n- Date: Thursday, July 3, 2025\n- Time: 11:41 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: knbkbkj\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 03:05:58', '2025-11-13 03:05:58', '2025-11-12 19:05:58'),
(56, 15, 'Appointment Status Update: Requested', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: bjhb\n- Date: Thursday, July 3, 2025\n- Time: 11:41 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: knbkbkj\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 03:15:08', '2025-11-13 03:15:08', '2025-11-12 19:15:08'),
(57, 17, 'IMMUCARE: Account Created Successfully', 'Dear hunter,\n\nYour IMMUCARE account has been successfully created.\n\nAccount Details:\nEmail: artiedastephany@gmail.com\nPhone: 09677726912\nPassword: 12345678\n\nFor security reasons, please change your password after logging in.\n\nNeed help? Contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 03:48:21', '2025-11-13 03:48:21', '2025-11-12 19:48:21'),
(58, 17, 'IMMUCARE: Account Deleted', 'Dear hunter,\n\nYour IMMUCARE account and patient profile have been deleted.\n\nAccount Details:\nPatient ID: \nName:  \nEmail: artiedastephany@gmail.com\n\nThis means:\n1. Patient records removed\n2. User account deactivated\n3. Appointments cancelled\n4. Vaccination reminders stopped\n\nIf this was done in error, contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 03:54:11', '2025-11-13 03:54:11', '2025-11-12 19:54:11'),
(59, 18, 'IMMUCARE: Account Created Successfully', 'Dear hunter,\n\nYour IMMUCARE account has been successfully created.\n\nAccount Details:\nEmail: artiedastephany@gmail.com\nPhone: 09677726912\nPassword: 12345678\n\nFor security reasons, please change your password after logging in.\n\nNeed help? Contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 03:54:51', '2025-11-13 03:54:51', '2025-11-12 19:54:51'),
(61, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 14, 2025 - 3:59 AM', '', 0, NULL, '2025-11-13 03:56:11', '2025-11-12 19:56:11'),
(62, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 14, 2025 - 3:59 AM', '', 0, NULL, '2025-11-13 03:56:11', '2025-11-12 19:56:11'),
(63, 15, 'IMMUCARE: Account Deleted', 'Dear Stephany lablab,\n\nYour IMMUCARE account and patient profile have been deleted.\n\nAccount Details:\nPatient ID: \nName:  \nEmail: janrusselpenafiel01172005@gmail.com\n\nThis means:\n1. Patient records removed\n2. User account deactivated\n3. Appointments cancelled\n4. Vaccination reminders stopped\n\nIf this was done in error, contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 04:05:43', '2025-11-13 04:05:43', '2025-11-12 20:05:43'),
(64, 19, 'IMMUCARE: Account Created Successfully', 'Dear asdada,\n\nYour IMMUCARE account has been successfully created.\n\nAccount Details:\nEmail: janrusselpenafiel01172005@gmail.com\nPhone: 09677726912\nPassword: 12345678\n\nFor security reasons, please change your password after logging in.\n\nNeed help? Contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 04:06:00', '2025-11-13 04:06:00', '2025-11-12 20:06:00'),
(65, 19, 'IMMUCARE: Account Deleted', 'Dear asdada,\n\nYour IMMUCARE account and patient profile have been deleted.\n\nAccount Details:\nPatient ID: \nName:  \nEmail: janrusselpenafiel01172005@gmail.com\n\nThis means:\n1. Patient records removed\n2. User account deactivated\n3. Appointments cancelled\n4. Vaccination reminders stopped\n\nIf this was done in error, contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 04:09:48', '2025-11-13 04:09:48', '2025-11-12 20:09:48'),
(66, 20, 'IMMUCARE: Account Created Successfully', 'Dear sasdad ada,\n\nYour IMMUCARE account has been successfully created.\n\nAccount Details:\nEmail: janrusselpenafiel01172005@gmail.com\nPhone: 09677726912\nPassword: 12345678\n\nFor security reasons, please change your password after logging in.\n\nNeed help? Contact us:\nPhone: +1-800-IMMUCARE\nEmail: support@immucare.com\n\nBest regards,\nIMMUCARE Team', 'system', 0, '2025-11-13 04:10:08', '2025-11-13 04:10:08', '2025-11-12 20:10:08'),
(68, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 04:34:46', '2025-11-13 04:34:46', '2025-11-12 20:34:46'),
(70, 18, 'Test SMS Notification', 'This is a test message to verify SMS functionality.', 'system', 0, '2025-11-13 04:40:45', '2025-11-13 04:40:45', '2025-11-12 20:40:45'),
(71, 18, 'Appointment Status Update: Requested', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 04:42:24', '2025-11-13 04:42:24', '2025-11-12 20:42:24'),
(73, 18, 'Test SMS Notification', 'This is a test message to verify SMS functionality.', 'system', 0, '2025-11-13 04:46:18', '2025-11-13 04:46:18', '2025-11-12 20:46:18'),
(74, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: Test notification from admin system\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 04:47:05', '2025-11-13 04:47:05', '2025-11-12 20:47:05'),
(75, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 04:53:42', '2025-11-13 04:53:42', '2025-11-12 20:53:42'),
(76, 18, 'Appointment Status Update: Requested', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 04:56:25', '2025-11-13 04:56:25', '2025-11-12 20:56:25'),
(77, 18, 'Test Notification', 'Testing iProg SMS integration via NotificationSystem class.', 'system', 0, '2025-11-13 05:00:36', '2025-11-13 05:00:36', '2025-11-12 21:00:36'),
(78, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:01:09', '2025-11-13 05:01:09', '2025-11-12 21:01:09'),
(79, 18, 'Appointment Status Update: Requested', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:04:00', '2025-11-13 05:04:00', '2025-11-12 21:04:00'),
(80, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:05:21', '2025-11-13 05:05:21', '2025-11-12 21:05:21'),
(81, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:09:05', '2025-11-13 05:09:05', '2025-11-12 21:09:05'),
(82, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:10:12', '2025-11-13 05:10:12', '2025-11-12 21:10:12'),
(83, 18, 'Appointment Confirmed', 'Appointment status updated.\n\nDetails:\nPurpose: Hepatitis B vaccination\nDate: Friday, November 14, 2025\nTime: 03:59 AM\nStatus: Confirmed\n\nYour appointment is confirmed. Please arrive 15 minutes early.\n\nNotes: This is a test notification.\n\nQuestions? Please contact us.', 'system', 0, '2025-11-13 05:10:52', '2025-11-13 05:10:52', '2025-11-12 21:10:52'),
(84, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:12:05', '2025-11-13 05:12:05', '2025-11-12 21:12:05'),
(85, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:12:38', '2025-11-13 05:12:38', '2025-11-12 21:12:38'),
(86, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:13:59', '2025-11-13 05:13:59', '2025-11-12 21:13:59'),
(87, 18, 'Appointment Status Update: Confirmed', 'Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', 'system', 0, '2025-11-13 05:15:22', '2025-11-13 05:15:22', '2025-11-12 21:15:22'),
(88, 18, 'Appointment Status Update: Confirmed', 'IMMUCARE: Your appointment on Friday, November 14, 2025 at 03:59 AM is CONFIRMED. Please arrive 15 minutes early. Note: This is a test notification.', 'system', 0, '2025-11-13 05:15:57', '2025-11-13 05:15:57', '2025-11-12 21:15:57'),
(89, 18, 'Appointment Status Update: Requested', 'IMMUCARE: Your appointment on Friday, November 14, 2025 at 03:59 AM is UPDATED. Thank you. Note: da', 'system', 0, '2025-11-13 05:16:47', '2025-11-13 05:16:47', '2025-11-12 21:16:47'),
(91, 18, 'Health Check Reminder', 'asdada', 'system', 0, '2025-11-15 00:46:59', '2025-11-15 00:46:59', '2025-11-14 16:46:59'),
(92, 18, 'Health Check Reminder', 'asdada', 'system', 0, '2025-11-15 00:47:04', '2025-11-15 00:47:04', '2025-11-14 16:47:04'),
(93, 18, 'Vaccination Due', 'sadada', 'system', 0, '2025-11-15 00:47:57', '2025-11-15 00:47:57', '2025-11-14 16:47:57'),
(94, 18, 'Test Results Available', 'sada', 'system', 0, '2025-11-15 00:48:38', '2025-11-15 00:48:38', '2025-11-14 16:48:38'),
(96, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 5, 2025 - 1:58 AM', '', 0, NULL, '2025-11-15 01:55:50', '2025-11-14 17:55:50'),
(97, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 5, 2025 - 1:58 AM', '', 0, NULL, '2025-11-15 01:55:50', '2025-11-14 17:55:50'),
(98, 2, 'New Appointment Request', 'New appointment request from patient #13 for October 29, 2025 - 1:56 AM', '', 0, NULL, '2025-11-15 01:56:08', '2025-11-14 17:56:08'),
(99, 3, 'New Appointment Request', 'New appointment request from patient #13 for October 29, 2025 - 1:56 AM', '', 0, NULL, '2025-11-15 01:56:08', '2025-11-14 17:56:08'),
(100, 21, 'Appointment Cancellation Notice', 'IMMUCARE: Your appointment on Friday, December 5, 2025 at 01:43 AM has been CANCELLED. Please contact +1-800-SCHEDULE to reschedule if needed.', 'system', 0, '2025-11-15 02:02:48', '2025-11-15 02:02:48', '2025-11-14 18:02:48'),
(101, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 6, 2025 - 2:16 AM', '', 0, NULL, '2025-11-15 02:13:17', '2025-11-14 18:13:17'),
(102, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 6, 2025 - 2:16 AM', '', 0, NULL, '2025-11-15 02:13:17', '2025-11-14 18:13:17'),
(103, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 6, 2025 - 2:31 AM', '', 0, NULL, '2025-11-15 02:29:49', '2025-11-14 18:29:49'),
(104, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 6, 2025 - 2:31 AM', '', 0, NULL, '2025-11-15 02:29:49', '2025-11-14 18:29:49'),
(105, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 14, 2025 - 2:37 AM', '', 0, NULL, '2025-11-15 02:34:21', '2025-11-14 18:34:21'),
(106, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 14, 2025 - 2:37 AM', '', 0, NULL, '2025-11-15 02:34:21', '2025-11-14 18:34:21'),
(107, 18, 'Appointment Update', 'Your appointment status has been updated to: confirmed Notes: assa', 'system', 0, NULL, '2025-11-15 02:38:02', '2025-11-14 18:38:02'),
(108, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 21, 2025 - 2:52 AM', '', 0, NULL, '2025-11-15 02:50:51', '2025-11-14 18:50:51'),
(109, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 21, 2025 - 2:52 AM', '', 0, NULL, '2025-11-15 02:50:51', '2025-11-14 18:50:51'),
(110, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 20, 2025 - 2:54 AM', '', 0, NULL, '2025-11-15 02:52:31', '2025-11-14 18:52:31'),
(111, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 20, 2025 - 2:54 AM', '', 0, NULL, '2025-11-15 02:52:31', '2025-11-14 18:52:31'),
(112, 2, 'New Appointment Request', 'New appointment request from patient #13 for November 28, 2025 - 2:00 AM', '', 0, NULL, '2025-11-15 02:57:58', '2025-11-14 18:57:58'),
(113, 3, 'New Appointment Request', 'New appointment request from patient #13 for November 28, 2025 - 2:00 AM', '', 0, NULL, '2025-11-15 02:57:58', '2025-11-14 18:57:58'),
(114, 18, 'Appointment Status Update: Confirmed', 'IMMUCARE: Your appointment on Friday, November 28, 2025 at 02:00 AM is CONFIRMED. Please arrive 15 minutes early. Note: dada', 'system', 0, '2025-11-15 02:58:17', '2025-11-15 02:58:17', '2025-11-14 18:58:17'),
(115, 18, 'Appointment Status Update: Requested', 'IMMUCARE: Your appointment on Wednesday, November 5, 2025 at 01:58 AM is UPDATED. Thank you. Note: dasda', 'system', 0, '2025-11-15 03:04:08', '2025-11-15 03:04:08', '2025-11-14 19:04:08'),
(116, 18, 'Appointment Status Update: Requested', 'IMMUCARE: Your appointment on Friday, November 28, 2025 at 02:00 AM is UPDATED. Thank you. Note: dada', 'system', 0, '2025-11-15 03:07:15', '2025-11-15 03:07:15', '2025-11-14 19:07:15');

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
  `email` varchar(255) DEFAULT NULL,
  `medical_history` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `diagnosis` text DEFAULT NULL COMMENT 'Patient diagnosis information',
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `user_id`, `first_name`, `middle_name`, `last_name`, `date_of_birth`, `gender`, `blood_type`, `purok`, `city`, `province`, `postal_code`, `phone_number`, `email`, `medical_history`, `allergies`, `diagnosis`, `created_at`, `updated_at`) VALUES
(12, NULL, 'Stephany', 'asdada', 'lablab', '2002-01-20', 'male', 'B+', 'asda', 'dada', 'asda', '', '09677726912', NULL, 'dada', 'dada', '', '2025-07-01 11:06:58', '2025-11-12 19:27:06'),
(13, 18, 'hunter', 'Elizares', 'Peñafiel', '2013-06-12', 'male', NULL, 'asda', 'Santo Niño', 'Davao', '9509', '09677726912', NULL, 'asda', 'dada', NULL, '2025-11-13 03:55:39', '2025-11-12 19:55:39'),
(14, NULL, 'sasdad', 'Elizares', 'ada', '2025-11-25', 'male', 'B-', 'asd', 'Santo Niño', 'adada', '9509', '09677726912', NULL, 'dasda', 'asda', NULL, '2025-11-13 04:10:43', '2025-11-14 16:19:56');

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
  `user_id` int(11) DEFAULT NULL,
  `phone_number` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `notification_type` varchar(50) DEFAULT 'general',
  `provider_response` text DEFAULT NULL,
  `related_to` varchar(50) DEFAULT 'general',
  `related_id` int(11) DEFAULT NULL,
  `scheduled_at` datetime DEFAULT NULL,
  `sent_at` datetime NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sms_logs`
--

INSERT INTO `sms_logs` (`id`, `notification_id`, `patient_id`, `user_id`, `phone_number`, `message`, `reference_id`, `status`, `retry_count`, `notification_type`, `provider_response`, `related_to`, `related_id`, `scheduled_at`, `sent_at`, `created_at`, `updated_at`) VALUES
(18, NULL, 12, 15, '639920157536', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: bjhb\n- Date: Thursday, July 3, 2025\n- Time: 11:41 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: knbkbkj\n\nIf you have any questions or need to make changes, please contact us.', 'iSms-01Hi0l', 'sent', 0, 'custom_notification', 'SMS sent successfully', 'custom_notification', NULL, NULL, '2025-11-12 20:06:06', '2025-11-13 03:06:06', '2025-11-13 03:06:06'),
(19, NULL, 12, 15, '639920157536', 'IMMUCARE: Appointment Status Update: Requested - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: bjhb\n- Date: Thursday, July 3, 2025\n- Time: 11:41 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: knbkbkj\n\nIf you have any questions or need to make changes, please contact us.', 'iSms-n6SCd9', 'sent', 0, 'custom_notification', 'SMS sent successfully', 'custom_notification', NULL, NULL, '2025-11-12 20:15:14', '2025-11-13 03:15:14', '2025-11-13 03:15:14'),
(22, 68, 13, 18, '639677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 04:34:52', '2025-11-13 04:34:52', '2025-11-13 04:34:52'),
(23, 70, 13, 18, '639677726912', 'IMMUCARE: Test SMS Notification - This is a test message to verify SMS functionality.', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-2GfhbG\",\"message_status_link\":\"https://sms.iprogtech.com/api/v1/sms_messages/status?api_token=12345\\u0026message_id=iSms-2GfhbG\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1.0}', 'custom_notification', NULL, NULL, '2025-11-13 04:40:46', '2025-11-13 04:40:46', '2025-11-13 04:40:46'),
(24, 71, 13, 18, '639677726912', 'IMMUCARE: Appointment Status Update: Requested - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 04:42:30', '2025-11-13 04:42:30', '2025-11-13 04:42:30'),
(25, 73, 13, 18, '09677726912', 'IMMUCARE: Test SMS Notification - This is a test message to verify SMS functionality.', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-iVYpjm\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-iVYpjm\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-13 04:46:19', '2025-11-13 04:46:19', '2025-11-13 04:46:19'),
(26, 74, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: Test notification from admin system\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 04:47:11', '2025-11-13 04:47:11', '2025-11-13 04:47:11'),
(27, 75, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 04:53:48', '2025-11-13 04:53:48', '2025-11-13 04:53:48'),
(28, 76, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Requested - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 04:56:32', '2025-11-13 04:56:32', '2025-11-13 04:56:32'),
(29, 77, 13, 18, '09677726912', 'IMMUCARE: Test Notification - Testing iProg SMS integration via NotificationSystem class.', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-Em2p5z\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-Em2p5z\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-13 05:00:37', '2025-11-13 05:00:37', '2025-11-13 05:00:37'),
(30, 78, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:01:14', '2025-11-13 05:01:14', '2025-11-13 05:01:14'),
(31, 79, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Requested - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Requested\n\nThank you for scheduling with us.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:04:05', '2025-11-13 05:04:05', '2025-11-13 05:04:05'),
(32, 80, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:05:27', '2025-11-13 05:05:27', '2025-11-13 05:05:27'),
(33, 81, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:09:11', '2025-11-13 05:09:11', '2025-11-13 05:09:11'),
(34, 82, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:10:18', '2025-11-13 05:10:18', '2025-11-13 05:10:18'),
(35, 83, 13, 18, '09677726912', 'IMMUCARE: Appointment Confirmed - Appointment status updated.\n\nDetails:\nPurpose: Hepatitis B vaccination\nDate: Friday, November 14, 2025\nTime: 03:59 AM\nStatus: Confirmed\n\nYour appointment is confirmed. Please arrive 15 minutes early.\n\nNotes: This is a test notification.\n\nQuestions? Please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:10:58', '2025-11-13 05:10:58', '2025-11-13 05:10:58'),
(36, 84, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:12:11', '2025-11-13 05:12:11', '2025-11-13 05:12:11'),
(37, 85, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:12:45', '2025-11-13 05:12:45', '2025-11-13 05:12:45'),
(38, 86, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: da\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:14:05', '2025-11-13 05:14:05', '2025-11-13 05:14:05'),
(39, 87, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - Your appointment status has been updated.\n\nAppointment Details:\n- Purpose: Hepatitis B vaccination\n- Date: Friday, November 14, 2025\n- Time: 03:59 AM\n- New Status: Confirmed\n\nYour appointment has been confirmed. Please arrive 15 minutes early.\n\nAdditional Notes: This is a test notification.\n\nIf you have any questions or need to make changes, please contact us.', NULL, 'sent', 0, 'general', '{\"status\":500,\"message\":[\"Your input contains inappropriate language.\"]}', 'custom_notification', NULL, NULL, '2025-11-13 05:15:27', '2025-11-13 05:15:27', '2025-11-13 05:15:27'),
(40, 88, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - IMMUCARE: Your appointment on Friday, November 14, 2025 at 03:59 AM is CONFIRMED. Please arrive 15 minutes early. Note: This is a test notification.', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-zwLvZm\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-zwLvZm\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-13 05:16:02', '2025-11-13 05:16:02', '2025-11-13 05:16:02'),
(41, 89, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Requested - IMMUCARE: Your appointment on Friday, November 14, 2025 at 03:59 AM is UPDATED. Thank you. Note: da', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-NYhYVq\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-NYhYVq\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-13 05:16:52', '2025-11-13 05:16:52', '2025-11-13 05:16:52'),
(42, 92, 13, 18, '09677726912', 'IMMUCARE: Health Check Reminder - asdada', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-ydP9Am\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-ydP9Am\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 00:47:06', '2025-11-15 00:47:06', '2025-11-15 00:47:06'),
(43, 93, 13, 18, '09677726912', 'IMMUCARE: Vaccination Due - sadada', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-rZgrcC\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-rZgrcC\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 00:47:58', '2025-11-15 00:47:58', '2025-11-15 00:47:58'),
(44, 94, 13, 18, '09677726912', 'IMMUCARE: Test Results Available - sada', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-KM2xkX\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-KM2xkX\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 00:48:39', '2025-11-15 00:48:39', '2025-11-15 00:48:39'),
(46, 114, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Confirmed - IMMUCARE: Your appointment on Friday, November 28, 2025 at 02:00 AM is CONFIRMED. Please arrive 15 minutes early. Note: dada', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-Qwrzri\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-Qwrzri\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 02:58:24', '2025-11-15 02:58:24', '2025-11-15 02:58:24'),
(47, 115, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Requested - IMMUCARE: Your appointment on Wednesday, November 5, 2025 at 01:58 AM is UPDATED. Thank you. Note: dasda', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-czZuEE\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-czZuEE\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 03:04:16', '2025-11-15 03:04:16', '2025-11-15 03:04:16'),
(48, 116, 13, 18, '09677726912', 'IMMUCARE: Appointment Status Update: Requested - IMMUCARE: Your appointment on Friday, November 28, 2025 at 02:00 AM is UPDATED. Thank you. Note: dada', NULL, 'sent', 0, 'general', '{\"status\":200,\"message\":\"SMS successfully queued for delivery.\",\"message_id\":\"iSms-vQsqa4\",\"message_status_link\":\"https:\\/\\/sms.iprogtech.com\\/api\\/v1\\/sms_messages\\/status?api_token=12345&message_id=iSms-vQsqa4\",\"message_status_request_mode\":\"GET\",\"sms_rate\":1}', 'custom_notification', NULL, NULL, '2025-11-15 03:07:22', '2025-11-15 03:07:22', '2025-11-15 03:07:22');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `setting_type` enum('text','boolean','number','json') DEFAULT 'text',
  `is_public` tinyint(1) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `description`, `setting_type`, `is_public`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'sms_provider', 'iprog', 'SMS service provider (iprog)', 'text', 0, NULL, '2025-06-30 20:52:14', '2025-11-12 20:33:28'),
(2, 'sms_enabled', 'true', 'Enable/disable SMS notifications', 'boolean', 0, NULL, '2025-06-30 20:52:14', '2025-11-12 16:22:07'),
(3, 'email_enabled', 'true', 'Enable/disable email notifications', 'boolean', 0, NULL, '2025-06-30 20:52:14', '2025-11-12 16:22:07'),
(4, 'appointment_reminder_days', '2', 'Days before appointment to send reminder', 'number', 1, 1, '2025-06-30 20:52:14', '2025-11-12 16:22:07'),
(5, 'auto_sync_mhc', 'true', 'Automatically sync data with Municipal Health Center', 'text', 0, 1, '2025-06-30 20:52:14', '2025-08-02 06:56:29'),
(8, 'smtp_host', 'smtp.gmail.com', 'SMTP Server Host', 'text', 0, 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(9, 'smtp_port', '587', 'SMTP Server Port', 'text', 0, 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(10, 'smtp_user', 'vmctaccollege@gmail.com', 'SMTP Username', 'text', 0, 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(11, 'smtp_pass', 'tqqs fkkh lbuz jbeg', 'SMTP Password', 'text', 0, 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(12, 'smtp_secure', 'tls', 'SMTP Security Type (tls/ssl)', 'text', 0, 1, '2025-07-01 09:04:09', '2025-07-01 01:04:09'),
(15, 'sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'IPROG SMS API key', 'text', 0, NULL, '0000-00-00 00:00:00', '2025-11-12 16:22:07'),
(18, 'immunization_reminder_days', '7', 'Days before due date to send immunization reminder', 'number', 1, NULL, '0000-00-00 00:00:00', '2025-11-12 16:22:07'),
(19, 'max_sms_retries', '3', 'Maximum number of SMS retry attempts', 'number', 0, NULL, '0000-00-00 00:00:00', '2025-11-12 16:22:07'),
(20, 'sms_rate_limit', '100', 'Maximum SMS messages per hour', 'number', 0, NULL, '0000-00-00 00:00:00', '2025-11-12 16:22:07'),
(29, 'iprogsms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'iProgSMS API Key for sending SMS notifications', 'text', 0, NULL, '2025-11-13 03:59:49', '2025-11-12 20:00:46'),
(30, 'iprogsms_sender_id', 'IMMUCARE', 'Sender ID for iProgSMS', 'text', 0, NULL, '2025-11-13 03:59:49', '2025-11-12 20:00:46'),
(35, 'iprog_sms_api_key', '1ef3b27ea753780a90cbdf07d027fb7b52791004', 'IProg SMS API Key', 'text', 0, NULL, '2025-11-13 04:33:28', '2025-11-12 20:33:28'),
(36, 'iprog_sms_sender_id', 'IMMUCARE', 'IProg SMS Sender ID', 'text', 0, NULL, '2025-11-13 04:33:28', '2025-11-12 20:33:28');

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
(1, 1, 'admin', 'System Admin', 'penafielliezl1122@gmail.com', '+1234567890', '12345678', NULL, NULL, 1, '2025-11-16 05:24:09', '2025-06-30 20:52:14', '2025-11-15 21:24:09'),
(2, 2, 'midwife', 'Jane Midwife', 'penafielliezl5555@gmail.com', '+1234567891', '12345678', NULL, NULL, 1, '2025-11-15 23:11:52', '2025-06-30 20:52:14', '2025-11-15 15:11:52'),
(3, 3, 'nurse', 'John Nurse', 'penafielliezl3322@gmail.com', '+1234567892', '12345678', NULL, NULL, 1, '2025-11-15 23:06:28', '2025-06-30 20:52:14', '2025-11-15 15:06:28'),
(4, 4, 'patient', 'Test Patient', 'penafielliezl9999@gmail.com', '09677726912', '$2y$10$i4nrJwhdt1o6A.LGZrcWLOAwws.oIQzkAKqI/H9sOglnCZ0xQt1CS', NULL, NULL, 1, '2025-07-21 20:08:04', '2025-06-30 20:52:14', '2025-11-12 16:08:17'),
(18, 4, 'patient', 'hunter', 'artiedastephany@gmail.com', '09677726912', '12345678', NULL, NULL, 1, '2025-11-15 23:15:52', '2025-11-13 03:54:51', '2025-11-15 15:15:52'),
(20, 4, 'patient', 'sasdad ada', 'janrusselpenafiel01172005@gmail.com', '09677726912', '12345678', NULL, NULL, 1, '2025-11-15 00:34:49', '2025-11-13 04:10:08', '2025-11-14 16:34:49');

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
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

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
  ADD KEY `notification_id` (`notification_id`),
  ADD KEY `idx_sms_logs_patient_status` (`patient_id`,`status`),
  ADD KEY `idx_sms_logs_type_created` (`notification_type`,`created_at`),
  ADD KEY `idx_sms_logs_scheduled_status` (`scheduled_at`,`status`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `data_transfers`
--
ALTER TABLE `data_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `health_centers`
--
ALTER TABLE `health_centers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `immunizations`
--
ALTER TABLE `immunizations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=117;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `sms_logs`
--
ALTER TABLE `sms_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

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
