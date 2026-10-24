<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();
$name = $_SESSION['user_name'] ?? 'Student';

// Handle state change: Complete Task (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_task') {
    validate_csrf_or_die();

    $taskId = (int)($_POST['task_id'] ?? 0);
    $source = $_POST['source'] ?? 'student';

    if ($taskId > 0) {
        if ($source === 'teacher') {
            $stmt = $conn->prepare("UPDATE teacher_tasks SET status = 'completed' WHERE id = ? AND student_id = ?");
            $stmt->bind_param("ii", $taskId, $userId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Teacher assignment marked as completed!');
        } else {
            $stmt = $conn->prepare("UPDATE tasks SET status = 'completed' WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $taskId, $userId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Task marked as completed!');
        }
    }
    header("Location: dashboard.php");
    exit();
}

// Fetch unified stats
$stats = get_student_task_stats($conn, $userId);

// Fetch student's daily study budget (default 3.0h)
$dailyBudget = get_student_study_budget($conn, $userId);

// Fetch all pending tasks for Smart Daily Plan
$allPendingTasks = get_student_all_tasks($conn, $userId, ['status' => 'pending']);
$smartPlan = generate_smart_daily_plan($allPendingTasks, $dailyBudget);

// Fetch today's scheduled tasks
$today = date('Y-m-d');
$todayTasks = get_student_all_tasks($conn, $userId, ['due_date' => $today]);

// Fetch streak
$streak = get_user_study_streak($conn, $userId);

$pageTitle = "Dashboard - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<?php
$pending   = (int)$stats['pending_tasks'];
$completed = (int)$stats['completed_tasks'];
$progress  = max(0, min(100, (int)$stats['progress']));
$hour      = (int)date('G');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
?>

<div class="sp-dashboard">

    <!-- HERO -->
    <section class="sp-hero">
        <div>
            <div class="sp-hero-date"><?php echo date('l, F j'); ?></div>
            <h1><?php echo $greeting; ?>, <?php echo e($name); ?> 👋</h1>
            <p>
                <?php if ($pending > 0): ?>
                    You have <strong><?php echo $pending; ?></strong> pending task<?php echo $pending === 1 ? '' : 's'; ?>. Let's get them done.
                <?php else: ?>
                    You're all caught up. Great work!
                <?php endif; ?>
            </p>
        </div>
        <div class="sp-hero-side">
            <div class="sp-streak">
                <span class="sp-streak-icon">🔥</span>
                <div>
                    <div class="sp-streak-num"><?php echo (int)$streak; ?> Day<?php echo $streak === 1 ? '' : 's'; ?></div>
                    <div class="sp-streak-label">STUDY STREAK</div>
                </div>
            </div>
            <a href="study.php" class="sp-btn sp-btn-light">⏱️ Open Study Timer</a>
        </div>
    </section>

    <!-- STAT CARDS -->
    <section class="sp-stats">
        <a class="sp-stat sp-stat-purple" href="subjects.php">
            <div class="sp-stat-top"><div class="sp-stat-icon">📚</div><span class="sp-stat-arrow">→</span></div>
            <div class="sp-stat-value"><?php echo (int)$stats['subject_count']; ?></div>
            <div class="sp-stat-label">Enrolled Subjects</div>
        </a>

        <a class="sp-stat sp-stat-orange" href="tasks.php?status=pending">
            <div class="sp-stat-top"><div class="sp-stat-icon">⏳</div><span class="sp-stat-arrow">→</span></div>
            <div class="sp-stat-value"><?php echo $pending; ?></div>
            <div class="sp-stat-label">Pending Tasks</div>
        </a>

        <a class="sp-stat sp-stat-green" href="tasks.php?status=completed">
            <div class="sp-stat-top"><div class="sp-stat-icon">✅</div><span class="sp-stat-arrow">→</span></div>
            <div class="sp-stat-value"><?php echo $completed; ?></div>
            <div class="sp-stat-label">Completed Tasks</div>
        </a>

        <div class="sp-stat sp-stat-blue">
            <div class="sp-stat-top"><div class="sp-stat-icon">📊</div></div>
            <div class="sp-stat-value"><?php echo $progress; ?>%</div>
            <div class="sp-stat-label">Study Progress</div>
            <div class="sp-progress"><span style="width: <?php echo $progress; ?>%;"></span></div>
        </div>
    </section>

    <!-- QUICK ACTIONS -->
    <section class="sp-actions">
        <a class="sp-action" href="subjects.php"><span class="sp-action-ico">➕</span> Add Subject</a>
        <a class="sp-action" href="add_task.php"><span class="sp-action-ico">📝</span> Add Task</a>
        <a class="sp-action" href="tasks.php"><span class="sp-action-ico">📋</span> View Tasks</a>
        <a class="sp-action" href="calendar.php"><span class="sp-action-ico">📅</span> Calendar</a>
    </section>

    <!-- SMART PLAN + TODAY -->
    <section class="sp-main">

        <!-- SMART DAILY PLAN -->
        <div class="sp-plan">
            <div class="sp-panel-head">
                <div>
                    <span class="sp-chip sp-chip-primary">🧠 Smart Study Plan</span>
                    <div class="sp-panel-title" style="margin-top: 10px;">Suggested Plan for Today</div>
                    <div class="sp-panel-sub">
                        Ranked by deadline, priority and effort, fitted to your
                        <strong><?php echo e($dailyBudget); ?>h</strong> daily budget.
                    </div>
                </div>
                <div class="sp-budget">
                    <span class="sp-chip sp-chip-soft">⏱️ <?php echo e($smartPlan['total_hours']); ?> / <?php echo e($dailyBudget); ?> hrs</span>
                    <a href="profile.php">Change budget</a>
                </div>
            </div>

            <?php if (!empty($smartPlan['planned_tasks'])): ?>
                <?php foreach ($smartPlan['planned_tasks'] as $pTask): ?>
                    <div class="sp-task">
                        <div class="sp-task-main">
                            <div class="sp-task-meta">
                                <h3 class="sp-task-name"><?php echo e($pTask['task_name']); ?></h3>
                                <?php echo render_priority_badge($pTask['priority']); ?>
                                <span class="hours-badge">⏱️ <?php echo e($pTask['estimated_hours']); ?>h</span>
                                <?php if ($pTask['source'] === 'teacher'): ?>
                                    <span class="badge-teacher">Teacher Assignment</span>
                                <?php endif; ?>
                            </div>
                            <div class="sp-task-reason">
                                <?php echo e($pTask['smart_reason']); ?> &bull; 📚 <?php echo e($pTask['subject_name']); ?>
                            </div>
                        </div>

                        <form method="POST" action="dashboard.php" class="sp-task-form">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="action" value="complete_task">
                            <input type="hidden" name="task_id" value="<?php echo (int)$pTask['id']; ?>">
                            <input type="hidden" name="source" value="<?php echo e($pTask['source']); ?>">
                            <button type="submit" class="sp-btn sp-btn-primary sp-btn-sm">✓ Complete</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sp-empty">
                    <div class="sp-empty-ico">🎉</div>
                    <h3>All tasks completed!</h3>
                    <p>You have no pending tasks. Enjoy your free time or add a new goal.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TODAY'S TASKS -->
        <div class="sp-panel">
            <div class="sp-panel-head">
                <div>
                    <div class="sp-panel-title">📅 Today's Tasks</div>
                    <div class="sp-panel-sub">Tasks you need to complete today.</div>
                </div>
                <span class="sp-chip sp-chip-soft"><?php echo count($todayTasks); ?></span>
            </div>

            <?php if (count($todayTasks) > 0): ?>
                <?php foreach ($todayTasks as $task): ?>
                    <div class="sp-today-item">
                        <div class="sp-today-ico"><?php echo ($task['source'] === 'teacher') ? '👩‍🏫' : '📝'; ?></div>
                        <div class="sp-today-body">
                            <h3 title="<?php echo e($task['task_name']); ?>"><?php echo e($task['task_name']); ?></h3>
                            <p>📚 <?php echo e($task['subject_name']); ?> &bull; ⏱️ <?php echo e($task['estimated_hours']); ?>h</p>
                        </div>
                        <div class="sp-today-side">
                            <?php if ($task['status'] === 'completed'): ?>
                                <span class="sp-pill sp-pill-done">✅ Completed</span>
                            <?php else: ?>
                                <?php if (!empty($task['is_overdue'])): ?>
                                    <span class="sp-pill sp-pill-over">⚠️ Overdue</span>
                                <?php else: ?>
                                    <span class="sp-pill sp-pill-due">⏳ Due Today</span>
                                <?php endif; ?>
                                <form method="POST" action="dashboard.php" class="sp-task-form">
                                    <?php echo csrf_field(); ?>
                                    <input type="hidden" name="action" value="complete_task">
                                    <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                    <input type="hidden" name="source" value="<?php echo e($task['source']); ?>">
                                    <button type="submit" class="sp-btn sp-btn-soft sp-btn-sm">✓ Complete</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="sp-empty">
                    <div class="sp-empty-ico">🎉</div>
                    <h3>No tasks due today!</h3>
                    <p>Check the Smart Plan or open your <a href="calendar.php">calendar</a>.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ANALYTICS -->
    <h2 class="sp-section-title">📈 Study Analytics &amp; Performance</h2>
    <section class="sp-charts">
        <div class="sp-chart-card">
            <h3>📚 Subject Completion %</h3>
            <div class="sp-chart-box"><canvas id="subjectChart"></canvas></div>
        </div>
        <div class="sp-chart-card">
            <h3>📅 Tasks Completed per Week</h3>
            <div class="sp-chart-box"><canvas id="weeklyChart"></canvas></div>
        </div>
        <div class="sp-chart-card">
            <h3>📊 Task Status Breakdown</h3>
            <div class="sp-chart-box"><canvas id="statusChart"></canvas></div>
        </div>
    </section>

</div>

<!-- CHARTS (colours follow the active theme) -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const charts = [];

    function applyThemeToChart(chart) {
        const text = css('--chart-text'), grid = css('--chart-grid'), surface = css('--surface');
        const o = chart.options;
        if (o.scales) {
            Object.values(o.scales).forEach(sc => {
                sc.ticks = Object.assign(sc.ticks || {}, { color: text });
                sc.grid  = Object.assign(sc.grid  || {}, { color: grid });
                sc.border = { display: false };
            });
        }
        o.plugins.legend.labels = Object.assign(o.plugins.legend.labels || {}, { color: text, usePointStyle: true, boxWidth: 8 });
        if (chart.config.type === 'doughnut') chart.data.datasets[0].borderColor = surface;
        chart.update('none');
    }

    fetch('api/stats.php')
        .then(r => r.json())
        .then(data => {
            if (data.error) return;
            Chart.defaults.font.family = '"Inter","Segoe UI",system-ui,Arial,sans-serif';

            // 1. Subject completion
            charts.push(new Chart(document.getElementById('subjectChart'), {
                type: 'bar',
                data: {
                    labels: data.subjects.labels.length ? data.subjects.labels : ['No Subjects'],
                    datasets: [{
                        label: 'Completion %',
                        data: data.subjects.percentages.length ? data.subjects.percentages : [0],
                        backgroundColor: '#6366f1',
                        borderRadius: 8,
                        maxBarThickness: 38
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, max: 100 }, x: {} },
                    plugins: { legend: { display: false } }
                }
            }));

            // 2. Weekly completed
            charts.push(new Chart(document.getElementById('weeklyChart'), {
                type: 'line',
                data: {
                    labels: data.weekly.labels,
                    datasets: [{
                        label: 'Tasks Completed',
                        data: data.weekly.completed,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.16)',
                        fill: true, tension: 0.35, borderWidth: 2.5,
                        pointRadius: 4, pointBackgroundColor: '#10b981'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: {} },
                    plugins: { legend: { display: false } }
                }
            }));

            // 3. Status breakdown
            charts.push(new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: data.status_breakdown.labels,
                    datasets: [{
                        data: data.status_breakdown.data,
                        backgroundColor: ['#f59e0b', '#ef4444', '#10b981'],
                        borderWidth: 3, hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '68%',
                    plugins: { legend: { position: 'bottom' } }
                }
            }));

            charts.forEach(applyThemeToChart);
        })
        .catch(err => console.log('Chart loading notice:', err));

    // Re-colour charts instantly when the theme is toggled
    document.addEventListener('themechange', () => charts.forEach(applyThemeToChart));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
