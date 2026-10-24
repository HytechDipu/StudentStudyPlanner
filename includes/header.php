<?php
// Global header and navbar template
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/auth.php';
}
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';

$pageTitle = $pageTitle ?? 'StudyPlanner - Student Study Planner';
$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
$isStudent = !empty($_SESSION['user_id']);
$isTeacher = !empty($_SESSION['teacher_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="css/style.css?v=25">
    <link rel="stylesheet" href="css/theme.css?v=2">
    <!-- Font Awesome CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Immediate theme initialisation (runs before paint, so there is no flash) -->
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('studyplanner_theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var dark = saved ? saved === 'dark' : prefersDark;
            if (dark) document.documentElement.classList.add('dark-mode');
            window.addEventListener('DOMContentLoaded', function () {
                document.body.classList.toggle('dark-mode', dark);
            });
        })();
    </script>
    <style>
        .inline-form {
            display: inline-block;
            margin: 0;
            padding: 0;
        }
        .action-btn-clean {
            background: none;
            border: none;
            cursor: pointer;
            font: inherit;
            padding: 0;
        }
        .message-alert {
            max-width: 1100px;
            margin: 20px auto 0 auto;
            width: 90%;
            padding: 14px 18px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .message-success {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .message-error {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .nav-links .active-link {
            font-weight: 700;
        }
        .badge-teacher {
            display: inline-block;
            background: #ede9fe;
            color: #6d28d9;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 6px;
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
    <a href="<?php echo $isTeacher ? 'teacher_dashboard.php' : ($isStudent ? 'dashboard.php' : 'index.php'); ?>" class="logo" style="text-decoration:none;">
        📚 StudyPlanner
    </a>

    <div class="nav-links">
        <?php if ($isStudent): ?>
            <a href="dashboard.php" class="<?php echo $currentScript === 'dashboard.php' ? 'active-link' : ''; ?>">Dashboard</a>
            <a href="tasks.php" class="<?php echo $currentScript === 'tasks.php' ? 'active-link' : ''; ?>">Tasks</a>
            <a href="calendar.php" class="<?php echo $currentScript === 'calendar.php' ? 'active-link' : ''; ?>">Calendar</a>
            <a href="study.php" class="<?php echo $currentScript === 'study.php' ? 'active-link' : ''; ?>">⏱️ Study Timer</a>
            <a href="subjects.php" class="<?php echo $currentScript === 'subjects.php' ? 'active-link' : ''; ?>">Subjects</a>
            <a href="profile.php" class="<?php echo $currentScript === 'profile.php' ? 'active-link' : ''; ?>" title="My Profile">👤 Profile</a>
            <a href="logout.php" class="nav-btn">Logout</a>
        <?php elseif ($isTeacher): ?>
            <a href="teacher_dashboard.php" class="<?php echo $currentScript === 'teacher_dashboard.php' ? 'active-link' : ''; ?>">Dashboard</a>
            <a href="teacher_students.php" class="<?php echo $currentScript === 'teacher_students.php' ? 'active-link' : ''; ?>">Students</a>
            <a href="courses.php" class="<?php echo $currentScript === 'courses.php' ? 'active-link' : ''; ?>">Courses</a>
            <a href="teacher_assign_task.php" class="<?php echo $currentScript === 'teacher_assign_task.php' ? 'active-link' : ''; ?>">Assign Task</a>
            <span style="color: #64748b; font-size: 14px;">👩‍🏫 <?php echo e($_SESSION['teacher_name'] ?? 'Teacher'); ?></span>
            <a href="teacher_logout.php" class="nav-btn">Logout</a>
        <?php else: ?>
            <a href="index.php" class="<?php echo $currentScript === 'index.php' ? 'active-link' : ''; ?>">Home</a>
            <a href="login.php" class="<?php echo $currentScript === 'login.php' ? 'active-link' : ''; ?>">Student Login</a>
            <a href="register.php" class="<?php echo $currentScript === 'register.php' ? 'active-link' : ''; ?>">Student Register</a>
            <a href="teacher_login.php" class="nav-btn">Teacher Portal</a>
        <?php endif; ?>
        <button type="button" id="spThemeToggle" class="sp-theme-toggle" aria-label="Toggle dark mode" title="Toggle dark mode">
            <svg class="icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
            <svg class="icon-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
        </button>
    </div>
</nav>
<script>
    // Global theme controller (used by the navbar button and the Profile page)
    window.SPTheme = {
        isDark: function () { return document.documentElement.classList.contains('dark-mode'); },
        set: function (dark) {
            document.documentElement.classList.toggle('dark-mode', dark);
            document.body.classList.toggle('dark-mode', dark);
            try { localStorage.setItem('studyplanner_theme', dark ? 'dark' : 'light'); } catch (e) {}
            document.dispatchEvent(new CustomEvent('themechange', { detail: { dark: dark } }));
        },
        toggle: function () { this.set(!this.isDark()); }
    };
    document.getElementById('spThemeToggle').addEventListener('click', function () { SPTheme.toggle(); });
</script>

<?php
$flash = get_flash();
if ($flash):
    $alertClass = ($flash['type'] === 'success') ? 'message-success' : 'message-error';
    $icon = ($flash['type'] === 'success') ? '✅' : '⚠️';
?>
    <div class="message-alert <?php echo $alertClass; ?>">
        <span><?php echo $icon; ?></span>
        <span><?php echo e($flash['message']); ?></span>
    </div>
<?php endif; ?>
