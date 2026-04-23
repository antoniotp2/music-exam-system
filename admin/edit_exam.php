<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireAdmin();

$examId = (int)($_GET['id'] ?? $_POST['exam_id'] ?? 0);

if ($examId <= 0) {
    setFlash('error', 'Invalid exam.');
    header("Location: manage_exams.php");
    exit;
}

/* LOAD ALL QUESTIONS */
$allQuestionsStmt = $pdo->query("
    SELECT q.id, q.question_text, c.name AS category_name
    FROM questions q
    LEFT JOIN categories c ON q.category_id = c.id
    ORDER BY q.id DESC
");
$allQuestions = $allQuestionsStmt->fetchAll();

/* UPDATE EXAM */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $isVisible = (int)($_POST['is_visible'] ?? 0);
    $availableFrom = !empty($_POST['available_from']) ? $_POST['available_from'] : null;
    $availableUntil = !empty($_POST['available_until']) ? $_POST['available_until'] : null;
    $randomQuestionCount = (int)($_POST['random_question_count'] ?? 0);
    $questionIds = $_POST['question_ids'] ?? [];
    if (
    $title === '' ||
    $duration <= 0 ||
    empty($questionIds) ||
    $randomQuestionCount <= 0 ||
    $randomQuestionCount > count($questionIds)
) {
        setFlash('error', 'Title, duration, questions and valid random question count are required.');
        header("Location: edit_exam.php?id=" . $examId);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE exams
            SET title = :title,
                duration_minutes = :duration,
                is_visible = :is_visible,
                available_from = :available_from,
                available_until = :available_until,
                random_question_count = :random_question_count
            WHERE id = :id
        ");
        $stmt->execute([
            'title' => $title,
            'duration' => $duration,
            'is_visible' => $isVisible,
            'available_from' => $availableFrom,
            'available_until' => $availableUntil,
            'random_question_count' => $randomQuestionCount,
            'id' => $examId
        ]);

        $stmt = $pdo->prepare("DELETE FROM exam_questions WHERE exam_id = :exam_id");
        $stmt->execute(['exam_id' => $examId]);

        $insertStmt = $pdo->prepare("
            INSERT INTO exam_questions (exam_id, question_id)
            VALUES (:exam_id, :question_id)
        ");

        foreach ($questionIds as $qid) {
            $insertStmt->execute([
                'exam_id' => $examId,
                'question_id' => (int)$qid
            ]);
        }

        $pdo->commit();

        setFlash('success', 'Exam updated successfully.');
        header("Location: manage_exams.php");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        setFlash('error', 'Failed to update exam: ' . $e->getMessage());
        header("Location: edit_exam.php?id=" . $examId);
        exit;
    }
}

/* LOAD EXAM */
$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $examId]);
$exam = $stmt->fetch();

if (!$exam) {
    setFlash('error', 'Exam not found.');
    header("Location: manage_exams.php");
    exit;
}

/* LOAD SELECTED QUESTIONS */
$stmt = $pdo->prepare("SELECT question_id FROM exam_questions WHERE exam_id = :exam_id");
$stmt->execute(['exam_id' => $examId]);
$selectedQuestionIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="card">
        <h1>Edit Exam</h1>
        <?php displayFlash(); ?>

        <form method="POST" class="form">
            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">

            <div class="form-group">
                <label>Exam Title</label>
                <input type="text" name="title" value="<?= htmlspecialchars($exam['title']) ?>" required>
            </div>

            <div class="form-group">
                <label>Duration (minutes)</label>
                <input type="number" name="duration_minutes" min="1" value="<?= (int)$exam['duration_minutes'] ?>" required>
            </div>

            <div class="form-group">
    <label>Random Questions Count</label>
    <input
        type="number"
        name="random_question_count"
        min="1"
        value="<?= htmlspecialchars($exam['random_question_count'] ?? '') ?>"
        required
    >
</div>

            <div class="form-group">
                <label>Visibility</label>
                <select name="is_visible" required>
                    <option value="1" <?= $exam['is_visible'] ? 'selected' : '' ?>>Visible to students</option>
                    <option value="0" <?= !$exam['is_visible'] ? 'selected' : '' ?>>Hidden from students</option>
                </select>
            </div>

            <div class="form-group">
                <label>Available From</label>
                <input
                    type="datetime-local"
                    name="available_from"
                    value="<?= !empty($exam['available_from']) ? date('Y-m-d\TH:i', strtotime($exam['available_from'])) : '' ?>"
                >
            </div>

            <div class="form-group">
                <label>Available Until</label>
                <input
                    type="datetime-local"
                    name="available_until"
                    value="<?= !empty($exam['available_until']) ? date('Y-m-d\TH:i', strtotime($exam['available_until'])) : '' ?>"
                >
            </div>

            <div class="form-group">
                <label>Assigned Questions</label>
                <div class="checkbox-list">
                    <?php foreach ($allQuestions as $question): ?>
                        <label class="checkbox-item" style="display:block; margin-bottom:8px;">
                            <input
                                type="checkbox"
                                name="question_ids[]"
                                value="<?= $question['id'] ?>"
                                <?= in_array($question['id'], $selectedQuestionIds) ? 'checked' : '' ?>
                            >
                            #<?= $question['id'] ?> -
                            <?= htmlspecialchars($question['question_text']) ?>
                            <?php if (!empty($question['category_name'])): ?>
                                (<?= htmlspecialchars($question['category_name']) ?>)
                            <?php endif; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Update Exam</button>
            <a href="manage_exams.php" class="btn btn-secondary">Back</a>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>