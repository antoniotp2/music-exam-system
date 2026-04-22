<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireStudent();

$attemptId = (int)($_GET['attempt_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT a.score, a.start_time, a.end_time, e.title
    FROM attempts a
    INNER JOIN exams e ON a.exam_id = e.id
    WHERE a.id = :attempt_id AND a.user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'attempt_id' => $attemptId,
    'user_id' => $_SESSION['user']['id']
]);
$result = $stmt->fetch();

if (!$result) {
    header("Location: dashboard.php");
    exit;
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="card result-card">
        <h1>Exam Result</h1>
        <h2><?= htmlspecialchars($result['title']) ?></h2>

        <p>Your exam has been submitted successfully.</p>

        <p><strong>Started:</strong> <?= htmlspecialchars($result['start_time']) ?></p>
        <p><strong>Submitted:</strong> <?= htmlspecialchars($result['end_time']) ?></p>

        <a href="dashboard.php" class="btn btn-primary">Back to Dashboard</a>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>