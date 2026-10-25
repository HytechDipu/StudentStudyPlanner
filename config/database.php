<?php

// Production Environment Settings
$appEnv = getenv('APP_ENV') ?: 'production';
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Enable mysqli exception mode for robust error handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host     = getenv('DB_HOST') ?: "localhost";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "";
$database = getenv('DB_NAME') ?: "test";
// Prevent using 'sys' because it is MySQL's internal protected schema
if ($database === "sys" || empty($database)) {
    $database = "test";
}
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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        ];

        foreach ($statements as $sql) {
            try {
                $conn->query($sql);
            } catch (\Throwable $e) {
                error_log("Schema statement notice: " . $e->getMessage());
            }
        }

        // Check if real project data already loaded
        $checkData = $conn->query("SELECT COUNT(*) FROM `users` WHERE `email` = 'abc22@gmail.com'");
        if ($checkData && (int)$checkData->fetch_row()[0] > 0) {
            return; // Real data already populated
        }

        $defaultHash = password_hash('password123', PASSWORD_DEFAULT);
        $studentDemoHash = password_hash('student123', PASSWORD_DEFAULT);
        $teacherDemoHash = password_hash('teacher123', PASSWORD_DEFAULT);

        $seedStatements = [
            "INSERT IGNORE INTO `courses` (`id`, `course_name`) VALUES 
             (1, 'B.Pharm'), (2, 'B.Sc. CS'), (3, 'B.Sc. IT')",

            "INSERT IGNORE INTO `course_subjects` (`id`, `course_id`, `subject_name`) VALUES
             (1, 3, 'C#'), (2, 3, '.NET'), (3, 3, 'Cyber Security'), (4, 3, 'Database Management System'),
             (5, 3, 'Web Technology'), (6, 1, 'Pharmaceutics'), (7, 1, 'Pharmaceutical Chemistry'),
             (8, 1, 'Pharmacology'), (9, 1, 'Pharmacognosy'), (10, 1, 'Human Anatomy and Physiology'),
             (11, 2, 'Programming in C'), (12, 2, 'Data Structures'), (13, 2, 'Database Management System'),
             (14, 2, 'Operating Systems'), (15, 2, 'Computer Networks')",

            "INSERT IGNORE INTO `teachers` (`id`, `name`, `email`, `password`) VALUES
             (1, 'Teacher', 'teacher@gmail.com', '{$defaultHash}'),
             (4, 'Rekha Naik', 'teacher2@gmail.com', '{$defaultHash}'),
             (5, 'Vina Shinde', 'vina@gmail.com', '{$defaultHash}'),
             (10, 'Professor Demo', 'teacher@example.com', '{$teacherDemoHash}')",

            "INSERT IGNORE INTO `teacher_courses` (`teacher_id`, `course_id`) VALUES
             (1, 1), (1, 2), (1, 3),
             (4, 2), (4, 3),
             (5, 2),
             (10, 1), (10, 2), (10, 3)",

            "INSERT IGNORE INTO `teacher_subjects` (`id`, `teacher_id`, `subject_name`) VALUES
             (1, 4, 'Cyber Security'),
             (2, 4, '.NET'),
             (3, 4, 'Computer Networks'),
             (4, 5, 'Data Structures')",

            "INSERT IGNORE INTO `users` (`id`, `name`, `email`, `course`, `password`, `daily_study_hours`) VALUES
             (1, 'Nidhi Gawad', 'abc22@gmail.com', 'B.Sc. IT', '{$defaultHash}', 3.0),
             (2, 'Nidhi', 'nidhi1@gmail.com', 'B.Sc. IT', '{$defaultHash}', 3.0),
             (3, 'Yuvaa Desh', 'yuva1@gmail.com', 'B.Pharm', '{$defaultHash}', 2.5),
             (4, 'Dhanu', 'dhanu123@gmail.com', 'B.Pharm', '{$defaultHash}', 3.5),
             (5, 'Durva Ranke', 'durva@gmail.com', 'B.Sc. CS', '{$defaultHash}', 3.0),
             (6, 'Anvi Raut', 'anvi@gmail.com', 'B.Sc. CS', '{$defaultHash}', 3.0),
             (10, 'Student Demo', 'student@example.com', 'B.Sc. IT', '{$studentDemoHash}', 3.0)",

            "INSERT IGNORE INTO `subjects` (`id`, `user_id`, `teacher_id`, `subject_name`) VALUES
             (1, 2, NULL, 'Information Technology'),
             (8, 2, 4, 'Cyber Security'),
             (10, 4, NULL, 'Pharmacy'),
             (11, 2, NULL, 'Maths'),
             (12, 1, 4, 'Cyber Security'),
             (13, 1, 4, '.NET'),
             (14, 2, 4, '.NET'),
             (15, 5, 4, 'Computer Networks'),
             (16, 5, 5, 'Data Structures'),
             (17, 6, 5, 'Data Structures')",

            "INSERT IGNORE INTO `tasks` (`id`, `user_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
             (1, 2, 1, 'Study Chapter 1', CURDATE() - INTERVAL 5 DAY, 'high', 2.0, 'completed'),
             (2, 2, 8, 'Write notes on chapter 2', CURDATE() - INTERVAL 4 DAY, 'medium', 1.5, 'completed'),
             (3, 2, 1, 'Study Chapter 2', CURDATE() - INTERVAL 2 DAY, 'medium', 2.0, 'completed'),
             (4, 4, 10, 'Note down any 50 medicines', CURDATE() - INTERVAL 1 DAY, 'high', 3.0, 'completed'),
             (5, 4, 10, 'Complete project research', CURDATE() + INTERVAL 3 DAY, 'high', 4.0, 'pending'),
             (6, 2, 11, 'Write all 5 sums in assignment', CURDATE() + INTERVAL 2 DAY, 'medium', 1.5, 'pending'),
             (7, 1, 12, 'Review Cyber Security firewalls', CURDATE() + INTERVAL 1 DAY, 'high', 2.0, 'pending'),
             (8, 5, 15, 'Network subnetting practice', CURDATE() + INTERVAL 2 DAY, 'medium', 2.5, 'pending')",

            "INSERT IGNORE INTO `teacher_tasks` (`id`, `teacher_id`, `student_id`, `subject_id`, `task_name`, `due_date`, `priority`, `estimated_hours`, `status`) VALUES
             (2, 4, 1, 12, 'Study Chapter 3 on Cryptography', CURDATE() + INTERVAL 4 DAY, 'high', 2.0, 'pending'),
             (3, 4, 2, 8, 'Study Chapter 3 on Cryptography', CURDATE() + INTERVAL 4 DAY, 'high', 2.0, 'pending'),
             (4, 4, 1, 13, 'Study Chapter 4 on ASP.NET Framework', CURDATE() + INTERVAL 5 DAY, 'medium', 2.5, 'pending'),
             (5, 4, 2, 14, 'Study Chapter 4 on ASP.NET Framework', CURDATE() + INTERVAL 5 DAY, 'medium', 2.5, 'pending'),
             (6, 4, 5, 15, 'Study Chapter 1 on OSI Model', CURDATE() + INTERVAL 3 DAY, 'high', 1.5, 'pending'),
             (7, 5, 5, 16, 'Write down the exercise of chapter 2', CURDATE() + INTERVAL 2 DAY, 'medium', 2.0, 'pending'),
             (8, 5, 6, 17, 'Write down the exercise of chapter 2', CURDATE() + INTERVAL 2 DAY, 'medium', 2.0, 'pending')",

            "INSERT IGNORE INTO `study_sessions` (`id`, `user_id`, `subject_id`, `minutes`, `started_at`) VALUES
             (1, 2, 1, 50, NOW() - INTERVAL 3 DAY),
             (2, 2, 8, 25, NOW() - INTERVAL 2 DAY),
             (3, 1, 12, 50, NOW() - INTERVAL 1 DAY),
             (4, 4, 10, 75, NOW() - INTERVAL 2 DAY),
             (5, 5, 16, 50, NOW() - INTERVAL 1 DAY)"
        ];

        foreach ($seedStatements as $sql) {
            try {
                $conn->query($sql);
            } catch (\Throwable $e) {
                error_log("Seed statement notice: " . $e->getMessage());
            }
        }
    } catch (\Throwable $t) {
        error_log("Schema auto-init error: " . $t->getMessage());
    }
}