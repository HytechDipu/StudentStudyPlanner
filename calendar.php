<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

// Handle complete action from calendar modal (POST + CSRF)
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
    header("Location: calendar.php");
    exit();
}

// Fetch all student tasks (personal + teacher assigned)
$allTasks = get_student_all_tasks($conn, $userId);

// Color palette generator for subjects
$colorPalette = [
    '#4f46e5', '#0284c7', '#0d9488', '#16a34a',
    '#ca8a04', '#ea580c', '#e11d48', '#9333ea',
    '#475569', '#2563eb'
];

$subjectColors = [];
$colorIndex = 0;

$calendarEvents = [];
foreach ($allTasks as $task) {
    $subj = $task['subject_name'];
    if (!isset($subjectColors[$subj])) {
        $subjectColors[$subj] = $colorPalette[$colorIndex % count($colorPalette)];
        $colorIndex++;
    }

    $baseColor = $subjectColors[$subj];
    if ($task['status'] === 'completed') {
        $bgColor = '#10b981'; // Green for completed
    } elseif ($task['is_overdue']) {
        $bgColor = '#ef4444'; // Red for overdue
    } else {
        $bgColor = $baseColor;
    }

    $calendarEvents[] = [
        'id' => (int)$task['id'],
        'title' => ($task['status'] === 'completed' ? '✓ ' : '') . $task['task_name'],
        'start' => $task['due_date'],
        'allDay' => true,
        'backgroundColor' => $bgColor,
        'borderColor' => $bgColor,
        'extendedProps' => [
            'task_id' => (int)$task['id'],
            'task_name' => $task['task_name'],
            'subject_name' => $task['subject_name'],
            'due_date' => $task['due_date'],
            'formatted_due' => format_date($task['due_date']),
            'priority' => $task['priority'],
            'estimated_hours' => $task['estimated_hours'],
            'status' => $task['status'],
            'is_overdue' => $task['is_overdue'],
            'source' => $task['source'],
            'teacher_name' => $task['teacher_name'],
            'can_edit' => $task['can_edit']
        ]
    ];
}

$pageTitle = "Calendar - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<!-- FullCalendar CDN -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<style>
    .calendar-wrapper {
        width: 90%;
        max-width: 1100px;
        margin: 35px auto;
    }
    .calendar-card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 8px 30px rgba(50, 50, 100, 0.08);
        border: 1px solid #e2e8f0;
    }
    .fc-theme-standard th {
        padding: 10px 0;
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
    }
    .fc-event {
        cursor: pointer;
        border-radius: 6px;
        padding: 3px 6px;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.15s;
    }
    .fc-event:hover {
        transform: scale(1.02);
    }
    .fc .fc-button-primary {
        background: #4f46e5;
        border-color: #4f46e5;
    }
    .fc .fc-button-primary:hover {
        background: #4338ca;
        border-color: #4338ca;
    }
    .fc .fc-button-primary:disabled {
        background: #a5b4fc;
        border-color: #a5b4fc;
    }
    .fc .fc-today-button {
        background: #6366f1;
        border-color: #6366f1;
    }

    /* Modal Styling */
    .task-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(15, 23, 42, 0.6);
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
    }
    .task-modal-content {
        background: white;
        border-radius: 16px;
        padding: 28px;
        width: 90%;
        max-width: 480px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        animation: modalFadeIn 0.2s ease-out;
    }
    @keyframes modalFadeIn {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .modal-detail-row {
        margin: 12px 0;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 14px;
        color: #475569;
    }
</style>

<div class="calendar-wrapper">

    <div class="subjects-header" style="text-align: left; margin-bottom: 25px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
            <div>
                <h1 style="font-size: 28px; margin-bottom: 6px;">📅 Study Calendar</h1>
                <p style="color: #64748b; font-size: 15px;">
                    Visual schedule of your coursework and deadlines. Click any task to review or complete it.
                </p>
            </div>
            <a href="add_task.php" class="quick-button">➕ Schedule Task</a>
        </div>
    </div>

    <!-- SUBJECT COLOR LEGEND -->
    <?php if (!empty($subjectColors)): ?>
        <div style="display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; background: white; padding: 14px 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
            <span style="font-size: 12px; font-weight: 700; color: #64748b; align-self: center;">SUBJECTS:</span>
            <?php foreach ($subjectColors as $subj => $clr): ?>
                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #334155;">
                    <span style="width: 12px; height: 12px; border-radius: 50%; background: <?php echo $clr; ?>;"></span>
                    <?php echo e($subj); ?>
                </span>
            <?php endforeach; ?>
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #16a34a; margin-left: auto;">
                <span style="width: 12px; height: 12px; border-radius: 50%; background: #10b981;"></span>
                Completed
            </span>
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; color: #dc2626;">
                <span style="width: 12px; height: 12px; border-radius: 50%; background: #ef4444;"></span>
                Overdue
            </span>
        </div>
    <?php endif; ?>

    <!-- CALENDAR CONTAINER -->
    <div class="calendar-card">
        <div id="calendar"></div>
    </div>

</div>

<!-- TASK DETAILS MODAL -->
<div id="taskModal" class="task-modal">
    <div class="task-modal-content">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
            <h2 id="modalTaskName" style="font-size: 20px; color: #1e1b4b; margin: 0;"></h2>
            <button onclick="closeModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>

        <div class="modal-detail-row">
            <span>📚</span>
            <strong>Subject:</strong>
            <span id="modalSubject"></span>
        </div>

        <div class="modal-detail-row">
            <span>📅</span>
            <strong>Deadline:</strong>
            <span id="modalDueDate"></span>
        </div>

        <div class="modal-detail-row">
            <span>⚡</span>
            <strong>Priority:</strong>
            <span id="modalPriority"></span>
        </div>

        <div class="modal-detail-row">
            <span>⏱️</span>
            <strong>Estimated Effort:</strong>
            <span id="modalHours"></span>
        </div>

        <div class="modal-detail-row" id="modalTeacherRow" style="display: none;">
            <span>👩‍🏫</span>
            <strong>Assigned By:</strong>
            <span id="modalTeacher"></span>
        </div>

        <div class="modal-detail-row">
            <span>📌</span>
            <strong>Status:</strong>
            <span id="modalStatusBadge"></span>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px; justify-content: flex-end;">
            <div id="modalCompleteFormContainer"></div>
            <a id="modalEditBtn" href="#" class="edit-button" style="display: none; padding: 9px 16px;">✏️ Edit</a>
            <button onclick="closeModal()" class="delete-button" style="padding: 9px 16px; background: #f1f5f9; color: #475569;">Close</button>
        </div>
    </div>
</div>

<script>
const eventsData = <?php echo json_encode($calendarEvents); ?>;
const csrfFieldHtml = '<?php echo addslashes(csrf_field()); ?>';

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listMonth'
        },
        events: eventsData,
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            const props = info.event.extendedProps;

            document.getElementById('modalTaskName').textContent = props.task_name;
            document.getElementById('modalSubject').textContent = props.subject_name;
            document.getElementById('modalDueDate').textContent = props.formatted_due;
            document.getElementById('modalHours').textContent = props.estimated_hours + ' hour(s)';

            // Priority badge
            let priorityBadgeHtml = '<span class="priority-badge priority-medium">⚡ Medium</span>';
            if (props.priority === 'high') {
                priorityBadgeHtml = '<span class="priority-badge priority-high">🔥 High</span>';
            } else if (props.priority === 'low') {
                priorityBadgeHtml = '<span class="priority-badge priority-low">🌱 Low</span>';
            }
            document.getElementById('modalPriority').innerHTML = priorityBadgeHtml;

            // Teacher info
            const teacherRow = document.getElementById('modalTeacherRow');
            if (props.source === 'teacher' && props.teacher_name) {
                document.getElementById('modalTeacher').textContent = props.teacher_name;
                teacherRow.style.display = 'flex';
            } else {
                teacherRow.style.display = 'none';
            }

            // Status badge
            let statusHtml = '';
            if (props.status === 'completed') {
                statusHtml = '<span class="task-completed">✅ Completed</span>';
            } else if (props.is_overdue) {
                statusHtml = '<span class="task-overdue">⚠️ Overdue</span>';
            } else {
                statusHtml = '<span class="task-pending">⏳ Pending</span>';
            }
            document.getElementById('modalStatusBadge').innerHTML = statusHtml;

            // Complete form button
            const completeContainer = document.getElementById('modalCompleteFormContainer');
            if (props.status !== 'completed') {
                completeContainer.innerHTML = `
                    <form method="POST" action="calendar.php" class="inline-form">
                        ${csrfFieldHtml}
                        <input type="hidden" name="action" value="complete_task">
                        <input type="hidden" name="task_id" value="${props.task_id}">
                        <input type="hidden" name="source" value="${props.source}">
                        <button type="submit" class="quick-button" style="padding: 9px 18px;">✓ Complete</button>
                    </form>
                `;
            } else {
                completeContainer.innerHTML = '';
            }

            // Edit button (personal tasks only)
            const editBtn = document.getElementById('modalEditBtn');
            if (props.can_edit) {
                editBtn.href = 'edit_task.php?id=' + props.task_id;
                editBtn.style.display = 'inline-block';
            } else {
                editBtn.style.display = 'none';
            }

            // Open modal
            document.getElementById('taskModal').style.display = 'flex';
        }
    });

    calendar.render();
});

function closeModal() {
    document.getElementById('taskModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('taskModal');
    if (event.target === modal) {
        closeModal();
    }
};
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
