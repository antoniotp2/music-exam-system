<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$totalQuestions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
$totalExams = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$totalAttempts = $pdo->query("SELECT COUNT(*) FROM attempts")->fetchColumn();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <h1>Admin Dashboard</h1>
    <?php displayFlash(); ?>

    <div class="stats-grid">
        <div class="card stat-card">
            <h3><?= $totalQuestions ?></h3>
            <p>Questions</p>
        </div>
        <div class="card stat-card">
            <h3><?= $totalExams ?></h3>
            <p>Exams</p>
        </div>
        <div class="card stat-card">
            <h3><?= $totalStudents ?></h3>
            <p>Students</p>
        </div>
        <div class="card stat-card">
            <h3><?= $totalAttempts ?></h3>
            <p>Attempts</p>
        </div>
    </div>

    <div class="card">
        <h2>Quick Actions</h2>
        <div class="actions-row">
            <a class="btn btn-primary" href="add_question.php">Add Question</a>
            <a class="btn btn-secondary" href="manage_questions.php">Manage Questions</a>
            <a class="btn btn-secondary" href="create_exam.php">Create Exam</a>
            <a class="btn btn-secondary" href="manage_exams.php">Manage Exams</a>
            <a class="btn btn-secondary" href="results.php">View Results</a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>