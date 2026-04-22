<?php if (session_status() === PHP_SESSION_NONE) session_start(); ?>

<nav class="navbar">
    <div class="container nav-inner">
        <a href="/music-exam-system-main/index.php" class="logo">Music Exam System</a>

        <?php if (!empty($_SESSION['user'])): ?>
            <div class="nav-links">
                <?php if ($_SESSION['user']['role'] === 'admin'): ?>
                    <a href="/music-exam-system-main/admin/dashboard.php">Admin Dashboard</a>
                    <a href="/music-exam-system-main/admin/add_question.php">Add Question</a>
                    <a href="/music-exam-system-main/admin/create_exam.php">Create Exam</a>
                    <a href="/music-exam-system-main/admin/results.php">Results</a>
                <?php else: ?>
                    <a href="/music-exam-system-main/student/dashboard.php">Student Dashboard</a>
                <?php endif; ?>

                <span class="nav-user">
                    <?= htmlspecialchars($_SESSION['user']['name']) ?> (<?= htmlspecialchars($_SESSION['user']['role']) ?>)
                </span>
                <a href="/music-exam-system-main/logout.php" class="btn btn-danger">Logout</a>
            </div>
        <?php endif; ?>
    </div>
</nav>