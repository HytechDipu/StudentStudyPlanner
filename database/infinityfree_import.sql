-- Clean schema & seed data for InfinityFree / Cloud Shared Hosting
-- Charset: utf8mb4 / Collation: utf8mb4_general_ci
-- Note: Do NOT add CREATE DATABASE or USE statements here.
-- First click your database name in phpMyAdmin, then import this file.

-- --------------------------------------------------------
-- Table structure for `courses`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_course_name` (`course_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `course_subjects`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `course_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_course_subjects_course_id` (`course_id`),
  CONSTRAINT `fk_course_subjects_course` FOREIGN KEY (`course_id`)
    REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `users` (Students)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `daily_study_hours` decimal(3,1) NOT NULL DEFAULT 3.0,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `teachers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teachers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
  `lockout_until` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teachers_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `teacher_courses`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_course` (`teacher_id`, `course_id`),
  KEY `idx_tc_teacher_id` (`teacher_id`),
  KEY `idx_tc_course_id` (`course_id`),
  CONSTRAINT `fk_tc_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tc_course` FOREIGN KEY (`course_id`)
    REFERENCES `courses` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `teacher_subjects`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_teacher_subjects_teacher_id` (`teacher_id`),
  CONSTRAINT `fk_ts_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `subjects`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_subject` (`user_id`, `subject_name`),
  KEY `idx_subjects_user_id` (`user_id`),
  KEY `idx_subjects_teacher_id` (`teacher_id`),
  CONSTRAINT `fk_subjects_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_subjects_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `tasks` (Student personal tasks)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `estimated_hours` decimal(4,1) NOT NULL DEFAULT 1.0,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tasks_user_id` (`user_id`),
  KEY `idx_tasks_subject_id` (`subject_id`),
  KEY `idx_tasks_due_date` (`due_date`),
  KEY `idx_tasks_status` (`status`),
  KEY `idx_tasks_priority` (`priority`),
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `teacher_tasks` (Tasks assigned by teacher)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `teacher_tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `estimated_hours` decimal(4,1) NOT NULL DEFAULT 1.0,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_teacher_tasks_teacher_id` (`teacher_id`),
  KEY `idx_teacher_tasks_student_id` (`student_id`),
  KEY `idx_teacher_tasks_subject_id` (`subject_id`),
  KEY `idx_teacher_tasks_due_date` (`due_date`),
  KEY `idx_teacher_tasks_status` (`status`),
  KEY `idx_teacher_tasks_priority` (`priority`),
  CONSTRAINT `fk_teacher_tasks_teacher` FOREIGN KEY (`teacher_id`)
    REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_tasks_student` FOREIGN KEY (`student_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_teacher_tasks_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure for `study_sessions` (Pomodoro study log)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `study_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `minutes` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_study_sessions_user` (`user_id`),
  KEY `idx_study_sessions_subject` (`subject_id`),
  KEY `idx_study_sessions_started` (`started_at`),
  CONSTRAINT `fk_study_sessions_user` FOREIGN KEY (`user_id`)
    REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_study_sessions_subject` FOREIGN KEY (`subject_id`)
    REFERENCES `subjects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Seed Data: Initial Courses
-- --------------------------------------------------------
INSERT IGNORE INTO `courses` (`id`, `course_name`) VALUES
(1, 'B.Pharm'),
(2, 'B.Sc. CS'),
(3, 'B.Sc. IT');

-- --------------------------------------------------------
-- Seed Data: Course Subjects
-- --------------------------------------------------------
INSERT IGNORE INTO `course_subjects` (`id`, `course_id`, `subject_name`) VALUES
(1, 3, 'C#'),
(2, 3, '.NET'),
(3, 3, 'Cyber Security'),
(4, 3, 'Database Management System'),
(5, 3, 'Web Technology'),
(6, 1, 'Pharmaceutics'),
(7, 1, 'Pharmaceutical Chemistry'),
(8, 1, 'Pharmacology'),
(9, 1, 'Pharmacognosy'),
(10, 1, 'Human Anatomy and Physiology'),
(11, 2, 'Programming in C'),
(12, 2, 'Data Structures'),
(13, 2, 'Database Management System'),
(14, 2, 'Operating Systems'),
(15, 2, 'Computer Networks');

-- --------------------------------------------------------
-- Seed Data: Demo Teacher (Password: teacher123)
-- --------------------------------------------------------
INSERT IGNORE INTO `teachers` (`id`, `name`, `email`, `password`) VALUES
(1, 'Professor Demo', 'teacher@example.com', '$2y$10$HXYbEF60s5mSt68EB9yjYu1iX6Az0hwrq.vvJlemhviRsbzBfiaSi');

-- Assign teacher to all courses
INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`) VALUES
(1, 1),
(1, 2),
(1, 3);

-- --------------------------------------------------------
-- Seed Data: Demo Student (Password: student123)
-- --------------------------------------------------------
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `course`, `password`, `daily_study_hours`) VALUES
(1, 'Student Demo', 'student@example.com', 'B.Sc. IT', '$2y$10$f/uJO02mg3mEpthFoaJoXutmm47zNjFedj9GcCLwnGKdk5bn9uzki', 3.0);

-- Demo Student Subjects
INSERT IGNORE INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`) VALUES
(1, 1, 1, 'Database Management System'),
(2, 1, 1, 'Web Technology'),
(3, 1, 1, 'Cyber Security');

-- Demo Student Tasks
INSERT IGNORE INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
(1, 1, 1, 'Design ER diagram for project', CURDATE() + INTERVAL 2 DAY, 'high', 2.0, 'pending'),
(2, 1, 2, 'Build login and signup pages', CURDATE() + INTERVAL 4 DAY, 'medium', 1.5, 'pending'),
(3, 1, 3, 'Read Chapter 3 on Cryptography', CURDATE() - INTERVAL 1 DAY, 'high', 1.0, 'pending'),
(4, 1, 1, 'Review SQL normalization notes', CURDATE() - INTERVAL 2 DAY, 'low', 1.0, 'completed');
