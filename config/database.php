<?php

// Enable mysqli exception mode for robust error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$database = getenv('DB_NAME') ?: "student_study_planner";
$port     = (int)(getenv('DB_PORT') ?: 3306);
$sslCa    = getenv('DB_SSL_CA') ?: null;

try {
    $conn = mysqli_init();
    if ($host !== 'localhost' && $host !== '127.0.0.1') {
        // TiDB Cloud and remote managed MySQL require SSL
        if ($sslCa && file_exists($sslCa)) {
            $conn->ssl_set(NULL, NULL, $sslCa, NULL, NULL);
        } elseif (file_exists('/etc/ssl/certs/ca-certificates.crt')) {
            // Standard CA bundle on Linux/Render/Docker
            $conn->ssl_set(NULL, NULL, '/etc/ssl/certs/ca-certificates.crt', NULL, NULL);
        } else {
            $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        }
        $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
        $conn->real_connect($host, $username, $password, $database, $port, NULL, MYSQLI_CLIENT_SSL);
    } else {
        $conn->real_connect($host, $username, $password, $database, $port);
    }
    $conn->set_charset("utf8mb4");

    // Automatically initialize missing database tables
    ensure_database_schema($conn);
} catch (mysqli_sql_exception $e) {
    // Show friendly message while not leaking sensitive credentials
    error_log("Database connection error: " . $e->getMessage());
    die("Database connection failed. Please ensure MySQL is running and properly configured.");
}

function ensure_database_schema(mysqli $conn): void {
    try {
        // Check if the primary tasks table exists
        $check = $conn->query("SHOW TABLES LIKE 'tasks'");
        if ($check && $check->num_rows > 0) {
            return; // Schema already created
        }

        $teacherHash = password_hash('teacher123', PASSWORD_DEFAULT);
        $studentHash = password_hash('student123', PASSWORD_DEFAULT);

        $statements = [
            "CREATE TABLE IF NOT EXISTS `courses` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `course_name` varchar(100) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_courses_course_name` (`course_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `course_subjects` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `course_id` int(11) NOT NULL,
              `subject_name` varchar(100) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `idx_course_subjects_course_id` (`course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `users` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `teachers` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(100) NOT NULL,
              `email` varchar(100) NOT NULL,
              `password` varchar(255) NOT NULL,
              `failed_login_attempts` int(11) NOT NULL DEFAULT 0,
              `lockout_until` datetime DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_teachers_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `teacher_courses` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `teacher_id` int(11) NOT NULL,
              `course_id` int(11) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_teacher_course` (`teacher_id`, `course_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `teacher_subjects` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `teacher_id` int(11) NOT NULL,
              `subject_name` varchar(100) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `subjects` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `teacher_id` int(11) DEFAULT NULL,
              `subject_name` varchar(100) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_user_subject` (`user_id`, `subject_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `tasks` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `teacher_tasks` (
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "CREATE TABLE IF NOT EXISTS `study_sessions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `subject_id` int(11) DEFAULT NULL,
              `minutes` int(11) NOT NULL,
              `started_at` timestamp NOT NULL DEFAULT current_timestamp(),
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

            "INSERT IGNORE INTO `courses` (`id`, `course_name`) VALUES (1, 'B.Pharm'), (2, 'B.Sc. CS'), (3, 'B.Sc. IT')",

            "INSERT IGNORE INTO `course_subjects` (`id`, `course_id`, `subject_name`) VALUES
             (1, 3, 'C#'), (2, 3, '.NET'), (3, 3, 'Cyber Security'), (4, 3, 'Database Management System'),
             (5, 3, 'Web Technology'), (6, 1, 'Pharmaceutics'), (7, 1, 'Pharmaceutical Chemistry'),
             (8, 1, 'Pharmacology'), (9, 1, 'Pharmacognosy'), (10, 1, 'Human Anatomy and Physiology'),
             (11, 2, 'Programming in C'), (12, 2, 'Data Structures'), (13, 2, 'Database Management System'),
             (14, 2, 'Operating Systems'), (15, 2, 'Computer Networks')",

            "INSERT IGNORE INTO `teachers` (`id`, `name`, `email`, `password`) VALUES
             (1, 'Professor Demo', 'teacher@example.com', '{$teacherHash}')",

            "INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`) VALUES (1, 1), (1, 2), (1, 3)",

            "INSERT IGNORE INTO `users` (`id`, `name`, `email`, `course`, `password`, `daily_study_hours`) VALUES
             (1, 'Student Demo', 'student@example.com', 'B.Sc. IT', '{$studentHash}', 3.0)",

            "INSERT IGNORE INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`) VALUES
             (1, 1, 1, 'Database Management System'), (2, 1, 1, 'Web Technology'), (3, 1, 1, 'Cyber Security')",

            "INSERT IGNORE INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
             (1, 1, 1, 'Design ER diagram for project', CURDATE() + INTERVAL 2 DAY, 'high', 2.0, 'pending'),
             (2, 1, 2, 'Build login and signup pages', CURDATE() + INTERVAL 4 DAY, 'medium', 1.5, 'pending'),
             (3, 1, 3, 'Read Chapter 3 on Cryptography', CURDATE() - INTERVAL 1 DAY, 'high', 1.0, 'pending'),
             (4, 1, 1, 'Review SQL normalization notes', CURDATE() - INTERVAL 2 DAY, 'low', 1.0, 'completed')"
        ];

        foreach ($statements as $sql) {
            $conn->query($sql);
        }
    } catch (\Throwable $t) {
        error_log("Schema auto-init error: " . $t->getMessage());
    }
}