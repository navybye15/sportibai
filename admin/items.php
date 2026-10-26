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
        $name = strip_tags(trim($_POST['name'] ?? ''));
        $brand = strip_tags(trim($_POST['brand'] ?? ''));
        $description = strip_tags(trim($_POST['description'] ?? ''));
        $price_raw = trim($_POST['price'] ?? '');
        $cost_raw = trim($_POST['cost_price'] ?? '0');
        $stock_raw = trim($_POST['stock'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);

        // Pure PHP Server-side Validation (MP5 Requirement)
        if ($name === '') {
            throw new Exception('Item name is required.');
        }
        if ($category_id <= 0) {
            throw new Exception('Please select a valid category.');
        }
        if ($price_raw === '' || !is_numeric($price_raw) || (float)$price_raw <= 0) {
            throw new Exception('Please enter a valid selling price greater than 0.');
        }
        $price = (float)$price_raw;
        $cost_price = ($cost_raw !== '' && is_numeric($cost_raw)) ? max(0, (float)$cost_raw) : 0.00;

        $size_type = category_supports_sizes($conn, $category_id);
        $stock = 0;

        if ($size_type === 'apparel') {
            $apparel_inputs = $_POST['apparel_sizes'] ?? array();
            foreach ($apparel_inputs as $s_label => $s_qty) {
                $stock += max(0, (int)$s_qty);
            }
        } elseif ($size_type === 'footwear') {
            $footwear_inputs = $_POST['footwear_sizes'] ?? array();
            foreach ($footwear_inputs as $s_label => $s_qty) {
                $stock += max(0, (int)$s_qty);
            }
        } else {
            if ($stock_raw === '' || !is_numeric($stock_raw) || (int)$stock_raw < 0) {
                throw new Exception('Please enter a valid stock quantity (0 or greater).');
            }
            $stock = (int)$stock_raw;
        }

        // MP1: Single primary photo
        $image = handle_item_image($_FILES['image']);

        $sql = "INSERT INTO items (category_id, name, brand, description, price, cost_price, stock, image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        mysqli_execute_query($conn, $sql, array($category_id, $name, $brand, $description, $price, $cost_price, $stock, $image));
        $new_id = mysqli_insert_id($conn);

        // MP1: Upload multiple photos into item_images table
        if (isset($_FILES['gallery_images'])) {
            handle_multiple_item_images($_FILES['gallery_images'], $new_id, $conn);
        }

        // Option 1: If category supports sizes (Apparel / Footwear), save individual size variants
        if ($size_type === 'apparel') {
            $default_apparel = get_default_sizes_for_type('apparel');
            foreach ($default_apparel as $s_label) {
                $s_qty = isset($_POST['apparel_sizes'][$s_label]) ? max(0, (int)$_POST['apparel_sizes'][$s_label]) : 0;
                mysqli_execute_query($conn, "INSERT INTO item_sizes (item_id, size_label, stock) VALUES (?, ?, ?)", array($new_id, $s_label, $s_qty));
            }
            sync_item_total_stock($conn, $new_id);
        } elseif ($size_type === 'footwear') {
            $default_footwear = get_default_sizes_for_type('footwear');
            foreach ($default_footwear as $s_label) {
                $s_qty = isset($_POST['footwear_sizes'][$s_label]) ? max(0, (int)$_POST['footwear_sizes'][$s_label]) : 0;
                mysqli_execute_query($conn, "INSERT INTO item_sizes (item_id, size_label, stock) VALUES (?, ?, ?)", array($new_id, $s_label, $s_qty));
            }
            sync_item_total_stock($conn, $new_id);
        }

        // Record initial stock in stock_log
        if ($stock > 0) {
            $user_id = $_SESSION['user_id'] ?? null;
            $sql_log = "INSERT INTO stock_log (item_id, change_qty, new_stock, reason, user_id) VALUES (?, ?, ?, 'Initial inventory count', ?)";
            @mysqli_execute_query($conn, $sql_log, array($new_id, $stock, $stock, $user_id));
        }

        $_SESSION['success'] = 'Item published to store catalog with photos' . ($size_type ? ' and size variants.' : '.');
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
    $size_id = isset($_POST['size_id']) ? (int)$_POST['size_id'] : 0;
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
        if ($size_id > 0) {
            // Adjust specific size variant (Option 1)
            $sz_res = mysqli_execute_query($conn, "SELECT stock, size_label FROM item_sizes WHERE size_id = ? AND item_id = ? LIMIT 1", array($size_id, $item_id));
            if ($sz_row = mysqli_fetch_assoc($sz_res)) {
                $new_sz_stock = max(0, $sz_row['stock'] + $qty_change);
                mysqli_execute_query($conn, "UPDATE item_sizes SET stock = ? WHERE size_id = ?", array($new_sz_stock, $size_id));
                sync_item_total_stock($conn, $item_id);

                $cur_res = mysqli_execute_query($conn, "SELECT stock, name FROM items WHERE item_id = ? LIMIT 1", array($item_id));
                $cur_item = mysqli_fetch_assoc($cur_res);
                $new_stock = $cur_item['stock'] ?? 0;

                $user_id = $_SESSION['user_id'] ?? null;
                $sql_log = "INSERT INTO stock_log (item_id, change_qty, new_stock, reason, user_id) VALUES (?, ?, ?, ?, ?)";
                @mysqli_execute_query($conn, $sql_log, array($item_id, $qty_change, $new_stock, $reason . ' (Size ' . $sz_row['size_label'] . ')', $user_id));

                $_SESSION['success'] = 'Updated size ' . htmlspecialchars($sz_row['size_label']) . ' stock for ' . htmlspecialchars($cur_item['name']) . ' (New size stock: ' . $new_sz_stock . ').';
            }
        } else {
            // Adjust non-sized item or general stock
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

$search_q     = trim($_GET['q'] ?? '');
$filter_cat   = (int)($_GET['cat'] ?? 0);
$filter_stock = $_GET['stock'] ?? 'all';

$items = array();
$active_val = $is_archived_view ? 0 : 1;
$where_clauses = array("i.is_active = ?");
$where_params  = array($active_val);

if ($search_q !== '') {
    $where_clauses[] = "(i.name LIKE ? OR i.brand LIKE ? OR i.item_id = ?)";
    $like_q = "%$search_q%";
    $where_params[] = $like_q;
    $where_params[] = $like_q;
    $where_params[] = is_numeric($search_q) ? (int)$search_q : 0;
}
if ($filter_cat > 0) {
    $where_clauses[] = "i.category_id = ?";
    $where_params[] = $filter_cat;
}
if ($filter_stock === 'low') {
    $where_clauses[] = "i.stock < 5 AND i.stock > 0";
} elseif ($filter_stock === 'out') {
    $where_clauses[] = "i.stock = 0";
} elseif ($filter_stock === 'in') {
    $where_clauses[] = "i.stock >= 5";
}

$where_sql = implode(' AND ', $where_clauses);
$sql = "SELECT i.item_id, i.name, i.brand, i.price, i.cost_price, i.stock, i.image, c.name AS category
        FROM items i JOIN categories c ON i.category_id = c.category_id
        WHERE $where_sql
        ORDER BY i.item_id DESC";
$result = mysqli_execute_query($conn, $sql, $where_params);
while ($row = mysqli_fetch_assoc($result)) {
    $items[] = $row;
}

// Fetch all size variants mapped by item_id (Option 1)
$item_sizes_map = array();
$all_sizes_res = mysqli_query($conn, "SELECT item_id, size_id, size_label, stock FROM item_sizes ORDER BY size_id ASC");
if ($all_sizes_res) {
    while ($srow = mysqli_fetch_assoc($all_sizes_res)) {
        $item_sizes_map[$srow['item_id']][] = $srow;
    }
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
<section class="reveal in">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ CATALOG MANAGEMENT ]</div>
            <h1 style="font-size: var(--text-display-l); line-height:1.15; margin:2px 0 0;">Manage Items</h1>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            <?php if (!$is_archived_view) { ?>
                <button type="button" class="btn sm" onclick="toggleAddForm()" style="box-shadow:0 0 16px rgba(10,132,255,0.35);">
                    + Add New Item
                </button>
            <?php } ?>
            <div class="order-tabs" style="margin-bottom:0; border-bottom:none;">
                <a href="items.php?view=active" class="order-tab <?= !$is_archived_view ? 'active' : '' ?>">
                    Active <span class="tab-badge"><?= $active_count ?></span>
                </a>
                <a href="items.php?view=archived" class="order-tab <?= $is_archived_view ? 'active' : '' ?>">
                    Archived <span class="tab-badge"><?= $archived_count ?></span>
                </a>
            </div>
        </div>
    </div>
    <?php require '../includes/alert.php'; ?>

    <!-- Catalog Filter & Search Toolbar -->
    <form method="GET" action="items.php" class="filter-search-bar" style="margin-bottom:1.5rem;">
        <input type="hidden" name="view" value="<?= $is_archived_view ? 'archived' : 'active' ?>">
        
        <input type="search" name="q" class="search" placeholder="Search by item name, brand, or ID…" value="<?= htmlspecialchars($search_q) ?>" style="min-width:14rem;">
        
        <select name="cat" class="search" style="max-width:14rem;" onchange="this.form.submit()">
            <option value="0">All Categories</option>
            <?php foreach ($categories as $c) { ?>
                <option value="<?= $c['category_id'] ?>" <?= $filter_cat == $c['category_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                </option>
            <?php } ?>
        </select>

        <select name="stock" class="search" style="max-width:12rem;" onchange="this.form.submit()">
            <option value="all" <?= $filter_stock === 'all' ? 'selected' : '' ?>>All Stock Levels</option>
            <option value="in" <?= $filter_stock === 'in' ? 'selected' : '' ?>>In Stock (5+)</option>
            <option value="low" <?= $filter_stock === 'low' ? 'selected' : '' ?>>Low Stock (1-4)</option>
            <option value="out" <?= $filter_stock === 'out' ? 'selected' : '' ?>>Out of Stock (0)</option>
        </select>

        <button class="btn sm" type="submit">Filter</button>
        <?php if ($search_q !== '' || $filter_cat > 0 || $filter_stock !== 'all') { ?>
            <a href="items.php?view=<?= $is_archived_view ? 'archived' : 'active' ?>" class="btn ghost sm">Reset</a>
        <?php } ?>
    </form>

    <?php if (!$is_archived_view) { ?>
    <!-- Add Item Form (MP1 Multi-photo + MP5 Pure PHP validation) -->
    <form id="add" class="form" method="POST" action="items.php" enctype="multipart/form-data" novalidate style="margin: 0 0 2.5rem; max-width: 100%; display: <?= isset($_GET['add']) ? 'block' : 'none' ?>;">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ PRODUCT INTAKE ]</div>
            <h2 style="font-size:1.35rem; margin-top:4px;">Add New Inventory Item</h2>
        </div>
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); gap:1rem;">
            <div>
                <label class="form-label">Item Name *</label>
                <input type="text" name="name" placeholder="e.g. Wilson Evolution Basketball" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Brand</label>
                <input type="text" name="brand" placeholder="e.g. Wilson" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Category *</label>
                <select name="category_id" id="item_category_select" onchange="onCategoryChange()" style="margin-top:0.6rem;">
                    <option value="" data-type="">-- Select Category --</option>
                    <?php foreach ($categories as $c) { 
                        $supp = category_supports_sizes($conn, $c['category_id']);
                    ?>
                        <option value="<?php echo $c['category_id']; ?>" data-type="<?php echo $supp; ?>"><?php echo htmlspecialchars($c['name']); ?><?php echo $supp ? ' (' . ucfirst($supp) . ' Sizes)' : ''; ?></option>
                    <?php } ?>
                </select>
            </div>
            <div>
                <label class="form-label">Cost Price (₱) <small class="muted">Capital</small></label>
                <input type="number" step="0.01" name="cost_price" placeholder="0.00" value="0.00" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Selling Price (₱) *</label>
                <input type="number" step="0.01" name="price" placeholder="0.00" style="margin-top:0.6rem;">
            </div>
            <div id="standard_stock_block">
                <label class="form-label">Initial Stock *</label>
                <input type="number" name="stock" id="standard_stock_input" placeholder="Starting quantity" style="margin-top:0.6rem;">
                <small class="muted" style="font-size:11px;">Units in inventory</small>
            </div>
            <div>
                <label class="form-label">Primary Cover Image (.jpg, .png, .webp)</label>
                <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.6rem;">
            </div>
            <div>
                <label class="form-label">Additional Gallery Photos (MP1: Multiple)</label>
                <input type="file" name="gallery_images[]" multiple accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.6rem;">
                <small class="muted" style="font-size:11px;">Hold Ctrl/Cmd to select multiple pictures</small>
            </div>

            <!-- Dynamic Size Breakdown for Apparel (Damit) -->
            <div id="apparel_sizes_block" style="display:none; grid-column: 1 / -1; background:rgba(224,35,28,0.06); border:1px solid rgba(224,35,28,0.3); border-radius:8px; padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:0.75rem;">
                    <div>
                        <strong style="color:var(--color-brand-red); font-size:13px;">👕 APPAREL SIZES BREAKDOWN (Damit)</strong>
                        <p class="muted" style="font-size:11px; margin-top:2px;">Lagyan ng stock ang bawat sukat ng damit (XS, S, M, L, XL, 2XL).</p>
                    </div>
                    <div style="font-size:12px; font-weight:700;">
                        Total Stock: <span id="apparel_total_badge" style="color:var(--color-brand-green); font-size:14px;">0</span> units
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(90px, 1fr)); gap:10px;">
                    <?php foreach (array('XS','S','M','L','XL','2XL') as $sz_lbl) { ?>
                        <div style="text-align:center; background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:8px 6px;">
                            <label style="font-size:12px; font-weight:700; color:var(--color-fg); display:block; margin-bottom:4px;"><?= $sz_lbl ?></label>
                            <input type="number" min="0" name="apparel_sizes[<?= $sz_lbl ?>]" value="0" class="apparel-size-input" style="width:100%; text-align:center; padding:5px 6px; font-size:13px; font-weight:600;" oninput="updateApparelTotal()">
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Dynamic Size Breakdown for Footwear (Sapatos) -->
            <div id="footwear_sizes_block" style="display:none; grid-column: 1 / -1; background:rgba(60,130,246,0.08); border:1px solid rgba(60,130,246,0.35); border-radius:8px; padding:1.25rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-bottom:0.75rem;">
                    <div>
                        <strong style="color:#60a5fa; font-size:13px;">👟 FOOTWEAR SIZES BREAKDOWN (Sapatos)</strong>
                        <p class="muted" style="font-size:11px; margin-top:2px;">Lagyan ng stock ang bawat EU shoe size (39, 40, 41, 42, 43, 44, 45).</p>
                    </div>
                    <div style="font-size:12px; font-weight:700;">
                        Total Stock: <span id="footwear_total_badge" style="color:var(--color-brand-green); font-size:14px;">0</span> units
                    </div>
                </div>
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(90px, 1fr)); gap:10px;">
                    <?php foreach (array('EU 39','EU 40','EU 41','EU 42','EU 43','EU 44','EU 45') as $sz_lbl) { ?>
                        <div style="text-align:center; background:rgba(0,0,0,0.35); border:1px solid rgba(255,255,255,0.1); border-radius:6px; padding:8px 6px;">
                            <label style="font-size:11px; font-weight:700; color:var(--color-fg); display:block; margin-bottom:4px;"><?= $sz_lbl ?></label>
                            <input type="number" min="0" name="footwear_sizes[<?= $sz_lbl ?>]" value="0" class="footwear-size-input" style="width:100%; text-align:center; padding:5px 6px; font-size:13px; font-weight:600;" oninput="updateFootwearTotal()">
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>

        <script>
        function onCategoryChange() {
            var sel = document.getElementById('item_category_select');
            var opt = sel.options[sel.selectedIndex];
            var type = opt ? opt.getAttribute('data-type') : '';

            var stdBlock = document.getElementById('standard_stock_block');
            var appBlock = document.getElementById('apparel_sizes_block');
            var footBlock = document.getElementById('footwear_sizes_block');
            var stdInput = document.getElementById('standard_stock_input');

            if (type === 'apparel') {
                stdBlock.style.display = 'none';
                footBlock.style.display = 'none';
                appBlock.style.display = 'block';
                if (stdInput) stdInput.required = false;
                updateApparelTotal();
            } else if (type === 'footwear') {
                stdBlock.style.display = 'none';
                appBlock.style.display = 'none';
                footBlock.style.display = 'block';
                if (stdInput) stdInput.required = false;
                updateFootwearTotal();
            } else {
                stdBlock.style.display = 'block';
                appBlock.style.display = 'none';
                footBlock.style.display = 'none';
                if (stdInput) stdInput.required = true;
            }
        }

        function updateApparelTotal() {
            var inputs = document.querySelectorAll('.apparel-size-input');
            var total = 0;
            inputs.forEach(function(inp) { total += parseInt(inp.value) || 0; });
            document.getElementById('apparel_total_badge').innerText = total;
            var stdInput = document.getElementById('standard_stock_input');
            if (stdInput) stdInput.value = total;
        }

        function updateFootwearTotal() {
            var inputs = document.querySelectorAll('.footwear-size-input');
            var total = 0;
            inputs.forEach(function(inp) { total += parseInt(inp.value) || 0; });
            document.getElementById('footwear_total_badge').innerText = total;
            var stdInput = document.getElementById('standard_stock_input');
            if (stdInput) stdInput.value = total;
        }

        function toggleAddForm() {
            var form = document.getElementById('add');
            if (!form) return;
            if (form.style.display === 'none') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth' });
            } else {
                form.style.display = 'none';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            onCategoryChange();
            if (window.location.hash === '#add') {
                var form = document.getElementById('add');
                if (form) form.style.display = 'block';
            }
        });
        </script>
        </div>
        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="2" placeholder="Item specs, materials, intended sport..." style="margin-top:0.6rem;"></textarea>
        </div>
        <div>
            <button class="btn" type="submit" name="add_item">+ Publish Item with Photos</button>
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
                <a href="item_edit.php?id=<?php echo $item['item_id']; ?>" style="text-decoration:none; display:inline-block; transition:transform 0.2s;" title="Click image to edit item">
                    <?php item_tile($item, '../'); ?>
                </a>
                <div style="flex-grow:1;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
                            <?php echo htmlspecialchars($item['category']); ?><?php echo $item['brand'] != '' ? ' • ' . htmlspecialchars($item['brand']) : ''; ?>
                        </span>
                        <?php if ($item['stock'] < 5 && !$is_archived_view) { ?>
                            <span class="status-badge" style="background:rgba(255,100,80,0.15); color:#ff9f96; border-color:rgba(255,100,80,0.3); font-size:10px; padding:2px 8px;">LOW STOCK</span>
                        <?php } ?>
                    </div>
                    <a href="item_edit.php?id=<?php echo $item['item_id']; ?>" style="color:inherit; text-decoration:none;" title="Click item name to edit">
                        <h2 style="font-size:1.15rem; margin-top:2px; display:inline-block;"><?php echo htmlspecialchars($item['name']); ?> <span style="font-size:12px; color:var(--color-accent-on-dark); opacity:0.8;">✏️</span></h2>
                    </a>
                    <div class="muted" style="font-size:var(--text-body-sm); margin-top:4px; display:flex; flex-wrap:wrap; gap:10px;">
                        <span>Price: <strong style="color:var(--color-fg)"><?php echo peso($item['price']); ?></strong></span>
                        <?php if ($cost > 0) { ?>
                            <span>Cost: <strong><?php echo peso($cost); ?></strong> (<?= round($margin, 1) ?>% profit)</span>
                        <?php } ?>
                        <span>Total Stock: <strong style="<?php echo $item['stock'] < 5 ? 'color:#ff9f96;' : 'color:var(--color-brand-green);'; ?>"><?php echo $item['stock']; ?> units</strong></span>
                    </div>

                    <?php if (!empty($item_sizes_map[$item['item_id']])) { ?>
                        <!-- Per-size variant inventory pills -->
                        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; align-items:center;">
                            <span class="muted" style="font-size:10px; text-transform:uppercase; letter-spacing:0.5px; font-weight:600;">Sizes:</span>
                            <?php foreach ($item_sizes_map[$item['item_id']] as $sz_info) { ?>
                                <span style="font-size:11px; padding:2px 7px; border-radius:4px; background:rgba(255,255,255,0.06); border:1px solid <?= $sz_info['stock'] > 0 ? 'rgba(255,255,255,0.12)' : 'rgba(255,50,50,0.3)' ?>; <?= $sz_info['stock'] <= 0 ? 'color:#ff6b6b; opacity:0.8;' : '' ?>">
                                    <strong><?= htmlspecialchars($sz_info['size_label']) ?></strong>: <?= (int)$sz_info['stock'] ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>

                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap; justify-content:flex-end;">
                    <?php if (!$is_archived_view) { ?>
                        <a class="btn ghost sm" href="item_edit.php?id=<?php echo $item['item_id']; ?>">Edit</a>

                        <!-- Stock Adjustment Form with Reason & Size Support -->
                        <form method="POST" action="items.php" style="display:inline-flex; gap:.4rem; align-items:center;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <?php if (!empty($item_sizes_map[$item['item_id']])) { ?>
                                <select name="size_id" class="search" style="padding:0.4rem 0.5rem; font-size:11px; max-width:7.5rem;">
                                    <?php foreach ($item_sizes_map[$item['item_id']] as $sz_opt) { ?>
                                        <option value="<?= $sz_opt['size_id'] ?>"><?= htmlspecialchars($sz_opt['size_label']) ?> (<?= $sz_opt['stock'] ?>)</option>
                                    <?php } ?>
                                </select>
                            <?php } ?>
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


