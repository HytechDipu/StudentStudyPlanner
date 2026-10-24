<?php

// Shared helper functions for Student Study Planner

/**
 * HTML escape helper to prevent XSS.
 */
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Set a flash message in the session.
 */
function set_flash(string $type, string $message): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['flash'] = [
        'type' => $type, // 'success' or 'error'
        'message' => $message
    ];
}

/**
 * Retrieve and clear flash message from session.
 */
function get_flash(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Format date nicely.
 */
function format_date(?string $dateStr): string {
    if (empty($dateStr)) {
        return '';
    }
    $time = strtotime($dateStr);
    return date("M j, Y", $time);
}

/**
 * Check if a task is overdue.
 */
function is_task_overdue(string $dueDate, string $status): bool {
    if ($status === 'completed') {
        return false;
    }
    $today = date('Y-m-d');
    return ($dueDate < $today);
}

/**
 * Validate password requirements (minimum 8 characters).
 */
function validate_password(string $password): ?string {
    if (strlen($password) < 8) {
        return "Password must be at least 8 characters long.";
    }
    return null;
}

/**
 * Render colored priority badge HTML.
 */
function render_priority_badge(string $priority): string {
    $priority = strtolower($priority);
    switch ($priority) {
        case 'high':
            return '<span class="priority-badge priority-high">🔥 High</span>';
        case 'low':
            return '<span class="priority-badge priority-low">🌱 Low</span>';
        case 'medium':
        default:
            return '<span class="priority-badge priority-medium">⚡ Medium</span>';
    }
}

/**
 * Unified task fetcher: combines student personal tasks and teacher-assigned tasks.
 *
 * @param mysqli $conn
 * @param int $userId
 * @param array $options Filters: 'search', 'subject_id', 'priority', 'status', 'due_date', 'date_from', 'date_to', 'sort'
 * @return array List of normalized task records
 */
function get_student_all_tasks(mysqli $conn, int $userId, array $options = []): array {
    $tasks = [];
    $search = trim($options['search'] ?? '');
    $subjectId = (int)($options['subject_id'] ?? 0);
    $priority = trim($options['priority'] ?? '');
    $statusFilter = trim($options['status'] ?? 'all');
    $dueDateFilter = trim($options['due_date'] ?? '');
    $dateFrom = trim($options['date_from'] ?? '');
    $dateTo = trim($options['date_to'] ?? '');
    $sort = trim($options['sort'] ?? 'due_asc');

    // 1. Fetch student's own tasks
    $sqlStudent = "
        SELECT t.id, t.task_name, t.due_date, t.status, t.subject_id, t.created_at,
               COALESCE(t.priority, 'medium') AS priority,
               COALESCE(t.estimated_hours, 1.0) AS estimated_hours,
               s.subject_name,
               'student' AS source,
               NULL AS teacher_name
        FROM tasks t
        INNER JOIN subjects s ON t.subject_id = s.id
        WHERE t.user_id = ?
    ";
    $params = [$userId];
    $types = "i";

    if (!empty($search)) {
        $sqlStudent .= " AND (t.task_name LIKE ? OR s.subject_name LIKE ?)";
        $searchTerm = "%{$search}%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    if ($subjectId > 0) {
        $sqlStudent .= " AND t.subject_id = ?";
        $params[] = $subjectId;
        $types .= "i";
    }

    if (in_array($priority, ['low', 'medium', 'high'], true)) {
        $sqlStudent .= " AND t.priority = ?";
        $params[] = $priority;
        $types .= "s";
    }

    if ($statusFilter === 'pending') {
        $sqlStudent .= " AND t.status = 'pending'";
    } elseif ($statusFilter === 'completed') {
        $sqlStudent .= " AND t.status = 'completed'";
    } elseif ($statusFilter === 'overdue') {
        $sqlStudent .= " AND t.status = 'pending' AND t.due_date < CURDATE()";
    }

    if (!empty($dueDateFilter)) {
        $sqlStudent .= " AND t.due_date = ?";
        $params[] = $dueDateFilter;
        $types .= "s";
    }

    if (!empty($dateFrom)) {
        $sqlStudent .= " AND t.due_date >= ?";
        $params[] = $dateFrom;
        $types .= "s";
    }

    if (!empty($dateTo)) {
        $sqlStudent .= " AND t.due_date <= ?";
        $params[] = $dateTo;
        $types .= "s";
    }

    $stmt = $conn->prepare($sqlStudent);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $row['is_overdue'] = is_task_overdue($row['due_date'], $row['status']);
        $row['can_edit'] = true;
        $row['can_delete'] = true;
        $row['unified_id'] = 'student_' . $row['id'];
        $row['estimated_hours'] = (float)$row['estimated_hours'];
        $tasks[] = $row;
    }
    $stmt->close();

    // 2. Fetch teacher-assigned tasks
    $sqlTeacher = "
        SELECT tt.id, tt.task_name, tt.due_date, tt.status, tt.subject_id, tt.created_at,
               COALESCE(tt.priority, 'medium') AS priority,
               COALESCE(tt.estimated_hours, 1.0) AS estimated_hours,
               s.subject_name,
               'teacher' AS source,
               tch.name AS teacher_name
        FROM teacher_tasks tt
        INNER JOIN subjects s ON tt.subject_id = s.id
        INNER JOIN teachers tch ON tt.teacher_id = tch.id
        WHERE tt.student_id = ?
    ";
    $paramsT = [$userId];
    $typesT = "i";

    if (!empty($search)) {
        $sqlTeacher .= " AND (tt.task_name LIKE ? OR s.subject_name LIKE ?)";
        $paramsT[] = $searchTerm;
        $paramsT[] = $searchTerm;
        $typesT .= "ss";
    }

    if ($subjectId > 0) {
        $sqlTeacher .= " AND tt.subject_id = ?";
        $paramsT[] = $subjectId;
        $typesT .= "i";
    }

    if (in_array($priority, ['low', 'medium', 'high'], true)) {
        $sqlTeacher .= " AND tt.priority = ?";
        $paramsT[] = $priority;
        $typesT .= "s";
    }

    if ($statusFilter === 'pending') {
        $sqlTeacher .= " AND tt.status = 'pending'";
    } elseif ($statusFilter === 'completed') {
        $sqlTeacher .= " AND tt.status = 'completed'";
    } elseif ($statusFilter === 'overdue') {
        $sqlTeacher .= " AND tt.status = 'pending' AND tt.due_date < CURDATE()";
    }

    if (!empty($dueDateFilter)) {
        $sqlTeacher .= " AND tt.due_date = ?";
        $paramsT[] = $dueDateFilter;
        $typesT .= "s";
    }

    if (!empty($dateFrom)) {
        $sqlTeacher .= " AND tt.due_date >= ?";
        $paramsT[] = $dateFrom;
        $typesT .= "s";
    }

    if (!empty($dateTo)) {
        $sqlTeacher .= " AND tt.due_date <= ?";
        $paramsT[] = $dateTo;
        $typesT .= "s";
    }

    $stmtT = $conn->prepare($sqlTeacher);
    $stmtT->bind_param($typesT, ...$paramsT);
    $stmtT->execute();
    $resT = $stmtT->get_result();
    while ($row = $resT->fetch_assoc()) {
        $row['is_overdue'] = is_task_overdue($row['due_date'], $row['status']);
        $row['can_edit'] = false;
        $row['can_delete'] = false;
        $row['unified_id'] = 'teacher_' . $row['id'];
        $row['estimated_hours'] = (float)$row['estimated_hours'];
        $tasks[] = $row;
    }
    $stmtT->close();

    // Sort tasks
    usort($tasks, function ($a, $b) use ($sort) {
        switch ($sort) {
            case 'due_desc':
                return strcmp($b['due_date'], $a['due_date']);
            case 'priority_desc':
                $weights = ['high' => 3, 'medium' => 2, 'low' => 1];
                $wA = $weights[$a['priority']] ?? 2;
                $wB = $weights[$b['priority']] ?? 2;
                if ($wA === $wB) {
                    return strcmp($a['due_date'], $b['due_date']);
                }
                return $wB <=> $wA;
            case 'name_asc':
                return strcasecmp($a['task_name'], $b['task_name']);
            case 'due_asc':
            default:
                if ($a['due_date'] === $b['due_date']) {
                    return $b['id'] <=> $a['id'];
                }
                return strcmp($a['due_date'], $b['due_date']);
        }
    });

    return $tasks;
}

/**
 * Get comprehensive student task stats (personal + teacher assigned).
 */
function get_student_task_stats(mysqli $conn, int $userId): array {
    // Subject count
    $stmtSub = $conn->prepare("SELECT COUNT(*) AS total FROM subjects WHERE user_id = ?");
    $stmtSub->bind_param("i", $userId);
    $stmtSub->execute();
    $subjectCount = (int)$stmtSub->get_result()->fetch_assoc()['total'];
    $stmtSub->close();

    // Personal tasks
    $stmtTask = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM tasks
        WHERE user_id = ?
    ");
    $stmtTask->bind_param("i", $userId);
    $stmtTask->execute();
    $taskRow = $stmtTask->get_result()->fetch_assoc();
    $stmtTask->close();

    // Teacher tasks
    $stmtTTask = $conn->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM teacher_tasks
        WHERE student_id = ?
    ");
    $stmtTTask->bind_param("i", $userId);
    $stmtTTask->execute();
    $tTaskRow = $stmtTTask->get_result()->fetch_assoc();
    $stmtTTask->close();

    $totalTasks = (int)($taskRow['total'] ?? 0) + (int)($tTaskRow['total'] ?? 0);
    $pendingTasks = (int)($taskRow['pending'] ?? 0) + (int)($tTaskRow['pending'] ?? 0);
    $completedTasks = (int)($taskRow['completed'] ?? 0) + (int)($tTaskRow['completed'] ?? 0);
    $overdueTasks = (int)($taskRow['overdue'] ?? 0) + (int)($tTaskRow['overdue'] ?? 0);

    $progress = ($totalTasks > 0) ? (int)round(($completedTasks / $totalTasks) * 100) : 0;

    return [
        'subject_count' => $subjectCount,
        'total_tasks' => $totalTasks,
        'pending_tasks' => $pendingTasks,
        'completed_tasks' => $completedTasks,
        'overdue_tasks' => $overdueTasks,
        'progress' => $progress,
    ];
}

/**
 * Smart Daily Plan Algorithm
 * Ranks pending tasks with score = priority_weight + urgency + effort factor,
 * and fits them into the student's daily study-hours budget.
 */
function generate_smart_daily_plan(array $pendingTasks, float $budgetHours = 3.0): array {
    $today = new DateTime('today');
    $scoredTasks = [];

    foreach ($pendingTasks as $task) {
        if ($task['status'] === 'completed') {
            continue;
        }

        $priority = $task['priority'] ?? 'medium';
        $estHours = max(0.5, (float)($task['estimated_hours'] ?? 1.0));
        $dueDateObj = new DateTime($task['due_date']);
        $diffDays = (int)$today->diff($dueDateObj)->format("%r%a");

        // 1. Priority Weight
        $priorityWeight = 20;
        if ($priority === 'high') {
            $priorityWeight = 35;
        } elseif ($priority === 'low') {
            $priorityWeight = 10;
        }

        // 2. Urgency Score
        $urgencyScore = 5;
        $reason = "";
        if ($diffDays < 0) {
            $urgencyScore = 55;
            $daysPast = abs($diffDays);
            $reason = "⚠️ Overdue by {$daysPast} day(s), " . ucfirst($priority) . " priority — urgent action!";
        } elseif ($diffDays === 0) {
            $urgencyScore = 45;
            $reason = "🚨 Due today (" . ucfirst($priority) . " priority, {$estHours}h)";
        } elseif ($diffDays === 1) {
            $urgencyScore = 35;
            $reason = "⏳ Due tomorrow (" . ucfirst($priority) . " priority)";
        } elseif ($diffDays <= 3) {
            $urgencyScore = 25;
            $reason = "📅 Due in {$diffDays} days (" . ucfirst($priority) . " priority)";
        } elseif ($diffDays <= 7) {
            $urgencyScore = 15;
            $reason = "🗓️ Due this week (" . ucfirst($priority) . " priority)";
        } else {
            $urgencyScore = 5;
            $reason = "Upcoming deadline (" . ucfirst($priority) . " priority)";
        }

        // 3. Effort Factor: bonus for appropriately sized task chunks
        $effortFactor = round(5.0 / min(4.0, $estHours), 1);

        $totalScore = $priorityWeight + $urgencyScore + $effortFactor;

        $task['smart_score'] = $totalScore;
        $task['smart_reason'] = $reason;
        $scoredTasks[] = $task;
    }

    // Sort by smart_score descending
    usort($scoredTasks, function ($a, $b) {
        return $b['smart_score'] <=> $a['smart_score'];
    });

    // Fit into daily study budget
    $plannedTasks = [];
    $accumulatedHours = 0.0;

    foreach ($scoredTasks as $task) {
        $taskHours = (float)($task['estimated_hours'] ?? 1.0);
        if ($accumulatedHours + $taskHours <= $budgetHours || empty($plannedTasks)) {
            $accumulatedHours += $taskHours;
            $plannedTasks[] = $task;
        }
    }

    return [
        'planned_tasks' => $plannedTasks,
        'total_hours' => round($accumulatedHours, 1),
        'budget_hours' => $budgetHours,
        'total_pending' => count($scoredTasks)
    ];
}

/**
 * Calculate consecutive daily study streak in days.
 */
function get_user_study_streak(mysqli $conn, int $userId): int {
    $stmt = $conn->prepare("
        SELECT DISTINCT DATE(started_at) AS session_date
        FROM study_sessions
        WHERE user_id = ?
        ORDER BY session_date DESC
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();

    $dates = [];
    while ($row = $res->fetch_assoc()) {
        $dates[] = $row['session_date'];
    }
    $stmt->close();

    if (empty($dates)) {
        return 0;
    }

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // If latest session is neither today nor yesterday, streak is broken
    if ($dates[0] !== $today && $dates[0] !== $yesterday) {
        return 0;
    }

    $streak = 1;
    $currentDate = new DateTime($dates[0]);

    for ($i = 1; $i < count($dates); $i++) {
        $prevDate = new DateTime($dates[$i]);
        $diff = (int)$prevDate->diff($currentDate)->format("%a");

        if ($diff === 1) {
            $streak++;
            $currentDate = $prevDate;
        } else {
            break;
        }
    }

    return $streak;
}

/**
 * Get student's configured daily study budget hours (default 3.0).
 */
function get_student_study_budget(mysqli $conn, int $userId): float {
    $stmt = $conn->prepare("SELECT daily_study_hours FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $hours = 3.0;
    if ($row = $res->fetch_assoc()) {
        $hours = (float)($row['daily_study_hours'] ?? 3.0);
    }
    $stmt->close();
    return ($hours > 0) ? $hours : 3.0;
}
