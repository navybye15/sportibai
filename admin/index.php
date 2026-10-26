<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

$total_items = 0;
$total_units = 0;
$pending_orders = 0;
$out_of_stock_count = 0;
$month_revenue = 0.0;
$month_completed_orders = 0;
$active_customers = 0;
$low_stock_items = array();
$recent_orders = array();

if ($conn != null) {
    // 1. Catalog & Inventory Metrics
    $res_items = mysqli_query($conn, "SELECT COUNT(*) AS total_items, COALESCE(SUM(stock), 0) AS total_units FROM items WHERE is_active = 1");
    $item_data = mysqli_fetch_assoc($res_items);
    $total_items = (int)($item_data['total_items'] ?? 0);
    $total_units = (int)($item_data['total_units'] ?? 0);

    // 2. Pending Orders
    $res_pending = mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE status = 'Pending'");
    $pending_orders = (int)(mysqli_fetch_assoc($res_pending)['n'] ?? 0);

    // 3. Out of stock count
    $res_oos = mysqli_query($conn, "SELECT COUNT(*) AS oos FROM items WHERE is_active = 1 AND stock = 0");
    $out_of_stock_count = (int)(mysqli_fetch_assoc($res_oos)['oos'] ?? 0);

    // 4. Low stock items (stock < 5)
    $res_low = mysqli_query($conn, "SELECT item_id, name, brand, stock, price, image FROM items WHERE is_active = 1 AND stock < 5 ORDER BY stock ASC LIMIT 5");
    while ($r = mysqli_fetch_assoc($res_low)) {
        $low_stock_items[] = $r;
    }

    // 5. Monthly Revenue (Completed orders)
    $m_start = date('Y-m-01 00:00:00');
    $m_end   = date('Y-m-t 23:59:59');
    $res_rev = mysqli_execute_query($conn, "SELECT COALESCE(SUM(total), 0) AS rev, COUNT(*) AS cnt FROM orders WHERE status = 'Completed' AND completed_at BETWEEN ? AND ?", array($m_start, $m_end));
    $rev_row = mysqli_fetch_assoc($res_rev);
    $month_revenue = (float)($rev_row['rev'] ?? 0);
    $month_completed_orders = (int)($rev_row['cnt'] ?? 0);

    // 6. Active Registered Customers
    $res_cust = mysqli_query($conn, "SELECT COUNT(*) AS c FROM users WHERE role = 'user' AND status = 'active'");
    $active_customers = (int)(mysqli_fetch_assoc($res_cust)['c'] ?? 0);

    // 7. Recent 5 Orders Feed
    $sql_recent = "SELECT o.order_id, o.reference_number, o.order_type, o.total, o.status, o.date_ordered, c.first_name, c.last_name
                   FROM orders o
                   JOIN customer c ON o.customer_id = c.customer_id
                   ORDER BY o.order_id DESC LIMIT 6";
    $res_recent = mysqli_query($conn, $sql_recent);
    if ($res_recent) {
        while ($ro = mysqli_fetch_assoc($res_recent)) {
            $recent_orders[] = $ro;
        }
    }
}

$base = '../';
$page_title = 'Command Center | Sportibai Admin';
require '../includes/header.php';
?>

<div class="reveal in">
    <!-- Top Greeting & Header Ribbon -->
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1.25rem; margin-bottom:2rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ STORE COMMAND CENTER ]</div>
            <h1 style="font-size:clamp(26px, 3.5vw, 42px); font-weight:800; color:#fff; line-height:1.15; margin:4px 0 6px;">
                Welcome back, <span class="highlight"><?= htmlspecialchars(explode('@', $_SESSION['email'])[0]) ?></span> 👋
            </h1>
            <p class="muted" style="font-size:14px; margin:0;">
                Overview of real-time store inventory, customer orders, and monthly revenue performance.
            </p>
        </div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <a href="items.php#add" class="btn sm" style="box-shadow:0 0 16px rgba(10,132,255,0.35);">+ Add New Item</a>
            <a href="orders.php?tab=Pending" class="btn ghost sm">
                Review Orders <?= $pending_orders > 0 ? "($pending_orders)" : '' ?>
            </a>
            <a href="../index.php" target="_blank" class="btn ghost sm">View Storefront ↗</a>
        </div>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- 4-Card Hero KPI Metric Grid -->
    <div class="admin-kpi-grid" style="margin-bottom:2rem;">
        <!-- KPI 1: Monthly Revenue -->
        <div class="admin-kpi-card">
            <div class="kpi-header">
                <span class="kpi-label">Revenue This Month</span>
                <span class="kpi-icon" style="background:rgba(48,209,88,0.15); color:var(--color-brand-green);">₱</span>
            </div>
            <div class="kpi-value" style="color:var(--color-brand-green);">
                <?= peso($month_revenue) ?>
            </div>
            <div class="kpi-sub">
                <strong><?= $month_completed_orders ?></strong> completed orders in <?= date('F') ?>
            </div>
        </div>

        <!-- KPI 2: Pending Orders -->
        <div class="admin-kpi-card <?= $pending_orders > 0 ? 'kpi-highlight-alert' : '' ?>">
            <div class="kpi-header">
                <span class="kpi-label">Pending Orders</span>
                <span class="kpi-icon" style="background:rgba(224,35,28,0.15); color:var(--color-brand-red);">📦</span>
            </div>
            <div class="kpi-value" style="color:<?= $pending_orders > 0 ? 'var(--color-brand-red)' : '#fff' ?>;">
                <?= $pending_orders ?>
            </div>
            <div class="kpi-sub">
                <?php if ($pending_orders > 0) { ?>
                    <a href="orders.php?tab=Pending" style="color:var(--color-brand-red); font-weight:700; text-decoration:underline;">Action required immediately →</a>
                <?php } else { ?>
                    All pipeline orders fulfilled
                <?php } ?>
            </div>
        </div>

        <!-- KPI 3: Inventory Catalog & Units -->
        <div class="admin-kpi-card">
            <div class="kpi-header">
                <span class="kpi-label">Catalog Products</span>
                <span class="kpi-icon" style="background:rgba(10,132,255,0.15); color:var(--color-accent-on-dark);">🏷️</span>
            </div>
            <div class="kpi-value" style="color:#fff;">
                <?= $total_items ?>
            </div>
            <div class="kpi-sub">
                <strong><?= number_format($total_units) ?></strong> total units in warehouse stock
            </div>
        </div>

        <!-- KPI 4: Active Customers & Health -->
        <div class="admin-kpi-card">
            <div class="kpi-header">
                <span class="kpi-label">Active Customers</span>
                <span class="kpi-icon" style="background:rgba(255,180,50,0.15); color:#ffd166;">👥</span>
            </div>
            <div class="kpi-value" style="color:#fff;">
                <?= number_format($active_customers) ?>
            </div>
            <div class="kpi-sub">
                Registered sports shoppers
            </div>
        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="admin-dashboard-layout">
        <!-- ════════ LEFT COLUMN: Recent Orders Pipeline ════════ -->
        <div class="dashboard-col-main">
            <div class="admin-panel">
                <div class="panel-header">
                    <div>
                        <div class="hero-eyebrow" style="color:var(--color-accent-on-dark);">[ FULFILLMENT FEED ]</div>
                        <h2 class="panel-title">Recent Orders</h2>
                    </div>
                    <a href="orders.php" class="panel-link">View All Orders →</a>
                </div>

                <?php if (count($recent_orders) === 0) { ?>
                    <div style="padding:2.5rem; text-align:center;">
                        <p class="muted" style="margin:0;">No orders received yet.</p>
                    </div>
                <?php } else { ?>
                    <div style="overflow-x:auto;">
                        <table class="dashboard-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Type</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $ro) { 
                                    $is_pending = ($ro['status'] === 'Pending');
                                ?>
                                    <tr>
                                        <td>
                                            <a href="orders.php?q=<?= urlencode($ro['reference_number']) ?>" style="font-weight:700; color:#fff; font-family:var(--font-mono); font-size:13px;">
                                                #<?= $ro['order_id'] ?>
                                            </a>
                                            <div class="muted" style="font-size:10px;"><?= date('M d, g:i A', strtotime($ro['date_ordered'])) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:#fff;">
                                                <?= htmlspecialchars(trim($ro['first_name'] . ' ' . $ro['last_name']) ?: 'Guest User') ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-size:11px; text-transform:uppercase; color:var(--color-fg-secondary); font-weight:600;">
                                                <?= $ro['order_type'] === 'pickup' ? '🏬 Pick Up' : '🚚 Delivery' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong style="color:var(--color-brand-green); font-size:13px;"><?= peso($ro['total']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="status-badge" style="<?php
                                                if ($ro['status'] === 'Completed') echo 'background:rgba(48,209,88,0.15); color:var(--color-brand-green); border-color:rgba(48,209,88,0.3);';
                                                elseif ($ro['status'] === 'Cancelled') echo 'background:rgba(255,70,70,0.15); color:#ff6b6b; border-color:rgba(255,70,70,0.3);';
                                                elseif ($ro['status'] === 'Pending') echo 'background:rgba(224,35,28,0.2); color:var(--color-brand-red); border-color:rgba(224,35,28,0.4); font-weight:800;';
                                                else echo 'background:rgba(10,132,255,0.15); color:var(--color-accent-on-dark); border-color:rgba(10,132,255,0.3);';
                                            ?> font-size:11px; padding:3px 8px;">
                                                <?= htmlspecialchars($ro['status']) ?>
                                            </span>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="orders.php?q=<?= urlencode($ro['reference_number']) ?>" class="btn ghost sm" style="font-size:11px; padding:3px 8px;">
                                                <?= $is_pending ? '⚡ Process' : 'Details' ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                <?php } ?>
            </div>
        </div>

        <!-- ════════ RIGHT COLUMN: Fast Hub & Inventory Health ════════ -->
        <div class="dashboard-col-side">
            <!-- Quick Actions Hub -->
            <div class="admin-panel" style="margin-bottom:1.5rem;">
                <div class="panel-header">
                    <div>
                        <div class="hero-eyebrow" style="color:var(--color-accent-on-dark);">[ QUICK ACCESS ]</div>
                        <h2 class="panel-title">Operations Hub</h2>
                    </div>
                </div>
                <div class="quick-nav-list">
                    <a href="items.php" class="quick-nav-item">
                        <span class="quick-icon">📦</span>
                        <div>
                            <div class="quick-title">Product Inventory</div>
                            <div class="quick-desc">Manage sizes, stock, variants & pricing</div>
                        </div>
                        <span class="quick-arrow">→</span>
                    </a>
                    <a href="sales_report.php" class="quick-nav-item">
                        <span class="quick-icon">📊</span>
                        <div>
                            <div class="quick-title">Sales & Financials</div>
                            <div class="quick-desc">Net profit, margins & daily revenue</div>
                        </div>
                        <span class="quick-arrow">→</span>
                    </a>
                    <a href="expenses.php" class="quick-nav-item">
                        <span class="quick-icon">💸</span>
                        <div>
                            <div class="quick-title">Store Expenses</div>
                            <div class="quick-desc">Record rent, freight, inventory & utility bills</div>
                        </div>
                        <span class="quick-arrow">→</span>
                    </a>
                    <a href="categories.php" class="quick-nav-item">
                        <span class="quick-icon">🏷️</span>
                        <div>
                            <div class="quick-title">Taxonomy & Categories</div>
                            <div class="quick-desc">Manage sports gear classifications</div>
                        </div>
                        <span class="quick-arrow">→</span>
                    </a>
                    <a href="users.php" class="quick-nav-item">
                        <span class="quick-icon">👥</span>
                        <div>
                            <div class="quick-title">User Accounts</div>
                            <div class="quick-desc">Admin roles, customer accounts & status</div>
                        </div>
                        <span class="quick-arrow">→</span>
                    </a>
                </div>
            </div>

            <!-- Inventory Watchlist / Low Stock Card -->
            <?php if (count($low_stock_items) > 0) { ?>
                <div class="admin-panel" style="border-color:rgba(224,35,28,0.25);">
                    <div class="panel-header">
                        <div>
                            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ INVENTORY WARNING ]</div>
                            <h2 class="panel-title" style="color:#ff6e64;">Low Stock Items</h2>
                        </div>
                        <a href="items.php" class="panel-link" style="color:var(--color-brand-red);">Restock All →</a>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:10px;">
                        <?php foreach ($low_stock_items as $lsi) { ?>
                            <div class="low-stock-tile">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div class="low-stock-thumb">
                                        <?php if (!empty($lsi['image']) && file_exists(__DIR__ . '/../images/uploads/' . basename($lsi['image']))) { ?>
                                            <img src="../images/uploads/<?= htmlspecialchars(basename($lsi['image'])) ?>" alt="Thumbnail">
                                        <?php } else { ?>
                                            <span>🏀</span>
                                        <?php } ?>
                                    </div>
                                    <div>
                                        <div style="font-size:13px; font-weight:700; color:#fff;"><?= htmlspecialchars($lsi['name']) ?></div>
                                        <div class="muted" style="font-size:11px;"><?= htmlspecialchars($lsi['brand'] ?: 'Sportibai') ?> • <?= peso($lsi['price']) ?></div>
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <span class="status-badge <?= $lsi['stock'] == 0 ? 'badge-out' : 'badge-low' ?>" style="font-size:11px; padding:3px 8px;">
                                        <?= $lsi['stock'] == 0 ? 'Out of Stock' : $lsi['stock'] . ' left' ?>
                                    </span>
                                    <div style="margin-top:4px;">
                                        <a href="item_edit.php?id=<?= $lsi['item_id'] ?>" style="font-size:11px; color:var(--color-accent-on-dark); font-weight:600;">Edit & Restock →</a>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<style>
.admin-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
    gap: 1.25rem;
}
.admin-kpi-card {
    background: rgba(22, 24, 29, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(16px);
    transition: transform 0.2s ease, border-color 0.2s ease;
}
.admin-kpi-card:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.16);
}
.kpi-highlight-alert {
    border-color: rgba(224, 35, 28, 0.45);
    background: rgba(224, 35, 28, 0.06);
}
.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}
.kpi-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: var(--color-fg-secondary);
}
.kpi-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    font-weight: 800;
}
.kpi-value {
    font-size: clamp(24px, 2.4vw, 32px);
    font-weight: 800;
    line-height: 1.1;
    margin-bottom: 0.4rem;
    letter-spacing: -0.02em;
}
.kpi-sub {
    font-size: 12px;
    color: var(--color-fg-secondary);
}
.admin-dashboard-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
    gap: 1.75rem;
    align-items: start;
}
@media (max-width: 960px) {
    .admin-dashboard-layout {
        grid-template-columns: 1fr;
    }
}
.admin-panel {
    background: rgba(22, 24, 29, 0.7);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    backdrop-filter: blur(16px);
}
.panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.25rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}
.panel-title {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    margin: 2px 0 0;
}
.panel-link {
    font-size: 12px;
    font-weight: 700;
    color: var(--color-accent-on-dark);
    text-decoration: none;
}
.panel-link:hover {
    text-decoration: underline;
}
.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.dashboard-table th {
    padding: 8px 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    color: var(--color-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    text-align: left;
}
.dashboard-table td {
    padding: 12px 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}
.dashboard-table tr:hover td {
    background: rgba(255, 255, 255, 0.02);
}
.quick-nav-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.quick-nav-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.06);
    border-radius: 12px;
    text-decoration: none;
    color: inherit;
    transition: all 0.18s ease;
}
.quick-nav-item:hover {
    background: rgba(255, 255, 255, 0.07);
    border-color: rgba(255, 255, 255, 0.14);
    transform: translateX(3px);
}
.quick-icon {
    font-size: 1.25rem;
    flex-shrink: 0;
}
.quick-title {
    font-size: 13px;
    font-weight: 700;
    color: #fff;
}
.quick-desc {
    font-size: 11px;
    color: var(--color-fg-secondary);
}
.quick-arrow {
    margin-left: auto;
    font-weight: 800;
    color: var(--color-accent-on-dark);
    opacity: 0.6;
    transition: transform 0.18s;
}
.quick-nav-item:hover .quick-arrow {
    opacity: 1;
    transform: translateX(3px);
}
.low-stock-tile {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: rgba(255, 255, 255, 0.03);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.low-stock-thumb {
    width: 38px;
    height: 38px;
    border-radius: 8px;
    overflow: hidden;
    background: #0b0c0e;
    border: 1px solid rgba(255, 255, 255, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.low-stock-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.badge-low {
    background: rgba(255, 180, 50, 0.15);
    color: #ffd166;
    border: 1px solid rgba(255, 180, 50, 0.3);
    border-radius: 6px;
    font-weight: 700;
}
.badge-out {
    background: rgba(255, 70, 70, 0.15);
    color: #ff6b6b;
    border: 1px solid rgba(255, 70, 70, 0.3);
    border-radius: 6px;
    font-weight: 700;
}
</style>

<?php require '../includes/footer.php'; ?>
