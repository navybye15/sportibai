<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Clear all session data, then destroy the session itself
session_unset();
session_destroy();
header('Location: login.php');
exit;
