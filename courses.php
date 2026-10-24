<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$teacherId = require_teacher_login();

// Handle Teacher Course Assignment Selection (POST + CSRF)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "update_my_courses") {
    validate_csrf_or_die();

    $selectedCourseIds = $_POST["assigned_courses"] ?? [];
    if (!is_array($selectedCourseIds)) {
        $selectedCourseIds = [];
    }
    $selectedCourseIds = array_map('intval', $selectedCourseIds);

    // Delete existing course associations for this teacher
    $delStmt = $conn->prepare("DELETE FROM teacher_courses WHERE teacher_id = ?");
    $delStmt->bind_param("i", $teacherId);
    $delStmt->execute();
    $delStmt->close();

    // Insert newly selected courses
    if (!empty($selectedCourseIds)) {
        $insStmt = $conn->prepare("INSERT INTO teacher_courses (teacher_id, course_id) VALUES (?, ?)");
        foreach ($selectedCourseIds as $cId) {
            if ($cId > 0) {
                $insStmt->bind_param("ii", $teacherId, $cId);
                $insStmt->execute();
            }
        }
        $insStmt->close();
    }

    set_flash('success', "Your teaching courses have been updated successfully!");
    header("Location: courses.php");
    exit();
}

// Handle Adding a New Global Course (POST + CSRF)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "add_course") {
    validate_csrf_or_die();

    $courseName = trim($_POST["course_name"] ?? "");

    if (empty($courseName)) {
        set_flash('error', "Please enter a course name.");
    } elseif (mb_strlen($courseName) < 2 || mb_strlen($courseName) > 100) {
        set_flash('error', "Course name must be between 2 and 100 characters.");
    } else {
        // Case-insensitive duplicate check
        $checkStmt = $conn->prepare("SELECT id FROM courses WHERE LOWER(course_name) = LOWER(?) LIMIT 1");
        $checkStmt->bind_param("s", $courseName);
        $checkStmt->execute();
        $checkRes = $checkStmt->get_result();

        if ($checkRes->num_rows > 0) {
            set_flash('error', "A course named '{$courseName}' already exists.");
        } else {
            $insertStmt = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
            $insertStmt->bind_param("s", $courseName);

            if ($insertStmt->execute()) {
                $newCourseId = $conn->insert_id;
                // Automatically assign newly created course to this teacher
                $autoAssign = $conn->prepare("INSERT IGNORE INTO teacher_courses (teacher_id, course_id) VALUES (?, ?)");
                $autoAssign->bind_param("ii", $teacherId, $newCourseId);
                $autoAssign->execute();
                $autoAssign->close();

                set_flash('success', "Course '{$courseName}' added and added to your teaching list!");
            } else {
                set_flash('error', "Something went wrong while adding the course.");
            }
            $insertStmt->close();
        }
        $checkStmt->close();
    }
    header("Location: courses.php");
    exit();
}

// Fetch all available courses
$coursesStmt = $conn->prepare("
    SELECT c.id, c.course_name,
           (SELECT COUNT(*) FROM users u WHERE LOWER(u.course) = LOWER(c.course_name)) AS student_count,
           (SELECT COUNT(*) FROM course_subjects cs WHERE cs.course_id = c.id) AS subject_count
    FROM courses c
    ORDER BY c.course_name ASC
");
$coursesStmt->execute();
$allCourses = $coursesStmt->get_result();

// Fetch courses assigned to current teacher
$myCoursesStmt = $conn->prepare("SELECT course_id FROM teacher_courses WHERE teacher_id = ?");
$myCoursesStmt->bind_param("i", $teacherId);
$myCoursesStmt->execute();
$myCourseResult = $myCoursesStmt->get_result();
$myCourseIds = [];
while ($row = $myCourseResult->fetch_assoc()) {
    $myCourseIds[] = (int)$row['course_id'];
}
$myCoursesStmt->close();

$pageTitle = "Manage Courses - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="tp-page tp-narrow">

    <div class="tp-head">
        <div>
            <h1>Course Management</h1>
            <p>Pick the courses you teach and create new academic programs.</p>
        </div>
        <div class="tp-head-actions">
            <a href="teacher_dashboard.php" class="sp-btn sp-btn-soft">← Dashboard</a>
        </div>
    </div>

    <!-- PICK TEACHING COURSES -->
    <section class="tp-card" style="margin-top: 26px;">
        <h2>🎓 Courses You Teach</h2>
        <p>Select the courses you actively teach. You will be able to assign coursework to students in these courses.</p>

        <form method="POST" action="courses.php">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="update_my_courses">

            <div class="tp-course-grid">
                <?php
                $allCourses->data_seek(0);
                while ($c = $allCourses->fetch_assoc()):
                    $isChecked = in_array((int)$c['id'], $myCourseIds, true);
                ?>
                    <label class="tp-course-option <?php echo $isChecked ? 'is-selected' : ''; ?>">
                        <input type="checkbox" name="assigned_courses[]" value="<?php echo (int)$c['id']; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                        <div>
                            <div class="tp-course-name"><?php echo e($c['course_name']); ?></div>
                            <div class="tp-course-meta">
                                👥 <?php echo (int)$c['student_count']; ?> student<?php echo ((int)$c['student_count'] === 1) ? '' : 's'; ?>
                                &bull; 📚 <?php echo (int)$c['subject_count']; ?> subjects
                            </div>
                        </div>
                    </label>
                <?php endwhile; ?>
            </div>

            <button type="submit" class="sp-btn sp-btn-primary">💾 Save Teaching Assignments</button>
        </form>
    </section>

    <!-- ADD NEW COURSE -->
    <section class="tp-card" style="margin-top: 24px;">
        <h2>➕ Create New Course</h2>
        <p>Add a new course program that students can select upon registration.</p>

        <form method="POST" action="courses.php" class="tp-form-row">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="add_course">
            <input type="text" id="course_name" name="course_name" class="tp-input"
                   placeholder="e.g. B.Tech Artificial Intelligence" required maxlength="100">
            <button type="submit" class="sp-btn sp-btn-primary">➕ Add Course</button>
        </form>
    </section>
</div>

<script>
    // live highlight of selected course cards
    document.querySelectorAll('.tp-course-option input').forEach(function (cb) {
        cb.addEventListener('change', function () {
            cb.closest('.tp-course-option').classList.toggle('is-selected', cb.checked);
        });
    });
</script>

<?php
$coursesStmt->close();
include __DIR__ . '/includes/footer.php';
?>