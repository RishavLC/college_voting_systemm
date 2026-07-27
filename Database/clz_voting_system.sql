-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2026 at 02:45 PM
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
-- Database: `clz_voting_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`id`, `email`, `password`) VALUES
(1, 'admin@google.com', '$2y$10$oHrwUJdBz20SniJmK9avGu5Gx2TkgtFkMLRK4JVxj/1.L7oBzZjY2');

-- --------------------------------------------------------

--
-- Table structure for table `candidate`
--

CREATE TABLE `candidate` (
  `candidate_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_photo` varchar(255) DEFAULT NULL,
  `supporter1` int(11) DEFAULT NULL,
  `supporter2` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate`
--

INSERT INTO `candidate` (`candidate_id`, `student_id`, `election_id`, `candidate_photo`, `supporter1`, `supporter2`) VALUES
(1, 79331, 1, NULL, NULL, NULL),
(2, 1054, 1, NULL, NULL, NULL),
(3, 79332, 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `election`
--

CREATE TABLE `election` (
  `election_id` int(11) NOT NULL,
  `election_name` varchar(100) NOT NULL,
  `alias` varchar(50) DEFAULT NULL,
  `election_date` date NOT NULL,
  `election_batch` varchar(20) NOT NULL,
  `election_faculty` varchar(50) NOT NULL,
  `election_semester` int(11) NOT NULL CHECK (`election_semester` between 1 and 8),
  `election_status` varchar(20) DEFAULT 'upcoming'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `election`
--

INSERT INTO `election` (`election_id`, `election_name`, `alias`, `election_date`, `election_batch`, `election_faculty`, `election_semester`, `election_status`) VALUES
(1, 'Test Event', 'tst', '2026-07-27', '2021', 'BCA', 5, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `excel_list`
--

CREATE TABLE `excel_list` (
  `excel_id` int(11) NOT NULL,
  `excel_name` varchar(200) NOT NULL,
  `excel_date` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `excel_list`
--

INSERT INTO `excel_list` (`excel_id`, `excel_name`, `excel_date`) VALUES
(3, 'votetest.csv', '2026-07-22');

-- --------------------------------------------------------

--
-- Table structure for table `otp_requests`
--
-- Stores every OTP generated for a student, when it was requested,
-- whether/when it was used to verify. The UNIQUE key on `student_id`
-- enforces "one OTP request per student" at the database level.
--

CREATE TABLE `otp_requests` (
  `otp_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `status` enum('sent','verified','expired') NOT NULL DEFAULT 'sent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student`
--

CREATE TABLE `student` (
  `student_id` int(11) NOT NULL,
  `student_name` varchar(100) NOT NULL,
  `student_batch` varchar(20) NOT NULL,
  `student_faculty` varchar(50) NOT NULL,
  `student_semester` int(11) NOT NULL CHECK (`student_semester` between 1 and 8),
  `student_phone` varchar(15) DEFAULT NULL,
  `student_email` varchar(100) NOT NULL,
  `student_otp` varchar(6) DEFAULT NULL,
  `voting_status` tinyint(1) DEFAULT 0,
  `is_candidate` tinyint(1) DEFAULT 0,
  `is_present` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `student_name`, `student_batch`, `student_faculty`, `student_semester`, `student_phone`, `student_email`, `student_otp`, `voting_status`, `is_candidate`, `is_present`) VALUES
(12, 'a', '2021', 'BCA', 5, '9800000000', '10001', '975272', 0, 0, 0),
(13, 'b', '3', 'set', 6, '5', '2@gmail.com', NULL, 0, 0, 0),
(14, 'c', '3', 'set', 6, '6', '3@gmail.com', NULL, 0, 0, 0),
(15, 'd', '3', 'set', 6, '7', '4@gmail.com', NULL, 0, 0, 0),
(16, 'e', '3', 'set', 6, '8', '5@gmail.com', NULL, 0, 0, 0),
(17, 'd', '3', 'set', 6, '9', '6@gmail.com', NULL, 0, 0, 0),
(18, 'e', '3', 'set', 6, '10', '7@gmail.com', NULL, 0, 0, 0),
(19, 'd', '3', 'set', 6, '11', '8', NULL, 0, 1, 0),
(456, 'Shishir khatiwada', '2021', 'BCA', 5, '9840747919', 'shishir@gmail.com', '775046', 1, 0, 0),
(1054, 'Rishav Shrestha', '2021', 'BCA', 5, '9811111111', '0', '697265', 0, 1, 0),
(56421, 'a', '2021', 'BCA', 5, '9822222222', 'Abc@gmail.com', '052979', 0, 0, 0),
(79331, 'Shuvam Rimal', '2021', 'BCA', 5, '9862887116', 'shuvamrimal111@gmail.com', NULL, 0, 1, 0),
(79332, 'Shuvam Rimal2', '2021', 'BCA', 5, '9702996588', 'shuvamrimal123@gmail.com', '926746', 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `vote`
--

CREATE TABLE `vote` (
  `vote_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_id` int(11) NOT NULL,
  `voter_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vote`
--

INSERT INTO `vote` (`vote_id`, `election_id`, `candidate_id`, `voter_id`) VALUES
(1, 1, 1, 79332),
(2, 1, 1, 12),
(3, 1, 1, 456);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `candidate`
--
ALTER TABLE `candidate`
  ADD PRIMARY KEY (`candidate_id`),
  ADD UNIQUE KEY `uc_candidate_unique` (`student_id`,`election_id`),
  ADD KEY `idx_candidate_election` (`election_id`),
  ADD KEY `fk_candidate_supporter1` (`supporter1`),
  ADD KEY `fk_candidate_supporter2` (`supporter2`);

--
-- Indexes for table `election`
--
ALTER TABLE `election`
  ADD PRIMARY KEY (`election_id`);

--
-- Indexes for table `excel_list`
--
ALTER TABLE `excel_list`
  ADD PRIMARY KEY (`excel_id`);

--
-- Indexes for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD PRIMARY KEY (`otp_id`),
  ADD UNIQUE KEY `uc_otp_student_once` (`student_id`),
  ADD KEY `idx_otp_student` (`student_id`);

--
-- Indexes for table `student`
--
ALTER TABLE `student`
  ADD PRIMARY KEY (`student_id`),
  ADD UNIQUE KEY `student_email` (`student_email`),
  ADD UNIQUE KEY `student_phone` (`student_phone`),
  ADD UNIQUE KEY `student_otp` (`student_otp`),
  ADD KEY `idx_student_batch` (`student_batch`),
  ADD KEY `idx_student_faculty` (`student_faculty`);

--
-- Indexes for table `vote`
--
ALTER TABLE `vote`
  ADD PRIMARY KEY (`vote_id`),
  ADD UNIQUE KEY `uc_vote_unique` (`election_id`,`voter_id`),
  ADD KEY `voter_id` (`voter_id`),
  ADD KEY `idx_vote_election` (`election_id`),
  ADD KEY `idx_vote_candidate` (`candidate_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidate`
--
ALTER TABLE `candidate`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `election`
--
ALTER TABLE `election`
  MODIFY `election_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `excel_list`
--
ALTER TABLE `excel_list`
  MODIFY `excel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `otp_requests`
--
ALTER TABLE `otp_requests`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

--
-- AUTO_INCREMENT for table `vote`
--
ALTER TABLE `vote`
  MODIFY `vote_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `candidate`
--
ALTER TABLE `candidate`
  ADD CONSTRAINT `candidate_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `candidate_ibfk_2` FOREIGN KEY (`election_id`) REFERENCES `election` (`election_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidate_supporter1` FOREIGN KEY (`supporter1`) REFERENCES `student` (`student_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_candidate_supporter2` FOREIGN KEY (`supporter2`) REFERENCES `student` (`student_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `otp_requests`
--
ALTER TABLE `otp_requests`
  ADD CONSTRAINT `otp_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vote`
--
ALTER TABLE `vote`
  ADD CONSTRAINT `vote_ibfk_1` FOREIGN KEY (`election_id`) REFERENCES `election` (`election_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `vote_ibfk_2` FOREIGN KEY (`candidate_id`) REFERENCES `candidate` (`candidate_id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `vote_ibfk_3` FOREIGN KEY (`voter_id`) REFERENCES `student` (`student_id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
