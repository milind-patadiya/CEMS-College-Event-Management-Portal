-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 10, 2026 at 06:23 PM
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
-- Database: `cems_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `full_name`, `username`, `password`) VALUES
(1, 'Administrator', 'admin', '$2y$12$ba4Bgo4aK.0Jr0SLrsH5Mu4CUamRuxUZco9IcDrE2XbgBZI2PvxRq');

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `event_id` int(11) DEFAULT NULL,
  `posted_by` int(11) NOT NULL,
  `posted_by_role` enum('faculty','admin') NOT NULL DEFAULT 'faculty',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `content`, `event_id`, `posted_by`, `posted_by_role`, `created_at`) VALUES
(1, 'Tech Symposium — Registration Open', 'Registrations for the Annual Tech Symposium are now open. Last date to register is 10th April 2026. All CS and IT students are encouraged to participate.', 1, 1, 'faculty', '2026-04-04 20:32:34'),
(2, 'Web Dev Workshop — Limited Seats', 'The Web Development Workshop has only 40 seats. Register early to secure your spot. Bring your own laptop.', NULL, 2, 'faculty', '2026-04-04 20:32:34'),
(3, 'Hackathon 2026 — Team Registration', 'Form your teams now for Hackathon 2026. Teams of 2 to 4 members. Problem statements will be released on the day of the event.', 4, 1, 'faculty', '2026-04-04 20:32:34'),
(4, 'Sports Day Volunteers Needed', 'We are looking for volunteers to help organize Sports Day. Interested students can contact the Sports Committee.', NULL, 2, 'faculty', '2026-04-04 20:32:34'),
(5, 'Campus Wi-Fi Maintenance', 'Campus Wi-Fi will be down for maintenance on Sunday from 10 PM to 2 AM. Plan accordingly.', NULL, 1, 'faculty', '2026-04-04 20:32:34'),
(6, 'Open for Coding Challenge', 'Registrations are now open for the upcoming Code Debugging Challenge. Interested students can register through the events section on the portal.\r\n\r\nSeats are limited and will be allocated on a first-come, first-served basis. Participants are encouraged to register early.\r\n\r\nLast date for registration is 15th March 2026.', NULL, 3, 'faculty', '2026-04-07 17:28:18'),
(7, 'Approval of Upcoming Technical Events', 'The administration has approved the upcoming technical events scheduled for this semester. All concerned faculty members are instructed to ensure smooth coordination and execution of the events.', NULL, 1, 'admin', '2026-04-08 12:28:27');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `status` enum('Pending','Present','Absent') NOT NULL DEFAULT 'Absent',
  `marked_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `attendance`
--

INSERT INTO `attendance` (`attendance_id`, `student_id`, `event_id`, `status`, `marked_at`) VALUES
(5, 4, 7, 'Present', '2026-04-07 17:26:02');

-- --------------------------------------------------------

--
-- Table structure for table `certificates`
--

CREATE TABLE `certificates` (
  `certificate_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `certificate_url` varchar(500) DEFAULT NULL,
  `issued_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `certificates`
--

INSERT INTO `certificates` (`certificate_id`, `event_id`, `student_id`, `certificate_url`, `issued_date`) VALUES
(2, 7, 4, '', '2026-04-07 21:40:52');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `event_name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Approved',
  `rejection_reason` varchar(500) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `description`, `venue`, `event_date`, `event_time`, `capacity`, `status`, `rejection_reason`, `created_by`, `created_at`) VALUES
(1, 'Tech Symposium 2026', 'Annual technical symposium featuring guest lectures, paper presentations and workshops on emerging technologies.', 'Main Auditorium', '2026-04-01', '10:00:00', 200, 'Approved', NULL, 1, '2026-04-04 20:32:34'),
(2, 'Cultural Fest — Utsav', 'Annual cultural festival with dance, music, drama and art competitions. Open to all students.', 'College Ground', '2026-04-20', '09:00:00', 500, 'Pending', NULL, 1, '2026-04-04 20:32:34'),
(4, 'Hackathon 2026', 'A 24-hour coding competition. Form teams of 2-4 and solve real-world problems.', 'Innovation Lab', '2026-05-10', '08:00:00', 100, 'Pending', NULL, 1, '2026-04-04 20:32:34'),
(6, 'AI & ML Seminar', 'Expert seminar on the latest trends in Artificial Intelligence and Machine Learning.', 'Seminar Hall B', '2026-05-20', '14:00:00', 80, 'Approved', NULL, 1, '2026-04-04 20:32:34'),
(7, 'Rhythm Night 2026', 'An exciting evening of dance, music, and live performances by students. Solo and group entries are allowed. Winners will receive exciting prizes.', 'Open Ground Stage', '2026-04-08', '06:30:00', 400, 'Approved', NULL, 3, '2026-04-07 17:02:41');

-- --------------------------------------------------------

--
-- Table structure for table `faculty`
--

CREATE TABLE `faculty` (
  `faculty_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `username` varchar(80) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `faculty`
--

INSERT INTO `faculty` (`faculty_id`, `full_name`, `email`, `department`, `username`, `password`, `created_at`) VALUES
(1, 'Prof. Hari Prasad', 'hari.prasad@cems.edu', 'Computer Science', 'hari.prasad', '$2y$12$0nQxHGbmdmbqzcnABrcfSulPOWM7dJVG/iscUcPOBZL/s5iA2jNpa', '2026-04-04 20:32:34'),
(3, 'Prof. Ram Mohan', 'ram.mohan@gmail.com', 'Computer Engineer', 'Prof. Ram Mohan', '$2y$10$jVmfAEj3tVRy/Qp/kn9nle0USAHjWV8X0jVynFWia3CfXIDY7RDsq', '2026-04-07 16:55:49'),
(6, 'Gopal Krishna', 'gopal.krishna@cems.edu', 'Computer Science', 'Prof. Gopal Krishna', '$2y$10$0XxGuXH121nNREJkVK1lLOxwmMWhymADEDeqHt9vzJXr/9X1fNLn2', '2026-04-08 10:17:30');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `registration_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`registration_id`, `student_id`, `event_id`, `registration_date`) VALUES
(5, 3, 1, '2026-04-07 16:16:23'),
(6, 3, 2, '2026-04-07 16:19:08'),
(7, 4, 1, '2026-04-07 16:33:42'),
(8, 4, 7, '2026-04-07 17:22:25');

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `student_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `enrollment_no` varchar(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`student_id`, `full_name`, `enrollment_no`, `email`, `phone`, `password`, `created_at`) VALUES
(3, 'Prashant Vala Pravinbhai', '92400000000', 'prashantvala2705@gmail.com', '', '$2y$10$GAWYME3ygHnRWRTwDlpz/.amCuRy/OZ27BD7Y4DMeXHbjvCgeYeG2', '2026-04-07 16:15:58'),
(4, 'Vala Prashant Pravinbhai', '92410103090', 'prashant.vala127726@marwadiuniversity.ac.in', '', '$2y$10$7Ox6X8jLsAYuTYaSDvuAo.QZOJdHMtvFStmZEkxNgncBz7F9tGMa2', '2026-04-07 16:32:28'),
(6, 'Milind Alpesh Patadiya', '92410103048', 'milind.patadiya127906@marwadiuniversity.ac.in', '', '$2y$10$jVmfAEj3tVRy/Qp/kn9nle0USAHjWV8X0jVynFWia3CfXIDY7RDsq', '2026-04-08 11:59:43');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `uq_att` (`student_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `certificates`
--
ALTER TABLE `certificates`
  ADD PRIMARY KEY (`certificate_id`),
  ADD UNIQUE KEY `uq_cert` (`student_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `faculty`
--
ALTER TABLE `faculty`
  ADD PRIMARY KEY (`faculty_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD UNIQUE KEY `uq_reg` (`student_id`,`event_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `enrollment_no` (`enrollment_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `certificates`
--
ALTER TABLE `certificates`
  MODIFY `certificate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `faculty`
--
ALTER TABLE `faculty`
  MODIFY `faculty_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `student_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE SET NULL;

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `certificates`
--
ALTER TABLE `certificates`
  ADD CONSTRAINT `certificates_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `certificates_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `faculty` (`faculty_id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`student_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
