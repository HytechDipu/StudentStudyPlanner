<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/functions.php';

$userId = require_student_login();

// Handle logging a completed study session (POST + CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'log_session') {
    validate_csrf_or_die();

    $subjectId = (int)($_POST['subject_id'] ?? 0);
    $minutes = (int)($_POST['minutes'] ?? 25);

    if ($minutes > 0) {
        // If subject is selected, verify it belongs to user
        $subParam = null;
        if ($subjectId > 0) {
            $checkSub = $conn->prepare("SELECT id FROM subjects WHERE id = ? AND user_id = ? LIMIT 1");
            $checkSub->bind_param("ii", $subjectId, $userId);
            $checkSub->execute();
            if ($checkSub->get_result()->num_rows > 0) {
                $subParam = $subjectId;
            }
            $checkSub->close();
        }

        $logStmt = $conn->prepare("INSERT INTO study_sessions (user_id, subject_id, minutes, started_at) VALUES (?, ?, ?, NOW())");
        $logStmt->bind_param("iii", $userId, $subParam, $minutes);
        $logStmt->execute();
        $logStmt->close();

        set_flash('success', "Great job! Logged {$minutes} minutes of focused study.");
    }
    header("Location: study.php");
    exit();
}

// Fetch user subjects for dropdown and analytics
$subStmt = $conn->prepare("SELECT id, subject_name FROM subjects WHERE user_id = ? ORDER BY subject_name ASC");
$subStmt->bind_param("i", $userId);
$subStmt->execute();
$subjectsRes = $subStmt->get_result();
$subjects = [];
while ($row = $subjectsRes->fetch_assoc()) {
    $subjects[] = $row;
}
$subStmt->close();

// Fetch study streak
$streak = get_user_study_streak($conn, $userId);

// Fetch total hours studied
$totalStmt = $conn->prepare("SELECT COALESCE(SUM(minutes), 0) AS total_min FROM study_sessions WHERE user_id = ?");
$totalStmt->bind_param("i", $userId);
$totalStmt->execute();
$totalMinutes = (int)$totalStmt->get_result()->fetch_assoc()['total_min'];
$totalHours = round($totalMinutes / 60, 1);
$totalStmt->close();

// Fetch hours studied per subject
$subjHoursStmt = $conn->prepare("
    SELECT COALESCE(s.subject_name, 'General Study') AS subject_name,
           ROUND(SUM(ss.minutes) / 60, 1) AS hours,
           SUM(ss.minutes) AS total_mins
    FROM study_sessions ss
    LEFT JOIN subjects s ON ss.subject_id = s.id
    WHERE ss.user_id = ?
    GROUP BY ss.subject_id, s.subject_name
    ORDER BY hours DESC
");
$subjHoursStmt->bind_param("i", $userId);
$subjHoursStmt->execute();
$subjectHoursRes = $subjHoursStmt->get_result();
$subjectHours = [];
while ($row = $subjectHoursRes->fetch_assoc()) {
    $subjectHours[] = $row;
}
$subjHoursStmt->close();

// Fetch daily study time over the last 7 days for Chart.js
$weeklyLabels = [];
$weeklyHours = [];
for ($i = 6; $i >= 0; $i--) {
    $dayDate = date('Y-m-d', strtotime("-{$i} days"));
    $weeklyLabels[] = date('D (M j)', strtotime($dayDate));

    $dayStmt = $conn->prepare("SELECT COALESCE(SUM(minutes), 0) AS mins FROM study_sessions WHERE user_id = ? AND DATE(started_at) = ?");
    $dayStmt->bind_param("is", $userId, $dayDate);
    $dayStmt->execute();
    $dayMins = (int)$dayStmt->get_result()->fetch_assoc()['mins'];
    $dayStmt->close();

    $weeklyHours[] = round($dayMins / 60, 2);
}

$pageTitle = "Study Timer - StudyPlanner";
include __DIR__ . '/includes/header.php';
?>

<div style="width: 90%; max-width: 1100px; margin: 35px auto;">

    <!-- HEADER & STREAK -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px;">
        <div>
            <h1 style="font-size: 28px; margin-bottom: 6px;">⏱️ Pomodoro Study Timer</h1>
            <p style="color: #64748b; font-size: 15px;">Focus in intervals, build daily study momentum, and log your hours.</p>
        </div>

        <!-- STREAK WIDGET -->
        <div class="streak-card" style="margin-bottom: 0;">
            <div class="streak-flame">🔥</div>
            <div>
                <div style="font-size: 26px; font-weight: 800; color: #ea580c; line-height: 1;"><?php echo $streak; ?> Day Streak</div>
                <div style="font-size: 12px; color: #9a3412; font-weight: 600; margin-top: 3px;">
                    <?php echo $streak > 0 ? "You're on fire! Keep it going today." : "Complete a session today to start your streak!"; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN TIMER SECTION -->
    <div class="timer-box">
        <!-- MODE SELECTOR -->
        <div style="display: flex; justify-content: center; gap: 8px; margin-bottom: 20px;">
            <button type="button" class="page-btn active" id="modePomodoro" onclick="setTimerMode('pomodoro')">🍅 Pomodoro (25m)</button>
            <button type="button" class="page-btn" id="modeShortBreak" onclick="setTimerMode('short')">☕ Short Break (5m)</button>
            <button type="button" class="page-btn" id="modeLongBreak" onclick="setTimerMode('long')">🌴 Long Break (15m)</button>
        </div>

        <!-- SUBJECT SELECTION -->
        <div style="max-width: 320px; margin: 0 auto 15px auto;">
            <label for="studySubjectSelect" style="display: block; font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px;">
                Studying Subject:
            </label>
            <select id="studySubjectSelect" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px;">
                <option value="0">General Study / No Subject</option>
                <?php foreach ($subjects as $s): ?>
                    <option value="<?php echo (int)$s['id']; ?>"><?php echo e($s['subject_name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- TIMER COUNTDOWN DISPLAY -->
        <div class="timer-display" id="timeDisplay">25:00</div>

        <!-- CONTROLS -->
        <div class="timer-controls">
            <button type="button" class="timer-btn timer-btn-primary" id="startBtn" onclick="toggleTimer()">▶ Start</button>
            <button type="button" class="timer-btn timer-btn-secondary" onclick="resetTimer()">↺ Reset</button>
            <button type="button" class="timer-btn" style="background: #10b981; color: white;" onclick="manualLogSession()">💾 Log Session</button>
        </div>

        <!-- HIDDEN LOGGING FORM -->
        <form id="logSessionForm" method="POST" action="study.php" style="display: none;">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="log_session">
            <input type="hidden" name="subject_id" id="formSubjectId" value="0">
            <input type="hidden" name="minutes" id="formMinutes" value="25">
        </form>
    </div>

    <!-- ANALYTICS GRID -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;">

        <!-- SUBJECT BREAKDOWN -->
        <div style="background: white; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px;">
                <h2 style="font-size: 18px; color: #1e1b4b; margin: 0;">📚 Hours Studied by Subject</h2>
                <span style="font-size: 14px; font-weight: 700; color: #4f46e5;">Total: <?php echo $totalHours; ?> hrs</span>
            </div>

            <?php if (!empty($subjectHours)): ?>
                <?php foreach ($subjectHours as $sh):
                    $barWidth = ($totalHours > 0) ? min(100, round(($sh['hours'] / $totalHours) * 100)) : 0;
                ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 5px;">
                            <span style="font-weight: 600; color: #334155;"><?php echo e($sh['subject_name']); ?></span>
                            <span style="color: #64748b; font-weight: 600;"><?php echo $sh['hours']; ?>h (<?php echo $sh['total_mins']; ?>m)</span>
                        </div>
                        <div style="height: 8px; background: #f1f5f9; border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; width: <?php echo $barWidth; ?>%; background: #6366f1; border-radius: 4px;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-subjects" style="padding: 25px;">
                    <div>⏱️</div>
                    <p>No study sessions recorded yet. Start the timer to log your hours!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- WEEKLY PROGRESS CHART -->
        <div style="background: white; border-radius: 16px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 15px rgba(0,0,0,0.03);">
            <h2 style="font-size: 18px; color: #1e1b4b; margin: 0 0 15px 0;">📊 Study Hours Past 7 Days</h2>
            <div style="height: 240px; position: relative;">
                <canvas id="studyWeeklyChart"></canvas>
            </div>
        </div>

    </div>

</div>

<script>
// Pomodoro Timer Logic
let timerModes = {
    pomodoro: 25 * 60,
    short: 5 * 60,
    long: 15 * 60
};
let currentMode = 'pomodoro';
let timeLeft = timerModes.pomodoro;
let isRunning = false;
let timerInterval = null;

const timeDisplay = document.getElementById('timeDisplay');
const startBtn = document.getElementById('startBtn');

function updateDisplay() {
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    timeDisplay.textContent = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
}

function setTimerMode(mode) {
    if (isRunning) pauseTimer();
    currentMode = mode;
    timeLeft = timerModes[mode];
    updateDisplay();

    document.querySelectorAll('.timer-box .page-btn').forEach(btn => btn.classList.remove('active'));
    if (mode === 'pomodoro') document.getElementById('modePomodoro').classList.add('active');
    else if (mode === 'short') document.getElementById('modeShortBreak').classList.add('active');
    else if (mode === 'long') document.getElementById('modeLongBreak').classList.add('active');
}

function playCompletionBeep() {
    try {
        const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.type = 'sine';
        osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
        osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.15); // A5
        gain.gain.setValueAtTime(0.3, audioCtx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.6);
        osc.start();
        osc.stop(audioCtx.currentTime + 0.6);
    } catch (e) {
        console.log('Audio feedback:', e);
    }
}

function toggleTimer() {
    if (isRunning) {
        pauseTimer();
    } else {
        startTimer();
    }
}

function startTimer() {
    isRunning = true;
    startBtn.textContent = '⏸ Pause';
    startBtn.style.background = '#eab308';

    timerInterval = setInterval(() => {
        if (timeLeft > 0) {
            timeLeft--;
            updateDisplay();
        } else {
            clearInterval(timerInterval);
            isRunning = false;
            startBtn.textContent = '▶ Start';
            startBtn.style.background = '#4f46e5';
            playCompletionBeep();

            if (currentMode === 'pomodoro') {
                if (confirm('🎉 Pomodoro session finished! Log this 25-minute study session?')) {
                    document.getElementById('formSubjectId').value = document.getElementById('studySubjectSelect').value;
                    document.getElementById('formMinutes').value = 25;
                    document.getElementById('logSessionForm').submit();
                }
            } else {
                alert('Break is over! Ready to focus again?');
                setTimerMode('pomodoro');
            }
        }
    }, 1000);
}

function pauseTimer() {
    isRunning = false;
    clearInterval(timerInterval);
    startBtn.textContent = '▶ Start';
    startBtn.style.background = '#4f46e5';
}

function resetTimer() {
    pauseTimer();
    timeLeft = timerModes[currentMode];
    updateDisplay();
}

function manualLogSession() {
    const defaultMins = Math.round((timerModes[currentMode] - timeLeft) / 60) || (currentMode === 'pomodoro' ? 25 : 5);
    const loggedMins = prompt('Log how many minutes studied?', defaultMins);
    if (loggedMins && !isNaN(loggedMins) && parseInt(loggedMins) > 0) {
        document.getElementById('formSubjectId').value = document.getElementById('studySubjectSelect').value;
        document.getElementById('formMinutes').value = parseInt(loggedMins);
        document.getElementById('logSessionForm').submit();
    }
}

// Chart.js Weekly Study Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('studyWeeklyChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklyLabels); ?>,
            datasets: [{
                label: 'Hours Studied',
                data: <?php echo json_encode($weeklyHours); ?>,
                backgroundColor: '#4f46e5',
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'Hours' } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
