<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '../';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Remember redirect query
$redirect = '';
if (isset($_GET['redirect'])) {
    $redirect = trim($_GET['redirect']);
} elseif (isset($_POST['redirect'])) {
    $redirect = trim($_POST['redirect']);
}

$errors = array();
$email_input = '';

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

    $email_input = trim($_POST['email'] ?? '');
    $email = strtolower($email_input);
    $password = trim($_POST['password'] ?? '');

    // Pure PHP Server-side Validation (MP5 Requirement: No HTML5 validation attributes)
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    }

    if (empty($errors)) {
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

                if (password_verify($password, $row['password'])) {
                    if ($row['status'] !== 'active') {
                        $_SESSION['message'] = 'Your account has been deactivated. Please contact support.';
                        header('Location: login.php');
                        exit;
                    }

                    session_regenerate_id(true);

                    // Reset login attempts
                    $_SESSION['login_attempts'] = 0;

                    $_SESSION['user_id'] = $row['user_id'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['last_activity'] = time();

                    $_SESSION['success'] = 'Welcome back!';
                    if ($row['role'] == 'admin') {
                        header('Location: ../admin/index.php');
                    } elseif ($redirect && !str_contains($redirect, '://')) {
                        $target = $redirect;
                        if (!str_starts_with($target, '/') && !str_starts_with($target, '../')) {
                            $target = '../' . $target;
                        }
                        header('Location: ' . $target);
                    } else {
                        header('Location: ../index.php');
                    }
                    exit;
                }
            }

            // Increment attempts on failure
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $_SESSION['message'] = 'Invalid email or password.';
        } catch (mysqli_sql_exception $e) {
            $_SESSION['message'] = 'Something went wrong. Please try again.';
        }
    }
}

$page_title = 'Log in | Sportibai';
require __DIR__ . '/../includes/header.php';
?>
<div class="reveal in">
    <!-- MP5: Form with novalidate, no HTML5 validation attributes -->
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" novalidate>
        <div>
            <div class="hero-eyebrow">[ ACCOUNT ACCESS ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Sign in</h1>
        </div>
        <?php require __DIR__ . '/../includes/alert.php'; ?>
        <?= csrf_field() ?>
        <?php if ($redirect) { ?>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
        <?php } ?>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">Enter your credentials to manage orders and checkout.</p>
        
        <div>
            <label class="form-label">Email Address</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($email_input); ?>" placeholder="e.g. alex@sportibai.com" style="margin-top:0.75rem; <?= isset($errors['email']) ? 'border-color:#ff6b6b;' : '' ?>">
            <?php if (isset($errors['email'])) { ?>
                <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['email']) ?></div>
            <?php } ?>
        </div>
        
        <div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <label class="form-label" style="margin:0;">Password</label>
                <a href="forgot_password.php" style="font-size:12px; color:var(--color-accent-on-dark); font-weight:600; text-decoration:none;">Forgot password?</a>
            </div>
            <input type="password" name="password" placeholder="••••••••" style="margin-top:0.75rem; <?= isset($errors['password']) ? 'border-color:#ff6b6b;' : '' ?>">
            <?php if (isset($errors['password'])) { ?>
                <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['password']) ?></div>
            <?php } ?>
        </div>

        <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit">Sign In to Sportibai</button>
        
        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <span class="muted">Don't have an account?</span> 
            <a href="register.php" style="color:var(--color-accent-on-dark); font-weight:600; margin-left:4px;">Create one here</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
