-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 10:40 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

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
(1, 'admin@google.com', '$2y$10$oHrwUJdBz20SniJmK9avGu5Gx2TkgtFkMLRK4JVxj/1.L7oBzZjY2'),
(3, 'admin1@google.com', '$2y$10$IhklPCE7kaX2CGL0p4oJ9uDBC9PuSfyfrt9yC2UKVvllAONqt0Yy2'),
(4, 'admin2@google.com', '$2y$10$datxSwi8BmYhmxKEfyC.oeKaOdYoyDhZApIh3IDy7Hi5.29Frj8gq');

-- --------------------------------------------------------

--
-- Table structure for table `admin_sms_log`
--

CREATE TABLE `admin_sms_log` (
  `log_id` int(11) NOT NULL,
  `admin_email` varchar(100) DEFAULT NULL,
  `student_id` int(11) DEFAULT NULL,
  `student_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `http_status` int(11) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `api_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_sms_log`
--

INSERT INTO `admin_sms_log` (`log_id`, `admin_email`, `student_id`, `student_name`, `phone`, `message`, `status`, `http_status`, `error_message`, `api_response`, `created_at`) VALUES
(1, 'admin@google.com', 79420, 'Rishav Shrestha', '9746883728', 'hello mittar', 'failed', NULL, NULL, '{\"status\":\"error\",\"message\":\"No valid Nepali phone numbers. Use format: 98XXXXXXXX, +97798XXXXXXXX, 97798XXXXXXXX\",\"timestamp\":1785150591}', '2026-07-27 11:09:52'),
(2, 'admin@google.com', 79420, 'Rishav Shrestha', '9845230513', 'hello mittar', 'sent', NULL, NULL, '{\"status\":\"success\",\"message\":\"SMS sent successfully\",\"timestamp\":1785150677,\"data\":{\"sms_count\":1,\"cost\":\"1.00\",\"new_balance\":\"760.00\"}}', '2026-07-27 11:11:19'),
(3, 'admin@google.com', 79331, 'Shuvam Rimal', '9862887116', 'boks bro', 'sent', NULL, NULL, '{\"status\":\"success\",\"message\":\"SMS sent successfully\",\"timestamp\":1785151201,\"data\":{\"sms_count\":1,\"cost\":\"1.00\",\"new_balance\":\"759.00\"}}', '2026-07-27 11:20:02');

-- --------------------------------------------------------

--
-- Table structure for table `candidate`
--

CREATE TABLE `candidate` (
  `candidate_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `election_id` int(11) NOT NULL,
  `candidate_photo` varchar(255) DEFAULT NULL,
  `supporter1` int(11) NOT NULL,
  `supporter2` int(11) NOT NULL,
  `proposer` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `candidate`
--

INSERT INTO `candidate` (`candidate_id`, `student_id`, `election_id`, `candidate_photo`, `supporter1`, `supporter2`, `proposer`) VALUES
(1, 79331, 1, NULL, 0, 0, NULL),
(2, 79414, 2, 'assets/uploads/candidates/candidate_79414_1785746222.png', 79406, 79419, NULL);

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
(1, 'Test Event', 'tst', '2026-07-21', '2021', 'BCA', 5, 'closed'),
(2, 'bim', '', '2026-08-02', '2079', 'BIM', 8, 'active'),
(3, 'Tryyyy', 'Try', '2026-08-04', '2079', 'BIM', 8, 'upcoming');

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
(1, 'bim8_2079.csv', '2026-07-31');

-- --------------------------------------------------------

--
-- Table structure for table `otp_requests`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `otp_requests`
--

INSERT INTO `otp_requests` (`otp_id`, `student_id`, `mobile`, `otp`, `requested_at`, `is_used`, `used_at`, `status`) VALUES
(4, 79212, '9862887116', '599915', '2026-07-30 12:20:43', 0, NULL, 'sent');

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
  `student_email` varchar(100) DEFAULT NULL,
  `student_otp` varchar(6) DEFAULT NULL,
  `voting_status` tinyint(1) DEFAULT 0,
  `is_candidate` tinyint(1) DEFAULT 0,
  `is_present` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `student`
--

INSERT INTO `student` (`student_id`, `student_name`, `student_batch`, `student_faculty`, `student_semester`, `student_phone`, `student_email`, `student_otp`, `voting_status`, `is_candidate`, `is_present`) VALUES
(79212, 'hiii', '2079', 'BCA', 8, '9862887116', 'gg76@gmail.com', '599915', 0, 0, 1),
(79253, 'Risali Shrestha', '2079', 'BCA', 5, '9876543210', 'risali@gmail.com', NULL, 0, 0, 1),
(79331, 'Shuvam Rimal', '2021', 'BCA', 5, '9842000000', 'shuvamrimal111@gmail.com', NULL, 0, 1, 0),
(79332, 'Shuvam Rimal2', '2021', 'BCA', 5, '9702996588', 'shuvamrimal123@gmail.com', '870389', 1, 0, 1),
(79401, 'AAMNA KHATOON', '2079', 'BIM', 8, '9817341640', '1@gmail.com', NULL, 0, 0, 0),
(79402, 'AAYUSH SHRESTHA', '2079', 'BIM', 8, '9845230513', '2@gmail.com', NULL, 0, 0, 0),
(79403, 'ANAMIKA POKHAREL', '2079', 'BIM', 8, '9746851491', '3@gmail.com', NULL, 0, 0, 0),
(79404, 'ANANDI YADAV', '2079', 'BIM', 8, '9812351022', '4@gmail.com', NULL, 0, 0, 0),
(79405, 'ARJUN PRASAD RAJBANSHI', '2079', 'BIM', 8, '9829355925', '5@gmail.com', NULL, 0, 0, 0),
(79406, 'AVISHEK RAJBANSHI', '2079', 'BIM', 8, '9825951650', '6@gmail.com', NULL, 0, 0, 0),
(79407, 'BHUMIKA SHRESTHA', '2079', 'BIM', 8, '9824328192', '7@gmail.com', NULL, 0, 0, 0),
(79409, 'BIKRANT THAPA', '2079', 'BIM', 8, '9824378501', '8@gmail.com', NULL, 0, 0, 0),
(79410, 'BISHWOJIT MUKHARJEE', '2079', 'BIM', 8, '9814364289', '9@gmail.com', NULL, 0, 0, 0),
(79412, 'HARISH THAKUR', '2079', 'BIM', 8, '9825351325', '10@gmail.com', NULL, 0, 0, 0),
(79413, 'KRISHNA KUMAR ROY', '2079', 'BIM', 8, '9819364607', '11@gmail.com', NULL, 0, 0, 0),
(79414, 'KRITIKA PRADHAN', '2079', 'BIM', 8, '9804094016', '12@gmail.com', NULL, 0, 1, 0),
(79415, 'MOLI SINGH', '2079', 'BIM', 8, '9815304625', '13@gmail.com', NULL, 0, 0, 0),
(79416, 'NIYASHA KOIRALA', '2079', 'BIM', 8, '9819073104', '14@gmail.com', NULL, 0, 0, 0),
(79417, 'PRATIK MAJHI', '2079', 'BIM', 8, '9811046503', '15@gmail.com', NULL, 0, 0, 0),
(79418, 'PRIYANKA GUPTA ROUNIYAR', '2079', 'BIM', 8, '9810523313', '16@gmail.com', NULL, 0, 0, 0),
(79419, 'RAHUL SAHANI', '2079', 'BIM', 8, '9816301749', '17@gmail.com', NULL, 0, 0, 0),
(79420, 'RISHAV SHRESTHA', '2079', 'BIM', 8, '9746883728', '18@gmail.com', '849469', 1, 0, 1),
(79421, 'RITIKA CHAUDHARY', '2079', 'BIM', 8, '9827354294', '20@gmail.com', NULL, 0, 0, 0),
(79422, 'RUKSHAR ZEBA', '2079', 'BIM', 8, '9816315444', '21@gmail.com', NULL, 0, 0, 0),
(79423, 'SADAB AHAMAD', '2079', 'BIM', 8, '9841076101', '22@gmail.com', NULL, 0, 0, 0),
(79425, 'SEMON NEUPANE', '2079', 'BIM', 8, '9827313757', '23@gmail.com', NULL, 0, 0, 0),
(79426, 'SHIVCHARAN BHAGAT', '2079', 'BIM', 8, '9827303820', '24@gmail.com', NULL, 0, 0, 0),
(79427, 'SHRIJESH KATTEL', '2079', 'BIM', 8, '9800965587', '25@gmail.com', NULL, 0, 0, 0),
(79428, 'SHRISTI NIROULA', '2079', 'BIM', 8, '9808588410', '26@gmail.com', NULL, 0, 0, 0),
(79429, 'SNEHA MAJHI', '2079', 'BIM', 8, '9863498688', '27@gmail.com', NULL, 0, 0, 0),
(79430, 'SUSHANT SINGH', '2079', 'BIM', 8, '9827758767', '28@gmail.com', NULL, 0, 0, 0),
(79431, 'SWASTIKA TIMALASENA', '2079', 'BIM', 8, '9742270601', '29@gmail.com', NULL, 0, 0, 0),
(79432, 'UJJWAL MISHRA', '2079', 'BIM', 8, '9805319275', '30@gmail.com', NULL, 0, 0, 0),
(79434, 'Rishikesh Paudyal', '2079', 'BIM', 8, '9800940908', '19@gmail.com', NULL, 0, 0, 0),
(79456, 'hiiiii', '2079', 'BCA', 8, '9876556098', 'sda1@yahoo.com', NULL, 0, 0, 0),
(79874, 'fgasscdsc', '2079', 'BCA', 8, '9746883730', 'fdgf@yahoo.com', NULL, 0, 0, 0);

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
(3, 2, 2, 79420);

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
-- Indexes for table `admin_sms_log`
--
ALTER TABLE `admin_sms_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `candidate`
--
ALTER TABLE `candidate`
  ADD PRIMARY KEY (`candidate_id`),
  ADD UNIQUE KEY `uc_candidate_unique` (`student_id`,`election_id`),
  ADD KEY `idx_candidate_election` (`election_id`),
  ADD KEY `fk_candidate_proposer` (`proposer`);

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
  ADD UNIQUE KEY `uc_otp_student_once` (`student_id`);

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `admin_sms_log`
--
ALTER TABLE `admin_sms_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `candidate`
--
ALTER TABLE `candidate`
  MODIFY `candidate_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `election`
--
ALTER TABLE `election`
  MODIFY `election_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `excel_list`
--
ALTER TABLE `excel_list`
  MODIFY `excel_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `otp_requests`
--
ALTER TABLE `otp_requests`
  MODIFY `otp_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  ADD CONSTRAINT `fk_candidate_proposer` FOREIGN KEY (`proposer`) REFERENCES `student` (`student_id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
