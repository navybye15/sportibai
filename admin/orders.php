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
            // Return stock for all items
            $sql    = "SELECT item_id, quantity FROM order_items WHERE order_id = ?";
            $result = mysqli_execute_query($conn, $sql, array($order_id));
            while ($row = mysqli_fetch_assoc($result)) {
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
    $sql    = "SELECT oi.order_id, i.name, i.image, oi.quantity, oi.price
               FROM order_items oi JOIN items i ON oi.item_id = i.item_id
               WHERE oi.order_id IN ($ids) ORDER BY oi.order_item_id";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[$row['order_id']][] = $row;
    }
}

// ── Tab counts ────────────────────────────────────────────────────────────────
$count_sql    = "SELECT status, COUNT(*) AS cnt FROM orders GROUP BY status";
$count_result = mysqli_query($conn, $count_sql);
$status_counts = array();
$total_count   = 0;
while ($cr = mysqli_fetch_assoc($count_result)) {
    $status_counts[$cr['status']] = (int)$cr['cnt'];
    $total_count += (int)$cr['cnt'];
}

$base = '../';
$page_title = 'Manage Orders | Sportibai';
require '../includes/header.php';
?>

<section class="reveal">
    <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ PIPELINE MANAGEMENT ]</div>
            <h1 style="font-size:var(--text-display-l)">Customer Orders</h1>
        </div>
        <!-- Search -->
        <form method="GET" action="orders.php" style="display:flex; gap:.75rem; align-items:center;">
            <?php if ($active_tab !== 'all') { ?>
                <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
            <?php } ?>
            <input type="search" name="q" class="search" style="min-width:16rem;"
                placeholder="Search name, ref, phone…"
                value="<?= htmlspecialchars($search) ?>">
            <button class="btn sm" type="submit">Search</button>
            <?php if ($search !== '') { ?>
                <a class="btn ghost sm" href="orders.php?tab=<?= urlencode($active_tab) ?>">Clear</a>
            <?php } ?>
        </form>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- Status Tabs -->
    <div class="order-tabs admin-tabs" style="margin-bottom:1.5rem; flex-wrap:wrap;">
        <?php
        $tab_defs = array(
            'all'              => 'All',
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
        ?>
            <a href="orders.php?tab=<?= urlencode($key) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"
               class="<?= $cls ?>" style="font-size:11px; padding:6px 14px;">
                <?= htmlspecialchars($label) ?>
                <?php if ($count > 0) { ?>
                    <span class="tab-badge"><?= $count ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>

    <!-- Orders -->
    <?php if (count($orders) === 0) { ?>
        <div class="card in" style="text-align:center; padding:4rem 2rem;">
            <p class="muted" style="font-size:var(--text-body-lead)">
                <?= $search ? 'No orders matching "' . htmlspecialchars($search) . '".' : 'No orders in this status.' ?>
            </p>
        </div>
    <?php } ?>

    <div class="order-list">
    <?php foreach ($orders as $o) {
        $ref      = $o['reference_number'] ?? ('SB-' . str_pad($o['order_id'], 7, '0', STR_PAD_LEFT));
        $name     = $o['snapshot_name'] ?? ($o['first_name'] . ' ' . $o['last_name']);
        $allowed  = allowed_next_statuses($o['status'], $o['order_type']);
    ?>
        <div class="order-card reveal">
            <div class="order-card-header">
                <div class="order-ref-row">
                    <span class="order-ref"><?= htmlspecialchars($ref) ?></span>
                    <span class="status-badge" style="<?= status_badge_style($o['status']) ?>">
                        <?= htmlspecialchars($o['status']) ?>
                    </span>
                </div>
                <div class="order-meta">
                    <strong style="color:var(--color-fg)"><?= htmlspecialchars($name) ?></strong>
                    &nbsp;·&nbsp;<?= ucfirst($o['order_type']) ?>
                    &nbsp;·&nbsp;<?= peso($o['total']) ?>
                    &nbsp;·&nbsp;<?= date('M d, Y g:i A', strtotime($o['date_ordered'])) ?>
                </div>
            </div>

            <!-- Items with Visual Thumbnails -->
            <?php if (isset($order_items[$o['order_id']])) { ?>
            <div class="order-items-preview" style="display:flex; flex-wrap:wrap; gap:10px; margin:0.75rem 0;">
                <?php foreach ($order_items[$o['order_id']] as $oi) { 
                    $has_img = !empty($oi['image']) && file_exists(__DIR__ . '/../images/uploads/' . $oi['image']);
                    $img_src = $has_img ? '../images/uploads/' . htmlspecialchars($oi['image']) : '../images/placeholder.svg';
                ?>
                    <div style="display:flex; align-items:center; gap:8px; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.06); padding:4px 10px; border-radius:8px;">
                        <img src="<?= $img_src ?>" alt="" style="width:34px; height:34px; object-fit:cover; border-radius:6px; flex-shrink:0; background:rgba(255,255,255,0.05);">
                        <span style="font-size:13px; font-weight:500;">
                            <strong style="color:var(--color-accent-on-dark);"><?= htmlspecialchars($oi['quantity']) ?>x</strong> <?= htmlspecialchars($oi['name']) ?>
                            <span class="muted" style="font-size:11px; margin-left:4px;">(<?= peso($oi['price']) ?>)</span>
                        </span>
                    </div>
                <?php } ?>
            </div>
            <?php } ?>

            <!-- Address / Contact -->
            <div class="muted" style="font-size:var(--text-body-sm); margin-top:.25rem;">
                <?php if ($o['order_type'] === 'delivery' && $o['delivery_address']) { ?>
                    📍 <?= nl2br(htmlspecialchars($o['delivery_address'])) ?> &nbsp;·&nbsp;
                <?php } ?>
                📞 <?= htmlspecialchars($o['contact_number']) ?>
            </div>

            <!-- Status Update Actions -->
            <div class="order-card-actions" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                <a href="../download_receipt.php?order_id=<?= $o['order_id'] ?>" target="_blank" class="btn ghost sm" style="font-size:11px;">📄 PDF Receipt</a>
                <?php if (count($allowed) > 0) { ?>
                <form method="POST" action="orders.php" style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                    <select name="new_status" class="search" style="min-width:10rem; padding:0.5rem 0.8rem; flex:0 1 auto;">
                        <?php foreach ($allowed as $status) { ?>
                            <option value="<?= htmlspecialchars($status) ?>"
                                <?= $status === 'Cancelled' ? 'style="color:#ff9f96;"' : '' ?>>
                                <?= htmlspecialchars($status) ?>
                            </option>
                        <?php } ?>
                    </select>
                    <button class="btn sm" type="submit" name="update_status">Update Status</button>
                </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    </div>

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
