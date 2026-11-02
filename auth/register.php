<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '../';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

$errors = array();
$email_val = '';

if (isset($_POST['submit'])) {
    if (!csrf_verify()) {
        $_SESSION['message'] = 'Security token expired. Please try again.';
        header('Location: register.php');
        exit;
    }

    $email_val   = trim($_POST['email'] ?? '');
    $email       = strtolower($email_val);
    $password    = trim($_POST['password'] ?? '');
    $confirm_pass = trim($_POST['confirm_pass'] ?? '');

    // Pure PHP validation (MP5 Requirement: No HTML5 validation attributes)
    if ($email === '') {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($confirm_pass === '') {
        $errors['confirm_pass'] = 'Please confirm your password.';
    } elseif ($password !== $confirm_pass) {
        $errors['confirm_pass'] = 'Passwords do not match.';
    }

    // Photo upload handling (MP2 Requirement: Registration upload photo)
    $profile_photo = '';
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $profile_photo = handle_user_photo($_FILES['profile_photo']);
        } catch (Exception $e) {
            $errors['profile_photo'] = $e->getMessage();
        }
    }

    if (empty($errors)) {
        if ($conn == null) {
            $_SESSION['message'] = 'Database is not connected yet.';
            header('Location: register.php');
            exit;
        }

        try {
            $sql_chk = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
            $res_chk = mysqli_execute_query($conn, $sql_chk, array($email));
            if (mysqli_num_rows($res_chk) > 0) {
                $errors['email'] = 'That email is already registered. Please log in instead.';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO users (email, password, role, status, profile_photo) VALUES (?, ?, 'user', 'active', ?)";
                mysqli_execute_query($conn, $sql, array($email, $hashed_password, $profile_photo ?: null));
                $new_user_id = mysqli_insert_id($conn);

                // Automatically register linked customer record
                $sql_c = "INSERT INTO customer (user_id, first_name, last_name, address, province, postal_code, landmark, phone) VALUES (?, '', '', '', '', '', '', '')";
                mysqli_execute_query($conn, $sql_c, array($new_user_id));

                $_SESSION['success'] = 'Account created successfully! You may now log in.';
                header('Location: login.php');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() == 1062) {
                $errors['email'] = 'That email is already registered. Please log in instead.';
            } else {
                $_SESSION['message'] = 'Registration error: ' . $e->getMessage();
            }
        }
    }
}

$page_title = 'Register | Sportibai';
require __DIR__ . '/../includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ NEW ATHLETE ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">Create Account</h1>
        </div>
        <?php require __DIR__ . '/../includes/alert.php'; ?>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top: -0.5rem;">Join Sportibai to order gear, write reviews, and track deliveries.</p>
        
        <!-- Email (MP5: Pure PHP validation, no HTML5 required/pattern) -->
        <div>
            <label class="form-label">Email Address</label>
            <input type="text" name="email" value="<?php echo htmlspecialchars($email_val); ?>" placeholder="athlete@sportibai.com" style="margin-top:0.75rem; <?= isset($errors['email']) ? 'border-color:#ff6b6b;' : '' ?>">
            <?php if (isset($errors['email'])) { ?>
                <div style="color:#ff9f96; font-size:13px; margin-top:5px; display:flex; align-items:center; gap:5px; font-weight:600;">
                    <?= icon('alert-triangle', '', 14) ?>
                    <span><?= htmlspecialchars($errors['email']) ?></span>
                </div>
            <?php } ?>
        </div>
        
        <!-- Password -->
        <div>
            <label class="form-label">Password (at least 6 characters)</label>
            <input type="password" name="password" placeholder="••••••••" style="margin-top:0.75rem; <?= isset($errors['password']) ? 'border-color:#ff6b6b;' : '' ?>">
            <?php if (isset($errors['password'])) { ?>
                <div style="color:#ff9f96; font-size:13px; margin-top:5px; display:flex; align-items:center; gap:5px; font-weight:600;">
                    <?= icon('alert-triangle', '', 14) ?>
                    <span><?= htmlspecialchars($errors['password']) ?></span>
                </div>
            <?php } ?>
        </div>

        <!-- Confirm Password -->
        <div>
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_pass" placeholder="••••••••" style="margin-top:0.75rem; <?= isset($errors['confirm_pass']) ? 'border-color:#ff6b6b;' : '' ?>">
            <?php if (isset($errors['confirm_pass'])) { ?>
                <div style="color:#ff9f96; font-size:13px; margin-top:5px; display:flex; align-items:center; gap:5px; font-weight:600;">
                    <?= icon('alert-triangle', '', 14) ?>
                    <span><?= htmlspecialchars($errors['confirm_pass']) ?></span>
                </div>
            <?php } ?>
        </div>

        <!-- Profile Photo (MP2 Requirement: Upload Photo on Registration) -->
        <div>
            <label class="form-label">Profile Photo <span class="muted" style="font-weight:400; font-size:12px;">(Optional)</span></label>
            <div style="margin-top:0.5rem; display:flex; align-items:center; gap:10px;">
                <input type="file" name="profile_photo" id="reg_photo_input" accept=".jpg,.jpeg,.png,.webp" style="display:none;" onchange="document.getElementById('reg_photo_preview_name').innerText = this.files[0] ? this.files[0].name : '';">
                <button type="button" class="btn ghost sm" onclick="document.getElementById('reg_photo_input').click();" style="display:inline-flex; align-items:center; gap:6px; font-size:13px; font-weight:700;">
                    <?= icon('camera', '', 15) ?>
                    <span>Choose Avatar Photo</span>
                </button>
                <span id="reg_photo_preview_name" class="muted" style="font-size:13px;"></span>
            </div>
            <p class="muted" style="font-size:12.5px; margin-top:5px;">JPG, PNG, or WEBP (Max 10MB). You can also set or change this anytime in My Profile.</p>
            <?php if (isset($errors['profile_photo'])) { ?>
                <div style="color:#ff9f96; font-size:13px; margin-top:5px; display:flex; align-items:center; gap:5px; font-weight:600;">
                    <?= icon('alert-triangle', '', 14) ?>
                    <span><?= htmlspecialchars($errors['profile_photo']) ?></span>
                </div>
            <?php } ?>
        </div>

        <button class="btn" style="width:100%; margin-top:0.75rem;" type="submit" name="submit">Create Sportibai Account</button>
        
        <div style="text-align:center; padding-top:1rem; border-top:var(--hairline) solid var(--color-border); font-size:var(--text-body-sm);">
            <span class="muted">Already registered?</span> 
            <a href="login.php" style="color:var(--color-accent-on-dark); font-weight:600; margin-left:4px;">Log in here</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
