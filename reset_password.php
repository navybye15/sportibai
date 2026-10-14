<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$token = trim($_GET['token'] ?? ($_POST['token'] ?? ''));
$email = strtolower(trim($_GET['email'] ?? ($_POST['email'] ?? '')));

$valid_request = false;
$user_id = null;

if (!empty($token) && !empty($email) && $conn != null) {
    $sql = "SELECT user_id, reset_token, reset_expires FROM users WHERE email = ? LIMIT 1";
    $res = mysqli_execute_query($conn, $sql, array($email));
    $user = mysqli_fetch_assoc($res);

    if ($user && !empty($user['reset_token']) && hash_equals($user['reset_token'], $token)) {
        if (strtotime($user['reset_expires']) > time()) {
            $valid_request = true;
            $user_id = $user['user_id'];
        }
    }
}

if (isset($_POST['submit_new_pass'])) {
    if (!csrf_verify()) {
        header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }

    if (!$valid_request || !$user_id) {
        $_SESSION['message'] = 'This password reset link is invalid or has expired.';
        header('Location: forgot_password.php');
        exit;
    }

    $password = trim($_POST['password'] ?? '');
    $confirm_pass = trim($_POST['confirm_pass'] ?? '');

    if (strlen($password) < 6) {
        $_SESSION['message'] = 'Password must be at least 6 characters long.';
        header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }

    if ($password !== $confirm_pass) {
        $_SESSION['message'] = 'Passwords do not match.';
        header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }

    try {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?";
        mysqli_execute_query($conn, $update_sql, array($hashed, $user_id));

        $_SESSION['success'] = 'Your password has been reset successfully! Please sign in with your new password.';
        header('Location: login.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['message'] = 'Could not update password. Please try again.';
        header('Location: reset_password.php?token=' . urlencode($token) . '&email=' . urlencode($email));
        exit;
    }
}

$page_title = 'Reset Password | Sportibai';
require 'includes/header.php';
?>

<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($email) ?>">

        <div>
            <div class="hero-eyebrow">[ ACCOUNT SECURITY ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Create New Password</h1>
        </div>
        <?php require 'includes/alert.php'; ?>

        <?php if (!$valid_request) { ?>
            <div class="alert alert-danger">
                This password reset link is invalid or has expired. Please request a new one.
            </div>
            <div style="text-align:center; margin-top:1.5rem;">
                <a href="forgot_password.php" class="btn">Request New Link →</a>
            </div>
        <?php } else { ?>
            <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">
                Resetting password for <strong><?= htmlspecialchars($email) ?></strong>. Enter your new password below.
            </p>

            <div>
                <label class="form-label">New Password (at least 6 characters)</label>
                <input type="password" name="password" placeholder="••••••••" required style="margin-top:0.75rem;" minlength="6">
            </div>

            <div>
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_pass" placeholder="••••••••" required style="margin-top:0.75rem;" minlength="6">
            </div>

            <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit_new_pass">Save New Password</button>
        <?php } ?>

        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <a href="login.php" style="color:var(--color-accent-on-dark); font-weight:600;">← Back to Sign In</a>
        </div>
    </form>
</div>

<?php require 'includes/footer.php'; ?>
