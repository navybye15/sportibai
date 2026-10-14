<?php
// Procedural mysqli, same as sir's style.
// $conn stays null if MySQL/the import isn't ready yet, so the shop
// still shows sample items instead of a blank page.
date_default_timezone_set('Asia/Manila');

// ── Secure Session Cookies (Item 26) ──────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
}

// ── Error Logging Configuration (Item 25) ────────────────────────────────────
ini_set('display_errors', '0');
ini_set('log_errors', '1');
$log_dir = __DIR__ . '/../logs';
if (is_dir($log_dir)) {
    ini_set('error_log', $log_dir . '/php_error.log');
}

// ── Separate DB Credentials Config (Item 24) ──────────────────────────────────
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sportibai';

$config_file = __DIR__ . '/../config/db_config.php';
if (file_exists($config_file)) {
    $cfg = include $config_file;
    if (is_array($cfg)) {
        $db_host = $cfg['host'] ?? $db_host;
        $db_user = $cfg['user'] ?? $db_user;
        $db_pass = $cfg['pass'] ?? $db_pass;
        $db_name = $cfg['name'] ?? $db_name;
    }
}

mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
if (!$conn) {
    $conn = null;
} else {
    // Once connected, switch to strict mode so bad queries throw an
    // exception we can catch, instead of failing silently.
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    @mysqli_query($conn, "SET time_zone = '+08:00'");
}
