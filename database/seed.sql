USE `student_study_planner`;

-- Insert initial courses
INSERT IGNORE INTO `courses` (`id`, `course_name`) VALUES
(1, 'B.Pharm'),
(2, 'B.Sc. CS'),
(3, 'B.Sc. IT');

-- Insert initial course subjects
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

-- Insert demo teacher (password: teacher123)
INSERT IGNORE INTO `teachers` (`id`, `name`, `email`, `password`) VALUES
(1, 'Professor Demo', 'teacher@example.com', '$2y$10$HXYbEF60s5mSt68EB9yjYu1iX6Az0hwrq.vvJlemhviRsbzBfiaSi');

-- Assign teacher to courses
INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`) VALUES
(1, 1),
(1, 2),
(1, 3);

-- Insert demo student (password: student123)
INSERT IGNORE INTO `users` (`id`, `name`, `email`, `course`, `password`, `daily_study_hours`) VALUES
(1, 'Student Demo', 'student@example.com', 'B.Sc. IT', '$2y$10$f/uJO02mg3mEpthFoaJoXutmm47zNjFedj9GcCLwnGKdk5bn9uzki', 3.0);

-- Insert subjects for student
INSERT IGNORE INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`) VALUES
(1, 1, 1, 'Database Management System'),
(2, 1, 1, 'Web Technology'),
(3, 1, 1, 'Cyber Security');

-- Insert sample tasks for student
INSERT IGNORE INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
(1, 1, 1, 'Design ER diagram for project', CURDATE() + INTERVAL 2 DAY, 'high', 2.0, 'pending'),
(2, 1, 2, 'Build login and signup pages', CURDATE() + INTERVAL 4 DAY, 'medium', 1.5, 'pending'),
(3, 1, 3, 'Read Chapter 3 on Cryptography', CURDATE() - INTERVAL 1 DAY, 'high', 1.0, 'pending'),
(4, 1, 1, 'Review SQL normalization notes', CURDATE() - INTERVAL 2 DAY, 'low', 1.0, 'completed');
