-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 14, 2025 at 11:50 AM
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
-- Database: `college_assets`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `asset_code` varchar(80) NOT NULL,
  `asset_name_id` int(11) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `room_id` int(11) NOT NULL,
  `parent_asset_id` int(11) DEFAULT NULL,
  `status` enum('Good','Needs Repair') NOT NULL DEFAULT 'Good',
  `warranty_end` date DEFAULT NULL,
  `dealer_id` int(11) NOT NULL,
  `added_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `asset_code`, `asset_name_id`, `category_id`, `room_id`, `parent_asset_id`, `status`, `warranty_end`, `dealer_id`, `added_at`) VALUES
(47, 'BENCH-101-1', 1, 15, 11, NULL, 'Needs Repair', '2026-07-22', 12, '2025-09-20 04:44:03'),
(48, 'BENCH-101-2', 1, 15, 11, NULL, 'Good', '2026-07-24', 12, '2025-08-21 04:45:08'),
(49, 'WHITEBOARD-101-1', 20, 15, 11, NULL, 'Good', '2027-06-16', 2, '2025-09-20 04:48:24'),
(50, 'CPU-205-1', 3, 16, 16, NULL, 'Good', '2026-09-17', 4, '2025-09-20 05:10:31'),
(51, 'MONITOR-205-1', 17, 16, 16, 50, 'Good', '2027-02-16', 12, '2025-09-20 18:29:38'),
(52, 'COMPUTER-101-1', 16, 13, 11, NULL, 'Good', '2026-10-07', 12, '2025-09-20 19:09:10'),
(53, 'KEYBOARD-101-1', 18, 13, 11, NULL, 'Good', '2026-07-22', 12, '2025-09-20 19:20:44'),
(54, 'CABINET-101-1', 23, 17, 11, 52, 'Good', '2222-02-22', 4, '2025-11-25 10:13:18'),
(55, 'DOOR-101-1', 40, 13, 11, 47, 'Good', '5555-05-05', 12, '2025-11-26 08:28:17'),
(56, 'MONITOR-101-1', 17, 13, 11, NULL, 'Needs Repair', '2027-11-26', 12, '2025-11-26 09:40:32'),
(57, 'CABINET-101-2', 23, 13, 11, NULL, 'Good', '2027-11-26', 12, '2025-11-26 09:45:00'),
(58, 'CAMERA-101-1', 29, 17, 11, 47, 'Good', '5555-04-04', 12, '2025-11-27 07:34:46');

-- --------------------------------------------------------

--
-- Table structure for table `asset_names`
--

CREATE TABLE `asset_names` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `asset_names`
--

INSERT INTO `asset_names` (`id`, `name`, `created_at`) VALUES
(1, 'bench', '2025-09-20 09:13:06'),
(2, 'chair', '2025-09-20 09:13:06'),
(3, 'cpu', '2025-09-20 09:13:06'),
(4, 'desk', '2025-09-20 09:13:06'),
(5, 'fan', '2025-09-20 09:13:06'),
(9, 'mouse', '2025-09-20 09:13:06'),
(16, 'Computer', '2025-09-20 09:13:06'),
(17, 'Monitor', '2025-09-20 09:13:06'),
(18, 'Keyboard', '2025-09-20 09:13:06'),
(19, 'Projector', '2025-09-20 09:13:06'),
(20, 'Whiteboard', '2025-09-20 09:13:06'),
(22, 'Table', '2025-09-20 09:13:06'),
(23, 'Cabinet', '2025-09-20 09:13:06'),
(24, 'Locker', '2025-09-20 09:13:06'),
(25, 'Printer', '2025-09-20 09:13:06'),
(27, 'Speaker', '2025-09-20 09:13:06'),
(29, 'Camera', '2025-09-20 09:13:06'),
(30, 'Laptop', '2025-09-20 09:13:06'),
(36, 'Light', '2025-09-20 09:25:48'),
(40, 'Door', '2025-11-13 05:03:35');

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`) VALUES
(17, 'Carpentry'),
(13, 'Electrical'),
(15, 'Furniture & Fixtures'),
(18, 'General Maintenance'),
(16, 'Networking/IT'),
(14, 'Plumbing');

-- --------------------------------------------------------

--
-- Table structure for table `damage_reports`
--

CREATE TABLE `damage_reports` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) NOT NULL,
  `reported_by` int(11) DEFAULT NULL,
  `issue_type` enum('Damage','Missing Sticker','Other') NOT NULL DEFAULT 'Damage',
  `description` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `urgency_priority` enum('Critical','High','Medium','Low') NOT NULL DEFAULT 'Medium',
  `status` enum('pending','assigned','in_progress','completed','resolved') NOT NULL DEFAULT 'pending',
  `assigned_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dealers`
--

CREATE TABLE `dealers` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `dealers`
--

INSERT INTO `dealers` (`id`, `name`, `contact`) VALUES
(1, 'Tech Solutions Pvt Ltd', 'Rajesh Kumar - 9876543210'),
(2, 'Office Furniture Co', 'Priya Sharma - 9876543211'),
(3, 'Lab Equipment India', 'Dr. Amit Singh - 9876543212'),
(4, 'Digital Systems Ltd', 'Sarah Johnson - 9876543213'),
(12, 'CampusCare Ltd', 'YaduKrishna -9856874598');

-- --------------------------------------------------------

--
-- Table structure for table `exam_rooms`
--

CREATE TABLE `exam_rooms` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `status_exam_ready` enum('Yes','No') NOT NULL DEFAULT 'Yes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `exam_rooms`
--

INSERT INTO `exam_rooms` (`id`, `room_id`, `status_exam_ready`, `created_at`, `updated_at`) VALUES
(5, 11, 'No', '2025-09-20 11:31:53', '2025-12-14 10:10:43'),
(6, 12, 'Yes', '2025-09-20 11:32:28', NULL),
(7, 16, 'Yes', '2025-09-20 12:04:13', '2025-12-14 08:54:35'),
(8, 17, 'Yes', '2025-11-24 05:49:28', NULL),
(9, 18, 'Yes', '2025-11-26 02:57:31', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `asset_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_read` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `building` varchar(100) NOT NULL,
  `floor` varchar(50) DEFAULT NULL,
  `room_no` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `room_type` varchar(50) DEFAULT 'classroom',
  `capacity` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `building`, `floor`, `room_no`, `created_at`, `room_type`, `capacity`, `notes`) VALUES
(11, 'Main', '1', '101', '2025-09-20 11:31:53', 'classroom', 30, 'Projecter ഇല'),
(12, 'Main', '2', '201', '2025-09-20 11:32:28', 'classroom', 30, 'Projecter ഇല'),
(13, 'Main', '1', '100', '2025-09-20 12:01:58', 'toilet', 3, 'Toilet ഉണ്ട്'),
(14, 'Main', '2', '200', '2025-09-20 12:02:40', 'toilet', 3, 'Toilet ഉണ്ട്'),
(15, 'Main', '1', '110', '2025-09-20 12:03:27', 'library', 20, 'Books ഉണ്ട്'),
(16, 'Main', '1', '205', '2025-09-20 12:04:13', 'lab', 30, 'Projecter ഉണ്ട്'),
(17, 'main', '2', '242', '2025-11-24 05:49:28', 'classroom', 34, 'sdf'),
(18, 'super', '1', '101', '2025-11-26 02:57:31', 'classroom', 34, 'sadf');

-- --------------------------------------------------------

--
-- Table structure for table `room_assignments`
--

CREATE TABLE `room_assignments` (
  `id` int(11) NOT NULL,
  `room_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `room_assignments`
--

INSERT INTO `room_assignments` (`id`, `room_id`, `faculty_id`, `assigned_at`) VALUES
(8, 11, 14, '2025-09-20 11:49:47'),
(9, 12, 15, '2025-09-20 11:50:20'),
(13, 16, 14, '2025-11-26 02:58:47');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `register_number` varchar(50) DEFAULT NULL,
  `email` varchar(200) NOT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('student','faculty','admin') NOT NULL DEFAULT 'student',
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `register_number`, `email`, `telegram_chat_id`, `password`, `role`, `is_verified`, `created_at`) VALUES
(1, 'Admin', NULL, 'admin@example.com', '2141223422', '$2y$10$crri2qLm/Lp.MP.dwj/68.rHnyR45URVu4RAIirV5JXWUe0mFRM0q', 'admin', 1, '2025-08-28 04:16:12'),
(14, 'faculty1', NULL, 'faculty@example.com', NULL, '$2y$10$crri2qLm/Lp.MP.dwj/68.rHnyR45URVu4RAIirV5JXWUe0mFRM0q', 'faculty', 1, '2025-08-28 04:41:21'),
(15, 'faculty2', NULL, 'faculty2@example.com', NULL, '$2y$10$crri2qLm/Lp.MP.dwj/68.rHnyR45URVu4RAIirV5JXWUe0mFRM0q', 'faculty', 1, '2025-08-28 04:41:37'),
(16, 'student1', NULL, 'student@example.com', '', '$2y$10$crri2qLm/Lp.MP.dwj/68.rHnyR45URVu4RAIirV5JXWUe0mFRM0q', 'student', 1, '2025-08-28 04:42:02'),
(17, 'student2', NULL, 'student2@example.com', '6338672616', '$2y$10$crri2qLm/Lp.MP.dwj/68.rHnyR45URVu4RAIirV5JXWUe0mFRM0q', 'student', 1, '2025-08-28 04:42:21');

-- --------------------------------------------------------

--
-- Table structure for table `workers`
--

CREATE TABLE `workers` (
  `worker_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact` varchar(255) DEFAULT NULL,
  `category_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `workers`
--

INSERT INTO `workers` (`worker_id`, `name`, `contact`, `category_id`, `created_at`) VALUES
(11, 'Ananthu', '9856874598', 14, '2025-09-20 11:52:42'),
(12, 'Shahil', '8856874598', 17, '2025-09-20 11:53:18'),
(13, 'Govind', '9830474598', 18, '2025-09-20 11:54:22'),
(14, 'cabinet', 'jnon-989898989', 17, '2025-11-26 02:59:22');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `asset_code` (`asset_code`),
  ADD KEY `parent_asset_id` (`parent_asset_id`),
  ADD KEY `idx_assets_warranty_end` (`warranty_end`),
  ADD KEY `idx_assets_room_id` (`room_id`),
  ADD KEY `idx_assets_status` (`status`),
  ADD KEY `idx_assets_dealer_id` (`dealer_id`),
  ADD KEY `fk_assets_asset_name` (`asset_name_id`),
  ADD KEY `idx_assets_category` (`category_id`);

--
-- Indexes for table `asset_names`
--
ALTER TABLE `asset_names`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD UNIQUE KEY `unique_asset_name` (`name`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_category_name` (`name`);

--
-- Indexes for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asset_id` (`asset_id`),
  ADD KEY `reported_by` (`reported_by`),
  ADD KEY `assigned_to` (`assigned_to`),
  ADD KEY `idx_damage_reports_status` (`status`),
  ADD KEY `idx_damage_reports_urgency` (`urgency_priority`),
  ADD KEY `idx_damage_reports_created_at` (`created_at`);

--
-- Indexes for table `dealers`
--
ALTER TABLE `dealers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_dealer_name` (`name`);

--
-- Indexes for table `exam_rooms`
--
ALTER TABLE `exam_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_room_id` (`room_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `fk_notifications_asset` (`asset_id`),
  ADD KEY `idx_notifications_created_at` (`created_at`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_room` (`building`,`floor`,`room_no`),
  ADD KEY `idx_rooms_type` (`room_type`);

--
-- Indexes for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_room_faculty` (`room_id`,`faculty_id`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `telegram_chat_id` (`telegram_chat_id`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `workers`
--
ALTER TABLE `workers`
  ADD PRIMARY KEY (`worker_id`),
  ADD KEY `fk_workers_category` (`category_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `asset_names`
--
ALTER TABLE `asset_names`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `damage_reports`
--
ALTER TABLE `damage_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `dealers`
--
ALTER TABLE `dealers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `exam_rooms`
--
ALTER TABLE `exam_rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=534;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `room_assignments`
--
ALTER TABLE `room_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `workers`
--
ALTER TABLE `workers`
  MODIFY `worker_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `assets_ibfk_2` FOREIGN KEY (`parent_asset_id`) REFERENCES `assets` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `assets_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
  ADD CONSTRAINT `fk_assets_asset_name` FOREIGN KEY (`asset_name_id`) REFERENCES `asset_names` (`id`),
  ADD CONSTRAINT `fk_assets_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `fk_assets_dealer` FOREIGN KEY (`dealer_id`) REFERENCES `dealers` (`id`);

--
-- Constraints for table `damage_reports`
--
ALTER TABLE `damage_reports`
  ADD CONSTRAINT `damage_reports_ibfk_1` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `damage_reports_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `damage_reports_ibfk_3` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `exam_rooms`
--
ALTER TABLE `exam_rooms`
  ADD CONSTRAINT `exam_rooms_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `room_assignments`
--
ALTER TABLE `room_assignments`
  ADD CONSTRAINT `room_assignments_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `room_assignments_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `workers`
--
ALTER TABLE `workers`
  ADD CONSTRAINT `fk_workers_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
