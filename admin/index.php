<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

$total_items = 0;
$pending_orders = 0;
$low_stock_items = array();
if ($conn != null) {
    $result = mysqli_query($conn, "SELECT COUNT(*) AS n FROM items WHERE is_active = 1");
    $row = mysqli_fetch_assoc($result);
    $total_items = $row['n'];

    $result = mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE status = 'Pending'");
    $row = mysqli_fetch_assoc($result);
    $pending_orders = $row['n'];

    $res_low = mysqli_query($conn, "SELECT item_id, name, stock FROM items WHERE is_active = 1 AND stock < 5 ORDER BY stock ASC LIMIT 6");
    while ($r = mysqli_fetch_assoc($res_low)) {
        $low_stock_items[] = $r;
    }
}

$base = '../';
$page_title = 'Admin | Sportibai';
require '../includes/header.php';
?>
<?php require '../includes/alert.php'; ?>

<?php if (count($low_stock_items) > 0) { ?>
<div class="alert alert-danger reveal in" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:1.5rem;">
    <div>
        <strong>⚠️ Low Stock Alert (<?= count($low_stock_items) ?> items):</strong>
        <span style="font-size:12px; margin-left:6px;">
            <?php foreach ($low_stock_items as $lsi) {
                echo htmlspecialchars($lsi['name']) . ' (' . $lsi['stock'] . ' left) &nbsp; ';
            } ?>
        </span>
    </div>
    <a href="items.php" class="btn sm" style="background:#ff6e64; padding:4px 10px; font-size:10px;">Restock Now</a>
</div>
<?php } ?>

<section class="hero reveal">
    <div class="hero-eyebrow">[ STORE COMMAND ]</div>
    <h1>Admin <span class="highlight">Portal</span></h1>
    <p>Signed in as <strong style="color:var(--color-fg);"><?php echo htmlspecialchars($_SESSION['email']); ?></strong>. Overview of store inventory, orders, and financial balance.</p>
</section>

<div class="grid reveal in">
    <a class="card" href="items.php">
        <div class="info">
            <span class="brand-tag">INVENTORY</span>
            <h2>Items & Stock</h2>
            <p class="muted" style="font-size:var(--text-body-sm);"><?php echo $total_items; ?> active catalog products</p>
        </div>
        <div class="row">
            <span class="muted" style="font-size:11px; text-transform:uppercase;">Manage stock</span>
            <span style="color:var(--color-accent-on-dark); font-weight:700;">→</span>
        </div>
    </a>
    <a class="card" href="categories.php">
        <div class="info">
            <span class="brand-tag">ORGANIZATION</span>
            <h2>Categories</h2>
            <p class="muted" style="font-size:var(--text-body-sm);">Group & classify store items</p>
        </div>
        <div class="row">
            <span class="muted" style="font-size:11px; text-transform:uppercase;">Manage categories</span>
            <span style="color:var(--color-accent-on-dark); font-weight:700;">→</span>
        </div>
    </a>
    <a class="card" href="orders.php">
        <div class="info">
            <span class="brand-tag">FULFILLMENT</span>
            <h2>Customer Orders</h2>
            <p class="muted" style="font-size:var(--text-body-sm);">
                <?php if ($pending_orders > 0) { ?>
                    <span style="color:var(--color-accent-on-dark); font-weight:700;"><?php echo $pending_orders; ?> pending action</span>
                <?php } else { ?>
                    0 pending orders
                <?php } ?>
            </p>
        </div>
        <div class="row">
            <span class="muted" style="font-size:11px; text-transform:uppercase;">Review orders</span>
            <span style="color:var(--color-accent-on-dark); font-weight:700;">→</span>
        </div>
    </a>
    <a class="card" href="expenses.php">
        <div class="info">
            <span class="brand-tag">DISBURSEMENTS</span>
            <h2>Store Expenses</h2>
            <p class="muted" style="font-size:var(--text-body-sm);">Log rent, freight, inventory & utilities</p>
        </div>
        <div class="row">
            <span class="muted" style="font-size:11px; text-transform:uppercase;">Record expenses</span>
            <span style="color:var(--color-accent-on-dark); font-weight:700;">→</span>
        </div>
    </a>
    <a class="card" href="sales_report.php">
        <div class="info">
            <span class="brand-tag">FINANCIALS</span>
            <h2>Sales & Revenue</h2>
            <p class="muted" style="font-size:var(--text-body-sm);">Net profit, top selling items & ranges</p>
        </div>
        <div class="row">
            <span class="muted" style="font-size:11px; text-transform:uppercase;">View reports</span>
            <span style="color:var(--color-accent-on-dark); font-weight:700;">→</span>
        </div>
    </a>
</div>
<?php require '../includes/footer.php'; ?>

