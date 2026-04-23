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
        q.question_text,
        sel.option_text AS selected_answer,
        corr.option_text AS correct_answer,
        ans.is_correct
    FROM answers ans
    INNER JOIN questions q ON ans.question_id = q.id
    LEFT JOIN question_options sel ON ans.selected_option_id = sel.id
    LEFT JOIN question_options corr 
        ON corr.question_id = q.id AND corr.is_correct = 1
    WHERE ans.attempt_id = ?
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

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
        <p><strong>Correct Answers:</strong> <?= $correctCount ?></p>
        <p><strong>Wrong Answers:</strong> <?= $wrongCount ?></p>
        <p><strong>Total Questions:</strong> <?= $totalQuestions ?></p>
    </div>

    <div class="card" style="margin-top: 20px;">
        <h2>Answer Analysis</h2>

        <?php if (empty($answers)): ?>
            <p>No answers found for this attempt.</p>
        <?php else: ?>
            <?php foreach ($answers as $index => $a): ?>
                <div style="border: 1px solid #ccc; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <p><strong>Question <?= $index + 1 ?>:</strong> <?= htmlspecialchars($a['question_text']) ?></p>

                    <p>
                        <strong>Student Answer:</strong>
                        <?= htmlspecialchars($a['selected_answer'] ?? 'No answer') ?>
                    </p>

                    <p>
                        <strong>Correct Answer:</strong>
                        <?= htmlspecialchars($a['correct_answer'] ?? '-') ?>
                    </p>

                    <p style="font-weight: bold; color: <?= $a['is_correct'] ? 'green' : 'red' ?>;">
                        <?= $a['is_correct'] ? '✔ Correct' : '✘ Wrong' ?>
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