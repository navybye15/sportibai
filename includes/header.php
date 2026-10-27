<?php
if (session_status() == PHP_SESSION_NONE) session_start();
if (!isset($base))       $base = '';
if (!isset($page_title)) $page_title = 'Sportibai';
$is_admin = false;
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    $is_admin = true;
}
if (isset($conn) && function_exists('check_user_session')) {
    check_user_session($conn);
}
$cart_count = 0;
if (isset($_SESSION['cart'])) $cart_count = array_sum($_SESSION['cart']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Sportibai — Premium sports gear for every court, gym, and road. Shop balls, footwear, apparel, and equipment.">
<title><?= htmlspecialchars($page_title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $base ?>css/style.css?v=<?= @filemtime(__DIR__ . '/../css/style.css') ?: time() ?>">
</head>
<body>
<div class="nav-container">
    <header class="nav">
    <?php if ($is_admin) { 
        $admin_cur_page = basename($_SERVER['PHP_SELF']);
        $admin_pending_orders = 0;
        if (isset($conn) && $conn) {
            $p_res = @mysqli_query($conn, "SELECT COUNT(*) as c FROM orders WHERE status = 'Pending'");
            if ($p_res) $admin_pending_orders = (int)(mysqli_fetch_assoc($p_res)['c'] ?? 0);
        }
    ?>
        <a class="brand" href="<?= $base ?>admin/index.php">
            SPORTI<span class="ops">BAI</span> 
            <span class="admin-badge-chip">ADMIN</span>
        </a>
        <nav class="nav-links admin-nav-links">
            <a href="<?= $base ?>admin/index.php" class="<?= $admin_cur_page === 'index.php' ? 'active' : '' ?>">Dashboard</a>
            <a href="<?= $base ?>admin/orders.php" class="<?= $admin_cur_page === 'orders.php' ? 'active' : '' ?>">
                Orders
                <?php if ($admin_pending_orders > 0) { ?>
                    <span class="admin-pill-badge"><?= $admin_pending_orders ?></span>
                <?php } ?>
            </a>
            <a href="<?= $base ?>admin/items.php" class="<?= in_array($admin_cur_page, array('items.php','item_edit.php')) ? 'active' : '' ?>">Inventory</a>
            <a href="<?= $base ?>admin/categories.php" class="<?= $admin_cur_page === 'categories.php' ? 'active' : '' ?>">Categories</a>
            <a href="<?= $base ?>admin/sales_report.php" class="<?= $admin_cur_page === 'sales_report.php' ? 'active' : '' ?>">Financials</a>
            <a href="<?= $base ?>admin/expenses.php" class="<?= in_array($admin_cur_page, array('expenses.php','expense_edit.php')) ? 'active' : '' ?>">Expenses</a>
            <a href="<?= $base ?>admin/users.php" class="<?= $admin_cur_page === 'users.php' ? 'active' : '' ?>">Users</a>
            <a href="<?= $base ?>index.php" target="_blank" title="View Customer Storefront" class="nav-ext-link">Shop ↗</a>
            <a href="<?= $base ?>admin/account.php" class="<?= $admin_cur_page === 'account.php' ? 'active' : '' ?>">Account</a>
            <a href="<?= $base ?>auth/logout.php" class="nav-logout-link">Logout</a>
        </nav>
    <?php } else { ?>
        <a class="brand" href="<?= $base ?>index.php">SPORTI<span class="ops">BAI</span></a>
        <nav class="nav-links">
            <a href="<?= $base ?>index.php">Home</a>
            <a href="<?= $base ?>shop.php">Shop</a>
            <a href="<?= $base ?>orders.php">Orders</a>
            <a href="<?= $base ?>view_cart.php">Cart <span class="badge" id="nav-cart-badge"><?= $cart_count ?></span></a>
            <?php if (isset($_SESSION['user_id'])) { ?>
                <a href="<?= $base ?>profile.php">Profile</a>
                <a href="<?= $base ?>auth/logout.php">Logout</a>
            <?php } else { ?>
                <a href="<?= $base ?>auth/login.php">Log in</a>
                <a href="<?= $base ?>auth/register.php">Register</a>
            <?php } ?>
        </nav>
    <?php } ?>
    </header>
</div>
<main>
