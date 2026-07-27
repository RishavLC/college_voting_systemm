-- ============================================================
-- Migration: OTP Requests table
-- Run this once against the existing `clz_voting_system` database
-- (phpMyAdmin -> SQL tab -> paste & Go), or:
--   mysql -u root -p clz_voting_system < add_otp_requests_table.sql
-- ============================================================

-- --------------------------------------------------------
-- Table structure for table `otp_requests`
--
-- Stores every OTP that gets generated for a student along with
-- when it was requested, whether it has been used to log in, and
-- when it was verified. This is what enforces the "one OTP request
-- per student" rule and gives admins a place to look up a student's
-- OTP when the student can't receive/request it again.
-- --------------------------------------------------------

CREATE TABLE `otp_requests` (
  `otp_id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `mobile` varchar(15) NOT NULL,
  `otp` varchar(6) NOT NULL,
  `requested_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `status` enum('sent','verified','expired') NOT NULL DEFAULT 'sent',
  PRIMARY KEY (`otp_id`),
  UNIQUE KEY `uc_otp_student_once` (`student_id`),
  KEY `idx_otp_student` (`student_id`),
  CONSTRAINT `otp_requests_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `student` (`student_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- NOTE on `uc_otp_student_once`:
-- This UNIQUE key means each student can only ever have ONE row in
-- this table for their whole lifetime in the system, i.e. only ONE
-- OTP can ever be logged for them by student_verify.php. That is what
-- makes "request OTP a second time" fail at the database level, on
-- top of the application-level check. If you later want to allow one
-- OTP request per election (instead of one forever), drop this unique
-- key and add `election_id` to the table + a UNIQUE(student_id, election_id)
-- key instead, then update student_verify.php's INSERT accordingly.
