<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $examId = (int)($_POST['exam_id'] ?? 0);

    if ($examId <= 0) {
        setFlash('error', 'Invalid exam.');
        header("Location: manage_exams.php");
        exit;
    }

    try {
        if ($action === 'hide') {
            $stmt = $pdo->prepare("UPDATE exams SET is_visible = 0 WHERE id = :id");
            $stmt->execute(['id' => $examId]);
            setFlash('success', 'Exam hidden successfully.');
        } elseif ($action === 'show') {
            $stmt = $pdo->prepare("UPDATE exams SET is_visible = 1 WHERE id = :id");
            $stmt->execute(['id' => $examId]);
            setFlash('success', 'Exam is now visible.');
        } elseif ($action === 'delete') {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = :id");
            $stmt->execute(['id' => $examId]);

            $stmt = $pdo->prepare("DELETE FROM attempts WHERE exam_id = :id");
            $stmt->execute(['id' => $examId]);

            $stmt = $pdo->prepare("DELETE FROM exams WHERE id = :id");
            $stmt->execute(['id' => $examId]);

            $pdo->commit();
            setFlash('success', 'Exam deleted successfully.');
        }

        header("Location: manage_exams.php");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        setFlash('error', 'Action failed: ' . $e->getMessage());
        header("Location: manage_exams.php");
        exit;
    }
}

$stmt = $pdo->query("
    SELECT 
        e.id,
        e.title,
        e.duration_minutes,
        e.is_visible,
        COUNT(eq.question_id) AS total_questions
    FROM exams e
    LEFT JOIN exam_questions eq ON e.id = eq.exam_id
    GROUP BY e.id, e.title, e.duration_minutes, e.is_visible
    ORDER BY e.id DESC
");
$exams = $stmt->fetchAll();
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <h1>Manage Exams</h1>
    <?php displayFlash(); ?>

    <div class="card">
        <?php if (empty($exams)): ?>
            <p>No exams found.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Duration</th>
                        <th>Questions</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td><?= htmlspecialchars($exam['title']) ?></td>
                            <td><?= (int)$exam['duration_minutes'] ?> min</td>
                            <td><?= (int)$exam['total_questions'] ?></td>
                            <td><?= $exam['is_visible'] ? 'Visible' : 'Hidden' ?></td>
                            <td class="actions-row">
                                <a href="edit_exam.php?id=<?= $exam['id'] ?>" class="btn btn-secondary">Edit</a>

                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                    <input type="hidden" name="action" value="<?= $exam['is_visible'] ? 'hide' : 'show' ?>">
                                    <button type="submit" class="btn btn-secondary">
                                        <?= $exam['is_visible'] ? 'Hide' : 'Show' ?>
                                    </button>
                                </form>

                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this exam?');">
                                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="btn btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>