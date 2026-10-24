<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

$userId = get_student_id();
if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

try {
    // 1. Completion % per subject (personal + teacher tasks)
    $subStmt = $conn->prepare("
        SELECT s.id, s.subject_name,
               (
                   (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id AND t.user_id = s.user_id)
                   +
                   (SELECT COUNT(*) FROM teacher_tasks tt WHERE tt.subject_id = s.id AND tt.student_id = s.user_id)
               ) AS total_tasks,
               (
                   (SELECT COUNT(*) FROM tasks t WHERE t.subject_id = s.id AND t.user_id = s.user_id AND t.status = 'completed')
                   +
                   (SELECT COUNT(*) FROM teacher_tasks tt WHERE tt.subject_id = s.id AND tt.student_id = s.user_id AND tt.status = 'completed')
               ) AS completed_tasks
        FROM subjects s
        WHERE s.user_id = ?
        ORDER BY s.subject_name ASC
    ");
    $subStmt->bind_param("i", $userId);
    $subStmt->execute();
    $subRes = $subStmt->get_result();

    $subjectLabels = [];
    $subjectPercentages = [];
    $subjectTotals = [];
    $subjectCompleted = [];

    while ($row = $subRes->fetch_assoc()) {
        $total = (int)$row['total_tasks'];
        $completed = (int)$row['completed_tasks'];
        $pct = ($total > 0) ? round(($completed / $total) * 100) : 0;

        $subjectLabels[] = $row['subject_name'];
        $subjectPercentages[] = $pct;
        $subjectTotals[] = $total;
        $subjectCompleted[] = $completed;
    }
    $subStmt->close();

    // 2. Tasks completed per week (last 8 weeks)
    // Build 8 weekly intervals ending today
    $weeklyLabels = [];
    $weeklyCompleted = [];

    for ($i = 7; $i >= 0; $i--) {
        $startOfWeek = date('Y-m-d 00:00:00', strtotime("-{$i} week monday this week"));
        $endOfWeek = date('Y-m-d 23:59:59', strtotime("-{$i} week sunday this week"));
        $label = date('M j', strtotime($startOfWeek));
        $weeklyLabels[] = $label;

        // Count personal completed
        $wkStmt1 = $conn->prepare("SELECT COUNT(*) AS total FROM tasks WHERE user_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?");
        $wkStmt1->bind_param("iss", $userId, $startOfWeek, $endOfWeek);
        $wkStmt1->execute();
        $cnt1 = (int)$wkStmt1->get_result()->fetch_assoc()['total'];
        $wkStmt1->close();

        // Count teacher completed
        $wkStmt2 = $conn->prepare("SELECT COUNT(*) AS total FROM teacher_tasks WHERE student_id = ? AND status = 'completed' AND created_at BETWEEN ? AND ?");
        $wkStmt2->bind_param("iss", $userId, $startOfWeek, $endOfWeek);
        $wkStmt2->execute();
        $cnt2 = (int)$wkStmt2->get_result()->fetch_assoc()['total'];
        $wkStmt2->close();

        $weeklyCompleted[] = $cnt1 + $cnt2;
    }

    // 3. Pending vs Overdue vs Completed counts
    // Personal tasks
    $statStmt1 = $conn->prepare("
        SELECT
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'pending' AND due_date >= CURDATE() THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM tasks
        WHERE user_id = ?
    ");
    $statStmt1->bind_param("i", $userId);
    $statStmt1->execute();
    $r1 = $statStmt1->get_result()->fetch_assoc();
    $statStmt1->close();

    // Teacher tasks
    $statStmt2 = $conn->prepare("
        SELECT
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN status = 'pending' AND due_date >= CURDATE() THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = 'pending' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue
        FROM teacher_tasks
        WHERE student_id = ?
    ");
    $statStmt2->bind_param("i", $userId);
    $statStmt2->execute();
    $r2 = $statStmt2->get_result()->fetch_assoc();
    $statStmt2->close();

    $completedCount = (int)($r1['completed'] ?? 0) + (int)($r2['completed'] ?? 0);
    $pendingCount = (int)($r1['pending'] ?? 0) + (int)($r2['pending'] ?? 0);
    $overdueCount = (int)($r1['overdue'] ?? 0) + (int)($r2['overdue'] ?? 0);

    echo json_encode([
        'subjects' => [
            'labels' => $subjectLabels,
            'percentages' => $subjectPercentages,
            'totals' => $subjectTotals,
            'completed' => $subjectCompleted
        ],
        'weekly' => [
            'labels' => $weeklyLabels,
            'completed' => $weeklyCompleted
        ],
        'status_breakdown' => [
            'labels' => ['Pending', 'Overdue', 'Completed'],
            'data' => [$pendingCount, $overdueCount, $completedCount]
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
