# 📚 Student Study Planner

A modern PHP 8.2 + MySQL (MariaDB 10.4) web application designed for students and educators running on XAMPP (Apache + phpMyAdmin). Built with pure Vanilla PHP, mysqli prepared statements, native CSS styling, Chart.js, FullCalendar, and zero framework dependencies.

---

## 🚀 Setup Instructions (XAMPP)

### Prerequisites
- **XAMPP** (Apache + MySQL / MariaDB 10.4+, PHP 8.2+)
- Web browser

### 1. Place the Project in XAMPP
Place this directory inside your XAMPP `htdocs` folder:
- **macOS / Linux**: `/Applications/XAMPP/xamppfiles/htdocs/StudentStudyPlanner`
- **Windows**: `C:\xampp\htdocs\StudentStudyPlanner`

### 2. Start Apache & MySQL
Open the **XAMPP Control Panel** and start:
1. **Apache**
2. **MySQL**

### 3. Database Installation

#### Option A: Fresh Clean Install
1. Open [phpMyAdmin](http://localhost/phpmyadmin/).
2. Click **Import** tab.
3. Select `database/schema.sql` and click **Go**.
   - This creates `student_study_planner` with foreign keys, indexes, priority, study sessions, and throttling columns.

#### Option B: Existing Database Migration
If you already imported the legacy database:
1. Open [phpMyAdmin](http://localhost/phpmyadmin/) and select the `student_study_planner` database.
2. Run `database/migrations/001_phase1_hardening.sql`.
   - Cleans duplicate subjects, creates `teacher_courses`, normalizes status, adds throttling and foreign keys.
3. Run `database/migrations/002_phase2_student_features.sql`.
   - Adds `priority` and `estimated_hours` to `tasks` and `teacher_tasks`.
   - Adds `daily_study_hours` to `users`.
   - Creates `study_sessions` table for Pomodoro study logging.

---

## 🌟 Feature Overview

### Phase 1: Hardening & Clean Foundation
- **Session Security & Guards**: Secure session cookies (`HttpOnly`, `SameSite=Lax`), `session_regenerate_id(true)` on login, and strict role guards (`require_student_login()`, `require_teacher_login()`).
- **CSRF Defense**: All state changes (`complete`, `delete`, task assignment, profile edits) use POST forms with cryptographically secure CSRF tokens.
- **Login Throttling & Security**: 5 failed login attempts lock account for 15 minutes. 8+ char password requirement on registration. `password_needs_rehash` evaluation.
- **Bug Fixes**:
  - Overdue tasks display both the `⚠️ Overdue` badge and `✓ Complete` button.
  - Subject deletion strictly counts only pending tasks before deletion.
- **Teacher Courses**: `teacher_courses` ensures teachers can only assign tasks to courses they actively teach.

### Phase 2: Student Productivity & Study Intelligence
- **Task Priority & Effort**: Colored badges for `🔥 High`, `⚡ Medium`, and `🌱 Low` priority, plus estimated effort in hours (`⏱️ 1.5h`) on tasks and teacher assignments.
- **Search, Filter, Sort & Pagination**: Full-featured task toolbar filtering by search query, subject, priority, status (including overdue), date range, sort order, and pagination.
- **Dashboard Analytics with Chart.js**: Live completion % per subject, weekly tasks completed (last 8 weeks), and pending/overdue/completed breakdown served by `api/stats.php`.
- **FullCalendar View (`calendar.php`)**: Interactive monthly/weekly study calendar color-coded by subject with modal click-to-complete actions.
- **Pomodoro Study Timer (`study.php`)**: 25/5/15 Pomodoro timer with subject selection, audio completion tone, session logging to `study_sessions`, hours-per-subject breakdown, 7-day chart, and daily study streak.
- **Smart Daily Plan**: Algorithmic task ranking (`score = priority + urgency + effort`) fitting pending tasks into the student's configurable daily study budget (default 3.0h) with one-line human explanations.
- **Profile & Settings (`profile.php`)**: Update name, change password with current password verification, adjust daily study budget, and toggle dark mode (persisted in `localStorage`).

---

## 🗺️ Pages & Routes Directory

| Route / File | Role | Method | Description |
| :--- | :--- | :--- | :--- |
| `index.php` | Public | GET | Application landing page with student and educator role selector |
| `login.php` | Public (Student) | GET / POST | Student login with CSRF, throttling, and password reveal toggle |
| `register.php` | Public (Student) | GET / POST | Student registration with course selection and 8+ char password rules |
| `logout.php` | Student | GET | Secure student logout with cookie destruction |
| `dashboard.php` | Student | GET / POST | Student dashboard with Smart Daily Plan, Chart.js analytics, today's schedule, and completion actions |
| `tasks.php` | Student | GET / POST | Unified task manager with search, filter (subject, priority, status, date range), sort, pagination, and edit/delete |
| `calendar.php` | Student | GET / POST | Interactive FullCalendar view with subject color coding and click-to-complete modal |
| `study.php` | Student | GET / POST | Pomodoro timer (25/5/15), session logging to `study_sessions`, streak counter, and weekly chart |
| `profile.php` | Student | GET / POST | Profile settings: edit name, change password (verify current), set study budget, and dark mode toggle |
| `subjects.php` | Student | GET / POST | Enrolled subjects, pending task checks, and subject creation/deletion |
| `add_task.php` | Student | GET / POST | Add new personal study task with priority, estimated hours, and subject ownership validation |
| `edit_task.php` | Student | GET / POST | Edit personal task details, priority, estimated hours, and status |
| `api/stats.php` | Student | GET | JSON analytics endpoint for Chart.js dashboard charts |
| `teacher_login.php` | Public (Teacher) | GET / POST | Educator portal login with CSRF, throttling, and secure sessions |
| `teacher_register.php` | Public (Teacher) | GET / POST | Educator account registration |
| `teacher_logout.php` | Teacher | GET | Educator session termination |
| `teacher_dashboard.php` | Teacher | GET | Educator analytics, student completion metrics, and recent assignments |
| `courses.php` | Teacher | GET / POST | Manage courses taught by teacher (`teacher_courses`) and create courses |
| `teacher_assign_task.php` | Teacher | GET / POST | Assign coursework with priority & effort to all students in an authorized course |
| `teacher_students.php` | Teacher | GET | Student directory with task completion and overdue statistics |

---

## 📂 Project Directory Structure

```text
StudentStudyPlanner/
├── api/
│   └── stats.php                   # JSON endpoint for dashboard Chart.js statistics
├── config/
│   └── database.php                # mysqli connection with utf8mb4 & exception mode
├── css/
│   └── style.css                   # Purple/indigo theme, dark mode, badges, and components
├── database/
│   ├── studentplanner_sql.sql      # Original database dump (unmodified source of truth)
│   ├── schema.sql                  # Fresh install clean schema (student_study_planner)
│   └── migrations/
│       ├── 001_phase1_hardening.sql # Phase 1 hardening migration
│       └── 002_phase2_student_features.sql # Phase 2 student features migration
├── includes/
│   ├── auth.php                    # Session security, role guards, throttling, rehash
│   ├── csrf.php                    # CSRF token generation and validation helpers
│   ├── functions.php               # Escape helper e(), smart daily plan, streak, task engine
│   ├── header.php                  # Global HTML head, navbar, and dark theme initializer
│   └── footer.php                  # Global HTML footer
├── add_task.php
├── calendar.php                    # FullCalendar study calendar
├── courses.php
├── dashboard.php                   # Smart Plan & Chart.js dashboard
├── edit_task.php
├── index.php
├── login.php
├── logout.php
├── profile.php                     # Profile, password change & dark mode
├── register.php
├── study.php                       # Pomodoro timer & study analytics
├── subjects.php
├── tasks.php                       # Search, filter, sort & pagination
├── teacher_assign_task.php
├── teacher_dashboard.php
├── teacher_login.php
├── teacher_logout.php
├── teacher_register.php
├── teacher_students.php
└── README.md
```
