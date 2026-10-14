<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_POST['submit'])) {
    $email = strtolower(trim($_POST['email']));
    $password = trim($_POST['password']);
    $confirm_pass = trim($_POST['confirm_pass']);

    if (!preg_match('/^[\w.+-]+@[\w-]+(\.[\w-]+)*\.[a-zA-Z]{2,}$/', $email)) {
        $_SESSION['message'] = 'Invalid email format.';
        header('Location: register.php');
        exit;
    }

    if (strlen($password) < 6) {
        $_SESSION['message'] = 'Password should be at least 6 characters.';
        header('Location: register.php');
        exit;
    }

    if ($password != $confirm_pass) {
        $_SESSION['message'] = 'Passwords do not match.';
        header('Location: register.php');
        exit;
    }

    if ($conn == null) {
        $_SESSION['message'] = 'Database is not connected yet.';
        header('Location: register.php');
        exit;
    }

    try {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (email, password, role, status) VALUES (?, ?, 'user', 'active')";
        mysqli_execute_query($conn, $sql, array($email, $hashed_password));

        $_SESSION['success'] = 'Account created. You may now log in.';
        header('Location: login.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        if ($e->getCode() == 1062) {
            $_SESSION['message'] = 'That email is already registered. Please log in instead.';
        } else {
            $_SESSION['message'] = 'Something went wrong. Please try again.';
        }
        header('Location: register.php');
        exit;
    }
}

$page_title = 'Register | Sportibai';
require 'includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <div>
            <div class="hero-eyebrow">[ NEW ATHLETE ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Create Account</h1>
        </div>
        <?php require 'includes/alert.php'; ?>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">Join Sportibai to order gear and track active deliveries.</p>
        
        <div>
            <label class="form-label">Email Address</label>
            <input type="email" name="email" placeholder="name@domain.com" required style="margin-top:0.75rem;">
        </div>
        
        <div>
            <label class="form-label">Password (at least 6 characters)</label>
            <input type="password" name="password" placeholder="••••••••" required style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_pass" placeholder="••••••••" required style="margin-top:0.75rem;">
        </div>

        <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit">Create Sportibai Account</button>
        
        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <span class="muted">Already registered?</span> 
            <a href="login.php" style="color:var(--color-accent-on-dark); font-weight:600; margin-left:4px;">Log in here</a>
        </div>
    </form>
</div>
<?php require 'includes/footer.php'; ?>
