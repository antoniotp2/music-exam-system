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

$checkStmt = $pdo->prepare("
    SELECT * FROM attempts
    WHERE user_id = :user_id AND exam_id = :exam_id AND end_time IS NULL
    ORDER BY id DESC
    LIMIT 1
");
$checkStmt->execute([
    'user_id' => $_SESSION['user']['id'],
    'exam_id' => $examId
]);
$existingAttempt = $checkStmt->fetch();

if ($existingAttempt) {
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
header("Location: take_exam.php?attempt_id=" . $attemptId);
exit;