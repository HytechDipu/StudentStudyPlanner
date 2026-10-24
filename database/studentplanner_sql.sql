-- Database: `student_study_planner`
--
CREATE DATABASE IF NOT EXISTS `student_study_planner` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `student_study_planner`;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `course_name`, `created_at`) VALUES
(1, 'B pharma', '2026-10-05 18:32:58'),
(2, 'B.Sc. CS', '2026-10-05 18:41:04'),
(3, 'B.Sc. IT', '2026-10-05 18:47:51');

-- --------------------------------------------------------

--
-- Table structure for table `course_subjects`
--

CREATE TABLE `course_subjects` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_subjects`
--

INSERT INTO `course_subjects` (`id`, `course_id`, `subject_name`, `created_at`) VALUES
(1, 3, 'C#', '2026-10-05 18:48:20'),
(2, 3, '.NET', '2026-10-05 18:48:20'),
(3, 3, 'Cyber Security', '2026-10-05 18:48:20'),
(4, 3, 'Database Management System', '2026-10-05 18:48:20'),
(5, 3, 'Web Technology', '2026-10-05 18:48:20'),
(6, 1, 'Pharmaceutics', '2026-10-05 18:48:20'),
(7, 1, 'Pharmaceutical Chemistry', '2026-10-05 18:48:20'),
(8, 1, 'Pharmacology', '2026-10-05 18:48:20'),
(9, 1, 'Pharmacognosy', '2026-10-05 18:48:20'),
(10, 1, 'Human Anatomy and Physiology', '2026-10-05 18:48:20'),
(11, 2, 'Programming in C', '2026-10-05 18:48:20'),
(12, 2, 'Data Structures', '2026-10-05 18:48:20'),
(13, 2, 'Database Management System', '2026-10-05 18:48:20'),
(14, 2, 'Operating Systems', '2026-10-05 18:48:20'),
(15, 2, 'Computer Networks', '2026-10-05 18:48:20');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `subject_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`, `created_at`) VALUES
(1, 2, NULL, 'information technology', '2026-09-23 16:59:08'),
(8, 2, 4, 'cyber security', '2026-09-23 17:51:52'),
(10, 4, NULL, 'pharmacy', '2026-09-27 19:20:56'),
(11, 2, NULL, 'maths', '2026-10-05 14:34:46'),
(12, 1, 4, 'Cyber Security', '2026-10-05 18:52:56'),
(13, 1, 4, '.NET', '2026-10-05 19:10:41'),
(14, 2, 4, '.NET', '2026-10-05 19:10:41'),
(15, 5, 4, 'Computer Networks', '2026-10-06 08:02:07'),
(16, 5, 5, 'Data Structures', '2026-10-06 08:26:23'),
(17, 6, 5, 'Data Structures', '2026-10-06 08:26:23');

-- --------------------------------------------------------

--
-- Table structure for table `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `tasks`
--

INSERT INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `status`, `created_at`) VALUES
(1, 2, 1, 'Study Chapter 1', '2026-09-23', 'completed', '2026-09-23 18:17:25'),
(2, 2, 8, 'write notes on chapter 2', '2026-09-24', 'completed', '2026-09-24 17:56:36'),
(3, 2, 1, 'Study Chapter 2', '2026-10-06', 'completed', '2026-09-24 18:32:18'),
(4, 4, 10, 'note down any 50 medicines', '2026-09-28', 'completed', '2026-09-27 19:21:38'),
(5, 4, 10, 'complete your project till 30 sep', '2026-09-28', 'completed', '2026-09-27 19:22:32'),
(6, 2, 11, 'write all 5 sums in your assignment', '2026-10-05', 'pending', '2026-10-05 14:35:29');

-- --------------------------------------------------------

--
-- Table structure for table `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teachers`
--

INSERT INTO `teachers` (`id`, `name`, `email`, `password`, `created_at`) VALUES
(1, 'Teacher', 'teacher@gmail.com', '$2y$10$cqYpgnWlu/9IOntvivOiO.gy2Gs/inWGx5XzMEI/F1odiFKqZzwWO', '2026-10-05 16:24:26'),
(4, 'Rekha Naik', 'teacher2@gmail.com', '$2y$10$5OMC5hMX1hsRhksng9glI.zspSWeDVti3KSH8ImHugvRp2BmG9WY.', '2026-10-05 16:42:58'),
(5, 'Vina Shinde', 'vina@gmail.com', '$2y$10$Wy1HQaURP2iFD0QjWLzhN.OxjIyE2bnTFrAIhvnNIOZPviwgzgM/O', '2026-10-06 08:25:27');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_subjects`
--

CREATE TABLE `teacher_subjects` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_subjects`
--

INSERT INTO `teacher_subjects` (`id`, `teacher_id`, `subject_name`) VALUES
(1, 4, 'Cyber Security');

-- --------------------------------------------------------

--
-- Table structure for table `teacher_tasks`
--

CREATE TABLE `teacher_tasks` (
  `id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `task_name` varchar(200) NOT NULL,
  `due_date` date NOT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `teacher_tasks`
--

INSERT INTO `teacher_tasks` (`id`, `teacher_id`, `student_id`, `subject_id`, `task_name`, `due_date`, `status`, `created_at`) VALUES
(2, 4, 1, 12, 'Study Chapter 3', '2026-10-08', 'pending', '2026-10-05 18:52:56'),
(3, 4, 2, 8, 'Study Chapter 3', '2026-10-08', 'pending', '2026-10-05 18:52:56'),
(4, 4, 1, 13, 'Study Chapter 4', '2026-10-09', 'pending', '2026-10-05 19:10:41'),
(5, 4, 2, 14, 'Study Chapter 4', '2026-10-09', 'pending', '2026-10-05 19:10:41'),
(6, 4, 5, 15, 'Study Chapter 1', '2026-10-09', 'pending', '2026-10-06 08:02:07'),
(7, 5, 5, 16, 'write down the excerise of chapter no 2', '2026-10-07', 'pending', '2026-10-06 08:26:23'),
(8, 5, 6, 17, 'write down the excerise of chapter no 2', '2026-10-07', 'pending', '2026-10-06 08:26:23');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `course` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `course`, `password`, `created_at`) VALUES
(1, 'Nidhi Gawad', 'abc22@gmail.com', 'B.Sc. IT', '$2y$10$wAAWnAq2DjA00QXqcr86G.Tqnl.sUKyvV9uuIwJvWkAM11mHhTmEm', '2026-09-22 18:14:52'),
(2, 'Nidhi', 'nidhi1@gmail.com', 'B.Sc. IT', '$2y$10$iK.a6/Y6j7hatxhnN5FJ3ODwONRiP8ty.wxgIE/ubUvaySWMsPIRu', '2026-09-22 18:18:43'),
(3, 'Yuvaa Desh', 'yuva1@gmail.com', 'B.Pharm', '$2y$10$2RaDD8P4HYu2XKyi96yzgeoJV5SC5t4zi2veQZ7pdnjOZmI3bZO06', '2026-09-22 18:37:19'),
(4, 'Dhanu ', 'dhanu123@gmail.com', 'B.Pharm', '$2y$10$J9clOA/ebBB8uhjqeTm6ueOYqcyrbZXArOUgZcd0HXgaA9PGsnr4y', '2026-09-27 19:20:22'),
(5, 'Durva Ranke', 'durva@gmail.com', 'B.Sc. CS', '$2y$10$Mb2cwYb67o/OHbPIpD74P.ZbDc2I908O6cMWdazF3jFpH/btz66Q2', '2026-10-05 14:38:42'),
(6, 'Anvi Raut', 'anvi@gmail.com', 'B.Sc. CS', '$2y$10$x2LIbRZ90QC5Dha6btoOMO7If52Kh0rd6vnbN7dfFCjnLdlozQWiO', '2026-10-06 08:22:20');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_name` (`course_name`);

--
-- Indexes for table `course_subjects`
--
ALTER TABLE `course_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `teacher_tasks`
--
ALTER TABLE `teacher_tasks`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `course_subjects`
--
ALTER TABLE `course_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `teacher_subjects`
--
ALTER TABLE `teacher_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `teacher_tasks`
--
ALTER TABLE `teacher_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
