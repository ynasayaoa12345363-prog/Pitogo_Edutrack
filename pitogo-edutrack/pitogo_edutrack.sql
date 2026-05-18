-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2026 at 08:36 AM
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
-- Database: `pitogo_edutrack`
--

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_parents`
-- (See below for the actual view)
--
CREATE TABLE `active_parents` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_staff`
-- (See below for the actual view)
--
CREATE TABLE `active_staff` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `active_students`
-- (See below for the actual view)
--
CREATE TABLE `active_students` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`id_num` varchar(100)
,`grade_level` varchar(50)
,`section` varchar(100)
,`parent_access_code` varchar(50)
,`qr_token` varchar(255)
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `approved_users`
-- (See below for the actual view)
--
CREATE TABLE `approved_users` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`role` enum('superadmin','admin','staff','student','parent')
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `archived_users`
--

CREATE TABLE `archived_users` (
  `id` int(11) NOT NULL,
  `original_user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `id_num` varchar(100) DEFAULT NULL,
  `archived_by` int(11) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL,
  `archived_at` datetime NOT NULL DEFAULT current_timestamp(),
  `json_data` longtext DEFAULT NULL,
  `status_before` varchar(50) DEFAULT NULL,
  `status_after` varchar(50) DEFAULT 'archived',
  `snapshot_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `archived_users`
--

INSERT INTO `archived_users` (`id`, `original_user_id`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `id_num`, `archived_by`, `archive_reason`, `archived_at`, `json_data`, `status_before`, `status_after`, `snapshot_json`) VALUES
(2, 14, 'Miguel', '', 'Salvador', 'jmiguel0904@gmail.com', 'student', '345678909876', 1, NULL, '2026-05-16 23:30:26', NULL, 'active', 'archived', '{\"id\":14,\"first_name\":\"Miguel\",\"middle_name\":\"\",\"last_name\":\"Salvador\",\"email\":\"jmiguel0904@gmail.com\",\"password\":\"$2y$10$vd84wcRPxVRIVanSCUo73OEy8bL4rUK7C0TLuRKZ0d3xNnPRzVwZ2\",\"role\":\"student\",\"id_num\":\"345678909876\",\"phone_number\":null,\"contact_number\":null,\"address\":null,\"profile_pic\":null,\"grade_level\":null,\"section\":null,\"guardian_name\":null,\"guardian_contact\":null,\"guardian_relationship\":null,\"emergency_contact_name\":null,\"emergency_contact_number\":null,\"emergency_contact_relationship\":null,\"qr_token\":\"eefa8320a779e16dacee3d9d35a997cb484fef893d974f7d3e6edd0b84236860\",\"parent_access_code\":\"PMGXUVJJTV\",\"admin_code\":null,\"staff_code\":null,\"information_correct\":1,\"otp_code\":null,\"otp_expiry\":null,\"email_verified_at\":\"2026-05-16 23:03:12\",\"status\":\"active\",\"last_login\":null,\"is_archived\":0,\"is_deleted\":0,\"created_at\":\"2026-05-16 23:02:59\",\"updated_at\":\"2026-05-16 23:08:10\",\"profile_photo\":null,\"profile_picture\":null,\"grade\":null,\"emergency_contact_person\":null,\"phone\":null,\"relationship_to_student\":null,\"occupation\":null}'),
(4, 9, 'Shanksu', '', 'Shanley', 'yoshgrei4@gmail.com', 'staff', NULL, 19, 'Archived by admin', '2026-05-17 12:36:08', NULL, 'active', 'archived', '{\"id\":9,\"first_name\":\"Shanksu\",\"middle_name\":\"\",\"last_name\":\"Shanley\",\"email\":\"yoshgrei4@gmail.com\",\"password\":\"$2y$10$lgNw0RNBonoZ.i47/6cbuugM/ihjbQJZs3DSyf.H5zIdv4tiM6Ii.\",\"role\":\"staff\",\"id_num\":null,\"phone_number\":null,\"contact_number\":null,\"address\":null,\"profile_pic\":null,\"grade_level\":null,\"section\":null,\"guardian_name\":null,\"guardian_contact\":null,\"guardian_relationship\":null,\"emergency_contact_name\":null,\"emergency_contact_number\":null,\"emergency_contact_relationship\":null,\"qr_token\":null,\"parent_access_code\":null,\"admin_code\":null,\"staff_code\":\"EDUTRACK-STAFF-2026-FSPV2U67\",\"information_correct\":1,\"otp_code\":null,\"otp_expiry\":null,\"email_verified_at\":\"2026-05-16 22:51:35\",\"status\":\"active\",\"last_login\":null,\"is_archived\":0,\"is_deleted\":0,\"created_at\":\"2026-05-16 22:51:19\",\"updated_at\":\"2026-05-16 23:08:18\",\"profile_photo\":null,\"profile_picture\":null,\"grade\":null,\"emergency_contact_person\":null,\"phone\":null,\"relationship_to_student\":null,\"occupation\":null}'),
(5, 8501, 'Archived', '', 'Student1', 'archivedstudent1@edutrack.com', 'student', '202633333001', 1, 'Graduated', '2026-02-01 08:00:00', NULL, 'active', 'archived', NULL),
(6, 8502, 'Archived', '', 'Student2', 'archivedstudent2@edutrack.com', 'student', '202633333002', 1, 'Transferred school', '2026-02-03 09:15:00', NULL, 'active', 'archived', NULL),
(7, 8503, 'Archived', '', 'Parent1', 'archivedparent1@edutrack.com', 'parent', NULL, 1, 'Inactive account', '2026-02-05 10:20:00', NULL, 'active', 'archived', NULL),
(8, 8504, 'Archived', '', 'Staff1', 'archivedstaff1@edutrack.com', 'staff', NULL, 1, 'End of contract', '2026-02-06 11:30:00', NULL, 'active', 'archived', NULL),
(9, 8505, 'Archived', '', 'Student3', 'archivedstudent3@edutrack.com', 'student', '202633333003', 1, 'Old record cleanup', '2026-02-08 01:45:00', NULL, 'pending', 'archived', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `archive_users`
-- (See below for the actual view)
--
CREATE TABLE `archive_users` (
`id` int(11)
,`original_user_id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`role` varchar(50)
,`id_num` varchar(100)
,`archived_by` int(11)
,`archive_reason` text
,`archived_at` datetime
,`json_data` longtext
);

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `status` enum('Present','Late','Absent') NOT NULL DEFAULT 'Present',
  `attendance_type` enum('qr','manual') NOT NULL DEFAULT 'qr',
  `scanned_by` int(11) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`id`, `student_id`, `attendance_date`, `time_in`, `time_out`, `status`, `attendance_type`, `scanned_by`, `remarks`, `created_at`) VALUES
(1, 6, '2026-05-16', '18:11:23', NULL, 'Present', 'qr', 5, NULL, '2026-05-16 10:11:23'),
(2, 6, '2026-03-08', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(3, 10, '2026-03-20', '07:15:00', NULL, 'Present', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(4, 11, '2026-03-04', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(5, 12, '2026-03-11', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(6, 13, '2026-03-13', '07:15:00', NULL, 'Late', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(7, 14, '2026-03-19', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(8, 15, '2026-03-04', '07:15:00', NULL, 'Late', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(9, 16, '2026-03-14', '07:15:00', NULL, 'Late', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(10, 21, '2026-03-10', '07:15:00', NULL, 'Present', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(11, 22, '2026-03-08', '07:15:00', NULL, 'Present', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(12, 23, '2026-03-25', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(13, 24, '2026-03-01', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(14, 25, '2026-03-28', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(15, 26, '2026-03-20', '07:15:00', NULL, 'Present', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(16, 27, '2026-03-02', '07:15:00', NULL, 'Late', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(17, 28, '2026-03-24', '07:15:00', NULL, 'Present', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(18, 29, '2026-03-12', '07:15:00', NULL, 'Late', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(19, 30, '2026-03-16', '07:15:00', NULL, 'Absent', 'qr', NULL, NULL, '2026-05-17 09:07:12'),
(68, 6, '2026-01-30', '07:38:00', '16:17:00', 'Absent', 'qr', NULL, 'Excused absence', '2026-01-25 23:00:00'),
(69, 10, '2026-01-16', '07:02:00', '16:13:00', 'Late', 'manual', NULL, 'No attendance', '2026-02-26 23:00:00'),
(70, 11, '2026-02-06', NULL, '16:08:00', 'Absent', 'manual', NULL, 'On time', '2026-03-10 23:00:00'),
(71, 12, '2026-01-21', '07:44:00', '16:14:00', 'Absent', 'manual', NULL, 'No attendance', '2026-03-05 23:00:00'),
(72, 13, '2026-01-09', '07:16:00', '16:06:00', 'Late', 'manual', NULL, 'On time', '2026-01-17 23:00:00'),
(73, 15, '2026-02-24', NULL, NULL, 'Absent', 'manual', NULL, 'Late arrival', '2026-03-08 23:00:00'),
(74, 16, '2026-02-08', '07:44:00', '16:08:00', 'Late', 'qr', NULL, 'Late arrival', '2026-01-26 23:00:00'),
(75, 21, '2026-02-10', '07:24:00', '16:13:00', 'Absent', 'manual', NULL, 'No attendance', '2026-03-05 23:00:00'),
(76, 22, '2026-01-10', '07:37:00', '16:04:00', 'Absent', 'qr', NULL, 'Recorded by system', '2026-01-17 23:00:00'),
(77, 23, '2026-01-12', '07:26:00', '16:19:00', 'Present', 'manual', NULL, 'Late arrival', '2026-01-25 23:00:00'),
(78, 24, '2026-02-14', NULL, '16:05:00', 'Late', 'qr', NULL, 'Recorded by system', '2026-01-30 23:00:00'),
(79, 25, '2026-01-21', NULL, '16:15:00', 'Absent', 'manual', NULL, 'Recorded by system', '2026-01-28 23:00:00'),
(80, 26, '2026-01-15', '07:03:00', '16:02:00', 'Absent', 'qr', NULL, 'Late arrival', '2026-01-17 23:00:00'),
(81, 27, '2026-02-01', '07:24:00', '16:13:00', 'Late', 'qr', NULL, 'Recorded by system', '2026-02-18 23:00:00'),
(82, 28, '2026-02-01', NULL, '16:03:00', 'Late', 'manual', NULL, 'No attendance', '2026-02-26 23:00:00'),
(83, 29, '2026-02-14', '07:11:00', '16:15:00', 'Late', 'manual', NULL, 'Late arrival', '2026-02-17 23:00:00'),
(84, 30, '2026-01-13', '07:40:00', '16:01:00', 'Absent', 'manual', NULL, 'On time', '2026-02-28 23:00:00'),
(85, 64, '2026-01-30', '07:10:00', '16:03:00', 'Late', 'qr', NULL, 'Recorded by system', '2026-01-05 23:00:00'),
(86, 65, '2026-02-12', '07:29:00', '16:10:00', 'Absent', 'qr', NULL, 'No attendance', '2026-01-24 23:00:00'),
(87, 66, '2026-03-03', '07:29:00', '16:00:00', 'Present', 'qr', NULL, 'Recorded by system', '2026-03-07 23:00:00'),
(88, 67, '2026-02-04', '07:13:00', '16:19:00', 'Absent', 'manual', NULL, 'Recorded by system', '2026-01-26 23:00:00'),
(89, 68, '2026-03-05', '07:01:00', '16:17:00', 'Absent', 'manual', NULL, 'Excused absence', '2026-02-16 23:00:00'),
(90, 69, '2026-03-15', NULL, '16:11:00', 'Present', 'manual', NULL, 'No attendance', '2026-01-19 23:00:00'),
(91, 70, '2026-02-16', '07:01:00', NULL, 'Present', 'qr', NULL, 'No attendance', '2026-02-05 23:00:00'),
(92, 71, '2026-03-03', '07:43:00', '16:19:00', 'Late', 'qr', NULL, 'On time', '2026-02-07 23:00:00'),
(93, 72, '2026-02-04', '07:08:00', '16:16:00', 'Late', 'manual', NULL, 'Recorded by system', '2026-03-10 23:00:00'),
(94, 73, '2026-02-25', '07:12:00', '16:09:00', 'Late', 'manual', NULL, 'Late arrival', '2026-03-13 23:00:00'),
(114, 6, '2026-05-01', '07:03:00', '04:05:00', 'Present', 'qr', NULL, 'On time', '2026-04-30 23:03:12'),
(115, 6, '2026-05-02', '07:16:00', '04:10:00', 'Late', 'qr', NULL, 'Late arrival', '2026-05-01 23:16:35'),
(116, 6, '2026-05-03', '06:58:00', '04:00:00', 'Present', 'qr', NULL, 'Early arrival', '2026-05-02 22:58:44'),
(117, 6, '2026-05-06', NULL, NULL, 'Absent', 'manual', NULL, 'No attendance recorded', '2026-05-06 00:30:00'),
(118, 6, '2026-05-07', '07:05:00', '04:08:00', 'Present', 'qr', NULL, 'On time', '2026-05-06 23:05:21'),
(119, 6, '2026-05-08', '07:24:00', '04:12:00', 'Late', 'qr', NULL, 'Traffic delay', '2026-05-07 23:24:19'),
(120, 6, '2026-05-10', '07:01:00', '04:03:00', 'Present', 'qr', NULL, 'On time', '2026-05-09 23:01:50'),
(121, 6, '2026-05-13', '06:55:00', '04:01:00', 'Present', 'qr', NULL, 'Very early', '2026-05-12 22:55:33'),
(122, 6, '2026-05-14', NULL, NULL, 'Absent', 'manual', NULL, 'Excused absence', '2026-05-14 01:00:00'),
(123, 6, '2026-05-15', '07:09:00', '04:06:00', 'Present', 'qr', NULL, 'On time', '2026-05-14 23:09:28'),
(124, 6, '2026-05-18', '11:46:11', NULL, 'Present', 'qr', 5, NULL, '2026-05-18 03:46:11'),
(125, 10, '2026-05-18', '11:59:41', NULL, 'Present', 'qr', 5, NULL, '2026-05-18 03:59:41'),
(126, 11, '2026-05-18', '12:09:36', NULL, 'Present', 'qr', 5, NULL, '2026-05-18 04:09:36');

-- --------------------------------------------------------

--
-- Table structure for table `attendance_scan_logs`
--

CREATE TABLE `attendance_scan_logs` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `scanned_by` int(11) DEFAULT NULL,
  `qr_token_scanned` varchar(255) DEFAULT NULL,
  `scan_result` enum('success','duplicate','invalid','inactive','error') NOT NULL,
  `message` text DEFAULT NULL,
  `scanned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance_scan_logs`
--

INSERT INTO `attendance_scan_logs` (`id`, `student_id`, `scanned_by`, `qr_token_scanned`, `scan_result`, `message`, `scanned_at`) VALUES
(1, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'success', 'QR attendance successfully recorded.', '2026-04-30 23:03:12'),
(2, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'duplicate', 'Student already scanned for today.', '2026-04-30 23:10:55'),
(3, 6, NULL, 'INVALIDQR123456', 'invalid', 'Invalid QR token detected.', '2026-05-02 23:40:11'),
(4, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'inactive', 'Account inactive. Scan denied.', '2026-05-05 23:15:20'),
(5, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'error', 'Scanner connection timeout.', '2026-05-07 23:01:45'),
(6, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'success', 'QR attendance successfully recorded.', '2026-05-09 22:58:33'),
(7, 6, NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'success', 'QR attendance successfully recorded.', '2026-05-12 22:55:18');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `log_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT NULL,
  `category` varchar(100) DEFAULT 'System',
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `target_table` varchar(100) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `timestamp` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `log_id`, `user_id`, `admin_id`, `user_role`, `category`, `action`, `details`, `target_table`, `target_id`, `target_user_id`, `ip_address`, `created_at`, `timestamp`) VALUES
(1, NULL, 1, NULL, 'superadmin', 'System', 'Manually updated registration codes', NULL, 'system_settings', NULL, NULL, '::1', '2026-05-16 08:19:16', NULL),
(2, NULL, 5, NULL, 'staff', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 5, NULL, '::1', '2026-05-16 08:39:28', NULL),
(3, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 5, NULL, '::1', '2026-05-16 08:40:00', NULL),
(4, NULL, 6, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 6, NULL, '::1', '2026-05-16 08:45:24', NULL),
(5, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 6, NULL, '::1', '2026-05-16 08:45:42', NULL),
(6, NULL, 6, NULL, NULL, 'System', 'Updated student profile', NULL, NULL, NULL, NULL, NULL, '2026-05-16 09:59:22', NULL),
(7, NULL, 6, NULL, NULL, 'System', 'Changed password from student settings', NULL, NULL, NULL, NULL, NULL, '2026-05-16 10:08:15', NULL),
(8, NULL, 5, NULL, 'staff', 'System', 'Marked QR attendance for Denisse Pacis Ibanga', NULL, 'attendance', 1, NULL, '::1', '2026-05-16 10:11:23', NULL),
(9, NULL, 7, NULL, 'parent', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 7, NULL, '::1', '2026-05-16 13:04:22', NULL),
(10, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 7, NULL, '::1', '2026-05-16 13:04:51', NULL),
(11, NULL, 7, NULL, NULL, 'System', 'Updated parent profile photo', NULL, NULL, NULL, NULL, NULL, '2026-05-16 14:23:31', NULL),
(12, NULL, 7, NULL, NULL, 'System', 'Updated parent profile information', NULL, NULL, NULL, NULL, NULL, '2026-05-16 14:23:58', NULL),
(13, NULL, 7, NULL, NULL, 'System', 'Changed password from parent settings', NULL, NULL, NULL, NULL, NULL, '2026-05-16 14:46:55', NULL),
(14, NULL, 1, NULL, 'superadmin', 'System', 'Generated new staff registration code', NULL, 'registration_codes', 1, NULL, '::1', '2026-05-16 14:48:43', NULL),
(15, NULL, 1, NULL, 'superadmin', 'System', 'Generated new admin registration code', NULL, 'registration_codes', 2, NULL, '::1', '2026-05-16 14:49:01', NULL),
(16, NULL, 8, NULL, 'staff', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 8, NULL, '::1', '2026-05-16 14:50:27', NULL),
(17, NULL, 9, NULL, 'staff', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 9, NULL, '::1', '2026-05-16 14:51:35', NULL),
(18, NULL, 10, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 10, NULL, '::1', '2026-05-16 14:53:16', NULL),
(19, NULL, 11, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 11, NULL, '::1', '2026-05-16 14:54:43', NULL),
(20, NULL, 12, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 12, NULL, '::1', '2026-05-16 14:56:13', NULL),
(21, NULL, 13, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 13, NULL, '::1', '2026-05-16 15:01:47', NULL),
(22, NULL, 14, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 14, NULL, '::1', '2026-05-16 15:03:12', NULL),
(23, NULL, 15, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 15, NULL, '::1', '2026-05-16 15:05:18', NULL),
(24, NULL, 16, NULL, 'student', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 16, NULL, '::1', '2026-05-16 15:06:11', NULL),
(25, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 16, NULL, '::1', '2026-05-16 15:08:04', NULL),
(26, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 15, NULL, '::1', '2026-05-16 15:08:09', NULL),
(27, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 14, NULL, '::1', '2026-05-16 15:08:10', NULL),
(28, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 13, NULL, '::1', '2026-05-16 15:08:12', NULL),
(29, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 12, NULL, '::1', '2026-05-16 15:08:13', NULL),
(30, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 11, NULL, '::1', '2026-05-16 15:08:15', NULL),
(31, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 10, NULL, '::1', '2026-05-16 15:08:17', NULL),
(32, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 9, NULL, '::1', '2026-05-16 15:08:18', NULL),
(33, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 8, NULL, '::1', '2026-05-16 15:08:20', NULL),
(34, NULL, 10, NULL, NULL, 'System', 'Updated student profile', NULL, NULL, NULL, NULL, NULL, '2026-05-16 15:11:24', NULL),
(35, NULL, 10, NULL, 'student', 'System', 'Generated parent access code', NULL, 'parent_access_codes', 9, NULL, '::1', '2026-05-16 15:11:43', NULL),
(36, NULL, 10, NULL, 'student', 'System', 'Generated parent access code', NULL, 'parent_access_codes', 10, NULL, '::1', '2026-05-16 15:14:17', NULL),
(37, NULL, 10, NULL, 'student', 'System', 'Generated parent access code', NULL, 'parent_access_codes', 11, NULL, '::1', '2026-05-16 15:15:32', NULL),
(38, NULL, 17, NULL, 'parent', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 17, NULL, '::1', '2026-05-16 15:16:45', NULL),
(39, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 17, NULL, '::1', '2026-05-16 15:24:30', NULL),
(40, NULL, 1, NULL, NULL, 'System', 'Bulk user action: archive | Processed: 2 | Skipped: 0', NULL, NULL, NULL, NULL, NULL, '2026-05-16 09:30:26', NULL),
(41, NULL, 1, NULL, 'superadmin', 'System', 'Restored archived user account', NULL, 'users', 12, NULL, '::1', '2026-05-16 15:31:45', NULL),
(42, NULL, 1, NULL, 'superadmin', 'System', 'Restored archived user account', NULL, 'users', 12, NULL, '::1', '2026-05-16 15:32:07', NULL),
(43, NULL, 1, NULL, 'superadmin', 'System', 'Restored archived user account', NULL, 'users', 12, NULL, '::1', '2026-05-16 15:32:11', NULL),
(44, NULL, 1, NULL, 'superadmin', 'System', 'Deleted user account', NULL, 'users', 12, NULL, '::1', '2026-05-16 15:34:52', NULL),
(45, NULL, 1, NULL, 'superadmin', 'System', 'Restored user from archived_users to users', NULL, 'users', 12, NULL, '::1', '2026-05-17 04:18:38', NULL),
(46, NULL, 1, NULL, 'superadmin', 'System', 'Moved user from users to archived_users', NULL, 'archived_users', 8, NULL, '::1', '2026-05-17 04:19:22', NULL),
(47, NULL, 1, NULL, 'superadmin', 'System', 'Moved archived user to deleted_users and removed from archived_users', NULL, 'deleted_users', 8, NULL, '::1', '2026-05-17 04:20:15', NULL),
(48, NULL, 19, NULL, 'admin', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 19, NULL, '::1', '2026-05-17 04:23:42', NULL),
(49, NULL, 1, NULL, 'superadmin', 'System', 'Approved user account', NULL, 'users', 19, NULL, '::1', '2026-05-17 04:24:00', NULL),
(50, NULL, 20, NULL, 'admin', 'System', 'Verified registration OTP and awaiting admin approval', NULL, 'users', 20, NULL, '::1', '2026-05-17 04:31:54', NULL),
(51, NULL, 19, NULL, 'admin', 'System', 'Moved user from users to archived_users', NULL, 'archived_users', 9, NULL, '::1', '2026-05-17 04:36:08', NULL),
(52, NULL, 1, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-03-24 00:00:00', NULL),
(53, NULL, 19, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-04-28 00:00:00', NULL),
(54, NULL, 20, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-02-13 00:00:00', NULL),
(55, NULL, 62, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-01-10 00:00:00', NULL),
(56, NULL, 63, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-01-28 00:00:00', NULL),
(57, NULL, 5, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-03-27 00:00:00', NULL),
(58, NULL, 36, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-03-02 00:00:00', NULL),
(59, NULL, 37, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-01-18 00:00:00', NULL),
(60, NULL, 38, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-01-27 00:00:00', NULL),
(61, NULL, 39, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-04-14 00:00:00', NULL),
(62, NULL, 40, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-02-21 00:00:00', NULL),
(63, NULL, 6, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-02-22 00:00:00', NULL),
(64, NULL, 10, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-02-16 00:00:00', NULL),
(65, NULL, 11, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-02-17 00:00:00', NULL),
(66, NULL, 12, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-01-10 00:00:00', NULL),
(67, NULL, 13, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-04-19 00:00:00', NULL),
(68, NULL, 14, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-03-07 00:00:00', NULL),
(69, NULL, 15, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-01-23 00:00:00', NULL),
(70, NULL, 16, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-03-28 00:00:00', NULL),
(71, NULL, 21, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-04-25 00:00:00', NULL),
(72, NULL, 22, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-03-27 00:00:00', NULL),
(73, NULL, 23, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-04-17 00:00:00', NULL),
(74, NULL, 24, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-03-01 00:00:00', NULL),
(75, NULL, 25, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-02-20 00:00:00', NULL),
(76, NULL, 26, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-03-14 00:00:00', NULL),
(77, NULL, 27, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-03-22 00:00:00', NULL),
(78, NULL, 28, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-01-29 00:00:00', NULL),
(79, NULL, 29, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-03-06 00:00:00', NULL),
(80, NULL, 30, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-01-02 00:00:00', NULL),
(81, NULL, 7, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-02-20 00:00:00', NULL),
(82, NULL, 17, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-04-10 00:00:00', NULL),
(83, NULL, 31, NULL, NULL, 'System', 'Approved a request', NULL, NULL, NULL, NULL, NULL, '2026-04-28 00:00:00', NULL),
(84, NULL, 32, NULL, NULL, 'System', 'Logged into the system', NULL, NULL, NULL, NULL, NULL, '2026-01-25 00:00:00', NULL),
(85, NULL, 33, NULL, NULL, 'System', 'Updated settings', NULL, NULL, NULL, NULL, NULL, '2026-01-07 00:00:00', NULL),
(86, NULL, 34, NULL, NULL, 'System', 'Archived a user', NULL, NULL, NULL, NULL, NULL, '2026-01-26 00:00:00', NULL),
(87, NULL, 35, NULL, NULL, 'System', 'Viewed attendance', NULL, NULL, NULL, NULL, NULL, '2026-03-04 00:00:00', NULL),
(115, NULL, 6, NULL, 'student', 'System', 'Submitted document request', NULL, 'document_requests', 166, NULL, '::1', '2026-05-17 09:47:20', NULL),
(116, NULL, 19, NULL, 'admin', 'System', 'Released digital document with OTP', NULL, 'document_requests', 166, NULL, '::1', '2026-05-17 09:47:47', NULL),
(117, NULL, 6, NULL, 'student', 'System', 'Submitted document request', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:00:23', NULL),
(118, NULL, 19, NULL, 'admin', 'System', 'Rejected document request', NULL, 'document_requests', 147, NULL, '::1', '2026-05-17 10:00:55', NULL),
(119, NULL, 19, NULL, 'admin', 'System', 'Approved document request', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:01:12', NULL),
(120, NULL, 19, NULL, 'admin', 'System', 'Released digital document and emailed OTP to requester', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:01:28', NULL),
(121, NULL, 6, NULL, 'student', 'System', 'Verified OTP for document access', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:02:07', NULL),
(122, NULL, 6, NULL, 'student', 'System', 'Opened secure document using OTP', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:19:12', NULL),
(123, NULL, 6, NULL, 'student', 'System', 'Opened secure document using OTP', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:19:19', NULL),
(124, NULL, 6, NULL, 'student', 'System', 'Opened secure document using OTP', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:19:46', NULL),
(125, NULL, 6, NULL, 'student', 'System', 'Submitted document request', NULL, 'document_requests', 168, NULL, '::1', '2026-05-17 10:32:43', NULL),
(126, NULL, 1, NULL, 'superadmin', 'System', 'Approved document request', NULL, 'document_requests', 168, NULL, '::1', '2026-05-17 10:33:16', NULL),
(127, NULL, 6, NULL, 'student', 'System', 'Submitted document request', NULL, 'document_requests', 169, NULL, '::1', '2026-05-17 10:38:19', NULL),
(128, NULL, 19, NULL, 'admin', 'System', 'Approved document request', NULL, 'document_requests', 169, NULL, '::1', '2026-05-17 10:38:54', NULL),
(129, NULL, 19, NULL, 'admin', 'System', 'Released digital document and emailed OTP to requester', NULL, 'document_requests', 169, NULL, '::1', '2026-05-17 10:39:14', NULL),
(130, NULL, 6, NULL, 'student', 'System', 'Opened secure document using OTP', NULL, 'document_requests', 167, NULL, '::1', '2026-05-17 10:40:06', NULL),
(131, NULL, 5, NULL, 'staff', 'System', 'Marked QR attendance for Denisse Pacis Ibanga', NULL, 'attendance', 124, NULL, '::1', '2026-05-18 03:46:11', NULL),
(132, NULL, 5, NULL, 'staff', 'System', 'Marked QR attendance for Margaret Pacis Ibanga', NULL, 'attendance', 125, NULL, '::1', '2026-05-18 03:59:41', NULL),
(133, NULL, 5, NULL, 'staff', 'System', 'Marked QR attendance for Dylan Balois Salvacion', NULL, 'attendance', 126, NULL, '::1', '2026-05-18 04:09:36', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `declined_users`
-- (See below for the actual view)
--
CREATE TABLE `declined_users` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`role` enum('superadmin','admin','staff','student','parent')
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `deleted_users`
--

CREATE TABLE `deleted_users` (
  `id` int(11) NOT NULL,
  `original_user_id` int(11) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `role` varchar(50) DEFAULT NULL,
  `id_num` varchar(100) DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `delete_reason` text DEFAULT NULL,
  `deleted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `json_data` longtext DEFAULT NULL,
  `status_before` varchar(50) DEFAULT NULL,
  `status_after` varchar(50) DEFAULT 'deleted',
  `snapshot_json` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `deleted_users`
--

INSERT INTO `deleted_users` (`id`, `original_user_id`, `first_name`, `middle_name`, `last_name`, `email`, `role`, `id_num`, `deleted_by`, `delete_reason`, `deleted_at`, `json_data`, `status_before`, `status_after`, `snapshot_json`) VALUES
(1, 12, 'Dhayan', 'Balois', 'Salvacion', 'dylanstore123@gmail.com', 'student', NULL, 1, 'Deleted by superadmin', '2026-05-16 23:34:52', NULL, NULL, 'deleted', NULL),
(2, 3, 'Antonio', '', 'Gaste', 'jullianjoquico@gmail.com', 'staff', NULL, 1, 'Deleted from archive by superadmin', '2026-05-17 12:20:15', NULL, 'active', 'deleted', '{\"id\":3,\"original_user_id\":8,\"first_name\":\"Antonio\",\"middle_name\":\"\",\"last_name\":\"Gaste\",\"email\":\"jullianjoquico@gmail.com\",\"role\":\"staff\",\"id_num\":null,\"archived_by\":1,\"archive_reason\":\"Archived by admin\",\"archived_at\":\"2026-05-17 12:19:21\",\"json_data\":null,\"status_before\":\"active\",\"status_after\":\"archived\",\"snapshot_json\":\"{\\\"id\\\":8,\\\"first_name\\\":\\\"Antonio\\\",\\\"middle_name\\\":\\\"\\\",\\\"last_name\\\":\\\"Gaste\\\",\\\"email\\\":\\\"jullianjoquico@gmail.com\\\",\\\"password\\\":\\\"$2y$10$qtEPyRH0N5zIVWhntJQX3uz45l4nlIgBcAYDanmjVY6d6OYiovU.m\\\",\\\"role\\\":\\\"staff\\\",\\\"id_num\\\":null,\\\"phone_number\\\":null,\\\"contact_number\\\":null,\\\"address\\\":null,\\\"profile_pic\\\":null,\\\"grade_level\\\":null,\\\"section\\\":null,\\\"guardian_name\\\":null,\\\"guardian_contact\\\":null,\\\"guardian_relationship\\\":null,\\\"emergency_contact_name\\\":null,\\\"emergency_contact_number\\\":null,\\\"emergency_contact_relationship\\\":null,\\\"qr_token\\\":null,\\\"parent_access_code\\\":null,\\\"admin_code\\\":null,\\\"staff_code\\\":\\\"EDUTRACK-STAFF-2026-FSPV2U67\\\",\\\"information_correct\\\":1,\\\"otp_code\\\":null,\\\"otp_expiry\\\":null,\\\"email_verified_at\\\":\\\"2026-05-16 22:50:27\\\",\\\"status\\\":\\\"active\\\",\\\"last_login\\\":null,\\\"is_archived\\\":0,\\\"is_deleted\\\":0,\\\"created_at\\\":\\\"2026-05-16 22:50:03\\\",\\\"updated_at\\\":\\\"2026-05-16 23:08:20\\\",\\\"profile_photo\\\":null,\\\"profile_picture\\\":null,\\\"grade\\\":null,\\\"emergency_contact_person\\\":null,\\\"phone\\\":null,\\\"relationship_to_student\\\":null,\\\"occupation\\\":null}\"}'),
(3, 9501, 'Deleted', '', 'Student1', 'deletedstudent1@edutrack.com', 'student', '202644444001', 1, 'Inactive account', '2026-01-10 08:00:00', NULL, 'active', 'deleted', NULL),
(4, 9502, 'Deleted', '', 'Student2', 'deletedstudent2@edutrack.com', 'student', '202644444002', 1, 'Duplicate registration', '2026-01-12 09:15:00', NULL, 'active', 'deleted', NULL),
(5, 9503, 'Deleted', '', 'Parent1', 'deletedparent1@edutrack.com', 'parent', NULL, 1, 'Requested deletion', '2026-01-14 10:20:00', NULL, 'active', 'deleted', NULL),
(6, 9504, 'Deleted', '', 'Staff1', 'deletedstaff1@edutrack.com', 'staff', NULL, 1, 'Account violation', '2026-01-16 11:30:00', NULL, 'archived', 'deleted', NULL),
(7, 9505, 'Deleted', '', 'Admin1', 'deletedadmin1@edutrack.com', 'admin', NULL, 1, 'Manual cleanup', '2026-01-18 01:45:00', NULL, 'active', 'deleted', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `document_access_logs`
--

CREATE TABLE `document_access_logs` (
  `id` int(11) NOT NULL,
  `document_request_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `access_type` enum('view','otp_sent','otp_verified','download','print') NOT NULL DEFAULT 'view',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_access_logs`
--

INSERT INTO `document_access_logs` (`id`, `document_request_id`, `user_id`, `access_type`, `created_at`) VALUES
(18, 10, 6, 'view', '2026-05-10 01:10:00'),
(19, 10, 6, 'otp_sent', '2026-05-10 01:11:12'),
(20, 10, 6, 'otp_verified', '2026-05-10 01:12:08'),
(21, 10, 6, 'download', '2026-05-10 01:13:30'),
(22, 11, 6, 'print', '2026-05-13 17:45:22'),
(23, 1, 6, 'view', '2026-01-05 00:10:00'),
(24, 1, 6, 'otp_sent', '2026-01-05 00:11:12'),
(25, 1, 6, 'otp_verified', '2026-01-05 00:12:20'),
(26, 1, 6, 'download', '2026-01-05 00:13:44'),
(27, 1, 6, 'view', '2026-01-10 01:15:00'),
(28, 1, 6, 'print', '2026-01-10 01:18:25'),
(29, 1, 6, 'view', '2026-02-02 02:05:00'),
(30, 1, 6, 'otp_sent', '2026-02-02 02:06:40'),
(31, 1, 6, 'otp_verified', '2026-02-02 02:08:11'),
(32, 1, 6, 'view', '2026-03-13 17:20:00'),
(33, 1, 6, 'download', '2026-03-13 17:22:15'),
(34, 1, 6, 'view', '2026-03-31 18:00:00'),
(35, 1, 6, 'print', '2026-03-31 18:04:18'),
(36, 1, 6, 'view', '2026-04-17 19:10:00'),
(37, 1, 6, 'otp_sent', '2026-04-17 19:11:14'),
(38, 1, 6, 'otp_verified', '2026-04-17 19:12:55'),
(39, 1, 6, 'download', '2026-04-17 19:15:10'),
(54, 167, 6, 'otp_sent', '2026-05-17 10:01:21'),
(55, 167, 6, 'otp_verified', '2026-05-17 10:19:12'),
(56, 167, 6, 'otp_verified', '2026-05-17 10:19:19'),
(57, 167, 6, 'otp_verified', '2026-05-17 10:19:46'),
(58, 168, 6, 'otp_sent', '2026-05-17 10:33:25'),
(59, 169, 6, 'otp_sent', '2026-05-17 10:39:02'),
(60, 167, 6, 'otp_verified', '2026-05-17 10:40:06');

-- --------------------------------------------------------

--
-- Table structure for table `document_requests`
--

CREATE TABLE `document_requests` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_by_role` enum('student','parent','admin','superadmin') NOT NULL,
  `document_type` varchar(150) NOT NULL,
  `purpose` text DEFAULT NULL,
  `status` varchar(30) DEFAULT 'pending',
  `otp_required` tinyint(1) NOT NULL DEFAULT 1,
  `otp_verified` tinyint(1) NOT NULL DEFAULT 0,
  `admin_remarks` text DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tracking_id` varchar(80) DEFAULT NULL,
  `release_method` varchar(50) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `access_otp` varchar(10) DEFAULT NULL,
  `otp_used` tinyint(1) NOT NULL DEFAULT 0,
  `pickup_date` date DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL,
  `file_expires_at` datetime DEFAULT NULL,
  `request_copy_type` varchar(50) DEFAULT 'printed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_requests`
--

INSERT INTO `document_requests` (`id`, `student_id`, `requested_by`, `requested_by_role`, `document_type`, `purpose`, `status`, `otp_required`, `otp_verified`, `admin_remarks`, `released_at`, `created_at`, `updated_at`, `tracking_id`, `release_method`, `file_path`, `access_otp`, `otp_used`, `pickup_date`, `instructions`, `remarks`, `processed_by`, `processed_at`, `archived_at`, `file_expires_at`, `request_copy_type`) VALUES
(1, 6, 6, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-09 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(2, 10, 10, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-19 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(3, 11, 11, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(4, 12, 12, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-02-14 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(5, 13, 13, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-02-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(6, 14, 14, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-09 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(7, 15, 15, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-05 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(8, 16, 16, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(9, 21, 21, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-10 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(10, 22, 22, 'student', 'Report Card', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(11, 23, 23, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-03-20 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(12, 24, 24, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-02 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(13, 25, 25, 'student', 'Form 137', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-05 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(14, 26, 26, 'student', 'Transcript of Records', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-15 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(15, 27, 27, 'student', 'Report Card', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-02-21 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(16, 28, 28, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-26 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(17, 29, 29, 'student', 'Transcript of Records', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-17 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(18, 30, 30, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-03-18 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(19, 6, 6, 'student', 'Transcript of Records', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-31 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(20, 10, 10, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-17 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(21, 11, 11, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-06 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(22, 12, 12, 'student', 'Report Card', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-26 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(23, 13, 13, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-10 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(24, 14, 14, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-19 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(25, 15, 15, 'student', 'Good Moral', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-02-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(26, 16, 16, 'student', 'Form 137', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-08 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(27, 21, 21, 'student', 'Form 137', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-01-14 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(28, 22, 22, 'student', 'Form 137', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(29, 23, 23, 'student', 'Transcript of Records', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(30, 24, 24, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-02-28 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(31, 25, 25, 'student', 'Report Card', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(32, 26, 26, 'student', 'Report Card', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-01-20 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(33, 27, 27, 'student', 'Transcript of Records', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-31 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(34, 28, 28, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-08 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(35, 29, 29, 'student', 'Transcript of Records', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-20 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(36, 30, 30, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-01-22 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(37, 6, 6, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-27 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(38, 10, 10, 'student', 'Good Moral', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(39, 11, 11, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-09 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(40, 12, 12, 'student', 'Report Card', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-07 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(41, 13, 13, 'student', 'Report Card', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-01-29 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(42, 14, 14, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(43, 15, 15, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-05 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(44, 16, 16, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-28 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(45, 21, 21, 'student', 'Transcript of Records', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-11 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(46, 22, 22, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-02-08 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(47, 23, 23, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(48, 24, 24, 'student', 'Form 137', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-23 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(49, 25, 25, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-03-11 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(50, 26, 26, 'student', 'Transcript of Records', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-01-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(51, 27, 27, 'student', 'Form 137', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-03-25 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(52, 28, 28, 'student', 'Form 137', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-31 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(53, 29, 29, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-01-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(54, 30, 30, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-15 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(55, 6, 6, 'student', 'Form 137', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-29 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(56, 10, 10, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-23 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(57, 11, 11, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(58, 12, 12, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-30 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(59, 13, 13, 'student', 'Form 137', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-03-05 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(60, 14, 14, 'student', 'Transcript of Records', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-30 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(61, 15, 15, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-14 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(62, 16, 16, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-02-27 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(63, 21, 21, 'student', 'Report Card', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-23 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(64, 22, 22, 'student', 'Transcript of Records', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-03 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(65, 23, 23, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-03-07 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(66, 24, 24, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(67, 25, 25, 'student', 'Report Card', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-03-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(68, 26, 26, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(69, 27, 27, 'student', 'Report Card', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(70, 28, 28, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-03-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(71, 29, 29, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-04 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(72, 30, 30, 'student', 'Transcript of Records', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-10 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(73, 6, 6, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-02-15 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(74, 10, 10, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-19 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(75, 11, 11, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-27 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(76, 12, 12, 'student', 'Form 137', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-02-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(77, 13, 13, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-01-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(78, 14, 14, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-11 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(79, 15, 15, 'student', 'Report Card', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-05 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(80, 16, 16, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-28 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(81, 21, 21, 'student', 'Transcript of Records', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-02-24 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(82, 22, 22, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-29 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(83, 23, 23, 'student', 'Good Moral', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-22 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(84, 24, 24, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-27 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(85, 25, 25, 'student', 'Report Card', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-28 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(86, 26, 26, 'student', 'Report Card', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-01-30 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(87, 27, 27, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-01-28 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(88, 28, 28, 'student', 'Certificate of Enrollment', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-02-20 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(89, 29, 29, 'student', 'Transcript of Records', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(90, 30, 30, 'student', 'Form 137', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-02-26 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(91, 6, 6, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-02-03 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(92, 10, 10, 'student', 'Report Card', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-04-03 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(93, 11, 11, 'student', 'Form 137', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-13 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(94, 12, 12, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-12 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(95, 13, 13, 'student', 'Good Moral', 'Scholarship Requirement', 'rejected', 1, 0, NULL, NULL, '2026-03-11 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(96, 14, 14, 'student', 'Transcript of Records', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-04-06 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(97, 15, 15, 'student', 'Form 137', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-04-01 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(98, 16, 16, 'student', 'Transcript of Records', 'Scholarship Requirement', 'approved', 1, 0, NULL, NULL, '2026-03-12 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(99, 21, 21, 'student', 'Form 137', 'Scholarship Requirement', 'released', 1, 0, NULL, NULL, '2026-04-09 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(100, 22, 22, 'student', 'Good Moral', 'Scholarship Requirement', 'pending', 1, 0, NULL, NULL, '2026-03-10 00:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'printed'),
(128, 6, 6, 'student', 'Good Moral Certificate', 'Scholarship requirement', 'pending', 1, 0, NULL, NULL, '2026-01-12 00:30:00', '2026-05-17 09:44:37', 'DEN-DOC-001', 'soft_copy', NULL, '482915', 0, NULL, 'Please process as soon as available.', 'Student request', NULL, NULL, NULL, NULL, 'soft'),
(129, 6, 6, 'student', 'Certificate of Enrollment', 'School transfer requirement', 'approved', 1, 0, 'Approved for release', NULL, '2026-02-05 01:15:00', '2026-05-17 09:44:37', 'DEN-DOC-002', 'printed', NULL, '739104', 0, '2026-02-08', 'Parent may claim at registrar.', 'Approved request', NULL, '2026-02-06 10:00:00', NULL, NULL, 'printed'),
(130, 6, 6, 'student', 'Form 137', 'Enrollment requirement', 'processing', 1, 0, 'Being prepared by registrar', NULL, '2026-03-10 02:05:00', '2026-05-17 09:44:37', 'DEN-DOC-003', 'printed', NULL, '195827', 0, '2026-03-15', 'Bring valid ID upon pickup.', 'Processing request', NULL, '2026-03-11 11:30:00', NULL, NULL, 'printed'),
(131, 6, 6, 'student', 'Report Card', 'Personal copy', 'released', 0, 0, 'Released successfully', '2026-04-18 02:20:00', '2026-04-14 17:40:00', '2026-05-17 09:44:37', 'DEN-DOC-004', 'printed', NULL, NULL, 0, '2026-04-18', 'Claimed by student.', 'Released', NULL, '2026-04-18 02:20:00', NULL, NULL, 'printed'),
(132, 6, 6, 'student', 'Certificate of Good Standing', 'Contest requirement', 'rejected', 1, 0, 'Incomplete requirement', NULL, '2026-05-03 00:25:00', '2026-05-17 09:44:37', 'DEN-DOC-005', 'soft_copy', NULL, '605194', 0, NULL, 'Submit complete details first.', 'Rejected due to missing details', NULL, '2026-05-04 09:00:00', NULL, NULL, 'soft'),
(135, 6, 6, 'student', 'Form 137', 'Enrollment requirement', 'approved', 1, 0, NULL, NULL, '2026-02-17 00:00:00', '2026-05-17 09:44:46', 'DOC-6-4065', 'soft_copy', NULL, '154440', 0, '2026-03-04', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(136, 10, 10, 'student', 'Good Moral Certificate', 'Scholarship requirement', 'pending', 1, 0, NULL, NULL, '2026-01-12 00:00:00', '2026-05-17 09:44:46', 'DOC-10-3639', 'printed', NULL, '264971', 0, '2026-02-10', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(137, 11, 11, 'student', 'Certificate of Enrollment', 'Enrollment requirement', 'processing', 1, 0, NULL, NULL, '2026-02-01 00:00:00', '2026-05-17 09:44:46', 'DOC-11-2910', 'printed', NULL, '154768', 0, '2026-02-11', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(138, 12, 12, 'student', 'Certificate of Enrollment', 'Scholarship requirement', 'approved', 1, 0, NULL, NULL, '2026-01-10 00:00:00', '2026-05-17 09:44:46', 'DOC-12-9119', 'printed', NULL, '787386', 0, '2026-05-06', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(139, 13, 13, 'student', 'Good Moral Certificate', 'Personal copy', 'approved', 1, 0, NULL, NULL, '2026-01-08 00:00:00', '2026-05-17 09:44:46', 'DOC-13-3361', 'printed', NULL, '884350', 0, '2026-05-10', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(140, 15, 15, 'student', 'Good Moral Certificate', 'Transfer requirement', 'pending', 1, 0, NULL, NULL, '2026-01-13 00:00:00', '2026-05-17 09:44:46', 'DOC-15-1236', 'soft_copy', NULL, '220397', 0, '2026-01-23', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(141, 16, 16, 'student', 'Good Moral Certificate', 'Personal copy', 'processing', 1, 0, NULL, NULL, '2026-02-26 00:00:00', '2026-05-17 09:44:46', 'DOC-16-3420', 'printed', NULL, '408720', 0, '2026-03-27', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(142, 21, 21, 'student', 'Report Card', 'Transfer requirement', 'released', 1, 0, NULL, NULL, '2026-03-02 00:00:00', '2026-05-17 09:44:46', 'DOC-21-5456', 'printed', NULL, '903911', 0, '2026-02-05', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(143, 22, 22, 'student', 'Certificate of Good Standing', 'Personal copy', 'pending', 1, 0, NULL, NULL, '2026-02-27 00:00:00', '2026-05-17 09:44:46', 'DOC-22-8188', 'soft_copy', NULL, '988644', 0, '2026-05-02', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(144, 23, 23, 'student', 'Good Moral Certificate', 'Transfer requirement', 'approved', 1, 0, NULL, NULL, '2026-01-29 00:00:00', '2026-05-17 09:44:46', 'DOC-23-8439', 'printed', NULL, '904916', 0, '2026-01-11', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(145, 24, 24, 'student', 'Report Card', 'Enrollment requirement', 'approved', 1, 0, NULL, NULL, '2026-02-26 00:00:00', '2026-05-17 09:44:46', 'DOC-24-1912', 'printed', NULL, '786495', 0, '2026-01-27', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(146, 25, 25, 'student', 'Certificate of Enrollment', 'Scholarship requirement', 'processing', 1, 0, NULL, NULL, '2026-04-12 00:00:00', '2026-05-17 09:44:46', 'DOC-25-9465', 'printed', NULL, '117410', 0, '2026-05-13', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(147, 26, 26, 'student', 'Form 137', 'Transfer requirement', 'rejected', 1, 0, NULL, NULL, '2026-05-09 00:00:00', '2026-05-17 10:00:55', 'DOC-26-4826', 'printed', NULL, '604121', 0, '2026-02-12', 'Please process this request.', 'Already requested', 19, '2026-05-17 18:00:55', NULL, NULL, 'soft'),
(148, 27, 27, 'student', 'Good Moral Certificate', 'Enrollment requirement', 'processing', 1, 0, NULL, NULL, '2026-02-04 00:00:00', '2026-05-17 09:44:46', 'DOC-27-2236', 'soft_copy', NULL, '184541', 0, '2026-04-14', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(149, 28, 28, 'student', 'Form 137', 'Personal copy', 'processing', 1, 0, NULL, NULL, '2026-01-03 00:00:00', '2026-05-17 09:44:46', 'DOC-28-5088', 'printed', NULL, '711934', 0, '2026-04-15', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(150, 29, 29, 'student', 'Certificate of Good Standing', 'Personal copy', 'processing', 1, 0, NULL, NULL, '2026-04-16 00:00:00', '2026-05-17 09:44:46', 'DOC-29-8127', 'soft_copy', NULL, '374972', 0, '2026-05-04', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(151, 30, 30, 'student', 'Report Card', 'Scholarship requirement', 'processing', 1, 0, NULL, NULL, '2026-05-01 00:00:00', '2026-05-17 09:44:46', 'DOC-30-7160', 'soft_copy', NULL, '250120', 0, '2026-05-02', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(152, 64, 64, 'student', 'Certificate of Enrollment', 'Personal copy', 'pending', 1, 0, NULL, NULL, '2026-02-08 00:00:00', '2026-05-17 09:44:46', 'DOC-64-7579', 'soft_copy', NULL, '679952', 0, '2026-05-04', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(153, 65, 65, 'student', 'Form 137', 'Transfer requirement', 'approved', 1, 0, NULL, NULL, '2026-01-13 00:00:00', '2026-05-17 09:44:46', 'DOC-65-6483', 'soft_copy', NULL, '925169', 0, '2026-02-17', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(154, 66, 66, 'student', 'Report Card', 'Personal copy', 'processing', 1, 0, NULL, NULL, '2026-04-25 00:00:00', '2026-05-17 09:44:46', 'DOC-66-3794', 'soft_copy', NULL, '620121', 0, '2026-01-28', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(155, 67, 67, 'student', 'Certificate of Enrollment', 'Enrollment requirement', 'processing', 1, 0, NULL, NULL, '2026-03-27 00:00:00', '2026-05-17 09:44:46', 'DOC-67-7162', 'printed', NULL, '308251', 0, '2026-04-17', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(156, 68, 68, 'student', 'Certificate of Good Standing', 'Enrollment requirement', 'approved', 1, 0, NULL, NULL, '2026-03-28 00:00:00', '2026-05-17 09:44:46', 'DOC-68-3676', 'printed', NULL, '571721', 0, '2026-01-27', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(157, 69, 69, 'student', 'Certificate of Good Standing', 'Personal copy', 'processing', 1, 0, NULL, NULL, '2026-03-22 00:00:00', '2026-05-17 09:44:46', 'DOC-69-6240', 'printed', NULL, '541166', 0, '2026-02-14', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'printed'),
(158, 70, 70, 'student', 'Form 137', 'Transfer requirement', 'processing', 1, 0, NULL, NULL, '2026-03-30 00:00:00', '2026-05-17 09:44:46', 'DOC-70-8790', 'printed', NULL, '828582', 0, '2026-01-31', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(159, 71, 71, 'student', 'Certificate of Enrollment', 'Transfer requirement', 'processing', 1, 0, NULL, NULL, '2026-01-18 00:00:00', '2026-05-17 09:44:46', 'DOC-71-9603', 'printed', NULL, '961034', 0, '2026-04-04', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(160, 72, 72, 'student', 'Certificate of Good Standing', 'Enrollment requirement', 'pending', 1, 0, NULL, NULL, '2026-02-02 00:00:00', '2026-05-17 09:44:46', 'DOC-72-2832', 'printed', NULL, '706543', 0, '2026-03-23', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(161, 73, 73, 'student', 'Good Moral Certificate', 'Enrollment requirement', 'released', 1, 0, NULL, NULL, '2026-04-19 00:00:00', '2026-05-17 09:44:46', 'DOC-73-7922', 'printed', NULL, '415734', 0, '2026-04-13', 'Please process this request.', 'Demo document transaction', NULL, NULL, NULL, NULL, 'soft'),
(166, 6, 6, 'student', 'Form 137', 'Enrollment', 'released', 1, 0, NULL, NULL, '2026-05-17 09:47:20', '2026-05-17 09:47:47', 'PET-20260517-0A2AB5F2', 'digital', 'uploads/documents/DOC_166_1779011267_IBANGA__DENISSE_MARGARET-Final_Certification_1.pdf', '691007', 0, NULL, NULL, NULL, 19, '2026-05-17 17:47:47', NULL, '2028-05-17 17:47:47', 'softcopy'),
(167, 6, 6, 'student', 'Certificate of Enrollment', 'Scholarship', 'released', 1, 1, NULL, NULL, '2026-05-17 10:00:23', '2026-05-17 10:02:07', 'PET-20260517-5C8303A9', 'digital', 'uploads/documents/DOC_167_1779012081_IBANGA__DENISSE_MARGARET-Final_Certification_1.pdf', '471932', 1, NULL, NULL, NULL, 19, '2026-05-17 18:01:21', NULL, '2028-05-17 18:01:21', 'softcopy'),
(168, 6, 6, 'student', 'Certificate of Enrollment', '', 'released', 1, 0, NULL, NULL, '2026-05-17 10:32:43', '2026-05-17 10:33:25', 'PET-20260517-42C98CFF', 'digital', 'uploads/documents/DOC_168_1779014005_IBANGA__DENISSE_MARGARET-Final_Certification_2.pdf', '700267', 0, NULL, NULL, NULL, 1, '2026-05-17 18:33:25', NULL, '2028-05-17 18:33:25', 'softcopy'),
(169, 6, 6, 'student', 'Good Moral Certificate', '', 'released', 1, 0, NULL, NULL, '2026-05-17 10:38:19', '2026-05-17 10:39:02', 'PET-20260517-4C85238A', 'digital', 'uploads/documents/DOC_169_1779014342_Ibanga-ISC2-Domain5.pdf', '858820', 0, NULL, NULL, NULL, 19, '2026-05-17 18:39:02', NULL, '2028-05-17 18:39:02', 'softcopy');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(30) DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(1, 6, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(2, 10, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(3, 11, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(4, 12, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(5, 13, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(6, 14, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(7, 15, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(8, 16, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(9, 21, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(10, 22, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(11, 23, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(12, 24, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(13, 25, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(14, 26, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(15, 27, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(16, 28, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(17, 29, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(18, 30, 'Attendance Update', 'Your attendance has been successfully recorded.', 'info', 0, '2026-05-17 09:07:12'),
(19, 7, 'Child Attendance Recorded', 'Denisse Pacis Ibanga entered school and was marked present at 11:46 AM.', 'attendance', 0, '2026-05-18 03:46:11'),
(20, 7, 'Child Attendance Recorded', 'Margaret Pacis Ibanga entered school and was marked present at 11:59 AM.', 'attendance', 0, '2026-05-18 03:59:41'),
(21, 17, 'Child Attendance Recorded', 'Margaret Pacis Ibanga entered school and was marked present at 11:59 AM.', 'attendance', 0, '2026-05-18 03:59:41');

-- --------------------------------------------------------

--
-- Table structure for table `otp_logs`
--

CREATE TABLE `otp_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` enum('registration','forgot_password','reset_password','document_access') NOT NULL,
  `status` enum('pending','verified','expired','failed') NOT NULL DEFAULT 'pending',
  `failed_attempts` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_logs`
--

INSERT INTO `otp_logs` (`id`, `user_id`, `email`, `otp_code`, `purpose`, `status`, `failed_attempts`, `expires_at`, `verified_at`, `created_at`) VALUES
(1, 19, 'admin15@regular.com', '399124', 'forgot_password', 'pending', 0, '2026-05-17 12:42:58', NULL, '2026-05-17 04:27:58'),
(2, 6, 'denissepearl132@gmail.com', '691007', 'document_access', 'pending', 0, '2026-05-18 17:47:47', NULL, '2026-05-17 09:47:47'),
(3, 6, 'denissepearl132@gmail.com', '471932', 'document_access', 'pending', 0, '2026-05-18 18:01:21', NULL, '2026-05-17 10:01:21'),
(4, 6, 'denissepearl132@gmail.com', '700267', 'document_access', 'pending', 0, '2026-05-18 18:33:25', NULL, '2026-05-17 10:33:25'),
(5, 6, 'denissepearl132@gmail.com', '858820', 'document_access', 'pending', 0, '2026-05-18 18:39:02', NULL, '2026-05-17 10:39:02');

-- --------------------------------------------------------

--
-- Table structure for table `parent_access_codes`
--

CREATE TABLE `parent_access_codes` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `status` enum('active','used','expired','revoked') NOT NULL DEFAULT 'active',
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_access_codes`
--

INSERT INTO `parent_access_codes` (`id`, `student_id`, `access_code`, `status`, `is_used`, `expires_at`, `created_at`) VALUES
(1, 6, 'L3PDZSPZ97', 'active', 0, NULL, '2026-05-16 08:45:01'),
(2, 10, 'EW2NQ998RX', 'expired', 0, NULL, '2026-05-16 14:52:58'),
(3, 11, 'A7TZAN3LU4', 'active', 0, NULL, '2026-05-16 14:54:30'),
(4, 12, 'AE5KEQZ245', 'active', 0, NULL, '2026-05-16 14:56:00'),
(5, 13, '9JP29DJJZ4', 'active', 0, NULL, '2026-05-16 15:01:32'),
(6, 14, 'PMGXUVJJTV', 'active', 0, NULL, '2026-05-16 15:02:59'),
(7, 15, 'FNGQ65CEFY', 'active', 0, NULL, '2026-05-16 15:05:03'),
(8, 16, 'NCVVQ7JM95', 'active', 0, NULL, '2026-05-16 15:05:57'),
(9, 10, 'PARENT-ED6A016F', 'expired', 0, '2026-06-15 23:11:43', '2026-05-16 15:11:43'),
(10, 10, 'PARENT-E3EBB1B2', 'used', 0, '2026-06-15 23:14:17', '2026-05-16 15:14:17'),
(11, 10, 'PARENT-10BD39DA', 'active', 0, '2026-06-15 23:15:32', '2026-05-16 15:15:32');

-- --------------------------------------------------------

--
-- Stand-in structure for view `parent_children`
-- (See below for the actual view)
--
CREATE TABLE `parent_children` (
`id` int(11)
,`parent_id` int(11)
,`student_id` int(11)
,`status` enum('Pending','Active','Revoked','Reported')
,`linked_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `parent_student_links`
--

CREATE TABLE `parent_student_links` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` enum('Pending','Active','Revoked','Reported') NOT NULL DEFAULT 'Active',
  `linked_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reported_at` datetime DEFAULT NULL,
  `report_reason` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `parent_student_links`
--

INSERT INTO `parent_student_links` (`id`, `parent_id`, `student_id`, `status`, `linked_at`, `reported_at`, `report_reason`, `created_at`) VALUES
(1, 7, 6, 'Active', '2026-05-16 21:03:55', NULL, NULL, '2026-05-16 21:06:08'),
(2, 7, 10, 'Active', '2026-05-16 23:14:49', NULL, NULL, '2026-05-16 23:14:49'),
(3, 17, 10, 'Active', '2026-05-16 23:16:12', NULL, NULL, '2026-05-16 23:16:12');

-- --------------------------------------------------------

--
-- Stand-in structure for view `pending_users`
-- (See below for the actual view)
--
CREATE TABLE `pending_users` (
`id` int(11)
,`first_name` varchar(100)
,`middle_name` varchar(100)
,`last_name` varchar(100)
,`email` varchar(150)
,`role` enum('superadmin','admin','staff','student','parent')
,`status` enum('pending','active','rejected','archived','deleted')
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `profile_photo_deletion_queue`
--

CREATE TABLE `profile_photo_deletion_queue` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `delete_after` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_codes`
--

CREATE TABLE `registration_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(100) NOT NULL,
  `code_type` varchar(30) NOT NULL,
  `status` varchar(30) DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `replaced_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_by` int(11) DEFAULT NULL,
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registration_codes`
--

INSERT INTO `registration_codes` (`id`, `code`, `code_type`, `status`, `notes`, `created_by`, `created_at`, `replaced_at`, `revoked_at`, `is_used`, `used_by`, `used_at`) VALUES
(1, 'EDUTRACK-STAFF-2026-FSPV2U67', 'staff', 'active', '', 1, '2026-05-16 14:48:43', NULL, NULL, 0, NULL, NULL),
(2, 'EDUTRACK-ADMIN-2026-ZCL59P27', 'admin', 'active', '', 1, '2026-05-16 14:49:01', NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `student_parent_view`
-- (See below for the actual view)
--
CREATE TABLE `student_parent_view` (
`link_id` int(11)
,`student_id` int(11)
,`student_name` varchar(302)
,`student_email` varchar(150)
,`student_lrn` varchar(100)
,`grade_level` varchar(50)
,`section` varchar(100)
,`parent_id` int(11)
,`parent_name` varchar(302)
,`parent_email` varchar(150)
,`link_status` enum('Pending','Active','Revoked','Reported')
,`linked_at` datetime
);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text NOT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('admin_code', 'EDUTRACK-ADMIN-2026-ZCL59P27', '2026-05-16 14:49:01'),
('document_access_otp_enabled', '1', '2026-05-16 07:11:58'),
('late_time', '07:31:00', '2026-05-16 07:11:58'),
('staff_code', 'EDUTRACK-STAFF-2026-FSPV2U67', '2026-05-16 14:48:43'),
('system_name', 'Pitogo EduTrack', '2026-05-16 07:11:58');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','staff','student','parent') NOT NULL DEFAULT 'student',
  `id_num` varchar(100) DEFAULT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `grade_level` varchar(50) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `guardian_name` varchar(150) DEFAULT NULL,
  `guardian_contact` varchar(50) DEFAULT NULL,
  `guardian_relationship` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_number` varchar(50) DEFAULT NULL,
  `emergency_contact_relationship` varchar(100) DEFAULT NULL,
  `qr_token` varchar(255) DEFAULT NULL,
  `parent_access_code` varchar(50) DEFAULT NULL,
  `admin_code` varchar(100) DEFAULT NULL,
  `staff_code` varchar(100) DEFAULT NULL,
  `information_correct` tinyint(1) NOT NULL DEFAULT 0,
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expiry` datetime DEFAULT NULL,
  `email_verified_at` datetime DEFAULT NULL,
  `status` enum('pending','active','rejected','archived','deleted') NOT NULL DEFAULT 'pending',
  `last_login` datetime DEFAULT NULL,
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_photo` varchar(255) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL,
  `grade` varchar(100) DEFAULT NULL,
  `emergency_contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `relationship_to_student` varchar(100) DEFAULT NULL,
  `occupation` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `middle_name`, `last_name`, `email`, `password`, `role`, `id_num`, `phone_number`, `contact_number`, `address`, `profile_pic`, `grade_level`, `section`, `guardian_name`, `guardian_contact`, `guardian_relationship`, `emergency_contact_name`, `emergency_contact_number`, `emergency_contact_relationship`, `qr_token`, `parent_access_code`, `admin_code`, `staff_code`, `information_correct`, `otp_code`, `otp_expiry`, `email_verified_at`, `status`, `last_login`, `is_archived`, `is_deleted`, `created_at`, `updated_at`, `profile_photo`, `profile_picture`, `grade`, `emergency_contact_person`, `phone`, `relationship_to_student`, `occupation`) VALUES
(1, 'Super', NULL, 'Admin', 'superadmin@edutrack.com', '$2y$10$Jnmb9bxSs6rdsBjHn1TgHu5xHTYhrOAbgt1oZmnP7NFaX6PxPPjS2', 'superadmin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL, NULL, '2026-05-16 15:38:10', 'active', NULL, 0, 0, '2026-05-16 07:11:58', '2026-05-16 07:38:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(5, 'Lady', '', 'Guard', 'ibangadm12@gmail.com', '$2y$10$tSlKEAf3KmZ6LxO4kXnzzOsuLCNUz6fxUDxc97.L9F/P3Kc4YrrKa', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'EDUTRACK-STAFF-2026', 1, NULL, NULL, '2026-05-16 16:39:28', 'active', NULL, 0, 0, '2026-05-16 08:39:05', '2026-05-16 08:40:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'Denisse', 'Pacis', 'Ibanga', 'denissepearl132@gmail.com', '$2y$10$aHvu2VVTpNFVmXemKVmj/OjB.sXOR0DKighOCeqF6fdRvvTvirPU6', 'student', '234567890984', '09282380590', NULL, NULL, 'student_6_1778925562_172cba21.jpg', 'Grade 10', 'Rizal', NULL, NULL, NULL, 'Maureen Ibanga', '09215437681', NULL, '04b612f1faa8166a1fb250af924d4ae3e3338e8986de7e880b915af7f7bb3197', 'L3PDZSPZ97', NULL, NULL, 1, NULL, NULL, '2026-05-16 16:45:24', 'active', NULL, 0, 0, '2026-05-16 08:45:01', '2026-05-16 10:08:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'Maureen', 'Pacis', 'Ibanga', 'margeaux.concerts8@gmail.com', '$2y$10$mTtEbBaU/omRs0XkC0vyJec802b0JkjxXnSZ2mIogMrecLjjsZCP2', 'parent', NULL, '09282380590', NULL, 'Dacanlao, Calaca, Batangas', 'parent_7_1778941411_497c360d.jpg', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'L3PDZSPZ97', NULL, NULL, 1, NULL, NULL, '2026-05-16 21:04:22', 'active', NULL, 0, 0, '2026-05-16 13:03:55', '2026-05-16 14:46:55', NULL, NULL, NULL, NULL, NULL, 'Mother', 'Admin Officer'),
(10, 'Margaret', 'Pacis', 'Ibanga', 'dibanga.k12151322@umak.edu.ph', '$2y$10$eWf05OY6WXbGpXBrbL4oYenint02X8c5MMJWBZ65rJXWL0vdGq4sK', 'student', '456798098765', '09217468293', NULL, NULL, 'student_10_1778944284_3c52e305.jpg', 'Grade 8', 'Diamond', NULL, NULL, NULL, 'Maureen Ibanga', '09215437681', NULL, '40e430b79ab11c89554e2dd359dbf446ce84ad3a8282ebdd07f841299e8e0063', 'PARENT-10BD39DA', NULL, NULL, 1, NULL, NULL, '2026-05-16 22:53:16', 'active', NULL, 0, 0, '2026-05-16 14:52:58', '2026-05-16 15:15:32', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(11, 'Dylan', 'Balois', 'Salvacion', 'dylanarchive15@gmail.com', '$2y$10$nVsmxeEDld8CqFNlIjwZIu0Qic85V.h/h7nWpDCnnGulgkLStj6qi', 'student', '876234907047', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '41f5530bca6ca282a4f8e7c52536a2432deab5daafc5f3f977a31080551605c5', 'A7TZAN3LU4', NULL, NULL, 1, NULL, NULL, '2026-05-16 22:54:43', 'active', NULL, 0, 0, '2026-05-16 14:54:30', '2026-05-16 15:08:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(12, 'Dhayan', 'Balois', 'Salvacion', 'dylanstore123@gmail.com', '$2y$10$ULy3s9xSbFJO9szdzCDUj.vi/l/ePToU3OPTREmYxGPt1KK4VnG4m', 'student', '909840980840', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '0d3e752847e5d2572e5c5ad2bd9f430cbf447e7b656cba373b4ca2048b8dd55c', 'AE5KEQZ245', NULL, NULL, 1, NULL, NULL, '2026-05-16 22:56:13', 'active', NULL, 0, 0, '2026-05-16 14:56:00', '2026-05-17 04:18:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(13, 'Jullian', '', 'Joquico', 'jjoquico0904@gmail.com', '$2y$10$bRBdIaMogbwDgiEJQ3C52uuM8sN.IZGB6gioEtHl.wZ7Bg6Xh0YA2', 'student', '989879874921', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'ce18ab812415be6804d01b985f306ba3dfdc9f1d2e656c1433fcd6c14a1ae5ed', '9JP29DJJZ4', NULL, NULL, 1, NULL, NULL, '2026-05-16 23:01:47', 'active', NULL, 0, 0, '2026-05-16 15:01:32', '2026-05-16 15:08:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(14, 'Miguel', '', 'Salvador', 'jmiguel0904@gmail.com', '$2y$10$vd84wcRPxVRIVanSCUo73OEy8bL4rUK7C0TLuRKZ0d3xNnPRzVwZ2', 'student', '345678909876', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'eefa8320a779e16dacee3d9d35a997cb484fef893d974f7d3e6edd0b84236860', 'PMGXUVJJTV', NULL, NULL, 1, NULL, NULL, '2026-05-16 23:03:12', 'archived', NULL, 1, 0, '2026-05-16 15:02:59', '2026-05-16 15:30:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(15, 'Emman', '', 'Ong', 'jmigueljoquico@gmail.com', '$2y$10$0B16Or2dqytE.1Ye1sYt2.LgD0w7wRj69UwWoEG3L8uES2Ed6MEau', 'student', '764234567980', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'c5d6c96ca0fe4767598ecded82b6e4a7d4412ebd95f060bee372ff3392aa3581', 'FNGQ65CEFY', NULL, NULL, 1, NULL, NULL, '2026-05-16 23:05:18', 'active', NULL, 0, 0, '2026-05-16 15:05:03', '2026-05-16 15:08:09', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(16, 'Yoshke', 'Buyao', 'Nasayao', 'yoshke56@gmail.com', '$2y$10$TkIcqQsevbPhkmVEs9NRXeodEl2NHh74WHMcrUXQcLtE/zLoCSBEO', 'student', '849824798649', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '6de2f7651fe632fba1d79b6dcf212f01183cd5e3dc7326b7d1cceff39174e8ff', 'NCVVQ7JM95', NULL, NULL, 1, NULL, NULL, '2026-05-16 23:06:11', 'active', NULL, 0, 0, '2026-05-16 15:05:57', '2026-05-16 15:08:04', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(17, 'Yoske', '', 'Buyao', 'yoskegre@gmail.com', '$2y$10$YECh0LmiVhO2hZlfnaE0duWdgCRrhieEipePKVaK./aXWYsRv4KtC', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'PARENT-10BD39DA', NULL, NULL, 1, NULL, NULL, '2026-05-16 23:16:45', 'active', NULL, 0, 0, '2026-05-16 15:16:12', '2026-05-16 15:24:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(19, 'Regular', '', 'Admin', 'admin15@regular.com', '$2y$10$0jCoJOv53ez0/rmyxI2oeu1NfWSrcdR.5xyCjsgPWBKhrNdizKGJm', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'EDUTRACK-ADMIN-2026-ZCL59P27', NULL, 1, '399124', '2026-05-17 12:42:58', '2026-05-17 12:23:42', 'active', NULL, 0, 0, '2026-05-17 04:22:52', '2026-05-17 04:27:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(20, 'Administrator', '', 'Register', 'admin16@regular.com', '$2y$10$I/T8gVdQsALsaHnY4Lq5PeQLciVQfDcn6lZBZdPh9xH3hMPeoUdNO', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'EDUTRACK-ADMIN-2026-ZCL59P27', NULL, 1, NULL, NULL, '2026-05-17 12:31:54', 'pending', NULL, 0, 0, '2026-05-17 04:31:35', '2026-05-17 04:31:54', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(21, 'Denisse', '', 'Ibanga', 'student1@edutrack.com', '123456', 'student', '202600000001', NULL, NULL, NULL, NULL, '7', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-03 00:11:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(22, 'Angela', '', 'Cruz', 'student2@edutrack.com', '123456', 'student', '202600000002', NULL, NULL, NULL, NULL, '7', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-05 01:15:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(23, 'Mark', '', 'Santos', 'student3@edutrack.com', '123456', 'student', '202600000003', NULL, NULL, NULL, NULL, '7', 'Bonifacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-07 00:44:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(24, 'Kyle', '', 'Rivera', 'student4@edutrack.com', '123456', 'student', '202600000004', NULL, NULL, NULL, NULL, '8', 'Mabini', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-07 23:21:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'Nicole', '', 'Garcia', 'student5@edutrack.com', '123456', 'student', '202600000005', NULL, NULL, NULL, NULL, '8', 'Mabini', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-08 23:41:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(26, 'Jessa', '', 'Reyes', 'student6@edutrack.com', '123456', 'student', '202600000006', NULL, NULL, NULL, NULL, '8', 'Aguinaldo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-09 23:55:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(27, 'Paolo', '', 'Flores', 'student7@edutrack.com', '123456', 'student', '202600000007', NULL, NULL, NULL, NULL, '9', 'Del Pilar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-12 00:02:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(28, 'Maria', '', 'Lopez', 'student8@edutrack.com', '123456', 'student', '202600000008', NULL, NULL, NULL, NULL, '9', 'Del Pilar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-14 00:17:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(29, 'Kevin', '', 'Torres', 'student9@edutrack.com', '123456', 'student', '202600000009', NULL, NULL, NULL, NULL, '9', 'Luna', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-15 00:22:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(30, 'Sofia', '', 'Ramos', 'student10@edutrack.com', '123456', 'student', '202600000010', NULL, NULL, NULL, NULL, '10', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-16 00:27:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(31, 'Maureen', NULL, 'Ibanga', 'parent1@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-01 02:00:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(32, 'Ana', NULL, 'Cruz', 'parent2@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-02 02:10:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(33, 'Jose', NULL, 'Rivera', 'parent3@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-03 02:20:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(34, 'Marites', NULL, 'Lopez', 'parent4@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-04 02:30:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(35, 'Victor', NULL, 'Ramos', 'parent5@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-05 02:40:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(36, 'Maria', NULL, 'Secretary', 'staff1@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-06 00:00:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(37, 'Lester', NULL, 'Registrar', 'staff2@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-07 00:10:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(38, 'Paula', NULL, 'Office', 'staff3@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-08 00:20:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(39, 'Rica', NULL, 'Encoder', 'staff4@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-09 00:30:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(40, 'Noel', NULL, 'Assistant', 'staff5@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-02-10 00:40:00', '2026-05-17 09:05:57', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(62, 'Regular', NULL, 'Admin', 'admin1@edutrack.com', '123456', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-01 23:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(63, 'Second', NULL, 'Admin', 'admin2@edutrack.com', '123456', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-02 23:00:00', '2026-05-17 09:07:12', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(64, 'John', '', 'Villanueva', 'student11@edutrack.com', '123456', 'student', '202600000011', NULL, NULL, NULL, NULL, '10', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-18 00:31:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(65, 'Carla', '', 'Aquino', 'student12@edutrack.com', '123456', 'student', '202600000012', NULL, NULL, NULL, NULL, '10', 'Bonifacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-20 00:33:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(66, 'Jerome', '', 'Perez', 'student13@edutrack.com', '123456', 'student', '202600000013', NULL, NULL, NULL, NULL, '11', 'STEM-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-21 00:37:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(67, 'Liza', '', 'Dela Cruz', 'student14@edutrack.com', '123456', 'student', '202600000014', NULL, NULL, NULL, NULL, '11', 'STEM-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-22 00:39:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(68, 'James', '', 'Castro', 'student15@edutrack.com', '123456', 'student', '202600000015', NULL, NULL, NULL, NULL, '11', 'HUMSS-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-24 00:42:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(69, 'Patricia', '', 'Navarro', 'student16@edutrack.com', '123456', 'student', '202600000016', NULL, NULL, NULL, NULL, '12', 'STEM-B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-25 00:44:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(70, 'Joshua', '', 'Fernandez', 'student17@edutrack.com', '123456', 'student', '202600000017', NULL, NULL, NULL, NULL, '12', 'STEM-B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-26 00:45:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(71, 'Bianca', '', 'Mendoza', 'student18@edutrack.com', '123456', 'student', '202600000018', NULL, NULL, NULL, NULL, '12', 'ABM-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-27 00:47:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(72, 'Ralph', '', 'Diaz', 'student19@edutrack.com', '123456', 'student', '202600000019', NULL, NULL, NULL, NULL, '12', 'ABM-A', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-28 00:49:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(73, 'Trisha', '', 'Salazar', 'student20@edutrack.com', '123456', 'student', '202600000020', NULL, NULL, NULL, NULL, '12', 'HUMSS-B', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'active', NULL, 0, 0, '2026-01-29 00:50:00', '2026-05-17 09:10:15', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(83, 'Pending', '', 'Student1', 'pendingstudent1@edutrack.com', '123456', 'student', '202699999001', NULL, NULL, NULL, NULL, '7', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-01 00:00:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(84, 'Pending', '', 'Student2', 'pendingstudent2@edutrack.com', '123456', 'student', '202699999002', NULL, NULL, NULL, NULL, '7', 'Bonifacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-02 00:10:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(85, 'Pending', '', 'Student3', 'pendingstudent3@edutrack.com', '123456', 'student', '202699999003', NULL, NULL, NULL, NULL, '8', 'Mabini', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-03 00:20:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(86, 'Pending', '', 'Student4', 'pendingstudent4@edutrack.com', '123456', 'student', '202699999004', NULL, NULL, NULL, NULL, '8', 'Aguinaldo', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-04 00:30:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(87, 'Pending', '', 'Student5', 'pendingstudent5@edutrack.com', '123456', 'student', '202699999005', NULL, NULL, NULL, NULL, '9', 'Del Pilar', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-05 00:40:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(88, 'Pending', '', 'Student6', 'pendingstudent6@edutrack.com', '123456', 'student', '202699999006', NULL, NULL, NULL, NULL, '9', 'Luna', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-06 00:50:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(89, 'Pending', '', 'Student7', 'pendingstudent7@edutrack.com', '123456', 'student', '202699999007', NULL, NULL, NULL, NULL, '10', 'Rizal', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-07 01:00:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(90, 'Pending', '', 'Student8', 'pendingstudent8@edutrack.com', '123456', 'student', '202699999008', NULL, NULL, NULL, NULL, '10', 'Bonifacio', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'pending', NULL, 0, 0, '2026-04-08 01:10:00', '2026-05-17 09:13:06', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(91, 'Declined', '', 'Student1', 'declinedstudent1@edutrack.com', '123456', 'student', '202688888001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-01 00:00:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(92, 'Declined', '', 'Student2', 'declinedstudent2@edutrack.com', '123456', 'student', '202688888002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-02 00:10:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(93, 'Declined', '', 'Student3', 'declinedstudent3@edutrack.com', '123456', 'student', '202688888003', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-03 00:20:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(94, 'Declined', '', 'Parent1', 'declinedparent1@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-04 00:30:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(95, 'Declined', '', 'Parent2', 'declinedparent2@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-05 00:40:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(96, 'Declined', '', 'Staff1', 'declinedstaff1@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, '', NULL, 0, 0, '2026-03-06 00:50:00', '2026-05-17 09:14:35', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(103, 'Rejected', '', 'StudentA', 'rejectedA@edutrack.com', '123456', 'student', '202677777001', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'rejected', NULL, 0, 0, '2026-03-01 00:00:00', '2026-05-17 09:16:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(104, 'Rejected', '', 'StudentB', 'rejectedB@edutrack.com', '123456', 'student', '202677777002', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'rejected', NULL, 0, 0, '2026-03-02 00:10:00', '2026-05-17 09:16:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(105, 'Rejected', '', 'ParentA', 'rejectedParentA@edutrack.com', '123456', 'parent', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'rejected', NULL, 0, 0, '2026-03-03 00:20:00', '2026-05-17 09:16:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(106, 'Rejected', '', 'StaffA', 'rejectedStaffA@edutrack.com', '123456', 'staff', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL, NULL, 'rejected', NULL, 0, 0, '2026-03-04 00:30:00', '2026-05-17 09:16:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `verification_codes`
-- (See below for the actual view)
--
CREATE TABLE `verification_codes` (
`id` int(11)
,`user_id` int(11)
,`email` varchar(150)
,`purpose` enum('registration','forgot_password','reset_password','document_access')
,`otp_code` varchar(10)
,`failed_attempts` int(11)
,`expires_at` datetime
,`created_at` timestamp
);

-- --------------------------------------------------------

--
-- Structure for view `active_parents`
--
DROP TABLE IF EXISTS `active_parents`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_parents`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`role` = 'parent' AND `users`.`status` = 'active' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `active_staff`
--
DROP TABLE IF EXISTS `active_staff`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_staff`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`role` = 'staff' AND `users`.`status` = 'active' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `active_students`
--
DROP TABLE IF EXISTS `active_students`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `active_students`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`id_num` AS `id_num`, `users`.`grade_level` AS `grade_level`, `users`.`section` AS `section`, `users`.`parent_access_code` AS `parent_access_code`, `users`.`qr_token` AS `qr_token`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`role` = 'student' AND `users`.`status` = 'active' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `approved_users`
--
DROP TABLE IF EXISTS `approved_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `approved_users`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`role` AS `role`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`status` = 'active' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `archive_users`
--
DROP TABLE IF EXISTS `archive_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `archive_users`  AS SELECT `archived_users`.`id` AS `id`, `archived_users`.`original_user_id` AS `original_user_id`, `archived_users`.`first_name` AS `first_name`, `archived_users`.`middle_name` AS `middle_name`, `archived_users`.`last_name` AS `last_name`, `archived_users`.`email` AS `email`, `archived_users`.`role` AS `role`, `archived_users`.`id_num` AS `id_num`, `archived_users`.`archived_by` AS `archived_by`, `archived_users`.`archive_reason` AS `archive_reason`, `archived_users`.`archived_at` AS `archived_at`, `archived_users`.`json_data` AS `json_data` FROM `archived_users` ;

-- --------------------------------------------------------

--
-- Structure for view `declined_users`
--
DROP TABLE IF EXISTS `declined_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `declined_users`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`role` AS `role`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`status` = 'declined' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `parent_children`
--
DROP TABLE IF EXISTS `parent_children`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `parent_children`  AS SELECT `parent_student_links`.`id` AS `id`, `parent_student_links`.`parent_id` AS `parent_id`, `parent_student_links`.`student_id` AS `student_id`, `parent_student_links`.`status` AS `status`, `parent_student_links`.`linked_at` AS `linked_at` FROM `parent_student_links` ;

-- --------------------------------------------------------

--
-- Structure for view `pending_users`
--
DROP TABLE IF EXISTS `pending_users`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `pending_users`  AS SELECT `users`.`id` AS `id`, `users`.`first_name` AS `first_name`, `users`.`middle_name` AS `middle_name`, `users`.`last_name` AS `last_name`, `users`.`email` AS `email`, `users`.`role` AS `role`, `users`.`status` AS `status`, `users`.`created_at` AS `created_at` FROM `users` WHERE `users`.`status` = 'pending' AND `users`.`is_archived` = 0 AND `users`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `student_parent_view`
--
DROP TABLE IF EXISTS `student_parent_view`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `student_parent_view`  AS SELECT `psl`.`id` AS `link_id`, `s`.`id` AS `student_id`, concat(`s`.`first_name`,' ',coalesce(`s`.`middle_name`,''),' ',`s`.`last_name`) AS `student_name`, `s`.`email` AS `student_email`, `s`.`id_num` AS `student_lrn`, `s`.`grade_level` AS `grade_level`, `s`.`section` AS `section`, `p`.`id` AS `parent_id`, concat(`p`.`first_name`,' ',coalesce(`p`.`middle_name`,''),' ',`p`.`last_name`) AS `parent_name`, `p`.`email` AS `parent_email`, `psl`.`status` AS `link_status`, `psl`.`linked_at` AS `linked_at` FROM ((`parent_student_links` `psl` join `users` `s` on(`s`.`id` = `psl`.`student_id`)) join `users` `p` on(`p`.`id` = `psl`.`parent_id`)) WHERE `s`.`role` = 'student' AND `p`.`role` = 'parent' AND `s`.`is_deleted` = 0 AND `p`.`is_deleted` = 0 ;

-- --------------------------------------------------------

--
-- Structure for view `verification_codes`
--
DROP TABLE IF EXISTS `verification_codes`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `verification_codes`  AS SELECT `otp_logs`.`id` AS `id`, `otp_logs`.`user_id` AS `user_id`, `otp_logs`.`email` AS `email`, `otp_logs`.`purpose` AS `purpose`, `otp_logs`.`otp_code` AS `otp_code`, `otp_logs`.`failed_attempts` AS `failed_attempts`, `otp_logs`.`expires_at` AS `expires_at`, `otp_logs`.`created_at` AS `created_at` FROM `otp_logs` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archived_users`
--
ALTER TABLE `archived_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_archived_original` (`original_user_id`),
  ADD KEY `idx_archived_by` (`archived_by`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_date` (`student_id`,`attendance_date`),
  ADD KEY `idx_attendance_date` (`attendance_date`),
  ADD KEY `idx_scanned_by` (`scanned_by`);

--
-- Indexes for table `attendance_scan_logs`
--
ALTER TABLE `attendance_scan_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scan_student` (`student_id`),
  ADD KEY `idx_scan_staff` (`scanned_by`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_admin` (`admin_id`),
  ADD KEY `idx_audit_category` (`category`),
  ADD KEY `idx_audit_created` (`created_at`);

--
-- Indexes for table `deleted_users`
--
ALTER TABLE `deleted_users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_deleted_original` (`original_user_id`),
  ADD KEY `idx_deleted_by` (`deleted_by`);

--
-- Indexes for table `document_access_logs`
--
ALTER TABLE `document_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_access_request` (`document_request_id`),
  ADD KEY `idx_doc_access_user` (`user_id`);

--
-- Indexes for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_student` (`student_id`),
  ADD KEY `idx_doc_requested_by` (`requested_by`),
  ADD KEY `idx_doc_status` (`status`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `otp_logs`
--
ALTER TABLE `otp_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_otp_user` (`user_id`),
  ADD KEY `idx_otp_email` (`email`),
  ADD KEY `idx_otp_purpose` (`purpose`);

--
-- Indexes for table `parent_access_codes`
--
ALTER TABLE `parent_access_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_access_code` (`access_code`),
  ADD KEY `idx_access_student` (`student_id`);

--
-- Indexes for table `parent_student_links`
--
ALTER TABLE `parent_student_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_parent_student` (`parent_id`,`student_id`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_student` (`student_id`);

--
-- Indexes for table `profile_photo_deletion_queue`
--
ALTER TABLE `profile_photo_deletion_queue`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `registration_codes`
--
ALTER TABLE `registration_codes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_qr_token` (`qr_token`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_id_num` (`id_num`),
  ADD KEY `idx_parent_access_code` (`parent_access_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archived_users`
--
ALTER TABLE `archived_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `attendance_scan_logs`
--
ALTER TABLE `attendance_scan_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=134;

--
-- AUTO_INCREMENT for table `deleted_users`
--
ALTER TABLE `deleted_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `document_access_logs`
--
ALTER TABLE `document_access_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `document_requests`
--
ALTER TABLE `document_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=170;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `otp_logs`
--
ALTER TABLE `otp_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `parent_access_codes`
--
ALTER TABLE `parent_access_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `parent_student_links`
--
ALTER TABLE `parent_student_links`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `profile_photo_deletion_queue`
--
ALTER TABLE `profile_photo_deletion_queue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_codes`
--
ALTER TABLE `registration_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7002;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_scanner` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_attendance_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `attendance_scan_logs`
--
ALTER TABLE `attendance_scan_logs`
  ADD CONSTRAINT `fk_scan_staff` FOREIGN KEY (`scanned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_scan_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_access_logs`
--
ALTER TABLE `document_access_logs`
  ADD CONSTRAINT `fk_doc_access_request` FOREIGN KEY (`document_request_id`) REFERENCES `document_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_doc_access_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `document_requests`
--
ALTER TABLE `document_requests`
  ADD CONSTRAINT `fk_doc_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_doc_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `otp_logs`
--
ALTER TABLE `otp_logs`
  ADD CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `parent_access_codes`
--
ALTER TABLE `parent_access_codes`
  ADD CONSTRAINT `fk_access_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `parent_student_links`
--
ALTER TABLE `parent_student_links`
  ADD CONSTRAINT `fk_link_parent` FOREIGN KEY (`parent_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_link_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
