<?php
session_start();
session_unset();
session_destroy();

header("Location: /music-exam-system-main/login.php");
exit;