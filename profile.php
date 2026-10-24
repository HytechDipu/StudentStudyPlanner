<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

// Fetch current user data
$stmt = $conn->prepare("SELECT id, name, email, course, password, COALESCE(daily_study_hours, 3.0) AS daily_study_hours, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$errorMessage = "";

// 1. Handle Profile Info Update (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    validate_csrf_or_die();

    $name = trim($_POST['name'] ?? '');
    $studyHours = (float)($_POST['daily_study_hours'] ?? 3.0);

    if (empty($name)) {
        $errorMessage = "Please enter your name.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errorMessage = "Name must be between 2 and 100 characters.";
    } elseif ($studyHours < 0.5 || $studyHours > 16.0) {
        $errorMessage = "Daily study budget must be between 0.5 and 16 hours.";
    } else {
        $upStmt = $conn->prepare("UPDATE users SET name = ?, daily_study_hours = ? WHERE id = ?");
        $upStmt->bind_param("sdi", $name, $studyHours, $userId);
        $upStmt->execute();
        $upStmt->close();

        $_SESSION['user_name'] = $name;
        $user['name'] = $name;
        $user['daily_study_hours'] = $studyHours;

        set_flash('success', "Profile information updated successfully!");
        header("Location: profile.php");
        exit();
    }
}

// 2. Handle Password Change (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    validate_csrf_or_die();

    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $errorMessage = "Please fill in all password fields.";
    } elseif (!password_verify($currentPassword, $user['password'])) {
        $errorMessage = "Your current password does not match our records.";
    } elseif ($newPassword !== $confirmPassword) {
        $errorMessage = "New password and confirmation do not match.";
    } elseif ($pwdErr = validate_password($newPassword)) {
        $errorMessage = $pwdErr;
    } else {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $pwdStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $pwdStmt->bind_param("si", $newHash, $userId);
        $pwdStmt->execute();
        $pwdStmt->close();

        set_flash('success', "Password changed successfully!");
        header("Location: profile.php");
        exit();
    }
}

$pageTitle = "My Profile - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="form-container" style="max-width: 750px;">

    <h1>👤 Student Profile & Settings</h1>
    <p class="form-subtitle">Manage your account credentials, study preferences, and visual theme.</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="message error-message" style="display:block; margin-bottom: 25px;">
            ⚠️ <?php echo e($errorMessage); ?>
        </div>
    <?php endif; ?>

    <!-- SECTION 1: THEME & DARK MODE TOGGLE -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h2 style="font-size: 18px; color: #1e1b4b; margin-bottom: 4px;">🌙 Visual Theme (Dark Mode)</h2>
                <p style="color: #64748b; font-size: 13px; margin: 0;">Switch between sleek violet-dark theme and classic light theme. Stored in your browser.</p>
            </div>
            <div>
                <button type="button" id="themeToggleBtn" onclick="toggleTheme()" class="quick-button" style="padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;">
                    <span id="themeBtnIcon">🌙</span>
                    <span id="themeBtnText">Toggle Dark Mode</span>
                </button>
            </div>
        </div>
    </div>

    <!-- SECTION 2: EDIT PROFILE & STUDY BUDGET -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; margin-bottom: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <h2 style="font-size: 18px; color: #1e1b4b; margin-bottom: 16px;">📝 Personal Information</h2>

        <form method="POST" action="profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    value="<?php echo e($user['name']); ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address (Registered)</label>
                <input
                    type="email"
                    id="email"
                    value="<?php echo e($user['email']); ?>"
                    disabled
                    style="background: #f1f5f9; color: #64748b; cursor: not-allowed;"
                >
                <small style="color: #94a3b8; font-size: 12px;">Email cannot be changed.</small>
            </div>

            <div class="form-group">
                <label for="course">Degree Program / Course</label>
                <input
                    type="text"
                    id="course"
                    value="<?php echo e($user['course'] ?? 'Undeclared'); ?>"
                    disabled
                    style="background: #f1f5f9; color: #64748b; cursor: not-allowed;"
                >
            </div>

            <div class="form-group">
                <label for="daily_study_hours">Daily Study Target (Hours / Day)</label>
                <input
                    type="number"
                    id="daily_study_hours"
                    name="daily_study_hours"
                    min="0.5"
                    max="16"
                    step="0.5"
                    value="<?php echo e((string)$user['daily_study_hours']); ?>"
                    required
                >
                <small style="color: #64748b; font-size: 12px;">This drives your Smart Daily Plan recommendations on the Dashboard.</small>
            </div>

            <button type="submit" class="quick-button" style="padding: 10px 22px;">Save Profile Changes</button>
        </form>
    </div>

    <!-- SECTION 3: CHANGE PASSWORD -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 14px; padding: 24px; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
        <h2 style="font-size: 18px; color: #1e1b4b; margin-bottom: 16px;">🔒 Change Account Password</h2>

        <form method="POST" action="profile.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input
                    type="password"
                    id="current_password"
                    name="current_password"
                    placeholder="Enter your existing password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="form-group">
                <label for="new_password">New Password</label>
                <input
                    type="password"
                    id="new_password"
                    name="new_password"
                    placeholder="At least 8 characters"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm New Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Repeat new password"
                    required
                    minlength="8"
                    autocomplete="new-password"
                >
            </div>

            <button type="submit" class="quick-button" style="padding: 10px 22px;">Update Password</button>
        </form>
    </div>

</div>

<script>
function updateThemeButtonState() {
    const isDark = SPTheme.isDark();
    document.getElementById('themeBtnIcon').textContent = isDark ? '☀️' : '🌙';
    document.getElementById('themeBtnText').textContent = isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode';
}

function toggleTheme() {
    SPTheme.toggle();
}

document.addEventListener('themechange', updateThemeButtonState);
document.addEventListener('DOMContentLoaded', updateThemeButtonState);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
