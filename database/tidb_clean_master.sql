-- ========================================================
-- Student Study Planner - Clean Master Database Script
-- Target: TiDB Cloud Serverless / MySQL 8.0+
-- Database: test
-- ========================================================

USE test;

-- Drop existing tables to start 100% fresh
DROP TABLE IF EXISTS `study_sessions`;
DROP TABLE IF EXISTS `teacher_tasks`;
DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `subjects`;
DROP TABLE IF EXISTS `teacher_subjects`;
DROP TABLE IF EXISTS `teacher_courses`;
DROP TABLE IF EXISTS `teachers`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `course_subjects`;
DROP TABLE IF EXISTS `courses`;

-- 1. Courses Table
CREATE TABLE `courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_courses_course_name` (`course_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Course Subjects Table
CREATE TABLE `course_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `course_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_course_subjects_course_id` (`course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3. Users Table (Students)
CREATE TABLE `users` (
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

-- 4. Teachers Table
CREATE TABLE `teachers` (
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

-- 5. Teacher Courses (Courses taught by teachers)
CREATE TABLE `teacher_courses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_teacher_course` (`teacher_id`, `course_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. Teacher Subjects
CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 7. Subjects (Enrolled by students)
CREATE TABLE `subjects` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_subject` (`user_id`, `subject_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8. Personal Tasks
CREATE TABLE `tasks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `due_date` date NOT NULL,
  `priority` enum('low','medium','high') NOT NULL DEFAULT 'medium',
  `estimated_hours` decimal(4,1) NOT NULL DEFAULT 1.0,
  `status` enum('pending','completed') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9. Teacher Assigned Tasks
CREATE TABLE `teacher_tasks` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 10. Study Sessions (Pomodoro Log)
CREATE TABLE `study_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `minutes` int(11) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ========================================================
-- Seed Initial Data
-- ========================================================

-- Initial Courses
INSERT INTO `courses` (`id`, `course_name`) VALUES
(1, 'B.Pharm'),
(2, 'B.Sc. CS'),
(3, 'B.Sc. IT');

-- Initial Course Subjects
INSERT INTO `course_subjects` (`id`, `course_id`, `subject_name`) VALUES
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

-- Demo Teacher (Password: teacher123)
INSERT INTO `teachers` (`id`, `name`, `email`, `password`) VALUES
(1, 'Professor Demo', 'teacher@example.com', '$2y$10$HXYbEF60s5mSt68EB9yjYu1iX6Az0hwrq.vvJlemhviRsbzBfiaSi');

-- Assign Demo Teacher to Courses
INSERT INTO `teacher_courses` (`teacher_id`, `course_id`) VALUES
(1, 1),
(1, 2),
(1, 3);

-- Demo Student (Password: student123)
INSERT INTO `users` (`id`, `name`, `email`, `course`, `password`, `daily_study_hours`) VALUES
(1, 'Student Demo', 'student@example.com', 'B.Sc. IT', '$2y$10$f/uJO02mg3mEpthFoaJoXutmm47zNjFedj9GcCLwnGKdk5bn9uzki', 3.0);

-- Demo Student Subjects
INSERT INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`) VALUES
(1, 1, 1, 'Database Management System'),
(2, 1, 1, 'Web Technology'),
(3, 1, 1, 'Cyber Security');

-- Demo Student Tasks
INSERT INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
(1, 1, 1, 'Design ER diagram for project', CURDATE() + INTERVAL 2 DAY, 'high', 2.0, 'pending'),
(2, 1, 2, 'Build login and signup pages', CURDATE() + INTERVAL 4 DAY, 'medium', 1.5, 'pending'),
(3, 1, 3, 'Read Chapter 3 on Cryptography', CURDATE() - INTERVAL 1 DAY, 'high', 1.0, 'pending'),
(4, 1, 1, 'Review SQL normalization notes', CURDATE() - INTERVAL 2 DAY, 'low', 1.0, 'completed');
