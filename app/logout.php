<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
session_unset();
session_destroy();

if (isset($_COOKIE['st_remember'])) {
    setcookie('st_remember', '', time() - 3600, '/');
}

header('Location: index.php');
exit;
