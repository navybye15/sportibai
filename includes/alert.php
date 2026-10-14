<?php
// Flash message, same pattern sir used: one-time message stored in session,
// shown once, then unset so it does not show again on refresh.
if (isset($_SESSION['message'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['message']);
}
if (isset($_SESSION['success'])) {
    echo '<div class="alert alert-primary">' . htmlspecialchars($_SESSION['success'], ENT_QUOTES, 'UTF-8') . '</div>';
    unset($_SESSION['success']);
}
