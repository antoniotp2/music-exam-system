<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireStudent();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit;
}

$attemptId = (int)($_POST['attempt_id'] ?? 0);
$submittedAnswers = $_POST['answers'] ?? [];
$autoSubmitted = isset($_POST['auto_submitted']) && $_POST['auto_submitted'] === '1';

$stmt = $pdo->prepare("
    SELECT a.*, e.duration_minutes
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

if (!$attempt || $attempt['end_time'] !== null) {
    setFlash('error', 'Invalid attempt.');
    header("Location: dashboard.php");
    exit;
}

$startTimestamp = strtotime($attempt['start_time']);
$endAllowed = $startTimestamp + ((int)$attempt['duration_minutes'] * 60);
$now = time();

$questionsStmt = $pdo->prepare("
    SELECT q.id
    FROM attempt_questions aq
    INNER JOIN questions q ON aq.question_id = q.id
    WHERE aq.attempt_id = :attempt_id
");
$questionsStmt->execute(['attempt_id' => $attemptId]);
$questions = $questionsStmt->fetchAll();

$totalQuestions = count($questions);
$correctCount = 0;

try {
    $pdo->beginTransaction();

    $answerInsert = $pdo->prepare("
        INSERT INTO answers (attempt_id, question_id, selected_option_id, is_correct)
        VALUES (:attempt_id, :question_id, :selected_option_id, :is_correct)
    ");

    $checkCorrectStmt = $pdo->prepare("
        SELECT id
        FROM question_options
        WHERE question_id = :question_id AND is_correct = 1
        LIMIT 1
    ");

    foreach ($questions as $question) {
        $questionId = $question['id'];
        $selectedOptionId = isset($submittedAnswers[$questionId]) ? (int)$submittedAnswers[$questionId] : null;

        $checkCorrectStmt->execute(['question_id' => $questionId]);
        $correctOption = $checkCorrectStmt->fetch();

        $isCorrect = ($selectedOptionId && $correctOption && $selectedOptionId === (int)$correctOption['id']) ? 1 : 0;

        if ($isCorrect) {
            $correctCount++;
        }

        $answerInsert->execute([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'selected_option_id' => $selectedOptionId,
            'is_correct' => $isCorrect
        ]);
    }

    $score = $totalQuestions > 0 ? ($correctCount / $totalQuestions) * 100 : 0;

    $updateAttempt = $pdo->prepare("
        UPDATE attempts
        SET score = :score, end_time = NOW()
        WHERE id = :attempt_id
    ");
    $updateAttempt->execute([
        'score' => $score,
        'attempt_id' => $attemptId
    ]);

    $pdo->commit();

    if ($autoSubmitted) {
    setFlash('success', 'Your exam was submitted automatically because the time expired.');
    }

    header("Location: result.php?attempt_id=" . $attemptId);
    exit;
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'Failed to submit exam.');
    header("Location: take_exam.php?attempt_id=" . $attemptId);
    exit;
}