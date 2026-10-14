<?php
// Require this at the top of customer-only pages (cart, checkout, orders, profile).
// An admin account manages the shop, it does not buy from it.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin') {
    $_SESSION['message'] = 'Admin accounts cannot shop. Please use a customer account.';
    $dest = (isset($base) ? $base : '') . 'admin/index.php';
    header("Location: $dest");
    exit;
}
