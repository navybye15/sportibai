<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

// ── Status transition rules ───────────────────────────────────────────────────
// Keeping all rules in one place prevents bypassing via direct POST.
function allowed_next_statuses($current_status, $order_type) {
    switch ($current_status) {
        case 'Pending':
            return array('Prepared', 'Cancelled');
        case 'Prepared':
            if ($order_type === 'delivery') {
                return array('Ready to Ship', 'Cancelled');
            }
            return array('Ready for Pick Up', 'Cancelled');
        case 'Ready to Ship':
            return array('Shipped', 'Cancelled');
        case 'Shipped':
            return array('Out for Delivery', 'Cancelled');
        case 'Out for Delivery':
            return array('Delivered', 'Cancelled');
        case 'Ready for Pick Up':
            return array('Delivered', 'Cancelled');
        case 'Delivered':
            // Customer marks Completed via receive_order.php, but admin can also force it
            return array('Completed');
    }
    // Completed and Cancelled are final
    return array();
}

// ── Handle status update POST ─────────────────────────────────────────────────
if (isset($_POST['update_status'])) {
    if (!csrf_verify()) {
        header('Location: orders.php');
        exit;
    }
    $order_id   = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'];

    try {
        mysqli_begin_transaction($conn);

        $sql    = "SELECT status, order_type FROM orders WHERE order_id = ? LIMIT 1 FOR UPDATE";
        $result = mysqli_execute_query($conn, $sql, array($order_id));
        $order  = mysqli_fetch_assoc($result);

        if ($order === null) throw new Exception('Order not found.');

        $allowed = allowed_next_statuses($order['status'], $order['order_type']);
        if (!in_array($new_status, $allowed)) {
            throw new Exception('That status change is not allowed.');
        }

        if ($new_status === 'Cancelled') {
            // Return stock for all items (both per-size stock and total item stock)
            $sql    = "SELECT item_id, selected_size, quantity FROM order_items WHERE order_id = ?";
            $result = mysqli_execute_query($conn, $sql, array($order_id));
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['selected_size'])) {
                    mysqli_execute_query($conn, "UPDATE item_sizes SET stock = stock + ? WHERE item_id = ? AND size_label = ?", array($row['quantity'], $row['item_id'], $row['selected_size']));
                }
                $sql2 = "UPDATE items SET stock = stock + ? WHERE item_id = ?";
                mysqli_execute_query($conn, $sql2, array($row['quantity'], $row['item_id']));
            }
            $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
            mysqli_execute_query($conn, $sql, array($new_status, $order_id));
        } elseif ($new_status === 'Completed') {
            $sql = "UPDATE orders SET status = ?, completed_at = NOW() WHERE order_id = ?";
            mysqli_execute_query($conn, $sql, array($new_status, $order_id));
        } else {
            $sql = "UPDATE orders SET status = ? WHERE order_id = ?";
            mysqli_execute_query($conn, $sql, array($new_status, $order_id));
        }

        // Log every status change
        $sql = "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?, ?, 'admin')";
        mysqli_execute_query($conn, $sql, array($order_id, $new_status));

        mysqli_commit($conn);

        // Term Test Lab requirement: Send email to customer on status update + PDF attachment
        require_once __DIR__ . '/../includes/mailer.php';
        send_order_status_update_email($order_id, $new_status, $conn);

        $_SESSION['success'] = 'Order #' . $order_id . ' is now ' . $new_status . '. Notification email & PDF receipt sent to customer.';
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = $e->getMessage();
    }
    header('Location: orders.php');
    exit;
}

// ── Active tab + search ───────────────────────────────────────────────────────
$valid_tabs = array('all','Pending','Prepared','Ready to Ship','Shipped',
                    'Out for Delivery','Ready for Pick Up','Delivered','Completed','Cancelled');
$active_tab = 'all';
if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)) {
    $active_tab = $_GET['tab'];
}
$search = trim($_GET['q'] ?? '');

// ── Fetch orders with optional filter ────────────────────────────────────────
$orders = array();
$params = array();
$where  = array();

if ($active_tab !== 'all') {
    $where[]  = "o.status = ?";
    $params[] = $active_tab;
}
if ($search !== '') {
    $where[]  = "(c.first_name LIKE ? OR c.last_name LIKE ? OR o.reference_number LIKE ? OR o.contact_number LIKE ?)";
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$where_sql = count($where) > 0 ? ('WHERE ' . implode(' AND ', $where)) : '';

// Count total matching orders for pagination
$count_sql = "SELECT COUNT(*) AS total
              FROM orders o
              JOIN customer c ON o.customer_id = c.customer_id
              $where_sql";
$count_res = count($params) > 0 ? mysqli_execute_query($conn, $count_sql, $params) : mysqli_query($conn, $count_sql);
$matching_orders = (int)(mysqli_fetch_assoc($count_res)['total'] ?? 0);

$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$total_pages = max(1, (int)ceil($matching_orders / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$sql    = "SELECT o.order_id, o.reference_number, o.order_type, o.delivery_address,
                  o.contact_number, o.snapshot_name, o.total, o.status, o.date_ordered,
                  c.first_name, c.last_name
           FROM orders o
           JOIN customer c ON o.customer_id = c.customer_id
           $where_sql
           ORDER BY o.order_id DESC
           LIMIT $per_page OFFSET $offset";
$result = count($params) > 0
    ? mysqli_execute_query($conn, $sql, $params)
    : mysqli_query($conn, $sql);
while ($row = mysqli_fetch_assoc($result)) {
    $orders[] = $row;
}

// ── Fetch all order items ─────────────────────────────────────────────────────
$order_items = array();
if (count($orders) > 0) {
    $ids    = implode(',', array_column($orders, 'order_id'));
    $sql    = "SELECT oi.order_id, i.name, i.image, oi.selected_size, oi.quantity, oi.price
               FROM order_items oi JOIN items i ON oi.item_id = i.item_id
               WHERE oi.order_id IN ($ids) ORDER BY oi.order_item_id";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[$row['order_id']][] = $row;
    }
}

// ── Tab counts & Pipeline KPI metrics ─────────────────────────────────────────
$count_sql    = "SELECT status, COUNT(*) AS cnt, SUM(total) AS sum_total FROM orders GROUP BY status";
$count_result = mysqli_query($conn, $count_sql);
$status_counts = array();
$total_count   = 0;
$kpi_pending   = 0;
$kpi_transit   = 0;
$kpi_completed = 0;
$kpi_completed_val = 0;

if ($count_result) {
    while ($cr = mysqli_fetch_assoc($count_result)) {
        $st = $cr['status'];
        $c  = (int)$cr['cnt'];
        $val = (float)$cr['sum_total'];
        $status_counts[$st] = $c;
        $total_count += $c;
        if ($st === 'Pending') {
            $kpi_pending += $c;
        } elseif (in_array($st, array('Prepared', 'Ready to Ship', 'Shipped', 'Out for Delivery', 'Ready for Pick Up'))) {
            $kpi_transit += $c;
        } elseif ($st === 'Completed') {
            $kpi_completed += $c;
            $kpi_completed_val += $val;
        }
    }
}

$base = '../';
$page_title = 'Manage Orders | Sportibai';
require '../includes/header.php';
?>

<section class="reveal in">
    <!-- Header & Search Toolbar -->
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ PIPELINE MANAGEMENT ]</div>
            <h1 style="font-size:var(--text-display-l); line-height:1.15; margin:2px 0 0;">Customer Orders</h1>
            <p class="muted" style="font-size:13px; margin:4px 0 0;">Track fulfillment, update order statuses, and inspect shipping information.</p>
        </div>
        <!-- Search -->
        <form method="GET" action="orders.php" style="display:flex; gap:.6rem; align-items:center; flex-wrap:wrap;">
            <?php if ($active_tab !== 'all') { ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <?php } ?>
            <div style="position:relative; display:flex; align-items:center;">
                <input type="search" name="q" class="search" style="min-width:17rem; padding:0.65rem 1rem 0.65rem 2.2rem; border-radius:10px;"
                    placeholder="Search name, ref #, phone…"
                    value="<?= htmlspecialchars($search) ?>">
                <span style="position:absolute; left:12px; opacity:0.5; pointer-events:none; font-size:13px;">🔍</span>
            </div>
            <button class="btn sm" type="submit" style="padding:0.65rem 1.25rem; border-radius:10px;">Search</button>
            <?php if ($search !== '') { ?>
                <a class="btn ghost sm" href="orders.php?tab=<?= urlencode($active_tab) ?>" style="border-radius:10px;">Clear</a>
            <?php } ?>
        </form>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- Pipeline KPI Metrics Ribbon -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; margin-bottom:1.75rem;">
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Total Pipeline</div>
            <div style="font-size:1.75rem; font-weight:800; color:#fff; margin-top:4px;"><?= $total_count ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;">All customer transactions</div>
        </div>
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid <?= $kpi_pending > 0 ? 'rgba(224, 35, 28, 0.4)' : 'rgba(255,255,255,0.08)' ?>;">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:<?= $kpi_pending > 0 ? '#ff6e64' : 'var(--color-muted)' ?>;">
                <?= $kpi_pending > 0 ? '⚠️ Pending Action' : 'Pending Action' ?>
            </div>
            <div style="font-size:1.75rem; font-weight:800; color:<?= $kpi_pending > 0 ? '#ff6e64' : '#fff' ?>; margin-top:4px;">
                <?= $kpi_pending ?>
            </div>
            <div class="muted" style="font-size:11px; margin-top:2px;">Orders waiting for confirmation</div>
        </div>
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">In Fulfillment</div>
            <div style="font-size:1.75rem; font-weight:800; color:var(--color-accent-on-dark); margin-top:4px;"><?= $kpi_transit ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;">Prepared / Out for delivery / Shipped</div>
        </div>
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Delivered Volume</div>
            <div style="font-size:1.75rem; font-weight:800; color:var(--color-brand-green); margin-top:4px;"><?= peso($kpi_completed_val) ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;"><?= $kpi_completed ?> orders completed</div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="order-tabs admin-tabs" style="margin-bottom:1.75rem; flex-wrap:wrap; gap:6px;">
        <?php
        $tab_defs = array(
            'all'              => 'All Orders',
            'Pending'          => 'Pending',
            'Prepared'         => 'Prepared',
            'Ready to Ship'    => 'Ready to Ship',
            'Shipped'          => 'Shipped',
            'Out for Delivery' => 'Out for Delivery',
            'Ready for Pick Up'=> 'Ready for Pick Up',
            'Delivered'        => 'Delivered',
            'Completed'        => 'Completed',
            'Cancelled'        => 'Cancelled',
        );
        foreach ($tab_defs as $key => $label) {
            $cls   = ($active_tab === $key) ? 'order-tab active' : 'order-tab';
            $count = ($key === 'all') ? $total_count : ($status_counts[$key] ?? 0);
            $is_alert_tab = ($key === 'Pending' && $count > 0);
        ?>
            <a href="orders.php?tab=<?= urlencode($key) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="<?= $cls ?>" style="font-size:12px; padding:7px 14px; border-radius:10px;">
                <?= htmlspecialchars($label) ?>
                <?php if ($count > 0) { ?>
                    <span class="tab-badge" style="<?= $is_alert_tab ? 'background:var(--color-brand-red); color:#fff;' : '' ?>">
                        <?= $count ?>
                    </span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>

    <!-- Orders Feed -->
    <?php if (count($orders) === 0) { ?>
        <div class="card in" style="text-align:center; padding:4rem 2rem; border-radius:16px;">
            <div style="font-size:2.5rem; margin-bottom:0.75rem;">📦</div>
            <h3 style="font-size:1.15rem; font-weight:700; color:#fff; margin-bottom:4px;">No Orders Found</h3>
            <p class="muted" style="font-size:13px; margin:0;">
                <?= $search ? 'No orders matching "' . htmlspecialchars($search) . '".' : 'There are no customer orders in this category right now.' ?>
            </p>
        </div>
    <?php } ?>

    <div class="order-list" style="display:flex; flex-direction:column; gap:1.25rem;">
    <?php foreach ($orders as $o) {
        $ref      = $o['reference_number'] ?? ('SB-' . str_pad($o['order_id'], 7, '0', STR_PAD_LEFT));
        $name     = $o['snapshot_name'] ?? ($o['first_name'] . ' ' . $o['last_name']);
        $allowed  = allowed_next_statuses($o['status'], $o['order_type']);
        $is_delivery = ($o['order_type'] === 'delivery');

        // Parse physical address and special instructions note cleanly
        $raw_addr  = $o['delivery_address'] ?? '';
        $addr_text = $raw_addr;
        $note_text = '';
        if (strpos($raw_addr, "Note: ") !== false) {
            $parts = explode("Note: ", $raw_addr, 2);
            $addr_text = trim($parts[0], " \t\n\r\0\x0B,");
            $note_text = trim($parts[1]);
        }
    ?>
        <div class="order-card reveal in" style="padding:1.5rem; border-radius:16px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255, 255, 255, 0.08); box-shadow:0 8px 24px rgba(0,0,0,0.25);">
            <!-- Card Header: Reference, Badges, Total & Date -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:1rem; padding-bottom:1rem; border-bottom:1px solid rgba(255,255,255,0.06);">
                <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button type="button" onclick="copyRef('<?= htmlspecialchars($ref) ?>', this)" title="Click to copy order reference"
                        style="background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.12); color:#fff; font-family:var(--font-mono); font-size:14px; font-weight:800; padding:4px 10px; border-radius:8px; cursor:pointer; display:inline-flex; align-items:center; gap:6px; letter-spacing:0.5px;">
                        <span>#<?= htmlspecialchars($ref) ?></span>
                        <span style="font-size:11px; opacity:0.6;">📋</span>
                    </button>
                    <span class="status-badge" style="<?= status_badge_style($o['status']) ?> font-size:11px; padding:3px 10px; border-radius:8px;">
                        ● <?= htmlspecialchars($o['status']) ?>
                    </span>
                    <span style="font-size:11px; font-weight:700; padding:3px 9px; border-radius:6px; background:rgba(255,255,255,0.06); color:var(--color-fg-secondary); text-transform:uppercase; letter-spacing:0.5px;">
                        <?= $is_delivery ? '🚚 Delivery' : '🏬 Store Pick Up' ?>
                    </span>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:1.25rem; font-weight:800; color:var(--color-brand-green); letter-spacing:-0.01em;">
                        <?= peso($o['total']) ?>
                    </div>
                    <div class="muted" style="font-size:11px; margin-top:2px;">
                        <?= date('M d, Y • g:i A', strtotime($o['date_ordered'])) ?>
                    </div>
                </div>
            </div>

            <!-- Customer & Shipping Information Strip -->
            <div style="display:flex; flex-direction:column; gap:8px; margin:1rem 0;">
                <div style="display:flex; flex-wrap:wrap; gap:1.25rem; align-items:center; background:rgba(255,255,255,0.025); border:1px solid rgba(255,255,255,0.04); border-radius:10px; padding:10px 14px; font-size:12px;">
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="opacity:0.6;">👤</span>
                        <strong style="color:#fff;"><?= htmlspecialchars($name ?: 'Customer') ?></strong>
                    </div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span style="opacity:0.6;">📞</span>
                        <a href="tel:<?= htmlspecialchars($o['contact_number']) ?>" style="color:var(--color-fg-secondary); text-decoration:none;">
                            <?= htmlspecialchars($o['contact_number'] ?: 'No phone provided') ?>
                        </a>
                    </div>
                    <?php if ($is_delivery && !empty($addr_text)) { ?>
                        <div style="display:flex; align-items:center; gap:6px; flex-grow:1;">
                            <span style="opacity:0.6;">📍</span>
                            <span style="color:var(--color-fg-secondary);"><?= htmlspecialchars($addr_text) ?></span>
                        </div>
                    <?php } ?>
                </div>

                <?php if (!empty($note_text)) { ?>
                    <div style="display:flex; align-items:center; gap:8px; background:rgba(245, 158, 11, 0.08); border:1px solid rgba(245, 158, 11, 0.25); border-radius:8px; padding:6px 12px; font-size:12px; color:#fbbf24;">
                        <span style="font-size:13px;">💬</span>
                        <span><strong>Delivery Note:</strong> <?= htmlspecialchars($note_text) ?></span>
                    </div>
                <?php } ?>
            </div>

            <!-- Items with Visual Thumbnails -->
            <?php if (isset($order_items[$o['order_id']])) { ?>
            <div style="margin:1rem 0;">
                <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted); margin-bottom:8px;">
                    Order Package (<?= count($order_items[$o['order_id']]) ?> items)
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:10px;">
                    <?php foreach ($order_items[$o['order_id']] as $oi) { 
                        $has_img = !empty($oi['image']) && file_exists(__DIR__ . '/../images/uploads/' . $oi['image']);
                        $img_src = $has_img ? '../images/uploads/' . htmlspecialchars($oi['image']) : '../images/placeholder.svg';
                    ?>
                        <div style="display:flex; align-items:center; gap:12px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:10px 14px; border-radius:10px;">
                            <img src="<?= $img_src ?>" alt="" style="width:46px; height:46px; object-fit:cover; border-radius:8px; flex-shrink:0; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08);">
                            <div style="flex-grow:1; min-width:0;">
                                <div style="font-size:13px; font-weight:600; color:#fff; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                                    <?= htmlspecialchars($oi['name']) ?>
                                </div>
                                <div style="display:flex; align-items:center; gap:6px; margin-top:3px; font-size:11px;">
                                    <span style="color:var(--color-accent-on-dark); font-weight:700;"><?= (int)$oi['quantity'] ?>x</span>
                                    <?php if (!empty($oi['selected_size'])) { ?>
                                        <span style="background:rgba(255,255,255,0.08); padding:1px 6px; border-radius:4px; font-size:10px; font-weight:700; color:#fff;">
                                            Size: <?= htmlspecialchars($oi['selected_size']) ?>
                                        </span>
                                    <?php } ?>
                                    <span class="muted"><?= peso($oi['price']) ?> each</span>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>

            <!-- Status Update & Receipt Actions Footer -->
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; padding-top:1rem; border-top:1px solid rgba(255,255,255,0.06);">
                <div>
                    <a href="../actions/download_receipt.php?order_id=<?= $o['order_id'] ?>" target="_blank" class="btn ghost sm" style="font-size:11px; padding:6px 14px; border-radius:8px; display:inline-flex; align-items:center; gap:6px;">
                        📄 View PDF Receipt
                    </a>
                </div>
                <div>
                    <?php if (count($allowed) > 0) { ?>
                        <form method="POST" action="orders.php" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                            <span style="font-size:11px; font-weight:700; text-transform:uppercase; color:var(--color-fg-secondary); letter-spacing:0.5px;">Transition to:</span>
                            <select name="new_status" class="search" style="min-width:11rem; padding:0.45rem 0.8rem; font-size:12px; font-weight:600; border-radius:8px;">
                                <?php foreach ($allowed as $status) { ?>
                                    <option value="<?= htmlspecialchars($status) ?>"
                                        <?= $status === 'Cancelled' ? 'style="color:#ff9f96;"' : '' ?>>
                                        <?= htmlspecialchars($status) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button class="btn sm" type="submit" name="update_status" style="font-size:11px; padding:0.48rem 1.1rem; border-radius:8px; font-weight:700;">
                                Apply Status &rarr;
                            </button>
                        </form>
                    <?php } elseif ($o['status'] === 'Completed') { ?>
                        <span style="font-size:12px; color:var(--color-brand-green); font-weight:700; display:inline-flex; align-items:center; gap:6px; background:rgba(81,175,67,0.1); border:1px solid rgba(81,175,67,0.25); padding:4px 12px; border-radius:8px;">
                            ✓ Order Fulfilled &amp; Completed
                        </span>
                    <?php } elseif ($o['status'] === 'Cancelled') { ?>
                        <span style="font-size:12px; color:#ff6b6b; font-weight:600; display:inline-flex; align-items:center; gap:6px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.25); padding:4px 12px; border-radius:8px;">
                            ✕ Order Cancelled &amp; Inventory Restocked
                        </span>
                    <?php } ?>
                </div>
            </div>
        </div>
    <?php } ?>
    </div>

<script>
function copyRef(ref, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(ref).then(function() {
            const orig = btn.innerHTML;
            btn.innerHTML = '<span>Copied!</span> <span style="font-size:11px; color:var(--color-brand-green);">✓</span>';
            setTimeout(function() { btn.innerHTML = orig; }, 1500);
        });
    }
}
</script>

    <!-- Admin Orders Pagination -->
    <?php if ($total_pages > 1) { ?>
        <div style="display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-top:2.5rem; flex-wrap:wrap;">
            <?php 
            $base_params = array('tab' => $active_tab);
            if ($search !== '') $base_params['q'] = $search;

            if ($page > 1) { 
                $prev_p = $base_params; $prev_p['page'] = $page - 1;
            ?>
                <a href="orders.php?<?= http_build_query($prev_p) ?>" class="btn ghost sm">← Prev</a>
            <?php } ?>

            <?php for ($p = 1; $p <= $total_pages; $p++) { 
                $p_params = $base_params; $p_params['page'] = $p;
                $is_cur = ($p === $page);
            ?>
                <a href="orders.php?<?= http_build_query($p_params) ?>" 
                   class="btn sm <?= $is_cur ? '' : 'ghost' ?>"
                   style="<?= $is_cur ? 'background:var(--color-accent);' : 'min-width:36px; padding:0.4rem 0.75rem;' ?>">
                    <?= $p ?>
                </a>
            <?php } ?>

            <?php if ($page < $total_pages) { 
                $next_p = $base_params; $next_p['page'] = $page + 1;
            ?>
                <a href="orders.php?<?= http_build_query($next_p) ?>" class="btn ghost sm">Next →</a>
            <?php } ?>
        </div>
    <?php } ?>
</section>
<?php require '../includes/footer.php'; ?>
