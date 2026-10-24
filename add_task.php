<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();
$errorMessage = "";

// Fetch subjects owned by this student
$subStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->bind_param("i", $userId);
$subStmt->execute();
$subjects = $subStmt->get_result();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validate_csrf_or_die();

    $taskName = trim($_POST["task_name"] ?? "");
    $subjectId = (int)($_POST["subject_id"] ?? 0);
    $dueDate = trim($_POST["due_date"] ?? "");
    $priority = trim($_POST["priority"] ?? "medium");
    $estimatedHours = (float)($_POST["estimated_hours"] ?? 1.0);

    if (!in_array($priority, ['low', 'medium', 'high'], true)) {
        $priority = 'medium';
    }
    if ($estimatedHours <= 0) {
        $estimatedHours = 1.0;
    }

    if (empty($taskName) || $subjectId <= 0 || empty($dueDate)) {
        $errorMessage = "Please fill in all fields.";
    } elseif (mb_strlen($taskName) < 2 || mb_strlen($taskName) > 200) {
        $errorMessage = "Task name must be between 2 and 200 characters.";
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dueDate)) {
        $errorMessage = "Invalid due date format.";
    } else {
        // Verify subject ownership
        $checkSub = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ? LIMIT 1");
        $checkSub->bind_param("ii", $subjectId, $userId);
        $checkSub->execute();
        $isOwner = $checkSub->get_result()->num_rows > 0;
        $checkSub->close();

        if (!$isOwner) {
            $errorMessage = "Invalid subject selected.";
        } else {
            $stmt = $conn->prepare("
                INSERT INTO tasks (user_id, subject_id, task_name, due_date, priority, estimated_hours, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("iisssd", $userId, $subjectId, $taskName, $dueDate, $priority, $estimatedHours);

            if ($stmt->execute()) {
                set_flash('success', "Task '{$taskName}' added successfully!");
                header("Location: tasks.php");
                exit();
            } else {
                $errorMessage = "Failed to add task. Please try again.";
            }
            $stmt->close();
        }
    }
}

$pageTitle = "Add Task - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h1>Add New Study Task</h1>
    <p class="form-subtitle">Schedule deadlines, set priority levels, and allocate study hours.</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="message error-message" style="display:block; margin-bottom: 20px;">
            ⚠️ <?php echo e($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($subjects->num_rows === 0): ?>
        <div class="message error-message" style="display:block; margin-bottom: 20px;">
            ⚠️ You don't have any subjects yet. Please <a href="subjects.php" style="color: #4f46e5; font-weight: bold; text-decoration: underline;">add a subject</a> first.
        </div>
    <?php else: ?>
        <form method="POST" action="add_task.php">
            <?php echo csrf_field(); ?>

            <div class="form-group">
                <label for="task_name">Task Name</label>
                <input
                    type="text"
                    id="task_name"
                    name="task_name"
                    placeholder="e.g. Read Chapter 4 and solve exercises"
                    value="<?php echo e($_POST['task_name'] ?? ''); ?>"
                    required
                    maxlength="200"
                >
            </div>

            <div class="form-group">
                <label for="subject_id">Subject</label>
                <select id="subject_id" name="subject_id" required>
                    <option value="">-- Choose Subject --</option>
                    <?php
                    $subjects->data_seek(0);
                    while ($sub = $subjects->fetch_assoc()):
                        $selected = (isset($_POST['subject_id']) && (int)$_POST['subject_id'] === (int)$sub['id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo (int)$sub['id']; ?>" <?php echo $selected; ?>>
                            <?php echo e($sub['subject_name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" required>
                        <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'high') ? 'selected' : ''; ?>>🔥 High Priority</option>
                        <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] === 'medium') ? 'selected' : ''; ?>>⚡ Medium Priority</option>
                        <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] === 'low') ? 'selected' : ''; ?>>🌱 Low Priority</option>
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
                        value="<?php echo e($_POST['estimated_hours'] ?? '1.5'); ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="due_date">Due Date</label>
                <input
                    type="date"
                    id="due_date"
                    name="due_date"
                    value="<?php echo e($_POST['due_date'] ?? date('Y-m-d')); ?>"
                    required
                >
            </div>

            <button type="submit" class="register-btn" style="width: 100%; margin-top: 10px;">Create Task</button>
            <a href="tasks.php" class="back-home" style="display:block; text-align: center; margin-top: 15px; color:#4f46e5; text-decoration: none;">← Cancel and return to tasks</a>
        </form>
    <?php endif; ?>
</div>

<?php
$subStmt->close();
include __DIR__ . '/includes/footer.php';
?>