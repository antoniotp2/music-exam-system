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

$questionsStmt = $pdo->prepare("
    SELECT q.id, q.answer_type
    FROM attempt_questions aq
    INNER JOIN questions q ON aq.question_id = q.id
    WHERE aq.attempt_id = :attempt_id
");
$questionsStmt->execute(['attempt_id' => $attemptId]);
$questions = $questionsStmt->fetchAll();

$totalQuestions = count($questions);
$totalScore = 0;

try {
    $pdo->beginTransaction();

    $answerInsert = $pdo->prepare("
        INSERT INTO answers (attempt_id, question_id, selected_option_id, is_correct)
        VALUES (:attempt_id, :question_id, NULL, :is_correct)
    ");

    $selectedOptionInsert = $pdo->prepare("
        INSERT INTO answer_selected_options (answer_id, option_id)
        VALUES (:answer_id, :option_id)
    ");

    $correctOptionsStmt = $pdo->prepare("
        SELECT id
        FROM question_options
        WHERE question_id = :question_id
          AND is_correct = 1
    ");

    $allOptionsStmt = $pdo->prepare("
        SELECT id
        FROM question_options
        WHERE question_id = :question_id
    ");

    foreach ($questions as $question) {
        $questionId = (int)$question['id'];
        $answerType = $question['answer_type'] ?? 'single';

        $selectedOptions = $submittedAnswers[$questionId] ?? [];

        if (!is_array($selectedOptions)) {
            $selectedOptions = [$selectedOptions];
        }

        $selectedOptions = array_map('intval', $selectedOptions);
        $selectedOptions = array_unique($selectedOptions);
        sort($selectedOptions);

        $correctOptionsStmt->execute([
            'question_id' => $questionId
        ]);

        $correctOptions = $correctOptionsStmt->fetchAll(PDO::FETCH_COLUMN);
        $correctOptions = array_map('intval', $correctOptions);
        sort($correctOptions);

        if ($answerType === 'multiple') {
            $allOptionsStmt->execute([
                'question_id' => $questionId
            ]);

            $allOptions = $allOptionsStmt->fetchAll(PDO::FETCH_COLUMN);
            $allOptions = array_map('intval', $allOptions);

            $totalOptions = count($allOptions);
            $totalCorrect = count($correctOptions);

            $correctSelected = count(array_intersect($selectedOptions, $correctOptions));

            $wrongOptions = array_values(array_diff($allOptions, $correctOptions));
            $wrongSelected = count(array_intersect($selectedOptions, $wrongOptions));

            $positiveScore = $totalCorrect > 0
                ? $correctSelected / $totalCorrect
                : 0;

            $penaltyPerWrong = $totalOptions > 1
                ? 1 / ($totalOptions - 1)
                : 0;

            $negativeScore = $wrongSelected * $penaltyPerWrong;

            $questionScore = $positiveScore - $negativeScore;

            if ($questionScore < 0) {
                $questionScore = 0;
            }

            if ($questionScore > 1) {
                $questionScore = 1;
            }

            $isCorrect = ($questionScore == 1) ? 1 : 0;
        } else {
            $questionScore = ($selectedOptions === $correctOptions) ? 1 : 0;
            $isCorrect = $questionScore === 1 ? 1 : 0;
        }

        $totalScore += $questionScore;

        $answerInsert->execute([
            'attempt_id' => $attemptId,
            'question_id' => $questionId,
            'is_correct' => $isCorrect
        ]);

        $answerId = $pdo->lastInsertId();

        foreach ($selectedOptions as $optionId) {
            $selectedOptionInsert->execute([
                'answer_id' => $answerId,
                'option_id' => $optionId
            ]);
        }
    }

    $score = $totalQuestions > 0 ? ($totalScore / $totalQuestions) * 100 : 0;

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
    } else {
        setFlash('success', 'Your exam was submitted successfully.');
    }

    header("Location: result.php?attempt_id=" . $attemptId);
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlash('error', 'Failed to submit exam: ' . $e->getMessage());
    header("Location: take_exam.php?attempt_id=" . $attemptId);
    exit;
}