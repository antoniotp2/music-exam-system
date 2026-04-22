<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header("Location: /music-exam-system/login.php");
        exit;
    }
}

function requireAdmin(): void
{
    requireLogin();
    if ($_SESSION['user']['role'] !== 'admin') {
        header("Location: /music-exam-system/index.php");
        exit;
    }
}

function requireStudent(): void
{
    requireLogin();
    if ($_SESSION['user']['role'] !== 'student') {
        header("Location: /music-exam-system/index.php");
        exit;
    }
}
?>