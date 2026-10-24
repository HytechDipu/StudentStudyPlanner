<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

// Handle Subject Deletion via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_subject') {
    validate_csrf_or_die();

    $subjectId = (int)($_POST['subject_id'] ?? 0);

    if ($subjectId > 0) {
        // Verify subject belongs to user
        $ownStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE id = ? AND user_id = ? LIMIT 1");
        $ownStmt->bind_param("ii", $subjectId, $userId);
        $ownStmt->execute();
        $subjectRow = $ownStmt->get_result()->fetch_assoc();
        $ownStmt->close();

        if ($subjectRow) {
            // Count ONLY pending tasks for this subject (personal tasks + teacher assigned tasks)
            $pendingTasks = 0;

            $checkPersonal = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE subject_id = ? AND user_id = ? AND status = 'pending'");
            $checkPersonal->bind_param("ii", $subjectId, $userId);
            $checkPersonal->execute();
            $pendingTasks += (int)$checkPersonal->get_result()->fetch_assoc()['total'];
            $checkPersonal->close();

            $checkTeacher = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_tasks WHERE subject_id = ? AND student_id = ? AND status = 'pending'");
            $checkTeacher->bind_param("ii", $subjectId, $userId);
            $checkTeacher->execute();
            $pendingTasks += (int)$checkTeacher->get_result()->fetch_assoc()['total'];
            $checkTeacher->close();

            if ($pendingTasks > 0) {
                set_flash('error', "Cannot delete subject '{$subjectRow['subject_name']}': it has {$pendingTasks} pending task(s). Please complete or delete pending tasks first.");
            } else {
                $delStmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND user_id = ?");
                $delStmt->bind_param("ii", $subjectId, $userId);
                $delStmt->execute();
                $delStmt->close();
                set_flash('success', "Subject '{$subjectRow['subject_name']}' deleted successfully.");
            }
        } else {
            set_flash('error', "Subject not found or access denied.");
        }
    }
    header("Location: subjects.php");
    exit();
}

// Handle Adding a New Subject via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    validate_csrf_or_die();

    $subjectName = trim($_POST['subject_name'] ?? '');

    if (empty($subjectName)) {
        set_flash('error', "Please enter a subject name.");
    } elseif (mb_strlen($subjectName) < 2 || mb_strlen($subjectName) > 100) {
        set_flash('error', "Subject name must be between 2 and 100 characters.");
    } else {
        // Case-insensitive duplicate check for this user
        $dupStmt = $conn->prepare("SELECT id FROM subjects WHERE user_id = ? AND LOWER(subject_name) = LOWER(?) LIMIT 1");
        $dupStmt->bind_param("is", $userId, $subjectName);
        $dupStmt->execute();
        $dupResult = $dupStmt->get_result();

        if ($dupResult->num_rows > 0) {
            set_flash('error', "You already have a subject named '{$subjectName}'.");
        } else {
            // Check if there is an assigned teacher for this subject name in teacher_subjects
            $teacherId = null;
            $teacherCheck = $conn->prepare("SELECT teacher_id FROM teacher_subjects WHERE LOWER(subject_name) = LOWER(?) LIMIT 1");
            $teacherCheck->bind_param("s", $subjectName);
            $teacherCheck->execute();
            $teacherRes = $teacherCheck->get_result();
            if ($tRow = $teacherRes->fetch_assoc()) {
                $teacherId = (int)$tRow['teacher_id'];
            }
            $teacherCheck->close();

            $addStmt = $conn->prepare("INSERT INTO subjects (user_id, teacher_id, subject_name) VALUES (?, ?, ?)");
            $addStmt->bind_param("iis", $userId, $teacherId, $subjectName);

            if ($addStmt->execute()) {
                set_flash('success', "Subject '{$subjectName}' added successfully!");
            } else {
                set_flash('error', "Failed to add subject. Please try again.");
            }
            $addStmt->close();
        }
        $dupStmt->close();
    }
    header("Location: subjects.php");
    exit();
}

// Fetch all subjects for this student
$stmt = $conn->prepare("
    SELECT s.id, s.subject_name, s.created_at,
           t.name AS teacher_name,
           (SELECT COUNT(*) FROM tasks WHERE subject_id = s.id AND user_id = s.user_id AND status = 'pending') AS personal_pending,
           (SELECT COUNT(*) FROM teacher_tasks WHERE subject_id = s.id AND student_id = s.user_id AND status = 'pending') AS teacher_pending,
           (SELECT COUNT(*) FROM tasks WHERE subject_id = s.id AND user_id = s.user_id AND status = 'completed') AS personal_completed,
           (SELECT COUNT(*) FROM teacher_tasks WHERE subject_id = s.id AND student_id = s.user_id AND status = 'completed') AS teacher_completed
    FROM subjects s
    LEFT JOIN teachers t ON s.teacher_id = t.id
    WHERE s.user_id = ?
    ORDER BY s.subject_name ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$subjectsResult = $stmt->get_result();
$subjects = [];
while ($row = $subjectsResult->fetch_assoc()) {
    $row['total_pending'] = (int)$row['personal_pending'] + (int)$row['teacher_pending'];
    $row['total_completed'] = (int)$row['personal_completed'] + (int)$row['teacher_completed'];
    $subjects[] = $row;
}
$stmt->close();

$pageTitle = "Subjects - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="subjects-page">

    <!-- PAGE HEADER -->
    <div class="subjects-header">
        <div class="subjects-icon">📖</div>
        <h1>My Subjects</h1>
        <p>Manage the courses and modules you are actively studying.</p>
    </div>

    <!-- ADD SUBJECT CARD -->
    <div class="add-subject-card">
        <div class="add-title">
            <div class="add-icon">➕</div>
            <div>
                <h2>Add New Subject</h2>
                <p>Enter the name of a new module or topic</p>
            </div>
        </div>

        <form method="POST" action="subjects.php" class="subject-form" style="display: flex; gap: 10px; width: 100%; max-width: 500px;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_subject">
            <input
                type="text"
                name="subject_name"
                placeholder="e.g. Data Structures, Pharmacology"
                required
                maxlength="100"
                style="flex: 1; padding: 12px 14px; border: 1px solid #dcd6ff; border-radius: 8px; font-size: 14px; outline: none;"
            >
            <button type="submit" class="quick-button">Add Subject</button>
        </form>
    </div>

    <!-- SUBJECTS LIST -->
    <div class="subjects-list-card">
        <div class="list-header">
            <div>
                <h2>Enrolled Subjects</h2>
                <p>Modules registered under your student profile</p>
            </div>
            <span class="subject-count"><?php echo count($subjects); ?></span>
        </div>

        <?php if (count($subjects) > 0): ?>
            <?php foreach ($subjects as $sub): ?>
                <div class="subject-card">
                    <div class="subject-info">
                        <div class="subject-icon">📚</div>
                        <div>
                            <h3><?php echo e($sub['subject_name']); ?></h3>
                            <?php if (!empty($sub['teacher_name'])): ?>
                                <p style="color: #6d28d9; font-weight: 500;">
                                    👩‍🏫 Assigned Teacher: <?php echo e($sub['teacher_name']); ?>
                                </p>
                            <?php endif; ?>
                            <p style="margin-top: 4px;">
                                <span style="color: <?php echo $sub['total_pending'] > 0 ? '#d97706' : '#16a34a'; ?>; font-weight: 600;">
                                    ⏳ <?php echo $sub['total_pending']; ?> pending task<?php echo $sub['total_pending'] === 1 ? '' : 's'; ?>
                                </span>
                                &nbsp;•&nbsp;
                                <span style="color: #64748b;">
                                    ✅ <?php echo $sub['total_completed']; ?> completed
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="task-actions">
                        <!-- DELETE SUBJECT FORM (POST + CSRF) -->
                        <form method="POST" action="subjects.php" class="inline-form" onsubmit="return confirm('Delete subject \'<?php echo e(addslashes($sub['subject_name'])); ?>\'? You cannot delete subjects with pending tasks.');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="delete_subject">
                            <input type="hidden" name="subject_id" value="<?php echo (int)$sub['id']; ?>">
                            <button type="submit" class="delete-button">🗑️ Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-subjects">
                <div>📚</div>
                <h3>No subjects added yet</h3>
                <p>Use the form above to add your academic subjects and start scheduling tasks.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>