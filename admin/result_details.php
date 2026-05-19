<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$attemptId = (int)($_GET['id'] ?? 0);

if ($attemptId <= 0) {
    die("Invalid attempt");
}

/* LOAD ATTEMPT INFO */
$stmt = $pdo->prepare("
    SELECT 
        a.id,
        u.name AS student_name,
        u.email,
        e.title AS exam_title,
        a.score,
        a.start_time,
        a.end_time
    FROM attempts a
    INNER JOIN users u ON a.user_id = u.id
    INNER JOIN exams e ON a.exam_id = e.id
    WHERE a.id = ?
");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    die("Attempt not found");
}

/* LOAD ANSWERS */
$stmt = $pdo->prepare("
    SELECT 
        ans.id AS answer_id,
        q.id AS question_id,
        q.question_text,
        q.answer_type,
        ans.is_correct
    FROM answers ans
    INNER JOIN questions q ON ans.question_id = q.id
    WHERE ans.attempt_id = ?
    ORDER BY ans.id ASC
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

/* PREPARE OPTION QUERIES */
$studentOptionsStmt = $pdo->prepare("
    SELECT qo.option_text
    FROM answer_selected_options aso
    INNER JOIN question_options qo ON aso.option_id = qo.id
    WHERE aso.answer_id = ?
    ORDER BY qo.id ASC
");

$correctOptionsStmt = $pdo->prepare("
    SELECT option_text
    FROM question_options
    WHERE question_id = ? AND is_correct = 1
    ORDER BY id ASC
");

/* COUNT CORRECT / WRONG */
$totalQuestions = count($answers);
$correctCount = 0;

foreach ($answers as $a) {
    if ($a['is_correct']) {
        $correctCount++;
    }
}

$wrongCount = $totalQuestions - $correctCount;
?>

<?php include __DIR__ . '/../includes/header.php'; ?>
<?php include __DIR__ . '/../includes/navbar.php'; ?>

<div class="container page">

    <div class="card">
        <h1>Attempt Details</h1>

        <p><strong>Attempt ID:</strong> <?= $attempt['id'] ?></p>
        <p><strong>Student:</strong> <?= htmlspecialchars($attempt['student_name']) ?></p>
        <p><strong>Email:</strong> <?= htmlspecialchars($attempt['email']) ?></p>
        <p><strong>Exam:</strong> <?= htmlspecialchars($attempt['exam_title']) ?></p>
        <p><strong>Score:</strong> <?= number_format($attempt['score'], 2) ?>%</p>
        <p><strong>Start Time:</strong> <?= htmlspecialchars($attempt['start_time']) ?></p>
        <p><strong>End Time:</strong> <?= htmlspecialchars($attempt['end_time'] ?? '-') ?></p>
        <p><strong>Fully Correct Answers:</strong> <?= $correctCount ?></p>
        <p><strong>Wrong / Partial Answers:</strong> <?= $wrongCount ?></p>
        <p><strong>Total Questions:</strong> <?= $totalQuestions ?></p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>Answer Analysis</h2>

        <?php if (empty($answers)): ?>
            <p>No answers found for this attempt.</p>
        <?php else: ?>
            <?php foreach ($answers as $index => $a): ?>
                <?php
                $studentOptionsStmt->execute([$a['answer_id']]);
                $studentAnswers = $studentOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

                $correctOptionsStmt->execute([$a['question_id']]);
                $correctAnswers = $correctOptionsStmt->fetchAll(PDO::FETCH_COLUMN);

                $studentAnswerText = !empty($studentAnswers)
                    ? implode(', ', $studentAnswers)
                    : 'No answer';

                $correctAnswerText = !empty($correctAnswers)
                    ? implode(', ', $correctAnswers)
                    : '-';
                ?>

                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <p>
                        <strong>Question <?= $index + 1 ?>:</strong>
                        <?= htmlspecialchars($a['question_text']) ?>
                    </p>

                    <p>
                        <strong>Type:</strong>
                        <?= htmlspecialchars($a['answer_type']) ?>
                    </p>

                    <p>
                        <strong>Student Answer:</strong>
                        <?= htmlspecialchars($studentAnswerText) ?>
                    </p>

                    <p>
                        <strong>Correct Answer:</strong>
                        <?= htmlspecialchars($correctAnswerText) ?>
                    </p>

                    <p style="font-weight: bold; color: <?= $a['is_correct'] ? 'green' : 'red' ?>;">
                        <?= $a['is_correct'] ? '✔ Fully Correct' : '✘ Wrong / Partial' ?>
                    </p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <p style="margin-top: 20px;">
        <a href="results.php">← Back to Results</a>
    </p>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>