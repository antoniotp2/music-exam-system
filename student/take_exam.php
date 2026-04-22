<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireStudent();

$attemptId = (int)($_GET['attempt_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT a.*, e.title, e.duration_minutes
    FROM attempts a
    INNER JOIN exams e ON a.exam_id = e.id
    WHERE a.id = :attempt_id AND a.user_id = :user_id
    LIMIT 1
");
$stmt->execute([
    'attempt_id' => $attemptId,
    'user_id' => $_SESSION['user']['id']
]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header("Location: dashboard.php");
    exit;
}

if ($attempt['end_time'] !== null) {
    header("Location: result.php?attempt_id=" . $attemptId);
    exit;
}

$questionsStmt = $pdo->prepare("
    SELECT q.id AS question_id, q.question_text, q.image_path
    FROM exam_questions eq
    INNER JOIN questions q ON eq.question_id = q.id
    WHERE eq.exam_id = :exam_id
    ORDER BY eq.id ASC
");
$questionsStmt->execute(['exam_id' => $attempt['exam_id']]);
$questions = $questionsStmt->fetchAll();

$optionsStmt = $pdo->prepare("
    SELECT id, option_text, question_id
    FROM question_options
    WHERE question_id = :question_id
    ORDER BY id ASC
");

$startTimestamp = strtotime($attempt['start_time']);
$durationSeconds = ((int)$attempt['duration_minutes']) * 60;
$endTimestamp = $startTimestamp + $durationSeconds;
$remainingSeconds = max(0, $endTimestamp - time());
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">
    <div class="exam-header">
        <h1><?= htmlspecialchars($attempt['title']) ?></h1>
        <p>DEBUG remainingSeconds: <?= $remainingSeconds ?></p>
        <div id="timer" class="timer-box" data-remaining-seconds="<?= $remainingSeconds ?>">
            Loading timer...
        </div>
    </div>

    <form method="POST" action="submit_exam.php" id="examForm">
        <input type="hidden" name="attempt_id" value="<?= $attemptId ?>">

        <?php foreach ($questions as $index => $question): ?>
            <div class="card question-card">
                <h3>Question <?= $index + 1 ?></h3>
                <p><?= htmlspecialchars($question['question_text']) ?></p>

                <?php if (!empty($question['image_path'])): ?>
                    <img src="/music-exam-system-main/<?= htmlspecialchars($question['image_path']) ?>" class="question-image" alt="Question image">
                <?php endif; ?>

                <?php
                $optionsStmt->execute(['question_id' => $question['question_id']]);
                $options = $optionsStmt->fetchAll();
                ?>

                <div class="options-group">
                    <?php foreach ($options as $option): ?>
                        <label class="option-item">
                            <input type="radio" name="answers[<?= $question['question_id'] ?>]" value="<?= $option['id'] ?>">
                            <?= htmlspecialchars($option['option_text']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <button type="submit" class="btn btn-primary btn-full" onclick="return confirmSubmit()">Submit Exam</button>
    </form>
</div>

<script src="/music-exam-system-main/assets/js/exam-timer.js"></script>
<script>
function confirmSubmit() {
    return confirm("Are you sure you want to submit the exam? You cannot change your answers after this.");
}
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>