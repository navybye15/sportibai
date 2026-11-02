<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '';
require_once 'includes/customer_guard.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php?redirect=orders.php');
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
    $sql = "SELECT oi.order_id, i.item_id, oi.selected_size, i.name, i.image, oi.quantity, oi.price, c.name AS category
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


// ── Fetch existing reviews by this customer (keyed by order_id and item_id) ──
$user_reviews = array();
if ($conn != null) {
    $rev_res = mysqli_execute_query($conn, "SELECT review_id, order_id, item_id, rating, comment, review_image FROM reviews WHERE user_id = ?", array($_SESSION['user_id']));
    while ($rw = mysqli_fetch_assoc($rev_res)) {
        if (!empty($rw['order_id'])) {
            $user_reviews[(int)$rw['order_id']][(int)$rw['item_id']] = $rw;
        }
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
        <div class="order-empty reveal in" style="text-align:center; padding:4rem 2rem; border-radius:18px;">
            <div style="margin-bottom:1rem; color:var(--color-fg-muted); display:flex; justify-content:center;">
                <?= icon('package', '', 52) ?>
            </div>
            <p style="font-size:16px; font-weight:600; color:#fff;">No orders in this tab yet.</p>
            <a class="btn" href="shop.php" style="margin-top:0.75rem;">Browse the Shop</a>
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
                    <strong style="color:#ffffff; font-size:1.35rem; font-family:var(--font-display); font-weight:800;"><?= peso($o['total']) ?></strong>
                </div>
            </div>

            <!-- Single Item Preview Body (Shopee/Lazada style) -->
            <div class="order-card-body" style="padding:1.35rem 1.75rem; display:flex; gap:1.25rem; align-items:center;">
                <?php if ($first_item) { ?>
                    <div style="width:72px; height:72px; flex-shrink:0;">
                        <?php item_tile($first_item, ''); ?>
                    </div>
                    <div style="flex-grow:1; display:flex; flex-direction:column; gap:4px;">
                        <span style="font-weight:700; color:#fff; font-size:16.5px; font-family:var(--font-display);">
                            <?= htmlspecialchars($first_item['name']) ?>
                            <?php if (!empty($first_item['selected_size'])) { ?>
                                <span class="badge" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); font-size:12px; font-weight:700; padding:2px 8px; margin-left:6px; vertical-align:middle;">
                                    Size: <?= htmlspecialchars($first_item['selected_size']) ?>
                                </span>
                            <?php } ?>
                        </span>
                        <span class="muted" style="font-size:14.5px; margin-top:2px;">
                            Qty: <strong style="color:var(--color-accent-on-dark);"><?= $first_item['quantity'] ?></strong> &nbsp;•&nbsp; <strong style="color:#fff;"><?= peso($first_item['price']) ?></strong>
                        </span>
                        <?php if ($more_count > 0) { ?>
                            <span class="brand-tag" style="font-size:12px; font-weight:700; color:var(--color-accent-on-dark); margin-top:3px;">
                                + <?= $more_count ?> more <?= $more_count === 1 ? 'item' : 'items' ?>
                            </span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Actions -->
            <div class="order-card-actions">
                <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn ghost sm" style="font-size:13px; font-weight:700; padding:7px 16px;">View Details</a>
                <a href="actions/download_receipt.php?order_id=<?= $o['order_id'] ?>" target="_blank" class="btn ghost sm" style="font-size:13px; font-weight:700; padding:7px 16px; display:inline-flex; align-items:center; gap:6px;">
                    <?= icon('file-text', '', 15) ?>
                    <span>PDF Receipt</span>
                </a>
                
                <?php if ($o['status'] === 'Completed') { 
                    $items_in_order = $order_items[$o['order_id']] ?? array();
                    if (!empty($items_in_order)) {
                        $order_revs = $user_reviews[$o['order_id']] ?? array();
                        $unreviewed_items = array();
                        foreach ($items_in_order as $it_chk) {
                            if (!isset($order_revs[$it_chk['item_id']])) {
                                $unreviewed_items[] = $it_chk;
                            }
                        }
                        $all_reviewed = (count($unreviewed_items) === 0);
                        $target_it = $all_reviewed ? $items_in_order[0] : $unreviewed_items[0];
                ?>
                    <a href="item/detail.php?id=<?= $target_it['item_id'] ?>&order_id=<?= $o['order_id'] ?>#reviews" class="btn sm" style="<?= $all_reviewed ? 'background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.45);' : 'background:rgba(245,158,11,0.28); color:#fbbf24; border:1px solid rgba(245,158,11,0.65); font-weight:700; box-shadow:0 0 12px rgba(245,158,11,0.3);' ?> font-size:13px; font-weight:700; padding:7px 16px; display:inline-flex; align-items:center; gap:6px;">
                        <?= $all_reviewed ? icon('check', '', 14) . ' <span>Reviewed (Edit)</span>' : icon('star', '', 14) . ' <span>Review Gear</span>' ?>
                    </a>
                    <?php } ?>
                <?php } ?>

                <?php if ($can_receive) { ?>
                    <form method="POST" action="actions/receive_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn sm" type="submit"
                            onclick="return confirm('Confirm that you received this order?')"
                            style="background:rgba(81,175,67,0.85); box-shadow:0 2px 14px rgba(81,175,67,0.3); font-size:13px; font-weight:700; padding:7px 16px; display:inline-flex; align-items:center; gap:6px;">
                            <?= icon('check-circle', '', 15) ?>
                            <span>Order Received</span>
                        </button>
                    </form>
                <?php } elseif ($o['status'] === 'Shipped' || $o['status'] === 'Out for Delivery' || $o['status'] === 'Ready for Pick Up') { ?>
                    <button class="btn ghost sm" disabled style="opacity:0.65; cursor:not-allowed; font-size:13px; font-weight:600; padding:7px 16px; display:inline-flex; align-items:center; gap:6px;" title="Waiting for delivery confirmation">
                        <?= icon('clock', '', 14) ?>
                        <span>Waiting for Delivery</span>
                    </button>
                <?php } ?>
                <?php if ($can_cancel) { ?>
                    <form method="POST" action="actions/cancel_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn ghost sm" type="submit"
                            onclick="return confirm('Cancel this order?')"
                            style="color:#ff9f96; border-color:rgba(255,100,80,0.3); font-size:13px; font-weight:700; padding:7px 16px; display:inline-flex; align-items:center; gap:6px;">
                            <?= icon('x', '', 14) ?>
                            <span>Cancel</span>
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
