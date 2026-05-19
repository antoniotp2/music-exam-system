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

    $availableFrom = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
    $availableUntil = !empty($_POST['available_until']) ? $_POST['available_until'] : null;

    $randomQuestionCount = ($_POST['random_question_count'] !== '')
        ? (int)$_POST['random_question_count']
        : null;

    if ($title === '' || $duration <= 0 || empty($questionIds)) {
        setFlash('error', 'All exam fields are required.');
        header("Location: create_exam.php");
        exit;
    }

    if ($randomQuestionCount !== null && ($randomQuestionCount <= 0 || $randomQuestionCount > count($questionIds))) {
        setFlash('error', 'Random Questions Count must be between 1 and the number of selected questions.');
        header("Location: create_exam.php");
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO exams (
                title,
                duration_minutes,
                is_visible,
                available_from,
                available_until,
                random_question_count
            )
            VALUES (
                :title,
                :duration,
                :is_visible,
                :available_from,
                :available_until,
                :random_question_count
            )
        ");

        $stmt->execute([
            'title' => $title,
            'duration' => $duration,
            'is_visible' => $isVisible,
            'available_from' => $availableFrom,
            'available_until' => $availableUntil,
            'random_question_count' => $randomQuestionCount
        ]);

        $examId = $pdo->lastInsertId();

        $linkStmt = $pdo->prepare("
            INSERT INTO exam_questions (exam_id, question_id)
            VALUES (:exam_id, :question_id)
        ");

        foreach ($questionIds as $qid) {
            $linkStmt->execute([
                'exam_id' => $examId,
                'question_id' => (int)$qid
            ]);
        }

        $pdo->commit();
        setFlash('success', 'Exam created successfully.');
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

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
                <label>Random Questions Count</label>
                <input
                    type="number"
                    name="random_question_count"
                    min="1"
                    placeholder="Leave empty to use all selected questions"
                >
                <small>If empty, all selected questions will appear.</small>
            </div>

            <div class="form-group">
                <label>Visibility</label>
                <select name="is_visible" required>
                    <option value="1">Visible to students</option>
                    <option value="0">Hidden from students</option>
                </select>
            </div>

            <div class="form-group">
                <label>Available From</label>
                <input type="datetime-local" name="available_from">
            </div>

            <div class="form-group">
                <label>Available Until</label>
                <input type="datetime-local" name="available_until">
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