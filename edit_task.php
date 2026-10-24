<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

$taskId = (int)($_GET["id"] ?? 0);
if ($taskId <= 0) {
    header("Location: tasks.php");
    exit();
}

// Fetch task ensuring it belongs to current user
$stmt = $conn->prepare("
    SELECT id, task_name, subject_id, due_date, status,
           COALESCE(priority, 'medium') AS priority,
           COALESCE(estimated_hours, 1.0) AS estimated_hours
    FROM tasks
    WHERE id = ? AND user_id = ?
    LIMIT 1
");
$stmt->bind_param("ii", $taskId, $userId);
$stmt->execute();
$task = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$task) {
    set_flash('error', "Task not found or access denied.");
    header("Location: tasks.php");
    exit();
}

$errorMessage = "";

// Fetch user's subjects
$subStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->bind_param("i", $userId);
$subStmt->execute();
$subjects = $subStmt->get_result();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    validate_csrf_or_die();

    $taskName = trim($_POST["task_name"] ?? "");
    $subjectId = (int)($_POST["subject_id"] ?? 0);
    $dueDate = trim($_POST["due_date"] ?? "");
    $status = trim($_POST["status"] ?? "pending");
    $priority = trim($_POST["priority"] ?? "medium");
    $estimatedHours = (float)($_POST["estimated_hours"] ?? 1.0);

    if (!in_array($status, ['pending', 'completed'], true)) {
        $status = 'pending';
    }
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
            $updateStmt = $conn->prepare("
                UPDATE tasks
                SET task_name = ?, subject_id = ?, due_date = ?, status = ?, priority = ?, estimated_hours = ?
                WHERE id = ? AND user_id = ?
            ");
            $updateStmt->bind_param("sisssdii", $taskName, $subjectId, $dueDate, $status, $priority, $estimatedHours, $taskId, $userId);

            if ($updateStmt->execute()) {
                set_flash('success', "Task '{$taskName}' updated successfully!");
                header("Location: tasks.php");
                exit();
            } else {
                $errorMessage = "Failed to update task. Please try again.";
            }
            $updateStmt->close();
        }
    }
}

$pageTitle = "Edit Task - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="form-container">
    <h1>Edit Study Task</h1>
    <p class="form-subtitle">Update task details, deadline, priority, and effort estimate</p>

    <?php if (!empty($errorMessage)): ?>
        <div class="message error-message" style="display:block; margin-bottom: 20px;">
            ⚠️ <?php echo e($errorMessage); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="edit_task.php?id=<?php echo $taskId; ?>">
        <?php echo csrf_field(); ?>

        <div class="form-group">
            <label for="task_name">Task Name</label>
            <input
                type="text"
                id="task_name"
                name="task_name"
                value="<?php echo e($_POST['task_name'] ?? $task['task_name']); ?>"
                required
                maxlength="200"
            >
        </div>

        <div class="form-group">
            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id" required>
                <?php
                $currentSubId = (int)($_POST['subject_id'] ?? $task['subject_id']);
                $subjects->data_seek(0);
                while ($sub = $subjects->fetch_assoc()):
                    $selected = ($currentSubId === (int)$sub['id']) ? 'selected' : '';
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
                <?php $currPriority = $_POST['priority'] ?? $task['priority']; ?>
                <select id="priority" name="priority" required>
                    <option value="high" <?php echo $currPriority === 'high' ? 'selected' : ''; ?>>🔥 High Priority</option>
                    <option value="medium" <?php echo $currPriority === 'medium' ? 'selected' : ''; ?>>⚡ Medium Priority</option>
                    <option value="low" <?php echo $currPriority === 'low' ? 'selected' : ''; ?>>🌱 Low Priority</option>
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
                    value="<?php echo e($_POST['estimated_hours'] ?? (string)$task['estimated_hours']); ?>"
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
                value="<?php echo e($_POST['due_date'] ?? $task['due_date']); ?>"
                required
            >
        </div>

        <div class="form-group">
            <label for="status">Status</label>
            <select id="status" name="status" required>
                <?php $currentStatus = $_POST['status'] ?? $task['status']; ?>
                <option value="pending" <?php echo $currentStatus === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                <option value="completed" <?php echo $currentStatus === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
            </select>
        </div>

        <button type="submit" class="register-btn" style="width: 100%; margin-top: 10px;">Update Task</button>
        <a href="tasks.php" class="back-home" style="display:block; text-align: center; margin-top: 15px; color:#4f46e5; text-decoration: none;">← Cancel and return to tasks</a>
    </form>
</div>

<?php
$subStmt->close();
include __DIR__ . '/includes/footer.php';
?>