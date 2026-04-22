<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$questions = $pdo->query("SELECT id, question_text FROM questions ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $isVisible = (int)($_POST['is_visible'] ?? 1);
    $questionIds = $_POST['question_ids'] ?? [];

    if ($title === '' || $duration <= 0 || empty($questionIds)) {
        setFlash('error', 'All exam fields are required.');
        header("Location: create_exam.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
    INSERT INTO exams (title, duration_minutes, is_visible)
    VALUES (:title, :duration, :is_visible)
");
$stmt->execute([
    'title' => $title,
    'duration' => $duration,
    'is_visible' => $isVisible
]);
        $examId = $pdo->lastInsertId();

        $linkStmt = $pdo->prepare("INSERT INTO exam_questions (exam_id, question_id) VALUES (:exam_id, :question_id)");
        foreach ($questionIds as $qid) {
            $linkStmt->execute([
                'exam_id' => $examId,
                'question_id' => (int)$qid
            ]);
        }

        $pdo->commit();
        setFlash('success', 'Exam created successfully.');
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to create exam.');
    }

    header("Location: create_exam.php");
    exit;
}
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="card">
        <h1>Create Exam</h1>
        <?php displayFlash(); ?>

        <form method="POST" class="form">
            <div class="form-group">
                <label>Exam Title</label>
                <input type="text" name="title" required>
            </div>

            <div class="form-group">
    <label>Duration (minutes)</label>
    <input type="number" name="duration_minutes" min="1" required>
</div>

<div class="form-group">
    <label>Visibility</label>
    <select name="is_visible" required>
        <option value="1">Visible to students</option>
        <option value="0">Hidden from students</option>
    </select>
</div>

<div class="form-group">
    <label>Assign Questions</label>
                <div class="checkbox-list">
                    <?php foreach ($questions as $question): ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="question_ids[]" value="<?= $question['id'] ?>">
                            <?= htmlspecialchars($question['question_text']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Create Exam</button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>