-- Migration 001: Phase 1 Hardening & Clean Foundation
-- Target DBMS: MariaDB 10.4+ / MySQL 8.0+
-- Idempotent: safe to run multiple times

USE `student_study_planner`;

-- 1. Add login throttling columns to users if not present
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'failed_login_attempts'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `failed_login_attempts` int(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'lockout_until'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `users` ADD COLUMN `lockout_until` datetime DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add login throttling columns to teachers if not present
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'failed_login_attempts'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `teachers` ADD COLUMN `failed_login_attempts` int(11) NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'lockout_until'
);
SET @sql = IF(@col_exists = 0, 'ALTER TABLE `teachers` ADD COLUMN `lockout_until` datetime DEFAULT NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Safely deduplicate subjects per user (case-insensitive) before adding UNIQUE constraint
-- Point any tasks referencing duplicates to the canonical (lowest id) subject
UPDATE `tasks` t
JOIN `subjects` dup ON t.subject_id = dup.id
JOIN (
  SELECT user_id, LOWER(subject_name) AS norm_name, MIN(id) AS canonical_id
  FROM `subjects`
  GROUP BY user_id, LOWER(subject_name)
) canon ON dup.user_id = canon.user_id AND LOWER(dup.subject_name) = canon.norm_name
SET t.subject_id = canon.canonical_id
WHERE dup.id <> canon.canonical_id;

-- Point any teacher_tasks referencing duplicates to the canonical subject
UPDATE `teacher_tasks` tt
JOIN `subjects` dup ON tt.subject_id = dup.id
JOIN (
  SELECT user_id, LOWER(subject_name) AS norm_name, MIN(id) AS canonical_id
  FROM `subjects`
  GROUP BY user_id, LOWER(subject_name)
) canon ON dup.user_id = canon.user_id AND LOWER(dup.subject_name) = canon.norm_name
SET tt.subject_id = canon.canonical_id
WHERE dup.id <> canon.canonical_id;

-- Delete the redundant duplicate subjects
DELETE dup FROM `subjects` dup
JOIN (
  SELECT user_id, LOWER(subject_name) AS norm_name, MIN(id) AS canonical_id
  FROM `subjects`
  GROUP BY user_id, LOWER(subject_name)
) canon ON dup.user_id = canon.user_id AND LOWER(dup.subject_name) = canon.norm_name
WHERE dup.id <> canon.canonical_id;

-- 4. Add UNIQUE constraint on subjects (user_id, subject_name) if not present
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND INDEX_NAME = 'uq_user_subject'
);
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `subjects` ADD UNIQUE KEY `uq_user_subject` (`user_id`, `subject_name`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Create teacher_courses table
CREATE TABLE IF NOT EXISTS `teacher_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_course` (`teacher_id`, `course_id`),
  KEY `idx_tc_teacher_id` (`teacher_id`),
  KEY `idx_tc_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Backfill teacher_courses from existing data so teachers retain course assignments
INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`)
SELECT DISTINCT ts.teacher_id, cs.course_id
FROM `teacher_subjects` ts
JOIN `course_subjects` cs ON LOWER(TRIM(ts.subject_name)) = LOWER(TRIM(cs.subject_name));

INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`)
SELECT DISTINCT tt.teacher_id, cs.course_id
FROM `teacher_tasks` tt
JOIN `subjects` s ON tt.subject_id = s.id
JOIN `course_subjects` cs ON LOWER(TRIM(s.subject_name)) = LOWER(TRIM(cs.subject_name));

-- 7. Normalize and update task status columns to ENUM('pending', 'completed')
UPDATE `tasks` SET `status` = 'pending' WHERE `status` IS NULL OR `status` NOT IN ('pending', 'completed');
ALTER TABLE `tasks` MODIFY COLUMN `status` enum('pending','completed') NOT NULL DEFAULT 'pending';

UPDATE `teacher_tasks` SET `status` = 'pending' WHERE `status` IS NULL OR `status` NOT IN ('pending', 'completed');
ALTER TABLE `teacher_tasks` MODIFY COLUMN `status` enum('pending','completed') NOT NULL DEFAULT 'pending';

-- 8. Add missing indexes
-- course_subjects
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_subjects' AND INDEX_NAME = 'idx_course_subjects_course_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `course_subjects` ADD KEY `idx_course_subjects_course_id` (`course_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subjects
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND INDEX_NAME = 'idx_subjects_user_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `subjects` ADD KEY `idx_subjects_user_id` (`user_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND INDEX_NAME = 'idx_subjects_teacher_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `subjects` ADD KEY `idx_subjects_teacher_id` (`teacher_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tasks
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_user_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `tasks` ADD KEY `idx_tasks_user_id` (`user_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_subject_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `tasks` ADD KEY `idx_tasks_subject_id` (`subject_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_due_date');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `tasks` ADD KEY `idx_tasks_due_date` (`due_date`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND INDEX_NAME = 'idx_tasks_status');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `tasks` ADD KEY `idx_tasks_status` (`status`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_tasks
SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND INDEX_NAME = 'idx_teacher_tasks_teacher_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `teacher_tasks` ADD KEY `idx_teacher_tasks_teacher_id` (`teacher_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND INDEX_NAME = 'idx_teacher_tasks_student_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `teacher_tasks` ADD KEY `idx_teacher_tasks_student_id` (`student_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND INDEX_NAME = 'idx_teacher_tasks_subject_id');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `teacher_tasks` ADD KEY `idx_teacher_tasks_subject_id` (`subject_id`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND INDEX_NAME = 'idx_teacher_tasks_due_date');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `teacher_tasks` ADD KEY `idx_teacher_tasks_due_date` (`due_date`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @idx_exists = (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND INDEX_NAME = 'idx_teacher_tasks_status');
SET @sql = IF(@idx_exists = 0, 'ALTER TABLE `teacher_tasks` ADD KEY `idx_teacher_tasks_status` (`status`)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 9. Add Foreign Keys safely
-- course_subjects -> courses
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'course_subjects' AND CONSTRAINT_NAME = 'fk_course_subjects_course');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `course_subjects` ADD CONSTRAINT `fk_course_subjects_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subjects -> users
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND CONSTRAINT_NAME = 'fk_subjects_user');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `subjects` ADD CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- subjects -> teachers
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'subjects' AND CONSTRAINT_NAME = 'fk_subjects_teacher');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `subjects` ADD CONSTRAINT `fk_subjects_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tasks -> users
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_tasks_user');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tasks -> subjects
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND CONSTRAINT_NAME = 'fk_tasks_subject');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_tasks -> teachers
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND CONSTRAINT_NAME = 'fk_teacher_tasks_teacher');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `teacher_tasks` ADD CONSTRAINT `fk_teacher_tasks_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_tasks -> users
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND CONSTRAINT_NAME = 'fk_teacher_tasks_student');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `teacher_tasks` ADD CONSTRAINT `fk_teacher_tasks_student` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_tasks -> subjects
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND CONSTRAINT_NAME = 'fk_teacher_tasks_subject');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `teacher_tasks` ADD CONSTRAINT `fk_teacher_tasks_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_courses -> teachers
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_courses' AND CONSTRAINT_NAME = 'fk_tc_teacher');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `teacher_courses` ADD CONSTRAINT `fk_tc_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- teacher_courses -> courses
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_courses' AND CONSTRAINT_NAME = 'fk_tc_course');
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `teacher_courses` ADD CONSTRAINT `fk_tc_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
