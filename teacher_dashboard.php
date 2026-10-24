<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$teacherId = require_teacher_login();
$teacherName = $_SESSION['teacher_name'] ?? 'Teacher';

// Count courses taught by this teacher
$courseCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_courses WHERE teacher_id = ?");
$courseCountStmt->bind_param("i", $teacherId);
$courseCountStmt->execute();
$myCourseCount = (int)$courseCountStmt->get_result()->fetch_assoc()['total'];
$courseCountStmt->close();

// Count students enrolled in courses taught by this teacher
$studentCountStmt = $conn->prepare("
    SELECT COUNT(DISTINCT u.id) AS total
    FROM users u
    INNER JOIN courses c ON LOWER(u.course) = LOWER(c.course_name)
    INNER JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
");
$studentCountStmt->bind_param("i", $teacherId);
$studentCountStmt->execute();
$totalStudents = (int)$studentCountStmt->get_result()->fetch_assoc()['total'];
$studentCountStmt->close();

// Tasks assigned by this teacher
$taskStmt = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
    FROM teacher_tasks
    WHERE teacher_id = ?
");
$taskStmt->bind_param("i", $teacherId);
$taskStmt->execute();
$taskStats = $taskStmt->get_result()->fetch_assoc();
$taskStmt->close();

$totalTasks = (int)($taskStats['total'] ?? 0);
$completedTasks = (int)($taskStats['completed'] ?? 0);
$pendingTasks = (int)($taskStats['pending'] ?? 0);
$completionRate = ($totalTasks > 0) ? (int)round(($completedTasks / $totalTasks) * 100) : 0;

// Active courses of this teacher with student counts
$activeCoursesStmt = $conn->prepare("
    SELECT c.id, c.course_name,
           (SELECT COUNT(*) FROM users u WHERE LOWER(u.course) = LOWER(c.course_name)) AS student_count
    FROM courses c
    INNER JOIN teacher_courses tc ON c.id = tc.course_id
    WHERE tc.teacher_id = ?
    ORDER BY c.course_name ASC
");
$activeCoursesStmt->bind_param("i", $teacherId);
$activeCoursesStmt->execute();
$activeCourses = $activeCoursesStmt->get_result();

// Recent assignments by this teacher
$recentStmt = $conn->prepare("
    SELECT tt.id, tt.task_name, tt.due_date, tt.status, tt.created_at,
           u.name AS student_name, u.course,
           s.subject_name
    FROM teacher_tasks tt
    INNER JOIN users u ON tt.student_id = u.id
    INNER JOIN subjects s ON tt.subject_id = s.id
    WHERE tt.teacher_id = ?
    ORDER BY tt.id DESC
    LIMIT 10
");
$recentStmt->bind_param("i", $teacherId);
$recentStmt->execute();
$recentAssignments = $recentStmt->get_result();

$pageTitle = "Teacher Dashboard - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="tp-page">

    <!-- HEADER -->
    <div class="tp-head">
        <div>
            <h1>Welcome, <?php echo e($teacherName); ?> 👩‍🏫</h1>
            <p>Monitor student progress, track assignment deadlines, and manage coursework across your courses.</p>
        </div>
        <div class="tp-head-actions">
            <a href="teacher_assign_task.php" class="sp-btn sp-btn-primary">➕ Assign Task</a>
            <a href="courses.php" class="sp-btn sp-btn-soft">🎓 My Courses</a>
        </div>
    </div>

    <!-- METRICS -->
    <section class="tp-stats">
        <a class="tp-stat tp-stat-blue" href="teacher_students.php">
            <div class="tp-stat-icon">👥</div>
            <div class="tp-stat-value"><?php echo $totalStudents; ?></div>
            <div class="tp-stat-label">Students in your courses</div>
        </a>
        <a class="tp-stat tp-stat-purple" href="courses.php">
            <div class="tp-stat-icon">🎓</div>
            <div class="tp-stat-value"><?php echo $myCourseCount; ?></div>
            <div class="tp-stat-label">Courses you teach</div>
        </a>
        <div class="tp-stat tp-stat-orange">
            <div class="tp-stat-icon">📋</div>
            <div class="tp-stat-value"><?php echo $totalTasks; ?></div>
            <div class="tp-stat-label">Total tasks assigned</div>
        </div>
        <div class="tp-stat tp-stat-green">
            <div class="tp-stat-icon">✅</div>
            <div class="tp-stat-value"><?php echo $completionRate; ?>%</div>
            <div class="tp-stat-label">Student completion rate</div>
            <div class="sp-progress"><span style="width: <?php echo $completionRate; ?>%;"></span></div>
        </div>
    </section>

    <!-- ACTIVE COURSES -->
    <section class="tp-courses-strip">
        <span class="tp-strip-label">Your active courses:</span>
        <?php if ($activeCourses->num_rows > 0): ?>
            <?php while ($ac = $activeCourses->fetch_assoc()): ?>
                <span class="tp-chip">🎓 <?php echo e($ac['course_name']); ?>
                    <small>(<?php echo (int)$ac['student_count']; ?> student<?php echo ((int)$ac['student_count'] === 1) ? '' : 's'; ?>)</small>
                </span>
            <?php endwhile; ?>
        <?php else: ?>
            <span class="tp-strip-empty">No courses selected yet</span>
        <?php endif; ?>
        <a href="courses.php" class="tp-strip-link">+ Manage Courses</a>
    </section>

    <!-- ACTION CARDS -->
    <section class="tp-grid-2">
        <div class="tp-card">
            <h2>📝 Assign New Coursework</h2>
            <p>Create tasks and deadlines for an entire class or specific course curriculum with custom priorities and study effort.</p>
            <a href="teacher_assign_task.php" class="sp-btn sp-btn-primary">Assign Coursework →</a>
        </div>

        <div class="tp-card">
            <h2>👥 Student Roster &amp; Tracking</h2>
            <p>Review individual student performance, see who has pending or overdue assignments, and follow up.</p>
            <a href="teacher_students.php" class="sp-btn tp-btn-dark">View Student Directory →</a>
        </div>
    </section>

    <!-- RECENT ASSIGNMENTS -->
    <section class="tp-card" style="margin-top: 26px;">
        <div class="tp-card-head">
            <div>
                <div class="tp-card-title">Recent Task Assignments</div>
                <div class="tp-card-sub">Recent tasks distributed to your students</div>
            </div>
            <a href="teacher_students.php" class="tp-strip-link" style="margin-left:0;">View All Students →</a>
        </div>

        <?php if ($recentAssignments->num_rows > 0): ?>
            <div class="tp-table-wrap">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th>Task Name</th>
                            <th>Subject</th>
                            <th>Student</th>
                            <th>Course</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $recentAssignments->fetch_assoc()):
                            $isOverdue = is_task_overdue($row['due_date'], $row['status']);
                        ?>
                            <tr>
                                <td class="tp-strong"><?php echo e($row['task_name']); ?></td>
                                <td class="tp-accent"><?php echo e($row['subject_name']); ?></td>
                                <td><?php echo e($row['student_name']); ?></td>
                                <td><?php echo e($row['course']); ?></td>
                                <td><?php echo format_date($row['due_date']); ?></td>
                                <td>
                                    <?php if ($row['status'] === 'completed'): ?>
                                        <span class="sp-pill sp-pill-done">✅ Completed</span>
                                    <?php elseif ($isOverdue): ?>
                                        <span class="sp-pill sp-pill-over">⚠️ Overdue</span>
                                    <?php else: ?>
                                        <span class="sp-pill sp-pill-due">⏳ Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tp-empty">
                <div class="tp-empty-ico">📋</div>
                <h3>No assignments created yet</h3>
                <p>Click "Assign Task" to set coursework for your students.</p>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php
$recentStmt->close();
$activeCoursesStmt->close();
include __DIR__ . '/includes/footer.php';
?>