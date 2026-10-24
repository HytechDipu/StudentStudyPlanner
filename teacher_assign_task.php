<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$teacherId = require_teacher_login();

$errorMessage = "";
$successMessage = "";

// Get courses ONLY taught by this teacher
$courseStmt = $conn->prepare("
    SELECT c.id, c.course_name
    FROM teacher_courses tc
    INNER JOIN courses c ON tc.course_id = c.id
    WHERE tc.teacher_id = ?
    ORDER BY c.course_name ASC
");
$courseStmt->bind_param("i", $teacherId);
$courseStmt->execute();
$courses = $courseStmt->get_result();
$courseStmt->close();

$selectedCourseId = (int)($_GET["course_id"] ?? 0);
$subjects = [];

if ($selectedCourseId > 0) {
    // Verify teacher teaches this course
    $verifyStmt = $conn->prepare("SELECT 1 FROM teacher_courses WHERE teacher_id = ? AND course_id = ? LIMIT 1");
    $verifyStmt->bind_param("ii", $teacherId, $selectedCourseId);
    $verifyStmt->execute();
    $teachesCourse = $verifyStmt->get_result()->num_rows > 0;
    $verifyStmt->close();

    if ($teachesCourse) {
        $subStmt = $conn->prepare("SELECT id, subject_name FROM course_subjects WHERE course_id = ? ORDER BY subject_name ASC");
        $subStmt->bind_param("i", $selectedCourseId);
        $subStmt->execute();
        $subRes = $subStmt->get_result();
        while ($row = $subRes->fetch_assoc()) {
            $subjects[] = $row;
        }
        $subStmt->close();
    } else {
        $selectedCourseId = 0;
    }
}

// Handle task assignment via POST + CSRF
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validate_csrf_or_die();

    $courseId = (int)($_POST["course_id"] ?? 0);
    $subjectName = trim($_POST["subject_name"] ?? "");
    $taskName = trim($_POST["task_name"] ?? "");
    $dueDate = trim($_POST["due_date"] ?? "");
    $priority = trim($_POST["priority"] ?? "medium");
    $estimatedHours = (float)($_POST["estimated_hours"] ?? 1.5);

    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }
    if ($estimatedHours <= 0) {
        $estimatedHours = 1.5;
    }

    if ($courseId <= 0 || empty($subjectName) || empty($taskName) || empty($dueDate)) {
        $errorMessage = "Please fill in all required fields.";
    } elseif (mb_strlen($taskName) < 2 || mb_strlen($taskName) > 200) {
        $errorMessage = "Task title must be between 2 and 200 characters.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $errorMessage = "Invalid due date format.";
    } else {
        // Enforce rule: teacher can only assign tasks to courses they teach
        $authCheck = $conn->prepare("
            SELECT c.course_name
            FROM teacher_courses tc
            INNER JOIN courses c ON tc.course_id = c.id
            WHERE tc.teacher_id = ? AND tc.course_id = ?
            LIMIT 1
        ");
        $authCheck->bind_param("ii", $teacherId, $courseId);
        $authCheck->execute();
        $authRes = $authCheck->get_result();

        if ($authRes->num_rows === 0) {
            $errorMessage = "Access denied: You can only assign tasks to courses you teach.";
        } else {
            $courseData = $authRes->fetch_assoc();
            $courseName = $courseData["course_name"];

            // Fetch all students enrolled in this course
            $studentStmt = $conn->prepare("SELECT id, name FROM users WHERE LOWER(course) = LOWER(?)");
            $studentStmt->bind_param("s", $courseName);
            $studentStmt->execute();
            $students = $studentStmt->get_result();

            if ($students->num_rows === 0) {
                $errorMessage = "No students are currently enrolled in {$courseName}.";
            } else {
                $assignedCount = 0;

                while ($student = $students->fetch_assoc()) {
                    $studentId = (int)$student["id"];

                    // Check if subject exists for student
                    $subCheck = $conn->prepare("SELECT id FROM subjects WHERE user_id = ? AND LOWER(subject_name) = LOWER(?) LIMIT 1");
                    $subCheck->bind_param("is", $studentId, $subjectName);
                    $subCheck->execute();
                    $subCheckRes = $subCheck->get_result();

                    if ($sRow = $subCheckRes->fetch_assoc()) {
                        $studentSubId = (int)$sRow["id"];
                    } else {
                        // Create subject with teacher attribution
                        $subInsert = $conn->prepare("INSERT INTO subjects (user_id, teacher_id, subject_name) VALUES (?, ?, ?)");
                        $subInsert->bind_param("iis", $studentId, $teacherId, $subjectName);
                        $subInsert->execute();
                        $studentSubId = $conn->insert_id;
                        $subInsert->close();
                    }
                    $subCheck->close();

                    // Insert task into teacher_tasks with priority and estimated_hours
                    $taskInsert = $conn->prepare("
                        INSERT INTO teacher_tasks (teacher_id, student_id, subject_id, task_name, due_date, priority, estimated_hours, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $taskInsert->bind_param("iiisssd", $teacherId, $studentId, $studentSubId, $taskName, $dueDate, $priority, $estimatedHours);
                    $taskInsert->execute();
                    $taskInsert->close();

                    $assignedCount++;
                }

                set_flash('success', "Successfully assigned '{$taskName}' to {$assignedCount} student(s) in {$courseName}!");
                header("Location: teacher_dashboard.php");
                exit();
            }
            $studentStmt->close();
        }
        $authCheck->close();
    }
}

$pageTitle = "Assign Task - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="tp-page tp-narrow">
    <div class="tp-head">
        <div>
            <h1>👩‍🏫 Assign Coursework</h1>
            <p>Create and distribute tasks to all students in your assigned courses.</p>
        </div>
        <div class="tp-head-actions">
            <a href="teacher_dashboard.php" class="sp-btn sp-btn-soft">← Dashboard</a>
        </div>
    </div>
    <div style="height: 24px;"></div>

    <?php if (!empty($errorMessage)): ?>
        <div class="message error-message" style="display:block; margin-bottom: 20px;">
            ⚠️ <?php echo e($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($courses->num_rows === 0): ?>
        <div class="tp-warn">
            <div style="font-size: 36px; margin-bottom: 10px;">📚</div>
            <h3 style="margin-bottom: 8px;">No Teaching Courses Assigned</h3>
            <p style="margin-bottom: 18px;">
                You haven't selected any courses to teach yet. You must pick your courses before assigning tasks.
            </p>
            <a href="courses.php" class="sp-btn sp-btn-primary">
                👉 Go to Courses Page to Select Courses
            </a>
        </div>
    <?php else: ?>
        <!-- SELECT COURSE STEP -->
        <div class="tp-card" style="margin-bottom: 24px; padding: 24px;">
            <form method="GET" action="teacher_assign_task.php" id="courseFilterForm">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="course_select" style="font-weight: 600;">1. Select Course You Teach</label>
                    <select id="course_select" name="course_id" onchange="document.getElementById('courseFilterForm').submit();" required>
                        <option value="">-- Choose Course --</option>
                        <?php
                        $courses->data_seek(0);
                        while ($c = $courses->fetch_assoc()):
                            $sel = ($selectedCourseId === (int)$c['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo $sel; ?>>
                                <?php echo e($c['course_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </form>
        </div>

        <?php if ($selectedCourseId > 0): ?>
            <!-- ASSIGN TASK FORM -->
            <div class="tp-card" style="padding: 24px;">
                <form method="POST" action="teacher_assign_task.php?course_id=<?php echo $selectedCourseId; ?>">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="course_id" value="<?php echo $selectedCourseId; ?>">

                    <div class="form-group">
                        <label for="subject_name">2. Subject Name</label>
                        <?php if (!empty($subjects)): ?>
                            <select id="subject_name" name="subject_name" required>
                                <option value="">-- Choose Subject from Course Curriculum --</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?php echo e($s['subject_name']); ?>">
                                        <?php echo e($s['subject_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <input
                                type="text"
                                id="subject_name"
                                name="subject_name"
                                placeholder="e.g. Data Structures, Web Technology"
                                required
                                maxlength="100"
                            >
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="task_name">3. Task Title & Assignment Instructions</label>
                        <input
                            type="text"
                            id="task_name"
                            name="task_name"
                            placeholder="e.g. Chapter 3 Exercise sums 1-5"
                            required
                            maxlength="200"
                        >
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" required>
                                <option value="high">🔥 High Priority</option>
                                <option value="medium" selected>⚡ Medium Priority</option>
                                <option value="low">🌱 Low Priority</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="estimated_hours">Est. Hours</label>
                            <input
                                type="number"
                                id="estimated_hours"
                                name="estimated_hours"
                                min="0.5"
                                max="24"
                                step="0.5"
                                value="1.5"
                                required
                            >
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="due_date">4. Deadline Date</label>
                        <input
                            type="date"
                            id="due_date"
                            name="due_date"
                            value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>"
                            required
                        >
                    </div>

                    <button type="submit" class="sp-btn sp-btn-primary" style="width: 100%; margin-top: 10px; padding: 14px;">
                        🚀 Assign to All Students in this Course
                    </button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>