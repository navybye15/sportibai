<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '../';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/mailer.php';

$simulated_link = '';

if (isset($_POST['submit'])) {
    if (!csrf_verify()) {
        header('Location: forgot_password.php');
        exit;
    }

    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email)) {
        $_SESSION['message'] = 'Please enter your registered email address.';
        header('Location: forgot_password.php');
        exit;
    }

    if ($conn == null) {
        $_SESSION['message'] = 'Database is not connected yet.';
        header('Location: forgot_password.php');
        exit;
    }

    try {
        $sql = "SELECT user_id, status FROM users WHERE email = ? LIMIT 1";
        $res = mysqli_execute_query($conn, $sql, array($email));
        $user = mysqli_fetch_assoc($res);

        if ($user && $user['status'] === 'active') {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour

            $update_sql = "UPDATE users SET reset_token = ?, reset_expires = ? WHERE user_id = ?";
            mysqli_execute_query($conn, $update_sql, array($token, $expires, $user['user_id']));

            // Construct reset URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
            $reset_link = $protocol . $host . $dir . '/reset_password.php?token=' . $token . '&email=' . urlencode($email);

            $result = send_password_reset_email($email, $reset_link);

            if (!empty($result['simulated'])) {
                $_SESSION['success'] = 'Reset link generated! Check your email or use the link below (Mailtrap sandbox simulated):';
                $_SESSION['dev_reset_link'] = $reset_link;
            } else {
                $_SESSION['success'] = 'Password reset instructions have been sent to your email.';
            }
        } else {
            // Generic message for security (prevents account enumeration)
            $_SESSION['success'] = 'If that account exists, password reset instructions have been sent to your email.';
        }
    } catch (Exception $e) {
        $_SESSION['message'] = 'Could not process request. Please try again.';
    }

    header('Location: forgot_password.php');
    exit;
}

$page_title = 'Forgot Password | Sportibai';
require __DIR__ . '/../includes/header.php';
?>

<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ ACCOUNT RECOVERY ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Forgot Password</h1>
        </div>
        <?php require __DIR__ . '/../includes/alert.php'; ?>

        <?php if (!empty($_SESSION['dev_reset_link'])) { ?>
            <div class="alert alert-primary" style="word-break:break-all; font-size:13px; line-height:1.5;">
                <strong>⚡ Test Link (PHPMailer Logged):</strong><br>
                <a href="<?= htmlspecialchars($_SESSION['dev_reset_link']) ?>" style="color:#51af43; font-weight:700;">
                    <?= htmlspecialchars($_SESSION['dev_reset_link']) ?>
                </a>
            </div>
            <?php unset($_SESSION['dev_reset_link']); ?>
        <?php } ?>

        <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">
            Enter your registered email address. We'll send you a secure link to reset your password.
        </p>

        <div>
            <label class="form-label">Email Address</label>
            <input type="email" name="email" placeholder="e.g. alex@sportibai.com" required style="margin-top:0.75rem;">
        </div>

        <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit">Send Reset Link</button>

        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <span class="muted">Remember your password?</span>
            <a href="login.php" style="color:var(--color-accent-on-dark); font-weight:600; margin-left:4px;">Sign in here</a>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
