<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

// ── Toggle user active/inactive (MP2 Requirement: Admin can deactivate a user) ─
if (isset($_POST['toggle_status'])) {
    if (!csrf_verify()) {
        header('Location: users.php');
        exit;
    }
    $target_id = (int)$_POST['user_id'];
    $new_status = $_POST['new_status'] === 'active' ? 'active' : 'inactive';

    // Prevent deactivating own account
    if ($target_id === (int)$_SESSION['user_id']) {
        $_SESSION['message'] = 'You cannot deactivate your own account.';
    } else {
        $sql = "UPDATE users SET status = ? WHERE user_id = ?";
        mysqli_execute_query($conn, $sql, array($new_status, $target_id));
        $_SESSION['success'] = 'User status updated to ' . $new_status . '.';
    }
    header('Location: users.php');
    exit;
}

// ── Update active user's role (MP2 Requirement: Admin updates an active user's role) ─
if (isset($_POST['update_role'])) {
    if (!csrf_verify()) {
        header('Location: users.php');
        exit;
    }
    $target_id = (int)$_POST['user_id'];
    $new_role  = ($_POST['role'] === 'admin') ? 'admin' : 'user';

    if ($target_id === (int)$_SESSION['user_id']) {
        $_SESSION['message'] = 'You cannot modify your own administrative role.';
    } else {
        // Can only update active user's role per requirement
        $sql = "UPDATE users SET role = ? WHERE user_id = ? AND status = 'active'";
        mysqli_execute_query($conn, $sql, array($new_role, $target_id));
        if (mysqli_affected_rows($conn) > 0) {
            $_SESSION['success'] = 'User role successfully updated to ' . strtoupper($new_role) . '.';
        } else {
            $_SESSION['message'] = 'Role unchanged or user is not active.';
        }
    }
    header('Location: users.php');
    exit;
}

// ── Fetch users with optional search & filter ────────────────────────────────
$search_u = trim($_GET['q'] ?? '');
$filter_role = $_GET['role'] ?? 'all';
$filter_status = $_GET['status'] ?? 'all';

$users = array();
$where_u = array();
$params_u = array();

if ($search_u !== '') {
    $where_u[] = "(u.email LIKE ? OR c.first_name LIKE ? OR c.last_name LIKE ? OR c.phone LIKE ?)";
    $like = '%' . $search_u . '%';
    $params_u[] = $like; $params_u[] = $like; $params_u[] = $like; $params_u[] = $like;
}
if ($filter_role === 'admin' || $filter_role === 'user') {
    $where_u[] = "u.role = ?";
    $params_u[] = $filter_role;
}
if ($filter_status === 'active' || $filter_status === 'inactive') {
    $where_u[] = "u.status = ?";
    $params_u[] = $filter_status;
}

$where_sql = count($where_u) > 0 ? ('WHERE ' . implode(' AND ', $where_u)) : '';
$sql = "SELECT u.user_id, u.email, u.role, u.status, u.profile_photo, u.created_at,
               c.first_name, c.last_name, c.phone
        FROM users u
        LEFT JOIN customer c ON u.user_id = c.user_id
        $where_sql
        ORDER BY u.user_id DESC";
$res = count($params_u) > 0 ? mysqli_execute_query($conn, $sql, $params_u) : mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($res)) {
    $users[] = $row;
}

$base = '../';
$page_title = 'Manage Users | Sportibai Admin';
require '../includes/header.php';
?>

<section class="reveal in">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ IDENTITY & ACCESS CONTROL ]</div>
            <h1 style="font-size:var(--text-display-l); line-height:1.15; margin:2px 0 0;">User Accounts</h1>
        </div>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- User Search & Filter Toolbar -->
    <form method="GET" action="users.php" class="filter-search-bar" style="margin-bottom:1.75rem;">
        <input type="search" name="q" class="search" placeholder="Search by name, email, or phone…" value="<?= htmlspecialchars($search_u) ?>" style="min-width:14rem;">
        
        <select name="role" class="search" style="max-width:11rem;" onchange="this.form.submit()">
            <option value="all" <?= $filter_role === 'all' ? 'selected' : '' ?>>All Roles</option>
            <option value="user" <?= $filter_role === 'user' ? 'selected' : '' ?>>Customer (User)</option>
            <option value="admin" <?= $filter_role === 'admin' ? 'selected' : '' ?>>Administrator</option>
        </select>

        <select name="status" class="search" style="max-width:11rem;" onchange="this.form.submit()">
            <option value="all" <?= $filter_status === 'all' ? 'selected' : '' ?>>All Statuses</option>
            <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active Only</option>
            <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
        </select>

        <button class="btn sm" type="submit">Filter</button>
        <?php if ($search_u !== '' || $filter_role !== 'all' || $filter_status !== 'all') { ?>
            <a href="users.php" class="btn ghost sm">Reset</a>
        <?php } ?>
    </form>

    <div class="order-list">
        <?php foreach ($users as $u) {
            $is_self = ($u['user_id'] == $_SESSION['user_id']);
            $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
        ?>
        <div class="order-card reveal in" style="padding:1.25rem 1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem;">
            <div style="display:flex; align-items:center; gap:1rem;">
                <!-- Profile Avatar (MP2 Requirement) -->
                <div style="width:48px; height:48px; border-radius:50%; overflow:hidden; background:rgba(255,255,255,0.06); display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,0.15); flex-shrink:0;">
                    <?php if (!empty($u['profile_photo']) && file_exists(__DIR__ . '/../images/uploads/' . $u['profile_photo'])) { ?>
                        <img src="../images/uploads/<?= htmlspecialchars($u['profile_photo']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                    <?php } else { ?>
                        <span style="font-size:1.4rem; opacity:0.8;">👤</span>
                    <?php } ?>
                </div>

                <div>
                    <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                        <strong style="font-size:1.05rem;"><?= htmlspecialchars($u['email']) ?></strong>
                        <span class="status-badge" style="<?= $u['status'] === 'active' ? 'background:rgba(81,175,67,0.18);color:#51af43;border-color:rgba(81,175,67,0.35);' : 'background:rgba(200,60,50,0.15);color:#ff9f96;border-color:rgba(255,100,80,0.3);' ?>">
                            <?= htmlspecialchars(strtoupper($u['status'])) ?>
                        </span>
                        <span class="brand-tag" style="font-size:10px; <?= $u['role'] === 'admin' ? 'background:rgba(255,165,0,0.15); color:#f59e0b; border-color:rgba(255,165,0,0.3);' : '' ?>">
                            <?= strtoupper($u['role']) ?>
                        </span>
                    </div>
                    <div class="muted" style="font-size:var(--text-body-sm); margin-top:4px;">
                        <?= $name ? htmlspecialchars($name) . ' &nbsp;·&nbsp; ' : '' ?>
                        <?= $u['phone'] ? '📞 ' . htmlspecialchars($u['phone']) . ' &nbsp;·&nbsp; ' : '' ?>
                        Joined <?= date('M d, Y', strtotime($u['created_at'])) ?>
                    </div>
                </div>
            </div>

            <div style="display:flex; align-items:center; gap:0.75rem; flex-wrap:wrap;">
                <?php if ($is_self) { ?>
                    <span class="muted" style="font-size:var(--text-body-sm); font-style:italic;">Your account</span>
                <?php } else { ?>
                    <!-- MP2: Admin updates active user's role -->
                    <?php if ($u['status'] === 'active') { ?>
                        <form method="POST" action="users.php" style="display:inline-flex; align-items:center; gap:6px;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                            <select name="role" style="padding:0.4rem 0.6rem; font-size:12px; background:rgba(0,0,0,0.4); border:1px solid rgba(255,255,255,0.15); color:#fff; border-radius:6px;">
                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <button type="submit" name="update_role" class="btn ghost sm" style="padding:0.4rem 0.75rem; font-size:11px;">
                                Change Role
                            </button>
                        </form>
                    <?php } ?>

                    <!-- MP2: Admin can deactivate/activate user -->
                    <form method="POST" action="users.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                        <?php if ($u['status'] === 'active') { ?>
                            <input type="hidden" name="new_status" value="inactive">
                            <button type="submit" name="toggle_status" class="btn ghost sm"
                                onclick="return confirm('Deactivate this account? They will be logged out.')"
                                style="color:#ff9f96; border-color:rgba(255,100,80,0.3); padding:0.4rem 0.75rem;">
                                Deactivate
                            </button>
                        <?php } else { ?>
                            <input type="hidden" name="new_status" value="active">
                            <button type="submit" name="toggle_status" class="btn sm"
                                style="background:rgba(81,175,67,0.85); padding:0.4rem 0.75rem;">
                                Activate
                            </button>
                        <?php } ?>
                    </form>
                <?php } ?>
            </div>
        </div>
        <?php } ?>
    </div>
</section>

<?php require '../includes/footer.php'; ?>
