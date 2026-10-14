<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '';
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Remember redirect query
$redirect = '';
if (isset($_GET['redirect'])) {
    $redirect = trim($_GET['redirect']);
} elseif (isset($_POST['redirect'])) {
    $redirect = trim($_POST['redirect']);
}

// ── Rate Limiting (max 5 failed attempts per 15 minutes) ──────────────────────
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}
if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt_time']) < 900) {
    $wait = ceil((900 - (time() - $_SESSION['last_attempt_time'])) / 60);
    $_SESSION['message'] = "Too many failed login attempts. Please wait $wait minutes.";
} elseif (isset($_POST['submit'])) {
    if (!csrf_verify()) {
        header('Location: login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    }

    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);

    if ($conn == null) {
        $_SESSION['message'] = 'Database is not connected yet.';
        header('Location: login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    }

    try {
        $sql = "SELECT user_id, email, password, role, status FROM users WHERE email = ? LIMIT 1";
        $result = mysqli_execute_query($conn, $sql, array($email));
        $number = mysqli_num_rows($result);

        if ($number == 1) {
            $row = mysqli_fetch_assoc($result);

            if (password_verify($password, $row['password']) && $row['status'] == 'active') {
                session_regenerate_id(true);

                // Reset login attempts
                $_SESSION['login_attempts'] = 0;

                $_SESSION['user_id'] = $row['user_id'];
                $_SESSION['email'] = $row['email'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['last_activity'] = time();

                $_SESSION['success'] = 'Welcome back!';
                if ($row['role'] == 'admin') {
                    header('Location: admin/index.php');
                } elseif ($redirect && !str_contains($redirect, '://')) {
                    header('Location: ' . $redirect);
                } else {
                    header('Location: index.php');
                }
                exit;
            }
        }

        // Increment attempts on failure
        $_SESSION['login_attempts']++;
        $_SESSION['last_attempt_time'] = time();
        $_SESSION['message'] = 'Wrong email or password.';
        header('Location: login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    } catch (mysqli_sql_exception $e) {
        $_SESSION['message'] = 'Something went wrong. Please try again.';
        header('Location: login.php' . ($redirect ? '?redirect=' . urlencode($redirect) : ''));
        exit;
    }
}

$page_title = 'Log in | Sportibai';
require 'includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div>
            <div class="hero-eyebrow">[ ACCOUNT ACCESS ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Sign in</h1>
        </div>
        <?php require 'includes/alert.php'; ?>
        <?= csrf_field() ?>
        <?php if ($redirect) { ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <?php } ?>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">Enter your credentials to manage orders and checkout.</p>
        
        <div>
            <label class="form-label">Email Address</label>
            <input type="email" name="email" placeholder="e.g. alex@sportibai.com" required style="margin-top:0.75rem;">
        </div>
        
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label" style="margin:0;">Password</label>
                <a href="forgot_password.php" style="font-size:12px; color:var(--color-accent-on-dark); font-weight:600; text-decoration:none;">Forgot password?</a>
            </div>
            <input type="password" name="password" placeholder="••••••••" required style="margin-top:0.75rem;">
        </div>

        <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit">Sign In to Sportibai</button>
        
        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <span class="muted">Don't have an account?</span> 
            <a href="register.php" style="color:var(--color-accent-on-dark); font-weight:600; margin-left:4px;">Create one here</a>
        </div>
    </form>
</div>
<?php require 'includes/footer.php'; ?>
