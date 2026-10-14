<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

// Add a new item
if (isset($_POST['add_item'])) {
    if (!csrf_verify()) {
        header('Location: items.php');
        exit;
    }
    try {
        $name = strip_tags(trim($_POST['name']));
        $brand = strip_tags(trim($_POST['brand']));
        $description = strip_tags(trim($_POST['description']));
        $price = (float) $_POST['price'];
        $cost_price = isset($_POST['cost_price']) ? (float) $_POST['cost_price'] : 0.00;
        $stock = (int) $_POST['stock'];
        $category_id = (int) $_POST['category_id'];

        if ($name == '' || $price <= 0 || $stock < 0) {
            throw new Exception('Please fill in a valid name, price, and stock.');
        }

        $image = handle_item_image($_FILES['image']);

        $sql = "INSERT INTO items (category_id, name, brand, description, price, cost_price, stock, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        mysqli_execute_query($conn, $sql, array($category_id, $name, $brand, $description, $price, $cost_price, $stock, $image));
        $new_id = mysqli_insert_id($conn);

        // Record initial stock in stock_log
        if ($stock > 0) {
            $user_id = $_SESSION['user_id'] ?? null;
            $sql_log = "INSERT INTO stock_log (item_id, change_qty, new_stock, reason, user_id) VALUES (?, ?, ?, 'Initial inventory count', ?)";
            @mysqli_execute_query($conn, $sql_log, array($new_id, $stock, $stock, $user_id));
        }

        $_SESSION['success'] = 'Item published to store catalog.';
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
    }
    header('Location: items.php');
    exit;
}

// Soft delete / archive item
if (isset($_POST['deactivate_item'])) {
    if (!csrf_verify()) {
        header('Location: items.php');
        exit;
    }
    $item_id = (int) $_POST['item_id'];
    $sql = "UPDATE items SET is_active = 0 WHERE item_id = ?";
    mysqli_execute_query($conn, $sql, array($item_id));
    $_SESSION['success'] = 'Item archived from the shop.';
    header('Location: items.php');
    exit;
}

// Restore soft-deleted item
if (isset($_POST['restore_item'])) {
    if (!csrf_verify()) {
        header('Location: items.php?view=archived');
        exit;
    }
    $item_id = (int) $_POST['item_id'];
    $sql = "UPDATE items SET is_active = 1 WHERE item_id = ?";
    mysqli_execute_query($conn, $sql, array($item_id));
    $_SESSION['success'] = 'Item restored back to the active catalog.';
    header('Location: items.php?view=archived');
    exit;
}

// Stock Adjustment (Add or Deduct with Reason audit log)
if (isset($_POST['adjust_stock'])) {
    if (!csrf_verify()) {
        header('Location: items.php');
        exit;
    }
    $item_id = (int) $_POST['item_id'];
    $qty_change = (int) $_POST['qty_change'];
    $operation = $_POST['operation'] ?? 'add'; // 'add' or 'deduct'
    $reason = strip_tags(trim($_POST['reason'] ?? ''));

    if ($operation === 'deduct') {
        $qty_change = -abs($qty_change);
    } else {
        $qty_change = abs($qty_change);
    }

    if ($reason === '') {
        $reason = ($qty_change > 0) ? 'Manual Restock' : 'Damaged / Inventory Loss';
    }

    if ($qty_change !== 0) {
        $cur_res = mysqli_execute_query($conn, "SELECT stock, name FROM items WHERE item_id = ? LIMIT 1", array($item_id));
        $cur_item = mysqli_fetch_assoc($cur_res);
        if ($cur_item) {
            $new_stock = max(0, $cur_item['stock'] + $qty_change);
            $real_change = $new_stock - $cur_item['stock'];

            $sql = "UPDATE items SET stock = ? WHERE item_id = ?";
            mysqli_execute_query($conn, $sql, array($new_stock, $item_id));

            $user_id = $_SESSION['user_id'] ?? null;
            $sql_log = "INSERT INTO stock_log (item_id, change_qty, new_stock, reason, user_id) VALUES (?, ?, ?, ?, ?)";
            @mysqli_execute_query($conn, $sql_log, array($item_id, $real_change, $new_stock, $reason, $user_id));

            $_SESSION['success'] = 'Updated stock for ' . htmlspecialchars($cur_item['name']) . ' (New stock: ' . $new_stock . ').';
        }
    }
    header('Location: items.php');
    exit;
}

$view = $_GET['view'] ?? 'active';
$is_archived_view = ($view === 'archived');

$categories = array();
$result = mysqli_query($conn, "SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

$items = array();
$active_val = $is_archived_view ? 0 : 1;
$sql = "SELECT i.item_id, i.name, i.brand, i.price, i.cost_price, i.stock, i.image, c.name AS category
        FROM items i JOIN categories c ON i.category_id = c.category_id
        WHERE i.is_active = ?
        ORDER BY i.item_id DESC";
$result = mysqli_execute_query($conn, $sql, array($active_val));
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Counts for tabs
$res_active = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE is_active = 1");
$active_count = mysqli_fetch_assoc($res_active)['c'] ?? 0;
$res_archived = mysqli_query($conn, "SELECT COUNT(*) AS c FROM items WHERE is_active = 0");
$archived_count = mysqli_fetch_assoc($res_archived)['c'] ?? 0;

$base = '../';
$page_title = 'Manage Items | Sportibai';
require '../includes/header.php';
?>
<section class="reveal">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ CATALOG CONTROL ]</div>
            <h1 style="font-size: var(--text-display-l)">Manage Items</h1>
        </div>
        <div class="order-tabs" style="margin-bottom:0; border-bottom:none;">
            <a href="items.php?view=active" class="order-tab <?= !$is_archived_view ? 'active' : '' ?>">
                Active Catalog <span class="tab-badge"><?= $active_count ?></span>
            </a>
            <a href="items.php?view=archived" class="order-tab <?= $is_archived_view ? 'active' : '' ?>">
                Archived / Hidden <span class="tab-badge"><?= $archived_count ?></span>
            </a>
        </div>
    </div>
    <?php require '../includes/alert.php'; ?>

    <?php if (!$is_archived_view) { ?>
    <!-- Add Item Form -->
    <form id="add" class="form" method="POST" action="items.php" enctype="multipart/form-data" style="margin: 1.5rem 0 2.5rem; max-width: 100%;">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ PRODUCT INTAKE ]</div>
            <h2 style="font-size:1.35rem; margin-top:4px;">Add New Inventory Item</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
            <div>
                <label class="form-label">Item Name</label>
                <input type="text" name="name" placeholder="e.g. Wilson Evolution Basketball" required style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Brand</label>
                <input type="text" name="brand" placeholder="e.g. Wilson" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Category</label>
                <select name="category_id" required style="margin-top:0.6rem;">
                    <?php foreach ($categories as $c) { ?>
                        <option value="<?php echo $c['category_id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="form-label">Cost Price (₱) <small class="muted">Capital</small></label>
                <input type="number" step="0.01" name="cost_price" placeholder="0.00" value="0.00" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Selling Price (₱)</label>
                <input type="number" step="0.01" name="price" placeholder="0.00" required style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Initial Stock</label>
                <input type="number" name="stock" placeholder="Starting quantity" required style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Product Image (.jpg, .png, .webp)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.6rem;">
            </div>
        </div>
        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="2" placeholder="Item specs, materials, intended sport..." style="margin-top:0.6rem;"></textarea>
        </div>
        <div>
            <button class="btn" type="submit" name="add_item">+ Publish Item to Store</button>
        </div>
    </form>
    <?php } ?>

    <!-- Inventory Listing -->
    <div class="lines" style="padding-top:1rem;">
        <div class="rail-section-title" style="margin-bottom:0.5rem;">
            <?= $is_archived_view ? 'Archived Items' : 'Active Catalog' ?> (<?= count($items); ?> items)
        </div>
        <?php if (count($items) === 0) { ?>
            <div class="card in" style="text-align:center; padding:3rem;">
                <p class="muted">No items found in this section.</p>
            </div>
        <?php } ?>
        <?php foreach ($items as $item) {
            $cost = (float)($item['cost_price'] ?? 0);
            $margin = $item['price'] > 0 ? (($item['price'] - $cost) / $item['price'] * 100) : 0;
        ?>
            <div class="line" style="gap:1.25rem;">
                <?php item_tile($item, '../'); ?>
                <div style="flex-grow:1;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
                            <?php echo htmlspecialchars($item['category']); ?><?php echo $item['brand'] != '' ? ' • ' . htmlspecialchars($item['brand']) : ''; ?>
                        </span>
                        <?php if ($item['stock'] < 5 && !$is_archived_view) { ?>
                            <span class="status-badge" style="background:rgba(255,100,80,0.15); color:#ff9f96; border-color:rgba(255,100,80,0.3); font-size:10px; padding:2px 8px;">LOW STOCK</span>
                        <?php } ?>
                    </div>
                    <h2 style="font-size:1.15rem; margin-top:2px;"><?php echo htmlspecialchars($item['name']); ?></h2>
                    <div class="muted" style="font-size:var(--text-body-sm); margin-top:4px; display:flex; flex-wrap:wrap; gap:10px;">
                        <span>Price: <strong style="color:var(--color-fg)"><?php echo peso($item['price']); ?></strong></span>
                        <?php if ($cost > 0) { ?>
                            <span>Cost: <strong><?php echo peso($cost); ?></strong> (<?= round($margin, 1) ?>% profit)</span>
                        <?php } ?>
                        <span>Stock: <strong style="<?php echo $item['stock'] < 5 ? 'color:#ff9f96;' : 'color:var(--color-brand-green);'; ?>"><?php echo $item['stock']; ?> units</strong></span>
                    </div>
                </div>

                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; justify-content:flex-end;">
                    <?php if (!$is_archived_view) { ?>
                        <a class="btn ghost sm" href="item_edit.php?id=<?php echo $item['item_id']; ?>">Edit</a>

                        <!-- Stock Adjustment Form with Reason -->
                        <form method="POST" action="items.php" style="display:inline-flex; gap:.4rem; align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <select name="operation" class="search" style="padding:0.4rem 0.6rem; min-width:4.8rem; font-size:11px;">
                                <option value="add">+ Add</option>
                                <option value="deduct">- Deduct</option>
                            </select>
                            <input class="qty" type="number" name="qty_change" placeholder="Qty" min="1" required style="width:4.2rem;">
                            <input type="text" name="reason" placeholder="Reason (e.g. Restock / Lost)" class="co-input" style="width:11rem; padding:0.4rem 0.6rem; font-size:11px;">
                            <button class="btn sm" type="submit" name="adjust_stock" style="font-size:10px; padding:0.45rem 0.8rem;">Adjust</button>
                        </form>

                        <!-- Archive / Remove button -->
                        <form method="POST" action="items.php" style="display:inline"
                              onsubmit="return confirm('Archive this item? It will be hidden from the customer shop.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <button class="btn ghost sm" type="submit" name="deactivate_item" style="color:#ff9f96; border-color:rgba(255,100,100,0.25);">Archive</button>
                        </form>
                    <?php } else { ?>
                        <!-- Restore Button -->
                        <form method="POST" action="items.php" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <button class="btn sm" type="submit" name="restore_item" style="background:rgba(81,175,67,0.85); box-shadow:0 2px 14px rgba(81,175,67,0.3);">
                                ↺ Restore to Shop
                            </button>
                        </form>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>


