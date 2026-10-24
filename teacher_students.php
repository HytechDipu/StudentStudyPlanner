<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$teacherId = require_teacher_login();

// Fetch students with their completion stats for tasks assigned by this teacher
$stmt = $conn->prepare("
    SELECT u.id, u.name, u.email, u.course, u.created_at,
           COUNT(tt.id) AS total_assigned,
           SUM(CASE WHEN tt.status = 'completed' THEN 1 ELSE 0 END) AS completed_assigned,
           SUM(CASE WHEN tt.status = 'pending' AND tt.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
    FROM users u
    LEFT JOIN teacher_tasks tt ON u.id = tt.student_id AND tt.teacher_id = ?
    GROUP BY u.id, u.name, u.email, u.course, u.created_at
    ORDER BY u.name ASC
");
$stmt->bind_param("i", $teacherId);
$stmt->execute();
$students = $stmt->get_result();

$pageTitle = "Students - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="tp-page">

    <div class="tp-head">
        <div>
            <h1>Enrolled Students</h1>
            <p>Monitor student progress across your assigned coursework.</p>
        </div>
        <div class="tp-head-actions">
            <a href="teacher_assign_task.php" class="sp-btn sp-btn-primary">➕ Assign Coursework</a>
        </div>
    </div>

    <section class="tp-card" style="margin-top: 26px;">
        <div class="tp-card-head">
            <div>
                <div class="tp-card-title">Student Directory</div>
                <div class="tp-card-sub">Total registered students: <?php echo $students->num_rows; ?></div>
            </div>
        </div>

        <?php if ($students->num_rows > 0): ?>
            <div class="tp-table-wrap">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th>Email</th>
                            <th>Course</th>
                            <th class="tp-center">Assigned Tasks</th>
                            <th class="tp-center">Completed</th>
                            <th class="tp-center">Overdue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $students->fetch_assoc()): ?>
                            <tr>
                                <td class="tp-strong">🎓 <?php echo e($row['name']); ?></td>
                                <td><?php echo e($row['email']); ?></td>
                                <td><span class="sp-chip sp-chip-soft"><?php echo e($row['course'] ?? 'Undeclared'); ?></span></td>
                                <td class="tp-center tp-strong"><?php echo (int)$row['total_assigned']; ?></td>
                                <td class="tp-center tp-good"><?php echo (int)$row['completed_assigned']; ?></td>
                                <td class="tp-center">
                                    <?php if ((int)$row['overdue_count'] > 0): ?>
                                        <span class="sp-pill sp-pill-over">⚠️ <?php echo (int)$row['overdue_count']; ?></span>
                                    <?php else: ?>
                                        <span class="tp-zero">0</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="tp-empty">
                <div class="tp-empty-ico">👥</div>
                <h3>No students registered yet</h3>
                <p>When students register with their courses, they will appear in this directory.</p>
            </div>
        <?php endif; ?>
    </section>

</div>

<?php
$stmt->close();
include __DIR__ . '/includes/footer.php';
?>