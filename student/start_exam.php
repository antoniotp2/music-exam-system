<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/flash.php';
requireStudent();

$examId = (int)($_GET['exam_id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM exams WHERE id = :id LIMIT 1");
$stmt->execute(['id' => $examId]);
$exam = $stmt->fetch();

if (!$exam) {
    setFlash('error', 'Exam not found.');
    header("Location: dashboard.php");
    exit;
}

if ((int)$exam['is_visible'] !== 1) {
    setFlash('error', 'This exam is not available.');
    header("Location: dashboard.php");
    exit;
}

$now = time();
$availableFrom = !empty($exam['available_from']) ? strtotime($exam['available_from']) : null;
$availableUntil = !empty($exam['available_until']) ? strtotime($exam['available_until']) : null;

if ($availableFrom && $now < $availableFrom) {
    setFlash('error', 'This exam is not open yet.');
    header("Location: dashboard.php");
    exit;
}

if ($availableUntil && $now > $availableUntil) {
    setFlash('error', 'This exam is no longer available.');
    header("Location: dashboard.php");
    exit;
}

$checkStmt = $pdo->prepare("
    SELECT * FROM attempts
    WHERE user_id = :user_id AND exam_id = :exam_id
    ORDER BY id DESC
    LIMIT 1
");
$checkStmt->execute([
    'user_id' => $_SESSION['user']['id'],
    'exam_id' => $examId
]);
$existingAttempt = $checkStmt->fetch();

if ($existingAttempt) {

    // Αν έχει τελειώσει
    if ($existingAttempt['end_time'] !== null) {
        setFlash('success', 'You have already submitted this exam successfully.');
        header("Location: result.php?attempt_id=" . $existingAttempt['id']);
        exit;
    }

    // Αν είναι σε εξέλιξη
    header("Location: take_exam.php?attempt_id=" . $existingAttempt['id']);
    exit;
}

$startTime = date('Y-m-d H:i:s');

$insertStmt = $pdo->prepare("
    INSERT INTO attempts (user_id, exam_id, score, start_time)
    VALUES (:user_id, :exam_id, 0, :start_time)
");
$insertStmt->execute([
    'user_id' => $_SESSION['user']['id'],
    'exam_id' => $examId,
    'start_time' => $startTime
]);

$attemptId = $pdo->lastInsertId();
/* RANDOM QUESTIONS FOR THIS ATTEMPT */

// πόσες θέλουμε
$questionLimit = (int)$exam['random_question_count'];

// πάρε τυχαίες από τις επιλεγμένες του exam
$questionsStmt = $pdo->prepare("
    SELECT question_id
    FROM exam_questions
    WHERE exam_id = :exam_id
    ORDER BY RAND()
    LIMIT $questionLimit
");
$questionsStmt->execute(['exam_id' => $examId]);

$randomQuestions = $questionsStmt->fetchAll(PDO::FETCH_COLUMN);

// αποθήκευση για τον συγκεκριμένο student
$insertAttemptQuestion = $pdo->prepare("
    INSERT INTO attempt_questions (attempt_id, question_id)
    VALUES (:attempt_id, :question_id)
");

foreach ($randomQuestions as $questionId) {
    $insertAttemptQuestion->execute([
        'attempt_id' => $attemptId,
        'question_id' => $questionId
    ]);
}
header("Location: take_exam.php?attempt_id=" . $attemptId);
exit;