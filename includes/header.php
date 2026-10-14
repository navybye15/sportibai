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
<link rel="icon" type="image/svg+xml" href="<?= $base ?>favicon.svg">
<link rel="stylesheet" href="<?= $base ?>css/style.css">
</head>
<body>
<div class="nav-container">
    <header class="nav">
    <?php if ($is_admin) { ?>
        <a class="brand" href="<?= $base ?>admin/index.php">SPORTI<span class="ops">BAI</span> <span style="font-size:10px; font-weight:700; background:rgba(255,255,255,0.1); padding:2px 6px; border-radius:4px; letter-spacing:1px; vertical-align:middle; margin-left:4px;">ADMIN</span></a>
        <nav class="nav-links">
            <a href="<?= $base ?>admin/index.php">Dashboard</a>
            <a href="<?= $base ?>index.php" target="_blank" title="View Customer Storefront">View Shop ↗</a>
            <a href="<?= $base ?>admin/account.php">Account</a>
            <a href="<?= $base ?>logout.php">Logout</a>
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
                <a href="<?= $base ?>logout.php">Logout</a>
            <?php } else { ?>
                <a href="<?= $base ?>login.php">Log in</a>
                <a href="<?= $base ?>register.php">Register</a>
            <?php } ?>
        </nav>
    <?php } ?>
    </header>
</div>
<main>
