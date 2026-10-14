<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

$user_id = (int)$_SESSION['user_id'];
$errors_account = array();
$errors_password = array();

// ── Handle Email / Photo Update ──────────────────────────────────────────────
if (isset($_POST['update_account'])) {
    if (!csrf_verify()) {
        header('Location: account.php');
        exit;
    }

    $email = strip_tags(trim($_POST['email'] ?? ''));

    // Pure PHP validation (MP5)
    if ($email === '') {
        $errors_account['email'] = 'Email address cannot be empty.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors_account['email'] = 'Please enter a valid email address.';
    } else {
        // Uniqueness check
        $check_sql = "SELECT user_id FROM users WHERE email = ? AND user_id != ? LIMIT 1";
        $check_res = mysqli_execute_query($conn, $check_sql, array($email, $user_id));
        if (mysqli_num_rows($check_res) > 0) {
            $errors_account['email'] = 'That email address is already in use by another account.';
        }
    }

    // Handle photo upload if provided
    $new_photo = '';
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $new_photo = handle_user_photo($_FILES['profile_photo']);
        } catch (Exception $e) {
            $errors_account['profile_photo'] = $e->getMessage();
        }
    }

    if (empty($errors_account)) {
        try {
            if ($new_photo !== '') {
                $sql = "UPDATE users SET email = ?, profile_photo = ? WHERE user_id = ?";
                mysqli_execute_query($conn, $sql, array($email, $new_photo, $user_id));
            } else {
                $sql = "UPDATE users SET email = ? WHERE user_id = ?";
                mysqli_execute_query($conn, $sql, array($email, $user_id));
            }

            $_SESSION['email'] = $email;
            $_SESSION['success'] = 'Account details updated successfully.';
            header('Location: account.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $errors_account['general'] = 'Failed to update account: ' . $e->getMessage();
        }
    }
}

// ── Handle Password Update ───────────────────────────────────────────────────
if (isset($_POST['change_password'])) {
    if (!csrf_verify()) {
        header('Location: account.php');
        exit;
    }

    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Pure PHP validation (MP5)
    if (empty($current_password)) {
        $errors_password['current_password'] = 'Current password is required.';
    }
    if (empty($new_password)) {
        $errors_password['new_password'] = 'New password is required.';
    } elseif (strlen($new_password) < 6) {
        $errors_password['new_password'] = 'New password must be at least 6 characters.';
    }
    if ($new_password !== $confirm_password) {
        $errors_password['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors_password)) {
        $sql = "SELECT password FROM users WHERE user_id = ? LIMIT 1";
        $res = mysqli_execute_query($conn, $sql, array($user_id));
        $u_row = mysqli_fetch_assoc($res);

        if (!$u_row || !password_verify($current_password, $u_row['password'])) {
            $errors_password['current_password'] = 'Current password is incorrect.';
        } else {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_up = "UPDATE users SET password = ? WHERE user_id = ?";
            mysqli_execute_query($conn, $sql_up, array($new_hash, $user_id));

            $_SESSION['success'] = 'Your password has been changed successfully.';
            header('Location: account.php');
            exit;
        }
    }
}

// Load current admin user record
$sql = "SELECT email, role, status, profile_photo, created_at FROM users WHERE user_id = ? LIMIT 1";
$res = mysqli_execute_query($conn, $sql, array($user_id));
$admin_user = mysqli_fetch_assoc($res);

$base = '../';
$page_title = 'Admin Account | Sportibai';
require '../includes/header.php';
?>
<section class="reveal">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ SYSTEM CREDENTIALS ]</div>
            <h1 style="font-size: var(--text-display-l)">Admin Account</h1>
        </div>
        <div>
            <a href="index.php" class="btn ghost sm">← Back to Dashboard</a>
        </div>
    </div>

    <?php require '../includes/alert.php'; ?>

    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:1.5rem; align-items:start;">
        <!-- Card 1: Account Profile & Email -->
        <div class="card in">
            <div style="display:flex; align-items:center; gap:1.25rem; margin-bottom:1.5rem; padding-bottom:1.25rem; border-bottom:1px solid rgba(255,255,255,0.08);">
                <div style="width:72px; height:72px; border-radius:50%; overflow:hidden; background:rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:center; border:2px solid var(--color-accent-on-dark); flex-shrink:0;">
                    <?php if (!empty($admin_user['profile_photo']) && file_exists(__DIR__ . '/../images/uploads/' . $admin_user['profile_photo'])) { ?>
                        <img src="../images/uploads/<?= htmlspecialchars($admin_user['profile_photo']) ?>" alt="Admin Avatar" style="width:100%; height:100%; object-fit:cover;">
                    <?php } else { ?>
                        <span style="font-size:2rem;">🛡️</span>
                    <?php } ?>
                </div>
                <div>
                    <div style="font-size:1.1rem; font-weight:700; color:var(--color-fg);"><?= htmlspecialchars($admin_user['email']) ?></div>
                    <div style="display:flex; gap:8px; align-items:center; margin-top:4px;">
                        <span class="status-badge" style="background:rgba(255,100,80,0.15); color:var(--color-accent-on-dark); border-color:rgba(255,100,80,0.3); font-size:10px; padding:2px 8px;">ADMINISTRATOR</span>
                        <span class="muted" style="font-size:11px;">Active since <?= date('M Y', strtotime($admin_user['created_at'])) ?></span>
                    </div>
                </div>
            </div>

            <form method="POST" action="account.php" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <div>
                    <div class="hero-eyebrow">[ CREDENTIAL ]</div>
                    <h2 style="font-size:1.2rem; margin-top:2px;">Update Email & Avatar</h2>
                </div>

                <?php if (isset($errors_account['general'])) { ?>
                    <div style="color:#ff9f96; font-size:13px; margin:0.5rem 0;">⚠️ <?= htmlspecialchars($errors_account['general']) ?></div>
                <?php } ?>

                <div style="margin-top:1rem;">
                    <label class="form-label">Email Address</label>
                    <input type="text" name="email" value="<?= htmlspecialchars($_POST['email'] ?? $admin_user['email']) ?>" style="margin-top:0.5rem; <?= isset($errors_account['email']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors_account['email'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors_account['email']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1.25rem;">
                    <label class="form-label">Update Profile Avatar</label>
                    <div style="margin-top:0.5rem;">
                        <input type="file" name="profile_photo" id="admin_photo_input" accept=".jpg,.jpeg,.png,.webp" style="display:none;" onchange="document.getElementById('admin_photo_name').innerText = this.files[0] ? this.files[0].name : '';">
                        <button type="button" class="btn ghost sm" onclick="document.getElementById('admin_photo_input').click();">📷 Choose Avatar Image</button>
                        <span id="admin_photo_name" class="muted" style="font-size:12px; margin-left:10px;"></span>
                    </div>
                    <small class="muted" style="font-size:11px; display:block; margin-top:4px;">JPG, PNG, or WEBP (Max 2MB)</small>
                    <?php if (isset($errors_account['profile_photo'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors_account['profile_photo']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1.5rem;">
                    <button class="btn" type="submit" name="update_account">Save Account Changes</button>
                </div>
            </form>
        </div>

        <!-- Card 2: Security & Password -->
        <div class="card in">
            <form method="POST" action="account.php" novalidate>
                <?= csrf_field() ?>
                <div>
                    <div class="hero-eyebrow">[ ENCRYPTION ]</div>
                    <h2 style="font-size:1.2rem; margin-top:2px;">Change Password</h2>
                    <p class="muted" style="font-size:var(--text-body-sm); margin-top:4px;">Use at least 6 characters with a combination of letters and numbers.</p>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" autocomplete="current-password" style="margin-top:0.5rem; <?= isset($errors_password['current_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors_password['current_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors_password['current_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password (min. 6 chars)" autocomplete="new-password" style="margin-top:0.5rem; <?= isset($errors_password['new_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors_password['new_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors_password['new_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password" style="margin-top:0.5rem; <?= isset($errors_password['confirm_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors_password['confirm_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors_password['confirm_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1.5rem;">
                    <button class="btn" type="submit" name="change_password">Update Password</button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
