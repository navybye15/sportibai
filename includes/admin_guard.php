<?php
// Require this at the very top of every file inside /admin.
// Without this check, anyone could open an admin page just by typing
// its URL directly, even without logging in as admin.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    $_SESSION['message'] = 'Please log in to access this page.';
    header('Location: ../auth/login.php');
    exit;
}
if ($_SESSION['role'] != 'admin') {
    $_SESSION['message'] = 'Access restricted: Only administrators can access CRUD management pages.';
    header('Location: ../auth/login.php');
    exit;
}
