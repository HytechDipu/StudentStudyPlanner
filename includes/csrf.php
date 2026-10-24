<?php

// CSRF Protection Helper

if (session_status() === PHP_SESSION_NONE) {
    // If auth.php wasn't loaded first, ensure session cookie settings
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

/**
 * Generate a new CSRF token or retrieve existing one from session.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field for forms.
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify submitted CSRF token against session token.
 */
function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Guard that halts execution if a POST request lacks a valid CSRF token.
 */
function validate_csrf_or_die(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!verify_csrf_token($submittedToken)) {
            http_response_code(403);
            die("Invalid or expired session security token (CSRF). Please go back, refresh the page, and try again.");
        }
    }
}
