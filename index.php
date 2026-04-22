<?php
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

if ($_SESSION['user']['role'] === 'admin') {
    header("Location: admin/dashboard.php");
    exit;
}

header("Location: student/dashboard.php");
exit;