<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

if (get_student_id()) {
    header("Location: dashboard.php");
    exit();
}

$errorMessage = "";
$successMessage = "";

// Fetch available courses from database
$courseStmt = $conn->prepare("SELECT id, course_name FROM courses ORDER BY course_name ASC");
$courseStmt->execute();
$courses = $courseStmt->get_result();
$courseStmt->close();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validate_csrf_or_die();

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $course = trim($_POST["course"] ?? "");
    $password = $_POST["password"] ?? "";

    if (empty($name) || empty($email) || empty($course) || empty($password)) {
        $errorMessage = "Please fill in all required fields.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errorMessage = "Name must be between 2 and 100 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif ($pwdErr = validate_password($password)) {
        $errorMessage = $pwdErr;
    } else {
        // Check if email already registered
        $checkStmt = $conn->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $errorMessage = "This email is already registered. Please log in.";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertStmt = $conn->prepare("INSERT INTO users (name, email, course, password) VALUES (?, ?, ?, ?)");
            $insertStmt->bind_param("ssss", $name, $email, $course, $hashedPassword);

            if ($insertStmt->execute()) {
                set_flash('success', 'Registration successful! You can now log in.');
                header("Location: login.php");
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
    <title>Student Registration - StudyPlanner</title>
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
            🎓
        </div>

        <h2>Create Student Account</h2>
        <p class="register-subtitle">Sign up to organize your studies and track your progress</p>

        <?php if (!empty($errorMessage)): ?>
            <div class="message error-message" style="display:block;">
                ⚠️ <?php echo e($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="name">Full Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="e.g. Alex Johnson"
                    value="<?php echo e($_POST['name'] ?? ''); ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="e.g. alex@example.com"
                    value="<?php echo e($_POST['email'] ?? ''); ?>"
                    required
                    maxlength="100"
                >
            </div>

            <div class="form-group">
                <label for="course">Select Course</label>
                <select id="course" name="course" required>
                    <option value="">-- Choose your course --</option>
                    <?php
                    $courses->data_seek(0);
                    while ($courseRow = $courses->fetch_assoc()):
                        $selected = (isset($_POST['course']) && $_POST['course'] === $courseRow['course_name']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo e($courseRow['course_name']); ?>" <?php echo $selected; ?>>
                            <?php echo e($courseRow['course_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
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

            <button type="submit" class="register-btn">Create Account</button>
        </form>

        <p class="login-link">
            Already have an account? <a href="login.php">Log in here</a>
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