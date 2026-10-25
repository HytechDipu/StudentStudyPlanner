<?php

// Authentication and session management helper

// Secure session cookie settings (HttpOnly, SameSite=Lax, Secure over HTTPS)
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

    if ($isHttps) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

/**
 * Require student login; redirect to login.php if not authenticated.
 */
function require_student_login(): int {
    if (empty($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
    return (int)$_SESSION['user_id'];
}

/**
 * Require teacher login; redirect to teacher_login.php if not authenticated.
 */
function require_teacher_login(): int {
    if (empty($_SESSION['teacher_id'])) {
        header("Location: teacher_login.php");
        exit();
    }
    return (int)$_SESSION['teacher_id'];
}

/**
 * Get current logged in student id or null.
 */
function get_student_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/**
 * Get current logged in teacher id or null.
 */
function get_teacher_id(): ?int {
    return isset($_SESSION['teacher_id']) ? (int)$_SESSION['teacher_id'] : null;
}

/**
 * Check if an account is locked due to too many failed attempts (5 attempts -> 15 min lock).
 * Returns array: ['locked' => bool, 'message' => string]
 */
function check_login_throttle(mysqli $conn, string $table, string $email): array {
    $allowedTables = ['users', 'teachers'];
    if (!in_array($table, $allowedTables, true)) {
        return ['locked' => false, 'message' => ''];
    }

    $stmt = $conn->prepare("SELECT failed_login_attempts, lockout_until FROM `{$table}` WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        if (!empty($row['lockout_until'])) {
            $lockoutTime = strtotime($row['lockout_until']);
            $now = time();
            if ($lockoutTime > $now) {
                $remainingMinutes = ceil(($lockoutTime - $now) / 60);
                $stmt->close();
                return [
                    'locked' => true,
                    'message' => "Too many failed attempts. Account locked. Please try again in {$remainingMinutes} minute(s)."
                ];
            } else {
                // Lock expired, reset throttle
                reset_login_throttle($conn, $table, $email);
            }
        }
    }
    $stmt->close();

    return ['locked' => false, 'message' => ''];
}

/**
 * Record a failed login attempt; lock if threshold reached.
 */
function record_failed_login(mysqli $conn, string $table, string $email): void {
    $allowedTables = ['users', 'teachers'];
    if (!in_array($table, $allowedTables, true)) {
        return;
    }

    $stmt = $conn->prepare("SELECT id, failed_login_attempts FROM `{$table}` WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $attempts = (int)$row['failed_login_attempts'] + 1;
        if ($attempts >= 5) {
            $update = $conn->prepare("UPDATE `{$table}` SET failed_login_attempts = ?, lockout_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE) WHERE id = ?");
            $update->bind_param("ii", $attempts, $row['id']);
            $update->execute();
            $update->close();
        } else {
            $update = $conn->prepare("UPDATE `{$table}` SET failed_login_attempts = ? WHERE id = ?");
            $update->bind_param("ii", $attempts, $row['id']);
            $update->execute();
            $update->close();
        }
    }
    $stmt->close();
}

/**
 * Reset failed attempts upon successful login.
 */
function reset_login_throttle(mysqli $conn, string $table, string $email): void {
    $allowedTables = ['users', 'teachers'];
    if (!in_array($table, $allowedTables, true)) {
        return;
    }

    $stmt = $conn->prepare("UPDATE `{$table}` SET failed_login_attempts = 0, lockout_until = NULL WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();
}

/**
 * Complete student login process: regenerate session ID and reset throttle.
 */
function login_student(mysqli $conn, array $user, string $password): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['role'] = 'student';

    // Clear any teacher session data to avoid role collisions
    unset($_SESSION['teacher_id'], $_SESSION['teacher_name'], $_SESSION['teacher_email']);

    reset_login_throttle($conn, 'users', $user['email']);

    // Check password rehashing
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $rehashStmt->bind_param("si", $newHash, $user['id']);
        $rehashStmt->execute();
        $rehashStmt->close();
    }
}

/**
 * Complete teacher login process: regenerate session ID and reset throttle.
 */
function login_teacher(mysqli $conn, array $teacher, string $password): void {
    session_regenerate_id(true);
    $_SESSION['teacher_id'] = (int)$teacher['id'];
    $_SESSION['teacher_name'] = $teacher['name'];
    $_SESSION['teacher_email'] = $teacher['email'];
    $_SESSION['role'] = 'teacher';

    // Clear student session data to avoid role collisions
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email']);

    reset_login_throttle($conn, 'teachers', $teacher['email']);

    // Check password rehashing
    if (password_needs_rehash($teacher['password'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $rehashStmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
        $rehashStmt->bind_param("si", $newHash, $teacher['id']);
        $rehashStmt->execute();
        $rehashStmt->close();
    }
}
