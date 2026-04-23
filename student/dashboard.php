<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireStudent();

$stmt = $pdo->query("
    SELECT *
    FROM exams
    WHERE is_visible = 1
      AND (available_from IS NULL OR available_from <= NOW())
      AND (available_until IS NULL OR available_until >= NOW())
    ORDER BY id DESC
");
$exams = $stmt->fetchAll();


?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <h1>Student Dashboard</h1>
    <?php displayFlash(); ?>

    <div class="card">
        <h2>Available Exams</h2>

        <?php if (empty($exams)): ?>
            <p>No exams available.</p>
        <?php else: ?>
            <div class="exam-grid">
                <?php foreach ($exams as $exam): ?>
                    <div class="card exam-card">
                        <h3><?= htmlspecialchars($exam['title']) ?></h3>
                        <p>Duration: <?= (int)$exam['duration_minutes'] ?> minutes</p>
                        <a href="start_exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary">Start Exam</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>