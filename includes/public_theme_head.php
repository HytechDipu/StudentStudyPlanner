<?php // Dark-mode support for pages that do not use includes/header.php (home, login, register) ?>
    <link rel="stylesheet" href="css/theme.css?v=2">
    <script>
        (function () {
            var saved = null;
            try { saved = localStorage.getItem('studyplanner_theme'); } catch (e) {}
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var dark = saved ? saved === 'dark' : prefersDark;
            if (dark) document.documentElement.classList.add('dark-mode');
        })();
    </script>
