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

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($name) || empty($email) || empty($password)) {
        $errorMessage = "Please fill in all fields.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errorMessage = "Name must be between 2 and 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif ($pwdErr = validate_password($password)) {
        $errorMessage = $pwdErr;
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM teachers WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMessage = "This email is already registered. Please log in.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("INSERT INTO teachers (name, email, password) VALUES (?, ?, ?)");
            $insertStmt->bind_param("sss", $name, $email, $hashedPassword);

            if ($insertStmt->execute()) {
                set_flash('success', 'Teacher account created successfully! You can now log in.');
                header("Location: teacher_login.php");
                exit();
            } else {
                $errorMessage = "Registration failed. Please try again.";
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration - StudyPlanner</title>
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
        .field-hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
    </style>
<?php include __DIR__ . '/includes/public_theme_head.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/public_theme_toggle.php'; ?>

<div class="register-container">
    <div class="register-box">
        <div class="register-icon">
            👩‍🏫
        </div>

        <h2>Create Teacher Account</h2>
        <p class="register-subtitle">Sign up to assign tasks and mentor students across courses</p>

        <?php if (!empty($errorMessage)): ?>
            <div class="message error-message" style="display:block;">
                ⚠️ <?php echo e($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="teacher_register.php">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="name">Teacher Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="e.g. Prof. Davis"
                    value="<?php echo e($_POST['name'] ?? ''); ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="email">Teacher Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="e.g. davis@university.edu"
                    value="<?php echo e($_POST['email'] ?? ''); ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="password-field-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="At least 8 characters"
                        required
                        minlength="8"
                    >
                    <i class="fa-solid fa-eye toggle-password" id="eyeIcon" onclick="togglePasswordVisibility()"></i>
                </div>
                <div class="field-hint">Must be at least 8 characters long.</div>
            </div>

            <button type="submit" class="register-btn">Register as Teacher</button>
        </form>

        <p class="login-link">
            Already registered? <a href="teacher_login.php">Log in here</a>
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