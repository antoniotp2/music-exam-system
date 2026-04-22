<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$stmt = $pdo->query("
    SELECT a.id, u.name AS student_name, u.email, e.title AS exam_title, a.score, a.start_time, a.end_time
    FROM attempts a
    INNER JOIN users u ON a.user_id = u.id
    INNER JOIN exams e ON a.exam_id = e.id
    ORDER BY a.id DESC
");
$results = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="card">
        <h1>Student Results</h1>

        <table class="table">
            <thead>
                <tr>
                    <th>Attempt ID</th>
                    <th>Student</th>
                    <th>Email</th>
                    <th>Exam</th>
                    <th>Score (%)</th>
                    <th>Start</th>
                    <th>End</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $row): ?>
                    <tr>
                        <td><?= $row['id'] ?></td>
                        <td><?= htmlspecialchars($row['student_name']) ?></td>
                        <td><?= htmlspecialchars($row['email']) ?></td>
                        <td><?= htmlspecialchars($row['exam_title']) ?></td>
                        <td><?= number_format($row['score'], 2) ?></td>
                        <td><?= htmlspecialchars($row['start_time']) ?></td>
                        <td><?= htmlspecialchars($row['end_time'] ?? '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>