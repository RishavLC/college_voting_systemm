-- ============================================================
-- Migration: make student_email optional
-- Run this once against your existing `clz_voting_system` database
-- (phpMyAdmin -> SQL tab -> paste & Go), or:
--   mysql -u root -p clz_voting_system < make_email_optional.sql
-- ============================================================

ALTER TABLE `student`
  MODIFY `student_email` varchar(100) DEFAULT NULL;

-- NOTE: the UNIQUE key on student_email is left in place and still
-- works correctly — MySQL's UNIQUE constraint allows multiple rows to
-- have a NULL value; it only enforces uniqueness among non-NULL
-- values. So many students can now have no email at all, while any
-- email that IS entered still has to be unique.
