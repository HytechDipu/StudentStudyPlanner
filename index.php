<?php
require_once __DIR__ . '/includes/auth.php';
if (get_student_id()) {
    header("Location: dashboard.php");
    exit();
}
if (get_teacher_id()) {
    header("Location: teacher_dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Study Planner</title>

    <link rel="stylesheet" href="css/style.css?v=10">

    <style>

        /* =========================
           HOME PAGE
        ========================= */

        body {
            background: #f7f8ff;
        }


        /* NAVBAR */

        .home-navbar {
            background: white;
            border-bottom: 1px solid #eeeeee;
            padding: 16px 7%;
            display: flex;
            align-items: center;
        }

        .home-logo {
            font-size: 25px;
            font-weight: 700;
            color: #4f46e5;
            display: flex;
            align-items: center;
            gap: 8px;
        }


        /* MAIN SECTION */

        .role-section {
            min-height: calc(100vh - 150px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 55px 20px 65px;
            position: relative;
            overflow: hidden;
        }


        .role-container {
            width: 100%;
            max-width: 900px;
            text-align: center;
            position: relative;
            z-index: 2;
        }


        /* BADGE */

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 17px;
            background: #eeeaff;
            color: #4f46e5;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }


        /* HEADING */

        .role-container h1 {
            font-size: 42px;
            line-height: 1.2;
            margin: 0 0 14px;
            color: #171717;
        }

        .role-container h1 span {
            color: #4f46e5;
        }


        .role-subtitle {
            max-width: 600px;
            margin: 0 auto 12px;
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }


        .choose-text {
            color: #4f46e5;
            font-size: 14px;
            font-weight: 600;
            margin: 25px 0 25px;
        }


        /* ROLE CARDS */

        .role-cards {
            display: flex;
            justify-content: center;
            gap: 28px;
            flex-wrap: wrap;
        }


        .role-card {
            width: 310px;
            min-height: 315px;
            padding: 35px 28px 30px;
            background: white;
            border: 1px solid #e4e5ef;
            border-radius: 18px;
            box-shadow: 0 8px 25px rgba(50, 50, 100, 0.08);
            text-decoration: none;
            color: #222;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }


        /* TOP LINE */

        .role-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: #4f46e5;
        }

        .teacher-card::before {
            background: #f59e0b;
        }


        /* CARD HOVER */

        .role-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(50, 50, 100, 0.14);
            border-color: #d8d5ff;
        }


        /* ICON */

        .role-icon {
            width: 72px;
            height: 72px;
            margin: 5px auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eeedff;
            border-radius: 18px;
            font-size: 36px;
            transition: 0.25s;
        }

        .role-card:hover .role-icon {
            transform: scale(1.06);
        }

        .teacher-card .role-icon {
            background: #fff3dc;
        }


        /* CARD TEXT */

        .role-card h2 {
            margin: 0 0 10px;
            font-size: 24px;
            color: #222;
        }

        .role-card p {
            color: #777;
            font-size: 14px;
            line-height: 1.6;
            margin: 0 auto 24px;
            max-width: 250px;
        }


        /* BUTTON */

        .role-button {
            display: inline-block;
            padding: 11px 24px;
            background: #4f46e5;
            color: white;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            transition: 0.2s;
        }

        .teacher-card .role-button {
            background: #f59e0b;
        }

        .role-card:hover .role-button {
            transform: translateX(2px);
        }


        /* FOOTER */

        .home-footer {
            background: #111827;
            color: #cbd5e1;
            text-align: center;
            padding: 20px;
            font-size: 14px;
        }

        .home-footer p {
            margin: 0;
        }


        /* MOBILE */

        @media (max-width: 700px) {

            .role-section {
                padding: 45px 15px;
            }

            .role-container h1 {
                font-size: 32px;
            }

            .role-subtitle {
                font-size: 14px;
            }

            .role-card {
                width: 90%;
                max-width: 330px;
            }

            .home-logo {
                font-size: 22px;
            }

        }

    </style>

<?php include __DIR__ . '/includes/public_theme_head.php'; ?>
</head>


<body>
<?php include __DIR__ . '/includes/public_theme_toggle.php'; ?>


    <!-- NAVBAR -->

    <nav class="home-navbar">

        <div class="home-logo">
            📚 StudyPlanner
        </div>

    </nav>


    <!-- ROLE SECTION -->

    <section class="role-section">


        <div class="role-container">


            <!-- BADGE -->

            <div class="role-badge">
                ✨ Smart Study Management
            </div>


            <!-- HEADING -->

            <h1>
                Welcome to <span>StudyPlanner</span>
            </h1>


            <p class="role-subtitle">
                A simple platform to manage studies, tasks,
                assignments and academic progress.
            </p>


            <p class="choose-text">
                Choose how you want to continue
            </p>


            <!-- ROLE CARDS -->

            <div class="role-cards">


                <!-- STUDENT CARD -->

                <a href="login.php" class="role-card">

                    <div class="role-icon">
                        👨‍🎓
                    </div>

                    <h2>
                        Student
                    </h2>

                    <p>
                        Login to manage your subjects,
                        study tasks and track your progress.
                    </p>

                    <span class="role-button">
                        Student Login →
                    </span>

                </a>


                <!-- TEACHER CARD -->

                <a href="teacher_login.php" class="role-card teacher-card">

                    <div class="role-icon">
                        👩‍🏫
                    </div>

                    <h2>
                        Teacher
                    </h2>

                    <p>
                        Login to manage students,
                        subjects and course assignments.
                    </p>

                    <span class="role-button">
                        Teacher Login →
                    </span>

                </a>


            </div>

        </div>

    </section>


    <!-- FOOTER -->

    <footer class="home-footer">

        <p>
            © 2026 Student Study Planner | Designed for Students
        </p>

    </footer>


</body>

</html>