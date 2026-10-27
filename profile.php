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
    $_SESSION['message'] = 'Please log in to access your profile.';
    header('Location: auth/login.php');
    exit;
}

$return_to = $_GET['return'] ?? ($_POST['return'] ?? '');
$active_tab = $_GET['tab'] ?? ($_POST['active_tab'] ?? 'personal');
$valid_tabs = array('personal', 'address', 'security');
if (!in_array($active_tab, $valid_tabs)) {
    $active_tab = 'personal';
}

$errors = array();
$password_errors = array();

// ── Handle Tab 1 & Tab 2: Profile & Shipping Details Update ─────────────────
if (isset($_POST['save_profile']) || isset($_POST['save_and_next'])) {
    if (!csrf_verify()) {
        header('Location: profile.php' . ($return_to ? '?return=' . urlencode($return_to) : ''));
        exit;
    }

    if ($active_tab === 'personal') {
        $first_name = strip_tags(trim($_POST['first_name'] ?? ''));
        $last_name  = strip_tags(trim($_POST['last_name'] ?? ''));
        $phone      = strip_tags(trim($_POST['phone'] ?? ''));

        // Pure PHP validation (MP5 Requirement: No HTML5 validation attributes)
        if ($first_name === '') {
            $errors['first_name'] = 'First name is required.';
        }
        if ($last_name === '') {
            $errors['last_name'] = 'Last name is required.';
        }
        if ($phone === '') {
            $errors['phone'] = 'Contact number is required.';
        } elseif (!preg_match('/^09\d{9}$/', $phone)) {
            $errors['phone'] = 'Please enter a valid 11-digit Philippine mobile number starting with 09 (e.g. 09171234567).';
        }

        // Photo upload handling (MP2 Requirement: Update profile with upload photo)
        $uploaded_photo = '';
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploaded_photo = handle_user_photo($_FILES['profile_photo']);
            } catch (Exception $e) {
                $errors['profile_photo'] = $e->getMessage();
            }
        }

        if (empty($errors)) {
            if ($conn == null) {
                $_SESSION['message'] = 'Database is not connected yet.';
                header('Location: profile.php?tab=personal' . ($return_to ? '&return=' . urlencode($return_to) : ''));
                exit;
            }

            try {
                // Update profile photo in users table if a new photo was uploaded
                if ($uploaded_photo !== '') {
                    $sql = "UPDATE users SET profile_photo = ? WHERE user_id = ?";
                    mysqli_execute_query($conn, $sql, array($uploaded_photo, $_SESSION['user_id']));
                }

                // Upsert customer record (only update personal details)
                $sql = "SELECT customer_id, address FROM customer WHERE user_id = ? LIMIT 1";
                $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
                $has_address = false;
                if ($row = mysqli_fetch_assoc($result)) {
                    $has_address = !empty(trim($row['address'] ?? ''));
                    $sql = "UPDATE customer SET first_name = ?, last_name = ?, phone = ? WHERE user_id = ?";
                    mysqli_execute_query($conn, $sql, array($first_name, $last_name, $phone, $_SESSION['user_id']));
                } else {
                    $sql = "INSERT INTO customer (user_id, first_name, last_name, phone, address, province, postal_code, landmark) VALUES (?, ?, ?, ?, '', '', '', '')";
                    mysqli_execute_query($conn, $sql, array($_SESSION['user_id'], $first_name, $last_name, $phone));
                }

                if (isset($_POST['save_and_next'])) {
                    $_SESSION['success'] = 'Personal details saved. Now add your delivery address.';
                    header('Location: profile.php?tab=address' . ($return_to ? '&return=' . urlencode($return_to) : ''));
                    exit;
                }

                if ($return_to === 'checkout') {
                    if (!$has_address) {
                        $_SESSION['message'] = 'Personal details saved! Please add your delivery address to proceed with checkout.';
                        header('Location: profile.php?tab=address&return=checkout');
                        exit;
                    }
                    $_SESSION['success'] = 'Personal details saved successfully.';
                    header('Location: checkout.php');
                    exit;
                } else {
                    $_SESSION['success'] = 'Personal details saved successfully.';
                    header('Location: profile.php?tab=personal');
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                $errors['general'] = 'Database error: ' . $e->getMessage();
            }
        }
    } elseif ($active_tab === 'address') {
        $address     = strip_tags(trim($_POST['address'] ?? ''));
        $province    = strip_tags(trim($_POST['province'] ?? ''));
        $postal_code = strip_tags(trim($_POST['postal_code'] ?? ''));
        $landmark    = strip_tags(trim($_POST['landmark'] ?? ''));

        // Pure PHP validation
        if ($address === '') {
            $errors['address'] = 'Street / Barangay address is required.';
        }

        if (empty($errors)) {
            if ($conn == null) {
                $_SESSION['message'] = 'Database is not connected yet.';
                header('Location: profile.php?tab=address' . ($return_to ? '&return=' . urlencode($return_to) : ''));
                exit;
            }

            try {
                // Upsert customer record (only update delivery address details)
                $sql = "SELECT customer_id, first_name, phone FROM customer WHERE user_id = ? LIMIT 1";
                $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
                $has_personal = false;
                if ($row = mysqli_fetch_assoc($result)) {
                    $has_personal = !empty(trim($row['first_name'] ?? '')) && !empty(trim($row['phone'] ?? ''));
                    $sql = "UPDATE customer SET address = ?, province = ?, postal_code = ?, landmark = ? WHERE user_id = ?";
                    mysqli_execute_query($conn, $sql, array($address, $province, $postal_code, $landmark, $_SESSION['user_id']));
                } else {
                    $sql = "INSERT INTO customer (user_id, first_name, last_name, phone, address, province, postal_code, landmark) VALUES (?, '', '', '', ?, ?, ?, ?)";
                    mysqli_execute_query($conn, $sql, array($_SESSION['user_id'], $address, $province, $postal_code, $landmark));
                }

                if ($return_to === 'checkout') {
                    if (!$has_personal) {
                        $_SESSION['message'] = 'Delivery address saved! Please complete your name and contact number to proceed with checkout.';
                        header('Location: profile.php?tab=personal&return=checkout');
                        exit;
                    }
                    $_SESSION['success'] = 'Delivery address saved successfully.';
                    header('Location: checkout.php');
                    exit;
                } else {
                    $_SESSION['success'] = 'Delivery address saved successfully.';
                    header('Location: profile.php?tab=address');
                    exit;
                }
            } catch (mysqli_sql_exception $e) {
                $errors['general'] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// ── Handle Tab 3: Password Update ───────────────────────────────────────────
if (isset($_POST['change_password'])) {
    if (!csrf_verify()) {
        header('Location: profile.php?tab=security');
        exit;
    }
    $active_tab = 'security';
    $current_password = $_POST['current_password'] ?? '';
    $new_password     = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Pure PHP validation (MP5)
    if (empty($current_password)) {
        $password_errors['current_password'] = 'Current password is required.';
    }
    if (empty($new_password)) {
        $password_errors['new_password'] = 'New password is required.';
    } elseif (strlen($new_password) < 6) {
        $password_errors['new_password'] = 'New password must be at least 6 characters long.';
    }
    if ($new_password !== $confirm_password) {
        $password_errors['confirm_password'] = 'New password and confirmation do not match.';
    }

    if (empty($password_errors)) {
        try {
            $sql = "SELECT password FROM users WHERE user_id = ? LIMIT 1";
            $res = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
            $user_row = mysqli_fetch_assoc($res);

            if (!$user_row || !password_verify($current_password, $user_row['password'])) {
                $password_errors['current_password'] = 'Current password is incorrect.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
                mysqli_execute_query($conn, $update_sql, array($new_hash, $_SESSION['user_id']));

                $_SESSION['success'] = 'Password updated successfully!';
                header('Location: profile.php?tab=security');
                exit;
            }
        } catch (mysqli_sql_exception $e) {
            $password_errors['general'] = 'Failed to update password. Please try again.';
        }
    }
}

// ── Load Current Data with Post-Submission Retention ─────────────────────────
$user_photo = '';
$user_email = '';
$db_first_name  = '';
$db_last_name   = '';
$db_address     = '';
$db_province    = '';
$db_postal_code = '';
$db_landmark    = '';
$db_phone       = '';

if ($conn != null) {
    $u_res = mysqli_execute_query($conn, "SELECT email, profile_photo FROM users WHERE user_id = ? LIMIT 1", array($_SESSION['user_id']));
    if ($u_row = mysqli_fetch_assoc($u_res)) {
        $user_email = $u_row['email'];
        $user_photo = $u_row['profile_photo'] ?? '';
    }

    $sql = "SELECT first_name, last_name, address, province, postal_code, landmark, phone FROM customer WHERE user_id = ? LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        $db_first_name  = $row['first_name'] ?? '';
        $db_last_name   = $row['last_name'] ?? '';
        $db_address     = $row['address'] ?? '';
        $db_province    = $row['province'] ?? '';
        $db_postal_code = $row['postal_code'] ?? '';
        $db_landmark    = $row['landmark'] ?? '';
        $db_phone       = $row['phone'] ?? '';
    }
}

// Retain typed values if validation error occurred on active tab, otherwise use DB values
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($errors)) {
    if ($active_tab === 'personal') {
        $first_name  = $_POST['first_name'] ?? $db_first_name;
        $last_name   = $_POST['last_name'] ?? $db_last_name;
        $phone       = $_POST['phone'] ?? $db_phone;
        $address     = $db_address;
        $province    = $db_province;
        $postal_code = $db_postal_code;
        $landmark    = $db_landmark;
    } else {
        $first_name  = $db_first_name;
        $last_name   = $db_last_name;
        $phone       = $db_phone;
        $address     = $_POST['address'] ?? $db_address;
        $province    = $_POST['province'] ?? $db_province;
        $postal_code = $_POST['postal_code'] ?? $db_postal_code;
        $landmark    = $_POST['landmark'] ?? $db_landmark;
    }
} else {
    $first_name  = $db_first_name;
    $last_name   = $db_last_name;
    $phone       = $db_phone;
    $address     = $db_address;
    $province    = $db_province;
    $postal_code = $db_postal_code;
    $landmark    = $db_landmark;
}

$page_title = 'My Profile | Sportibai';
require_once __DIR__ . '/includes/header.php';
?>
<section class="reveal">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ ATHLETE IDENTITY ]</div>
            <h1 style="font-size: var(--text-display-l); margin-top: 4px;">My Profile</h1>
            <p class="muted" style="font-size:var(--text-body-sm); margin-top:2px;">Manage your personal identity, delivery destination, and account security.</p>
        </div>
        <?php if ($return_to === 'checkout') { ?>
            <a href="checkout.php" class="btn ghost sm">← Back to Checkout</a>
        <?php } ?>
    </div>

    <?php require __DIR__ . '/includes/alert.php'; ?>

    <!-- Modular Profile Tabs -->
    <div class="order-tabs" style="margin-bottom:1.5rem;">
        <a href="profile.php?tab=personal<?= $return_to ? '&return=' . urlencode($return_to) : '' ?>" class="order-tab <?= $active_tab === 'personal' ? 'active' : '' ?>">
            👤 Personal Info
        </a>
        <a href="profile.php?tab=address<?= $return_to ? '&return=' . urlencode($return_to) : '' ?>" class="order-tab <?= $active_tab === 'address' ? 'active' : '' ?>">
            📍 Delivery Address
        </a>
        <a href="profile.php?tab=security<?= $return_to ? '&return=' . urlencode($return_to) : '' ?>" class="order-tab <?= $active_tab === 'security' ? 'active' : '' ?>">
            🔒 Account Security
        </a>
    </div>

    <?php if ($active_tab === 'personal' || $active_tab === 'address') { ?>
    <!-- Profile & Address Form -->
    <form class="form" method="POST" action="profile.php" enctype="multipart/form-data" novalidate style="max-width:100%;">
        <?= csrf_field() ?>
        <input type="hidden" name="active_tab" value="<?= htmlspecialchars($active_tab) ?>">
        <?php if ($return_to) { ?>
            <input type="hidden" name="return" value="<?= htmlspecialchars($return_to) ?>">
        <?php } ?>

        <?php if (isset($errors['general'])) { ?>
            <div style="color:#ff9f96; font-size:13px; margin-bottom:1rem;">⚠️ <?= htmlspecialchars($errors['general']) ?></div>
        <?php } ?>

        <?php if ($active_tab === 'personal') { ?>
            <!-- TAB 1: Personal Info & Avatar -->
            <div class="card in" style="margin-bottom:1.5rem;">
                <div style="display:flex; align-items:center; gap:1.5rem; flex-wrap:wrap;">
                    <div style="width:84px; height:84px; border-radius:50%; overflow:hidden; background:rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:center; border:2px solid var(--color-accent-on-dark); flex-shrink:0;">
                        <?php if ($user_photo && file_exists(__DIR__ . '/images/uploads/' . $user_photo)) { ?>
                            <img src="images/uploads/<?= htmlspecialchars($user_photo) ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover;">
                        <?php } else { ?>
                            <span style="font-size:2.5rem;">👤</span>
                        <?php } ?>
                    </div>
                    <div style="flex:1; min-width:200px;">
                        <div style="font-size:1.15rem; font-weight:700; color:var(--color-fg);"><?= htmlspecialchars($first_name || $last_name ? "$first_name $last_name" : 'Athlete Member') ?></div>
                        <div class="muted" style="font-size:13px;"><?= htmlspecialchars($user_email) ?></div>
                        
                        <div style="margin-top:0.75rem;">
                            <input type="file" name="profile_photo" id="profile_photo_file" accept=".jpg,.jpeg,.png,.webp" style="display:none;" onchange="document.getElementById('photo_file_name').innerText = this.files[0] ? this.files[0].name : '';">
                            <button type="button" class="btn ghost sm" onclick="document.getElementById('profile_photo_file').click();">📷 Change Profile Photo</button>
                            <span id="photo_file_name" class="muted" style="font-size:12px; margin-left:8px;"></span>
                        </div>
                        <span class="muted" style="font-size:11px; display:block; margin-top:4px;">JPG, PNG, or WEBP (Max 10MB)</span>
                        <?php if (isset($errors['profile_photo'])) { ?>
                            <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['profile_photo']) ?></div>
                        <?php } ?>
                    </div>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1.5rem;">
                    <div>
                        <label class="form-label">First Name</label>
                        <input type="text" name="first_name" placeholder="First name" value="<?php echo htmlspecialchars($first_name); ?>" style="margin-top:0.5rem; <?= isset($errors['first_name']) ? 'border-color:#ff6b6b;' : '' ?>">
                        <?php if (isset($errors['first_name'])) { ?>
                            <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['first_name']) ?></div>
                        <?php } ?>
                    </div>
                    <div>
                        <label class="form-label">Last Name</label>
                        <input type="text" name="last_name" placeholder="Last name" value="<?php echo htmlspecialchars($last_name); ?>" style="margin-top:0.5rem; <?= isset($errors['last_name']) ? 'border-color:#ff6b6b;' : '' ?>">
                        <?php if (isset($errors['last_name'])) { ?>
                            <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['last_name']) ?></div>
                        <?php } ?>
                    </div>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Contact Mobile Number (11-digit PH mobile)</label>
                    <input type="text" name="phone" placeholder="09171234567" value="<?php echo htmlspecialchars($phone); ?>" style="margin-top:0.5rem; <?= isset($errors['phone']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors['phone'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['phone']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1.5rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <button class="btn" type="submit" name="save_profile">Save Personal Details</button>
                    <button class="btn ghost sm" type="submit" name="save_and_next" value="address">Save &amp; Go to Delivery Address →</button>
                    <a href="profile.php?tab=address<?= $return_to ? '&return=' . urlencode($return_to) : '' ?>" class="muted" style="font-size:12px; margin-left:auto;">Switch to Delivery Address →</a>
                </div>
            </div>
        <?php } ?>

        <?php if ($active_tab === 'address') { ?>
            <!-- TAB 2: Shipping Address -->
            <div class="card in" style="margin-bottom:1.5rem;">
                <div>
                    <div class="hero-eyebrow">[ FULFILLMENT DESTINATION ]</div>
                    <h2 style="font-size:1.25rem; margin-top:2px;">Delivery Address</h2>
                    <p class="muted" style="font-size:var(--text-body-sm); margin-top:2px;">Where your sports gear and parcels will be dispatched.</p>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Street Address / House No. / Barangay</label>
                    <input type="text" name="address" placeholder="e.g. 123 Mabini St., Brgy. Lahug" value="<?php echo htmlspecialchars($address); ?>" style="margin-top:0.5rem; <?= isset($errors['address']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($errors['address'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($errors['address']) ?></div>
                    <?php } ?>
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-top:1rem;">
                    <div>
                        <label class="form-label">Province / City</label>
                        <input type="text" name="province" placeholder="e.g. Cebu" value="<?php echo htmlspecialchars($province); ?>" style="margin-top:0.5rem;">
                    </div>
                    <div>
                        <label class="form-label">Postal Code</label>
                        <input type="text" name="postal_code" placeholder="e.g. 6000" value="<?php echo htmlspecialchars($postal_code); ?>" style="margin-top:0.5rem;">
                    </div>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Landmark / Delivery Note</label>
                    <textarea name="landmark" rows="2" placeholder="e.g. Near 7-Eleven, blue gate" style="margin-top:0.5rem;"><?php echo htmlspecialchars($landmark); ?></textarea>
                </div>

                <div style="margin-top:1.5rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
                    <button class="btn" type="submit" name="save_profile">Save Delivery Address</button>
                    <a href="profile.php?tab=personal<?= $return_to ? '&return=' . urlencode($return_to) : '' ?>" class="btn ghost sm">← Back to Personal Info</a>
                    <?php if ($return_to === 'checkout') { ?>
                        <button class="btn sm" type="submit" name="save_profile" style="margin-left:auto;">Save &amp; Proceed to Checkout →</button>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </form>
    <?php } ?>

    <?php if ($active_tab === 'security') { ?>
        <!-- TAB 3: Account Security (Change Password) -->
        <div class="card in" style="max-width:32rem;">
            <form method="POST" action="profile.php?tab=security" novalidate>
                <?= csrf_field() ?>
                <div>
                    <div class="hero-eyebrow">[ ACCOUNT SECURITY ]</div>
                    <h2 style="font-size:1.25rem; margin-top:2px;">Change Password</h2>
                    <p class="muted" style="font-size:var(--text-body-sm); margin-top:2px;">Must be at least 6 characters long.</p>
                </div>

                <?php if (isset($password_errors['general'])) { ?>
                    <div style="color:#ff9f96; font-size:13px; margin:0.75rem 0;">⚠️ <?= htmlspecialchars($password_errors['general']) ?></div>
                <?php } ?>

                <div style="margin-top:1.25rem;">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" placeholder="Enter current password" autocomplete="current-password" style="margin-top:0.5rem; <?= isset($password_errors['current_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($password_errors['current_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($password_errors['current_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password (min. 6 characters)" autocomplete="new-password" style="margin-top:0.5rem; <?= isset($password_errors['new_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($password_errors['new_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($password_errors['new_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1rem;">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password" style="margin-top:0.5rem; <?= isset($password_errors['confirm_password']) ? 'border-color:#ff6b6b;' : '' ?>">
                    <?php if (isset($password_errors['confirm_password'])) { ?>
                        <div style="color:#ff9f96; font-size:12px; margin-top:4px;">⚠️ <?= htmlspecialchars($password_errors['confirm_password']) ?></div>
                    <?php } ?>
                </div>

                <div style="margin-top:1.5rem;">
                    <button class="btn" type="submit" name="change_password">Update Password</button>
                </div>
            </form>
        </div>
    <?php } ?>
</section>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
