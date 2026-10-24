-- Migration 002: Phase 2 Student Features
-- Target DBMS: MariaDB 10.4+ / MySQL 8.0+
-- Idempotent: safe to run multiple times

USE `student_study_planner`;

-- 1. Add priority and estimated_hours to tasks
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'priority'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE `tasks` ADD COLUMN `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks' AND COLUMN_NAME = 'estimated_hours'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE `tasks` ADD COLUMN `estimated_hours` decimal(4,1) NOT NULL DEFAULT 1.0", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. Add priority and estimated_hours to teacher_tasks
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND COLUMN_NAME = 'priority'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE `teacher_tasks` ADD COLUMN `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium'", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teacher_tasks' AND COLUMN_NAME = 'estimated_hours'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE `teacher_tasks` ADD COLUMN `estimated_hours` decimal(4,1) NOT NULL DEFAULT 1.0", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Add daily_study_hours to users
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'daily_study_hours'
);
SET @sql = IF(@col_exists = 0, "ALTER TABLE `users` ADD COLUMN `daily_study_hours` decimal(3,1) NOT NULL DEFAULT 3.0", 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. Create study_sessions table
CREATE TABLE IF NOT EXISTS `study_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `minutes` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_study_sessions_user` (`user_id`),
  KEY `idx_study_sessions_subject` (`subject_id`),
  KEY `idx_study_sessions_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 5. Add foreign keys for study_sessions safely
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'study_sessions' AND CONSTRAINT_NAME = 'fk_study_sessions_user'
);
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `study_sessions` ADD CONSTRAINT `fk_study_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'study_sessions' AND CONSTRAINT_NAME = 'fk_study_sessions_subject'
);
SET @sql = IF(@fk_exists = 0, 'ALTER TABLE `study_sessions` ADD CONSTRAINT `fk_study_sessions_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE SET NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
