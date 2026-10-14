<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '';
require_once 'includes/customer_guard.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ── Fetch all orders for this customer ───────────────────────────────────────
$orders = array();
if ($conn != null) {
    $sql = "SELECT o.order_id, o.reference_number, o.order_type, o.total,
                   o.status, o.date_ordered, o.snapshot_name,
                   o.delivery_address, o.contact_number
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            WHERE c.user_id = ?
            ORDER BY o.order_id DESC";
    $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
}

// ── Fetch items for each order (one query, grouped by order_id) ──────────────
$order_items = array();
if ($conn != null && count($orders) > 0) {
    $ids = implode(',', array_column($orders, 'order_id'));
    $sql = "SELECT oi.order_id, i.item_id, i.name, i.image, oi.quantity, oi.price, c.name AS category
            FROM order_items oi
            JOIN items i ON oi.item_id = i.item_id
            JOIN categories c ON i.category_id = c.category_id
            WHERE oi.order_id IN ($ids)
            ORDER BY oi.order_item_id";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[$row['order_id']][] = $row;
    }
}

// ── Tab counts ───────────────────────────────────────────────────────────────
$tab_counts = array('all' => 0, 'topay' => 0, 'toreceive' => 0, 'completed' => 0, 'cancelled' => 0);
foreach ($orders as $o) {
    $tab_counts['all']++;
    $t = status_to_tab($o['status']);
    if (isset($tab_counts[$t])) $tab_counts[$t]++;
}

// ── Active tab from URL ───────────────────────────────────────────────────────
$active_tab = 'all';
$valid_tabs = array('all', 'topay', 'toreceive', 'completed', 'cancelled');
if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)) {
    $active_tab = $_GET['tab'];
}

$page_title = 'My Orders | Sportibai';
require 'includes/header.php';
?>

<section class="reveal">
    <div style="margin-bottom:1.5rem;">
        <div class="hero-eyebrow">[ HISTORY &amp; TRACKING ]</div>
        <h1 style="font-size:var(--text-display-l)">My Orders</h1>
    </div>
    <?php require 'includes/alert.php'; ?>

    <!-- Order Tabs -->
    <div class="order-tabs">
        <?php
        $tab_labels = array(
            'all'       => 'All',
            'topay'     => 'To Pay',
            'toreceive' => 'To Receive',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        );
        foreach ($tab_labels as $key => $label) {
            $cls = ($active_tab === $key) ? 'order-tab active' : 'order-tab';
            $count = $tab_counts[$key];
        ?>
            <a href="orders.php?tab=<?= $key ?>" class="<?= $cls ?>">
                <?= $label ?>
                <?php if ($count > 0) { ?>
                    <span class="tab-badge"><?= $count ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>

    <!-- Orders List -->
    <?php
    $visible = array_filter($orders, function($o) use ($active_tab) {
        if ($active_tab === 'all') return true;
        return status_to_tab($o['status']) === $active_tab;
    });
    ?>

    <?php if (count($visible) === 0) { ?>
        <div class="order-empty reveal in">
            <div class="order-empty-icon">📦</div>
            <p>No orders in this tab yet.</p>
            <a class="btn" href="shop.php">Browse the Shop</a>
        </div>
    <?php } ?>

    <div class="order-list">
    <?php foreach ($visible as $o) {
        $ref = $o['reference_number'] ?? ('SB-' . str_pad($o['order_id'], 7, '0', STR_PAD_LEFT));
        $items = $order_items[$o['order_id']] ?? array();
        $item_count = count($items);
        $first_item = $items[0] ?? null;
        $more_count = $item_count > 1 ? ($item_count - 1) : 0;
        
        $can_cancel  = in_array($o['status'], array('Pending', 'Prepared'));
        $can_receive = ($o['status'] === 'Delivered');
    ?>
        <div class="order-card reveal" style="cursor:pointer;" onclick="if(!event.target.closest('button, form, a')) { window.location.href='order_detail.php?id=<?= $o['order_id'] ?>'; }">
            <!-- Card Header -->
            <div class="order-card-header">
                <div class="order-ref-row">
                    <span class="order-ref"><?= htmlspecialchars($ref) ?></span>
                    <span class="status-badge" style="<?= status_badge_style($o['status']) ?>">
                        <?= htmlspecialchars($o['status']) ?>
                    </span>
                </div>
                <div class="order-meta">
                    <?= date('M d, Y · g:i A', strtotime($o['date_ordered'])) ?>
                    &nbsp;·&nbsp;
                    <?= ucfirst($o['order_type']) ?>
                    &nbsp;·&nbsp;
                    <strong style="color:var(--color-fg); font-size:1.05rem;"><?= peso($o['total']) ?></strong>
                </div>
            </div>

            <!-- Single Item Preview Body (Shopee/Lazada style) -->
            <div class="order-card-body" style="padding:1.25rem 1.5rem; display:flex; gap:1.25rem; align-items:center;">
                <?php if ($first_item) { ?>
                    <div style="width:68px; height:68px; flex-shrink:0;">
                        <?php item_tile($first_item, ''); ?>
                    </div>
                    <div style="flex-grow:1; display:flex; flex-direction:column; gap:4px;">
                        <span style="font-weight:600; color:var(--color-fg);"><?= htmlspecialchars($first_item['name']) ?></span>
                        <span class="muted" style="font-size:var(--text-body-sm);">
                            Qty: <?= $first_item['quantity'] ?> &nbsp;•&nbsp; <?= peso($first_item['price']) ?>
                        </span>
                        <?php if ($more_count > 0) { ?>
                            <span class="brand-tag" style="font-size:11px; color:var(--color-accent-on-dark); font-weight:600;">
                                + <?= $more_count ?> more <?= $more_count === 1 ? 'item' : 'items' ?>
                            </span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Actions -->
            <div class="order-card-actions">
                <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn ghost sm">View Details</a>
                <?php if ($can_receive) { ?>
                    <form method="POST" action="receive_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn sm" type="submit"
                            onclick="return confirm('Confirm that you received this order?')"
                            style="background:rgba(81,175,67,0.85);box-shadow:0 2px 14px rgba(81,175,67,0.3);">
                            ✓ Order Received
                        </button>
                    </form>
                <?php } elseif ($o['status'] === 'Shipped' || $o['status'] === 'Out for Delivery' || $o['status'] === 'Ready for Pick Up') { ?>
                    <button class="btn ghost sm" disabled style="opacity:0.6; cursor:not-allowed;" title="Waiting for delivery confirmation">
                        Waiting for Delivery
                    </button>
                <?php } ?>
                <?php if ($can_cancel) { ?>
                    <form method="POST" action="cancel_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn ghost sm" type="submit"
                            onclick="return confirm('Cancel this order?')"
                            style="color:#ff9f96;border-color:rgba(255,100,80,0.3);">
                            Cancel
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
