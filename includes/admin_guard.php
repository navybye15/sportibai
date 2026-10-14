<?php
// Require this at the very top of every file inside /admin.
// Without this check, anyone could open an admin page just by typing
// its URL directly, even without logging in as admin.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}
