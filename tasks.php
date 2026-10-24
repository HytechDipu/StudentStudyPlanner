<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

// Handle state changes via POST + CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_or_die();
    $action = $_POST['action'] ?? '';

    // Preserve query params on redirect
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $redirectUrl = 'tasks.php' . (!empty($queryString) ? '?' . $queryString : '');

    if ($action === 'complete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $source = $_POST['source'] ?? 'student';

        if ($taskId > 0) {
            if ($source === 'teacher') {
                $stmt = $conn->prepare("UPDATE teacher_tasks SET status = 'completed' WHERE id = ? AND student_id = ?");
                $stmt->bind_param("ii", $taskId, $userId);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Teacher task marked as completed!');
            } else {
                $stmt = $conn->prepare("UPDATE tasks SET status = 'completed' WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $taskId, $userId);
                $stmt->execute();
                $stmt->close();
                set_flash('success', 'Task marked as completed!');
            }
        }
        header("Location: " . $redirectUrl);
        exit();

    } elseif ($action === 'delete') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $taskId, $userId);
            $stmt->execute();
            $stmt->close();
            set_flash('success', 'Task deleted successfully.');
        }
        header("Location: " . $redirectUrl);
        exit();
    }
}

// Fetch user subjects for filter dropdown
$subStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->bind_param("i", $userId);
$subStmt->execute();
$subjectsList = $subStmt->get_result();

// Read filter parameters
$search = trim($_GET['search'] ?? '');
$subjectId = (int)($_GET['subject_id'] ?? 0);
$priority = trim($_GET['priority'] ?? '');
$statusFilter = trim($_GET['status'] ?? 'all');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$sort = trim($_GET['sort'] ?? 'due_asc');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;

// Fetch filtered and sorted tasks
$allTasks = get_student_all_tasks($conn, $userId, [
    'search' => $search,
    'subject_id' => $subjectId,
    'priority' => $priority,
    'status' => $statusFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort,
]);

// Apply pagination
$totalTasks = count($allTasks);
$totalPages = max(1, (int)ceil($totalTasks / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$startIndex = ($page - 1) * $perPage;
$paginatedTasks = array_slice($allTasks, $startIndex, $perPage);

// Helper for pagination links to preserve existing query parameters
function build_page_url(int $targetPage): string {
    $params = $_GET;
    $params['page'] = $targetPage;
    return 'tasks.php?' . http_build_query($params);
}

$pageTitle = "My Tasks - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div class="subjects-page">

    <!-- PAGE HEADER -->
    <div class="subjects-header">
        <div class="subjects-icon">📋</div>
        <h1>My Study Tasks</h1>
        <p>Search, filter, prioritize, and manage all your coursework and deadlines.</p>
    </div>

    <!-- FILTER & SEARCH TOOLBAR -->
    <div class="filter-toolbar">
        <form method="GET" action="tasks.php">
            <div class="filter-form-grid">
                <!-- Search -->
                <div class="filter-item">
                    <label for="search">🔍 Search Tasks</label>
                    <input
                        type="text"
                        id="search"
                        name="search"
                        placeholder="Search by title or subject..."
                        value="<?php echo e($search); ?>"
                    >
                </div>

                <!-- Subject -->
                <div class="filter-item">
                    <label for="subject_id">📚 Subject</label>
                    <select id="subject_id" name="subject_id">
                        <option value="">All Subjects</option>
                        <?php
                        $subjectsList->data_seek(0);
                        while ($s = $subjectsList->fetch_assoc()):
                            $sel = ($subjectId === (int)$s['id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo (int)$s['id']; ?>" <?php echo $sel; ?>>
                                <?php echo e($s['subject_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Priority -->
                <div class="filter-item">
                    <label for="priority">⚡ Priority</label>
                    <select id="priority" name="priority">
                        <option value="">All Priorities</option>
                        <option value="high" <?php echo $priority === 'high' ? 'selected' : ''; ?>>🔥 High</option>
                        <option value="medium" <?php echo $priority === 'medium' ? 'selected' : ''; ?>>⚡ Medium</option>
                        <option value="low" <?php echo $priority === 'low' ? 'selected' : ''; ?>>🌱 Low</option>
                    </select>
                </div>

                <!-- Status -->
                <div class="filter-item">
                    <label for="status">📌 Status</label>
                    <select id="status" name="status">
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>⏳ Pending</option>
                        <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>✅ Completed</option>
                        <option value="overdue" <?php echo $statusFilter === 'overdue' ? 'selected' : ''; ?>>⚠️ Overdue</option>
                    </select>
                </div>

                <!-- Sort -->
                <div class="filter-item">
                    <label for="sort">🔀 Sort By</label>
                    <select id="sort" name="sort">
                        <option value="due_asc" <?php echo $sort === 'due_asc' ? 'selected' : ''; ?>>Due Date (Earliest)</option>
                        <option value="due_desc" <?php echo $sort === 'due_desc' ? 'selected' : ''; ?>>Due Date (Latest)</option>
                        <option value="priority_desc" <?php echo $sort === 'priority_desc' ? 'selected' : ''; ?>>Priority (High to Low)</option>
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Task Name (A-Z)</option>
                    </select>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="quick-button" style="padding: 10px 16px;">
                        Filter
                    </button>
                    <?php if (!empty($search) || $subjectId > 0 || !empty($priority) || $statusFilter !== 'all' || !empty($dateFrom) || !empty($dateTo) || $sort !== 'due_asc'): ?>
                        <a href="tasks.php" class="delete-button" style="padding: 10px 14px; font-weight: normal;" title="Clear Filters">
                            Clear
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Optional Date Range Row -->
            <div style="display: flex; gap: 15px; margin-top: 12px; align-items: center; font-size: 13px;">
                <span style="color: #64748b; font-weight: 600;">📅 Date Range:</span>
                <input type="date" name="date_from" value="<?php echo e($dateFrom); ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
                <span style="color: #94a3b8;">to</span>
                <input type="date" name="date_to" value="<?php echo e($dateTo); ?>" style="padding: 6px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px;">
            </div>
        </form>
    </div>

    <!-- QUICK ADD & STATUS SUMMARY BAR -->
    <div class="add-subject-card" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <div style="font-size: 14px; color: #475569;">
            Showing <strong><?php echo min($startIndex + 1, $totalTasks); ?>-<?php echo min($startIndex + count($paginatedTasks), $totalTasks); ?></strong> of <strong><?php echo $totalTasks; ?></strong> task<?php echo $totalTasks === 1 ? '' : 's'; ?>
        </div>

        <a href="add_task.php" class="quick-button">
            ➕ Add Personal Task
        </a>
    </div>

    <!-- TASK LIST CARD -->
    <div class="subjects-list-card">
        <div class="list-header">
            <div>
                <h2>Task List</h2>
                <p>Personal tasks & coursework assigned by teachers</p>
            </div>
            <span class="subject-count"><?php echo $totalTasks; ?></span>
        </div>

        <?php if (!empty($paginatedTasks)): ?>
            <?php foreach ($paginatedTasks as $task): ?>
                <div class="subject-card">
                    <div class="subject-info">
                        <div class="subject-icon">
                            <?php echo ($task['source'] === 'teacher') ? '👩‍🏫' : '📝'; ?>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                                <h3 style="margin: 0; font-size: 17px;">
                                    <?php echo e($task['task_name']); ?>
                                </h3>
                                <?php echo render_priority_badge($task['priority']); ?>
                                <span class="hours-badge">⏱️ <?php echo $task['estimated_hours']; ?>h</span>
                                <?php if ($task['source'] === 'teacher'): ?>
                                    <span class="badge-teacher">Teacher: <?php echo e($task['teacher_name'] ?? 'Instructor'); ?></span>
                                <?php endif; ?>
                            </div>
                            <p>📚 <?php echo e($task['subject_name']); ?></p>
                            <p>📅 Due: <?php echo format_date($task['due_date']); ?></p>
                        </div>
                    </div>

                    <!-- TASK ACTIONS -->
                    <div class="task-actions">
                        <?php if ($task['status'] === 'completed'): ?>
                            <span class="task-completed">✅ Completed</span>
                        <?php else: ?>
                            <!-- Overdue and Pending Badges -->
                            <?php if ($task['is_overdue']): ?>
                                <span class="task-overdue">⚠️ Overdue</span>
                            <?php else: ?>
                                <span class="task-pending">⏳ Pending</span>
                            <?php endif; ?>

                            <!-- Complete Button (Available for both Overdue and regular Pending tasks) -->
                            <form method="POST" action="<?php echo e(build_page_url($page)); ?>" class="inline-form">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="complete">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <input type="hidden" name="source" value="<?php echo e($task['source']); ?>">
                                <button type="submit" class="quick-button">✓ Complete</button>
                            </form>
                        <?php endif; ?>

                        <!-- Only Student personal tasks can be edited or deleted -->
                        <?php if ($task['can_edit']): ?>
                            <a href="edit_task.php?id=<?php echo (int)$task['id']; ?>" class="edit-button">
                                ✏️ Edit
                            </a>

                            <form method="POST" action="<?php echo e(build_page_url($page)); ?>" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this task?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <button type="submit" class="delete-button">🗑️ Delete</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- PAGINATION CONTROLS -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination-container">
                    <span style="font-size: 13px; color: #64748b;">
                        Page <?php echo $page; ?> of <?php echo $totalPages; ?>
                    </span>

                    <div class="pagination-links">
                        <?php if ($page > 1): ?>
                            <a href="<?php echo e(build_page_url($page - 1)); ?>" class="page-btn">« Prev</a>
                        <?php endif; ?>

                        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                            <a href="<?php echo e(build_page_url($p)); ?>" class="page-btn <?php echo $p === $page ? 'active' : ''; ?>">
                                <?php echo $p; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo e(build_page_url($page + 1)); ?>" class="page-btn">Next »</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="empty-subjects">
                <div>🔍</div>
                <h3>No tasks found matching your criteria</h3>
                <p>Try adjusting your search query or filters. <a href="tasks.php" style="color: #4f46e5; font-weight: bold;">View all tasks</a>.</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<?php
$subStmt->close();
include __DIR__ . '/includes/footer.php';
?>