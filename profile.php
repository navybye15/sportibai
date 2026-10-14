<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '';
require_once __DIR__ . '/includes/customer_guard.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

// Only logged-in users may access this page
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$return_to = $_GET['return'] ?? ($_POST['return'] ?? '');

if (isset($_POST['submit'])) {
    if (!csrf_verify()) {
        header('Location: profile.php' . ($return_to ? '?return=' . urlencode($return_to) : ''));
        exit;
    }
    // strip_tags removes any HTML someone might type in, like <script> tags
    $first_name = strip_tags(trim($_POST['first_name']));
    $last_name  = strip_tags(trim($_POST['last_name']));
    $address    = strip_tags(trim($_POST['address']));
    $province   = strip_tags(trim($_POST['province'] ?? ''));
    $postal_code= strip_tags(trim($_POST['postal_code'] ?? ''));
    $landmark   = strip_tags(trim($_POST['landmark'] ?? ''));
    $phone      = strip_tags(trim($_POST['phone']));

    if (!preg_match('/^09\d{9}$/', $phone)) {
        $_SESSION['message'] = 'Please enter a valid 11-digit mobile number (e.g. 09171234567).';
        header('Location: profile.php' . ($return_to ? '?return=' . urlencode($return_to) : ''));
        exit;
    }

    if ($conn == null) {
        $_SESSION['message'] = 'Database is not connected yet.';
        header('Location: profile.php' . ($return_to ? '?return=' . urlencode($return_to) : ''));
        exit;
    }

    try {
        // Does this user already have a customer profile row?
        $sql = "SELECT customer_id FROM customer WHERE user_id = ? LIMIT 1";
        $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
        $number = mysqli_num_rows($result);

        if ($number == 1) {
            // Already has one, update it
            $sql = "UPDATE customer SET first_name = ?, last_name = ?, address = ?, province = ?, postal_code = ?, landmark = ?, phone = ? WHERE user_id = ?";
            mysqli_execute_query($conn, $sql, array($first_name, $last_name, $address, $province, $postal_code, $landmark, $phone, $_SESSION['user_id']));
        } else {
            // First time, insert a new row
            $sql = "INSERT INTO customer (user_id, first_name, last_name, address, province, postal_code, landmark, phone) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            mysqli_execute_query($conn, $sql, array($_SESSION['user_id'], $first_name, $last_name, $address, $province, $postal_code, $landmark, $phone));
        }

        $_SESSION['success'] = 'Profile saved successfully.';
        if ($return_to === 'checkout') {
            header('Location: checkout.php');
        } else {
            header('Location: profile.php');
        }
        exit;
    } catch (mysqli_sql_exception $e) {
        $_SESSION['message'] = 'Something went wrong. Please try again.';
        header('Location: profile.php' . ($return_to ? '?return=' . urlencode($return_to) : ''));
        exit;
    }
}

if (isset($_POST['change_password'])) {
    if (!csrf_verify()) {
        header('Location: profile.php');
        exit;
    }
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($conn == null) {
        $_SESSION['message'] = 'Database is not connected yet.';
        header('Location: profile.php');
        exit;
    }

    if (empty($current_password) || empty($new_password)) {
        $_SESSION['message'] = 'Please fill in all password fields.';
        header('Location: profile.php');
        exit;
    }

    if ($new_password !== $confirm_password) {
        $_SESSION['message'] = 'New password and confirmation do not match.';
        header('Location: profile.php');
        exit;
    }

    if (strlen($new_password) < 6) {
        $_SESSION['message'] = 'New password must be at least 6 characters long.';
        header('Location: profile.php');
        exit;
    }

    try {
        $sql = "SELECT password FROM users WHERE user_id = ? LIMIT 1";
        $res = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
        $user_row = mysqli_fetch_assoc($res);

        if (!$user_row || !password_verify($current_password, $user_row['password'])) {
            $_SESSION['message'] = 'Current password is incorrect.';
            header('Location: profile.php');
            exit;
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
        mysqli_execute_query($conn, $update_sql, array($new_hash, $_SESSION['user_id']));

        $_SESSION['success'] = 'Password updated successfully!';
        header('Location: profile.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        $_SESSION['message'] = 'Failed to update password. Please try again.';
        header('Location: profile.php');
        exit;
    }
}

// Load current profile, if any, so the form shows existing values
$first_name = '';
$last_name = '';
$address = '';
$province = '';
$postal_code = '';
$landmark = '';
$phone = '';
if ($conn != null) {
    $sql = "SELECT first_name, last_name, address, province, postal_code, landmark, phone FROM customer WHERE user_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $first_name = $row['first_name'] ?? '';
        $last_name = $row['last_name'] ?? '';
        $address = $row['address'] ?? '';
        $province = $row['province'] ?? '';
        $postal_code = $row['postal_code'] ?? '';
        $landmark = $row['landmark'] ?? '';
        $phone = $row['phone'] ?? '';
    }
}

$page_title = 'My Profile | Sportibai';
require_once __DIR__ . '/includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
        <?= csrf_field() ?>
        <?php if ($return_to) { ?>
            <input type="hidden" name="return" value="<?= htmlspecialchars($return_to) ?>">
        <?php } ?>
        <div>
            <div class="hero-eyebrow">[ ATHLETE IDENTITY ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top: 6px;">My Profile</h1>
        </div>
        <?php require __DIR__ . '/includes/alert.php'; ?>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top:-0.5rem;">Your shipping and contact info needed to verify and complete orders.</p>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label class="form-label">First Name</label>
                <input type="text" name="first_name" placeholder="First name" value="<?php echo htmlspecialchars($first_name); ?>" required style="margin-top:0.75rem;">
            </div>
            <div>
                <label class="form-label">Last Name</label>
                <input type="text" name="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($last_name); ?>" required style="margin-top:0.75rem;">
            </div>
        </div>
        
        <div>
            <label class="form-label">Street / Barangay</label>
            <input type="text" name="address" placeholder="e.g. 123 Mabini St., Lahug" value="<?php echo htmlspecialchars($address); ?>" required style="margin-top:0.75rem;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label class="form-label">Province</label>
                <input type="text" name="province" placeholder="e.g. Cebu" value="<?php echo htmlspecialchars($province); ?>" style="margin-top:0.75rem;">
            </div>
            <div>
                <label class="form-label">Postal Code</label>
                <input type="text" name="postal_code" placeholder="e.g. 6000" value="<?php echo htmlspecialchars($postal_code); ?>" style="margin-top:0.75rem;">
            </div>
        </div>

        <div>
            <label class="form-label">Landmark / Note</label>
            <textarea name="landmark" class="co-input" rows="2" placeholder="e.g. Beside 7-Eleven" style="margin-top:0.75rem;"><?php echo htmlspecialchars($landmark); ?></textarea>
        </div>
        
        <div>
            <label class="form-label">Contact Number</label>
            <input type="tel" name="phone" placeholder="09171234567" pattern="09[0-9]{9}" maxlength="11" title="Please enter an 11-digit mobile number starting with 09" value="<?php echo htmlspecialchars($phone); ?>" required style="margin-top:0.75rem;">
        </div>

        <button class="btn" style="width:100%; margin-top:0.5rem;" type="submit" name="submit">Save Profile Details</button>
    </form>

    <!-- Change Password Form -->
    <form class="form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" style="margin-top:2rem;">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ ACCOUNT SECURITY ]</div>
            <h2 style="font-size: var(--text-display-s); margin-top: 6px;">Change Password</h2>
        </div>
        <p class="muted" style="font-size:var(--text-body-sm); margin-top:-0.5rem;">Update your account password. Must be at least 6 characters.</p>

        <div>
            <label class="form-label">Current Password</label>
            <input type="password" name="current_password" placeholder="Enter current password" required autocomplete="current-password" style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)" minlength="6" required autocomplete="new-password" style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Re-enter new password" minlength="6" required autocomplete="new-password" style="margin-top:0.75rem;">
        </div>

        <button class="btn ghost" style="width:100%; margin-top:0.5rem;" type="submit" name="change_password">Update Password</button>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
