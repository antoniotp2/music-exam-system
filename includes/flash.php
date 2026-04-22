<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function displayFlash(): void
{
    if (!empty($_SESSION['flash'])) {
        $type = htmlspecialchars($_SESSION['flash']['type']);
        $message = htmlspecialchars($_SESSION['flash']['message']);
        echo "<div class='flash {$type}'>{$message}</div>";
        unset($_SESSION['flash']);
    }
}
?>