<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '';
require_once __DIR__ . '/includes/customer_guard.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($order_id < 1) {
    header('Location: orders.php');
    exit;
}

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php?redirect=' . urlencode('order_detail.php?id=' . $order_id));
    exit;
}

// ── Load order (must belong to this customer) ─────────────────────────────────
$order = null;
if ($conn != null) {
    $sql = "SELECT o.order_id, o.reference_number, o.order_type,
                   o.delivery_address, o.contact_number, o.snapshot_name,
                   o.payment_method, o.total, o.status, o.date_ordered,
                   c.first_name, c.last_name, c.province, c.postal_code
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            WHERE o.order_id = ? AND c.user_id = ?
            LIMIT 1";
    $result = mysqli_execute_query($conn, $sql, array($order_id, $_SESSION['user_id']));
    $order = mysqli_fetch_assoc($result);
}
if ($order === null) {
    header('Location: orders.php');
    exit;
}

// ── Load order items ──────────────────────────────────────────────────────────
$items = array();
if ($conn != null) {
    $sql = "SELECT i.item_id, i.name, i.image, oi.selected_size, oi.quantity, oi.price
            FROM order_items oi
            JOIN items i ON oi.item_id = i.item_id
            WHERE oi.order_id = ?
            ORDER BY oi.order_item_id";
    $result = mysqli_execute_query($conn, $sql, array($order_id));
    while ($row = mysqli_fetch_assoc($result)) {
        $items[] = $row;
    }
}

// ── Load reviews for this specific order ─────────────────────────────────────
$order_reviews = array();
if ($conn != null) {
    $rev_res = mysqli_execute_query($conn, "SELECT review_id, item_id, rating, comment FROM reviews WHERE order_id = ?", array($order_id));
    while ($rw = mysqli_fetch_assoc($rev_res)) {
        $order_reviews[(int)$rw['item_id']] = $rw;
    }
}

// ── Load status log ───────────────────────────────────────────────────────────
$log = get_status_log($conn, $order_id);

// ── Build full status timeline for display ───────────────────────────────────
$is_delivery = ($order['order_type'] === 'delivery');
if ($is_delivery) {
    $timeline_steps = array('Pending', 'Prepared', 'Ready to Ship', 'Shipped', 'Out for Delivery', 'Delivered', 'Completed');
} else {
    $timeline_steps = array('Pending', 'Prepared', 'Ready for Pick Up', 'Delivered', 'Completed');
}
if ($order['status'] === 'Cancelled') {
    $timeline_steps[] = 'Cancelled';
}

// Build map: status → timestamp from log
$log_map = array();
foreach ($log as $entry) {
    if (!isset($log_map[$entry['status']])) {
        $log_map[$entry['status']] = $entry['changed_at'];
    }
}

// Find current step index in timeline
$current_step_index = 0;
foreach ($timeline_steps as $i => $step) {
    if ($step === $order['status']) {
        $current_step_index = $i;
    }
}

$ref = $order['reference_number'] ?? ('SB-' . str_pad($order['order_id'], 7, '0', STR_PAD_LEFT));
$can_receive = ($order['status'] === 'Delivered');
$can_cancel  = in_array($order['status'], array('Pending', 'Prepared'));

// Build full address display
$addr_lines = array();
if ($order['delivery_address']) $addr_lines[] = $order['delivery_address'];

$page_title = 'Order ' . $ref . ' | Sportibai';
require __DIR__ . '/includes/header.php';
?>

<nav class="res-crumbs">
    <a href="index.php">Home</a>
    <span class="sep">/</span>
    <a href="orders.php">Orders</a>
    <span class="sep">/</span>
    <span><?= htmlspecialchars($ref) ?></span>
</nav>

<?php require __DIR__ . '/includes/alert.php'; ?>

<?php
$status_banners = array(
    'Pending'           => array('title' => 'Order Placed', 'desc' => 'Waiting for the shop to review and prepare your items.', 'icon' => icon('clock', '', 26)),
    'Prepared'          => array('title' => 'Seller is Preparing Your Order', 'desc' => 'Your items are being packed and checked.', 'icon' => icon('package', '', 26)),
    'Ready to Ship'     => array('title' => 'Ready for Courier Pick Up', 'desc' => 'Your package is sealed and waiting for courier dispatch.', 'icon' => icon('truck', '', 26)),
    'Ready for Pick Up' => array('title' => 'Ready for Store Pick Up', 'desc' => 'You may now visit our store and present your Reference Number.', 'icon' => icon('store', '', 26)),
    'Shipped'           => array('title' => 'Parcel Has Shipped', 'desc' => 'Your order is currently in transit with the courier.', 'icon' => icon('truck', '', 26)),
    'Out for Delivery'  => array('title' => 'Out for Delivery', 'desc' => 'Rider is on the way to your delivery address today.', 'icon' => icon('truck', '', 26)),
    'Delivered'         => array('title' => 'Parcel Delivered', 'desc' => 'Please inspect your items and click "Order Received" below.', 'icon' => icon('map-pin', '', 26)),
    'Completed'         => array('title' => 'Order Completed', 'desc' => 'Thank you for shopping with Sportibai!', 'icon' => icon('check-circle', '', 26)),
    'Cancelled'         => array('title' => 'Order Cancelled', 'desc' => 'This order was cancelled and inventory has been restored.', 'icon' => icon('x-circle', '', 26)),
);
$banner = $status_banners[$order['status']] ?? array('title' => $order['status'], 'desc' => '', 'icon' => icon('package', '', 26));
?>

<!-- Status Banner -->
<div class="order-status-banner reveal in" style="margin-bottom:1.75rem; padding:1.25rem 1.5rem; border-radius:var(--radius-xl); border:1px solid rgba(255,255,255,0.1); background:rgba(10, 132, 255, 0.12); display:flex; align-items:center; gap:1.25rem;">
    <div style="display:flex; align-items:center; justify-content:center; width:48px; height:48px; border-radius:12px; background:rgba(255,255,255,0.06); color:var(--color-accent-on-dark); flex-shrink:0;">
        <?= $banner['icon'] ?>
    </div>
    <div style="flex-grow:1;">
        <h2 style="font-size:1.2rem; font-weight:700; margin:0; color:#ffffff; font-family:var(--font-display);"><?= htmlspecialchars($banner['title']) ?></h2>
        <?php if ($banner['desc']) { ?>
            <p class="muted" style="margin:4px 0 0; font-size:14.5px;"><?= htmlspecialchars($banner['desc']) ?></p>
        <?php } ?>
    </div>
    <span class="status-badge" style="<?= status_badge_style($order['status']); ?> font-size:13px; font-weight:800; padding:6px 16px;">
        <?= htmlspecialchars($order['status']) ?>
    </span>
</div>

<section class="reveal in">
    <!-- Page Header -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:2rem;">
        <div>
            <div class="hero-eyebrow">[ ORDER DETAILS ]</div>
            <div style="display:flex; align-items:center; gap:10px; margin-top:6px;">
                <h1 style="font-size:var(--text-display-m); margin:0; font-family:var(--font-mono); font-weight:800;">
                    <?= htmlspecialchars($ref) ?>
                </h1>
                <button type="button" class="btn ghost sm" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($ref) ?>'); this.innerHTML='<span style=\'color:var(--color-brand-green);\'>Copied!</span>'; setTimeout(() => this.innerHTML='<?= icon('copy', '', 13) ?> Copy Ref', 1500);" style="padding:5px 12px; font-size:12px; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
                    <?= icon('copy', '', 13) ?>
                    <span>Copy Ref</span>
                </button>
            </div>
        </div>
        <div style="display:flex; gap:8px;">
            <?php if ($order['status'] !== 'Cancelled') { ?>
                <button type="button" class="btn ghost sm" onclick="window.print()" style="font-size:13px; font-weight:700; padding:8px 16px; display:inline-flex; align-items:center; gap:8px;">
                    <?= icon('printer', '', 16) ?>
                    <span>Print Receipt</span>
                </button>
            <?php } ?>
        </div>
    </div>

    <div class="order-detail-grid">
        <!-- LEFT: Items + Summary -->
        <div class="order-detail-left">
            <!-- Items -->
            <div class="detail-card">
                <div class="detail-card-title">Items Ordered</div>
                <?php foreach ($items as $item) {
                    $has_img = isset($item['image']) && $item['image'] != '' && file_exists(__DIR__ . '/images/uploads/' . basename($item['image']));
                ?>
                <div class="detail-item-row">
                    <?php if ($has_img) { ?>
                        <img class="detail-item-thumb" src="images/uploads/<?= htmlspecialchars(basename($item['image'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                    <?php } else { ?>
                        <div class="detail-item-thumb tile" style="--h:<?= tile_hue($item['item_id']) ?>; aspect-ratio:1; border-radius:var(--radius-md);"></div>
                    <?php } ?>
                    <div class="detail-item-info">
                        <span style="font-weight:700; font-size:16.5px; color:#ffffff; font-family:var(--font-display);">
                            <?= htmlspecialchars($item['name']) ?>
                            <?php if (!empty($item['selected_size'])) { ?>
                                <span class="badge" style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.22); color:var(--color-fg); font-size:12px; font-weight:700; padding:2px 8px; margin-left:6px; vertical-align:middle;">
                                    Size: <?= htmlspecialchars($item['selected_size']) ?>
                                </span>
                            <?php } ?>
                        </span>
                        <span class="muted" style="font-size:14.5px; margin-top:2px;">
                            <?= $item['quantity'] ?> × <strong style="color:#ffffff;"><?= peso($item['price']) ?></strong>
                        </span>
                        <?php if ($order['status'] === 'Completed') { 
                            $is_it_rev = isset($order_reviews[$item['item_id']]);
                        ?>
                            <div style="margin-top:6px;">
                                <a href="item/detail.php?id=<?= $item['item_id'] ?>&order_id=<?= $order['order_id'] ?>#reviews" style="<?= $is_it_rev ? 'background:rgba(245,158,11,0.18); color:#f59e0b; border:1px solid rgba(245,158,11,0.4);' : 'background:rgba(245,158,11,0.28); color:#fbbf24; border:1px solid rgba(245,158,11,0.6); font-weight:700; box-shadow:0 0 10px rgba(245,158,11,0.2);' ?> font-size:12px; font-weight:700; padding:3px 10px; border-radius:6px; text-decoration:none; display:inline-flex; align-items:center; gap:6px;">
                                    <?= $is_it_rev ? icon('check', '', 13) . ' <span>Reviewed (Edit)</span>' : icon('star', '', 13) . ' <span>Review Item</span>' ?>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                    <span style="font-weight:800; font-size:17px; color:var(--color-accent-on-dark); font-family:var(--font-display);">
                        <?= peso($item['price'] * $item['quantity']) ?>
                    </span>
                </div>
                <?php } ?>
                <div class="detail-total-row">
                    <span class="muted" style="font-size:13px; font-weight:800; letter-spacing:var(--tracking-label); text-transform:uppercase;">Order Total</span>
                    <span style="font-size:1.75rem; font-weight:800; color:var(--color-accent-on-dark); font-family:var(--font-display);"><?= peso($order['total']) ?></span>
                </div>
            </div>

            <!-- Delivery / Pickup info -->
            <div class="detail-card">
                <div class="detail-card-title">
                    <?= $is_delivery ? 'Delivery Details' : 'Pick Up Details' ?>
                </div>
                <div class="detail-info-list">
                    <div class="detail-info-row">
                        <span class="muted detail-info-label">Name</span>
                        <span style="font-weight:700; font-size:15px; color:#fff;"><?= htmlspecialchars($order['snapshot_name'] ?? ($order['first_name'] . ' ' . $order['last_name'])) ?></span>
                    </div>
                    <div class="detail-info-row">
                        <span class="muted detail-info-label">Contact</span>
                        <span style="font-weight:600; font-family:var(--font-mono); font-size:15px; color:#fff;"><?= htmlspecialchars($order['contact_number']) ?></span>
                    </div>
                    <?php if ($is_delivery && $order['delivery_address']) { ?>
                    <div class="detail-info-row">
                        <span class="muted detail-info-label">Address</span>
                        <span style="font-size:15px; color:#e2e8f0; line-height:1.5;"><?= nl2br(htmlspecialchars($order['delivery_address'])) ?></span>
                    </div>
                    <?php } ?>
                    <div class="detail-info-row">
                        <span class="muted detail-info-label">Payment</span>
                        <span style="font-weight:600; font-size:15px; color:#fff;">Cash on Delivery (COD)</span>
                    </div>
                    <div class="detail-info-row">
                        <span class="muted detail-info-label">Date Placed</span>
                        <span style="font-size:15px; color:#e2e8f0;"><?= date('M d, Y · g:i A', strtotime($order['date_ordered'])) ?></span>
                    </div>
                </div>
            </div>

            <!-- CTA Actions -->
            <div style="display:flex; gap:.75rem; flex-wrap:wrap; align-items:center;">
                <a href="orders.php" class="btn ghost sm" style="font-size:13px; font-weight:700; padding:8px 16px; display:inline-flex; align-items:center; gap:6px;">
                    <?= icon('arrow-left', '', 15) ?>
                    <span>Back to Orders</span>
                </a>
                <?php if ($order['status'] === 'Completed' && !empty($items)) { 
                    $unreviewed = array();
                    foreach ($items as $it) {
                        if (!isset($order_reviews[$it['item_id']])) {
                            $unreviewed[] = $it;
                        }
                    }
                    $all_reviewed = (count($unreviewed) === 0);
                    $target_it = $all_reviewed ? $items[0] : $unreviewed[0];
                ?>
                    <a href="item/detail.php?id=<?= $target_it['item_id'] ?>&order_id=<?= $order['order_id'] ?>#reviews" class="btn sm" style="<?= $all_reviewed ? 'background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.45);' : 'background:rgba(245,158,11,0.28); color:#fbbf24; border:1px solid rgba(245,158,11,0.65); font-weight:700; box-shadow:0 0 10px rgba(245,158,11,0.3);' ?> font-size:13px; font-weight:700; padding:8px 18px; display:inline-flex; align-items:center; gap:6px;">
                        <?= $all_reviewed ? icon('check', '', 14) . ' <span>Reviewed (Edit)</span>' : icon('star', '', 14) . ' <span>Review Gear</span>' ?>
                    </a>
                <?php } ?>
                <?php if ($can_receive) { ?>
                    <form method="POST" action="actions/receive_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button class="btn sm" type="submit"
                            onclick="return confirm('Confirm you received this order?')"
                            style="background:rgba(81,175,67,0.85); box-shadow:0 2px 14px rgba(81,175,67,0.3); font-size:13px; font-weight:700; padding:8px 18px; display:inline-flex; align-items:center; gap:6px;">
                            <?= icon('check-circle', '', 16) ?>
                            <span>Order Received</span>
                        </button>
                    </form>
                <?php } elseif ($order['status'] === 'Shipped' || $order['status'] === 'Out for Delivery' || $order['status'] === 'Ready for Pick Up') { ?>
                    <button class="btn ghost sm" disabled style="opacity:0.6; cursor:not-allowed;" title="Waiting for delivery confirmation">
                        Waiting for Delivery
                    </button>
                <?php } ?>
                <?php if ($can_cancel) { ?>
                    <form method="POST" action="actions/cancel_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $order['order_id'] ?>">
                        <button class="btn ghost sm" type="submit"
                            onclick="return confirm('Cancel this order?')"
                            style="color:#ff9f96;border-color:rgba(255,100,80,0.3);">
                            Cancel Order
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>

        <!-- RIGHT: Status Timeline -->
        <div class="order-detail-right">
            <div class="detail-card timeline-card">
                <div class="detail-card-title">Order Timeline</div>
                <div class="timeline">
                    <?php foreach ($timeline_steps as $i => $step) {
                        $is_done    = ($i < $current_step_index);
                        $is_current = ($i === $current_step_index);
                        $is_future  = ($i > $current_step_index);
                        $cls = 'timeline-step';
                        if ($is_done)    $cls .= ' done';
                        if ($is_current) $cls .= ' current';
                        if ($is_future)  $cls .= ' future';
                        $ts = isset($log_map[$step]) ? date('M d, g:i A', strtotime($log_map[$step])) : null;
                    ?>
                        <div class="<?= $cls ?>">
                            <div class="timeline-dot">
                                <?php if ($is_done) { echo '✓'; }
                                elseif ($is_current && $step !== 'Cancelled') { echo '●'; }
                                elseif ($step === 'Cancelled') { echo '✕'; }
                                else { echo '○'; } ?>
                            </div>
                            <div class="timeline-content">
                                <span class="timeline-label"><?= htmlspecialchars($step) ?></span>
                                <?php if ($ts) { ?>
                                    <span class="timeline-time muted"><?= $ts ?></span>
                                <?php } elseif ($is_current && $step !== 'Completed' && $step !== 'Cancelled') { ?>
                                    <span class="timeline-time" style="color:var(--color-accent-on-dark);">← Current</span>
                                <?php } elseif ($is_future && $step === 'Completed') { ?>
                                    <span class="timeline-time muted">Waiting for your confirmation</span>
                                <?php } ?>
                            </div>
                        </div>
                        <?php if ($i < count($timeline_steps) - 1) { ?>
                            <div class="timeline-line <?= ($i < $current_step_index) ? 'done' : '' ?>"></div>
                        <?php } ?>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
