<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (get_teacher_id()) {
    header("Location: teacher_dashboard.php");
    exit();
}

$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validate_csrf_or_die();

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($email) || empty($password)) {
        $errorMessage = "Please enter both email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        $throttle = check_login_throttle($conn, 'teachers', $email);
        if ($throttle['locked']) {
            $errorMessage = $throttle['message'];
        } else {
            $stmt = $conn->prepare("SELECT id, name, email, password FROM teachers WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($teacher = $result->fetch_assoc()) {
                if (password_verify($password, $teacher["password"])) {
                    login_teacher($conn, $teacher, $password);
                    header("Location: teacher_dashboard.php");
                    exit();
                } else {
                    record_failed_login($conn, 'teachers', $email);
                    $errorMessage = "Invalid email or password.";
                }
            } else {
                record_failed_login($conn, 'teachers', $email);
                $errorMessage = "Invalid email or password.";
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Login - StudyPlanner</title>
    <link rel="stylesheet" href="css/style.css?v=20">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .back-home {
            display: block;
            text-align: center;
            margin-top: 18px;
            color: #4f46e5;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .back-home:hover {
            text-decoration: underline;
        }
        .password-field-wrapper {
            position: relative;
        }
        .password-field-wrapper input {
            width: 100%;
            padding-right: 42px;
        }
        .toggle-password {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #888;
        }
    </style>
<?php include __DIR__ . '/includes/public_theme_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/public_theme_toggle.php'; ?>

<div class="teacher-login-page">
    <div class="teacher-login-card">
        <div class="teacher-login-icon">
            👩‍🏫
        </div>

        <h1>Teacher Login</h1>
        <p class="teacher-login-subtitle">Access your educator portal and student assignments</p>

        <?php if (!empty($errorMessage)): ?>
            <div class="message error-message" style="display:block;">
                ⚠️ <?php echo e($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="teacher_login.php">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="email">Teacher Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="teacher@example.com"
                    value="<?php echo e($_POST['email'] ?? ''); ?>"
                    required
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        autocomplete="current-password"
                    >
                    <i class="fa-solid fa-eye toggle-password" id="eyeIcon" onclick="togglePasswordVisibility()"></i>
                </div>
            </div>

            <button type="submit" class="teacher-login-button">Log In as Teacher</button>
        </form>

        <p class="teacher-login-footer">
            Don't have an educator account? <a href="teacher_register.php">Register here</a>
        </p>

        <p class="teacher-login-footer" style="margin-top: 8px;">
            Are you a student? <a href="login.php">Student Login</a>
        </p>

        <a href="index.php" class="back-home">← Back to Home</a>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const pwdInput = document.getElementById('password');
    const eyeIcon = document.getElementById('eyeIcon');
    if (pwdInput.type === 'password') {
        pwdInput.type = 'text';
        eyeIcon.classList.remove('fa-eye');
        eyeIcon.classList.add('fa-eye-slash');
    } else {
        pwdInput.type = 'password';
        eyeIcon.classList.remove('fa-eye-slash');
        eyeIcon.classList.add('fa-eye');
    }
}
</script>

</body>
</html>