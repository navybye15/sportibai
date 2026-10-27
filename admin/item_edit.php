<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

$item_id = 0;
if (isset($_GET['id'])) {
    $item_id = (int) $_GET['id'];
}

// ── Delete a specific gallery image (MP1) ───────────────────────────────────
if (isset($_POST['delete_gallery_image'])) {
    if (!csrf_verify()) {
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
    $target_image_id = (int)($_POST['image_id'] ?? $_POST['delete_gallery_image'] ?? 0);
    $sql_find = "SELECT image_path FROM item_images WHERE image_id = ? AND item_id = ? LIMIT 1";
    $find_res = mysqli_execute_query($conn, $sql_find, array($target_image_id, $item_id));
    if ($img_row = mysqli_fetch_assoc($find_res)) {
        $file_path = __DIR__ . '/../images/uploads/' . basename($img_row['image_path']);
        if (file_exists($file_path)) {
            @unlink($file_path);
        }
        mysqli_execute_query($conn, "DELETE FROM item_images WHERE image_id = ?", array($target_image_id));
        $_SESSION['success'] = 'Gallery photo deleted.';
    }
    header('Location: item_edit.php?id=' . $item_id);
    exit;
}

// ── Delete a specific size variant (Option 1) ──────────────────────────────
if (isset($_POST['delete_size_id'])) {
    if (!csrf_verify()) {
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
    $del_id = (int)$_POST['delete_size_id'];
    mysqli_execute_query($conn, "DELETE FROM item_sizes WHERE size_id = ? AND item_id = ?", array($del_id, $item_id));
    sync_item_total_stock($conn, $item_id);
    $_SESSION['success'] = 'Size variant removed.';
    header('Location: item_edit.php?id=' . $item_id);
    exit;
}

// ── Initialize standard size variants (Option 1) ───────────────────────────
if (isset($_POST['init_default_sizes'])) {
    if (!csrf_verify()) {
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
    $cat_res = mysqli_execute_query($conn, "SELECT category_id, stock FROM items WHERE item_id = ? LIMIT 1", array($item_id));
    if ($crow = mysqli_fetch_assoc($cat_res)) {
        $stype = category_supports_sizes($conn, (int)$crow['category_id']);
        if ($stype) {
            $defs = get_default_sizes_for_type($stype);
            $count = count($defs);
            $cur_stock = (int)$crow['stock'];
            $per_sz = $count > 0 ? intdiv($cur_stock, $count) : 0;
            $rem = $count > 0 ? ($cur_stock % $count) : 0;
            foreach ($defs as $idx => $dlabel) {
                $init_qty = $per_sz + ($idx < $rem ? 1 : 0);
                mysqli_execute_query($conn, "INSERT IGNORE INTO item_sizes (item_id, size_label, stock) VALUES (?, ?, ?)", array($item_id, $dlabel, $init_qty));
            }
            sync_item_total_stock($conn, $item_id);
            $_SESSION['success'] = 'Standard size variants initialized.';
        }
    }
    header('Location: item_edit.php?id=' . $item_id);
    exit;
}

// ── Save Item Changes (MP1 & MP5) ───────────────────────────────────────────
if (isset($_POST['save'])) {
    if (!csrf_verify()) {
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
    try {
        $name        = strip_tags(trim($_POST['name'] ?? ''));
        $brand       = strip_tags(trim($_POST['brand'] ?? ''));
        $description = strip_tags(trim($_POST['description'] ?? ''));
        $price_raw   = trim($_POST['price'] ?? '');
        $cost_raw    = trim($_POST['cost_price'] ?? '0');
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

        // Replacing primary cover image is optional
        $new_image = handle_item_image($_FILES['image']);

        if ($new_image != '') {
            $old_sql = "SELECT image FROM items WHERE item_id = ? LIMIT 1";
            $old_res = mysqli_execute_query($conn, $old_sql, array($item_id));
            if ($old_row = mysqli_fetch_assoc($old_res)) {
                $old_file = $old_row['image'];
                if ($old_file != '' && file_exists('../images/uploads/' . basename($old_file))) {
                    @unlink('../images/uploads/' . basename($old_file));
                }
            }

            $sql = "UPDATE items SET name=?, brand=?, description=?, price=?, cost_price=?, category_id=?, image=? WHERE item_id=?";
            mysqli_execute_query($conn, $sql, array($name, $brand, $description, $price, $cost_price, $category_id, $new_image, $item_id));
        } else {
            $sql = "UPDATE items SET name=?, brand=?, description=?, price=?, cost_price=?, category_id=? WHERE item_id=?";
            mysqli_execute_query($conn, $sql, array($name, $brand, $description, $price, $cost_price, $category_id, $item_id));
        }

        // Upload new additional gallery photos (MP1 Requirement: Multiple photos)
        if (isset($_FILES['gallery_images'])) {
            handle_multiple_item_images($_FILES['gallery_images'], $item_id, $conn);
        }

        // Update per-size stock breakdown (Option 1)
        if (isset($_POST['size_stock']) && is_array($_POST['size_stock'])) {
            foreach ($_POST['size_stock'] as $sid => $sqty) {
                $sqty = max(0, (int)$sqty);
                mysqli_execute_query($conn, "UPDATE item_sizes SET stock = ? WHERE size_id = ? AND item_id = ?", array($sqty, (int)$sid, $item_id));
            }
            sync_item_total_stock($conn, $item_id);
        } elseif (isset($_POST['stock'])) {
            $plain_stock = max(0, (int)$_POST['stock']);
            mysqli_execute_query($conn, "UPDATE items SET stock = ? WHERE item_id = ?", array($plain_stock, $item_id));
        }

        // Add custom new size variant if provided
        if (!empty($_POST['new_size_label'])) {
            $new_lbl = strtoupper(trim(strip_tags($_POST['new_size_label'])));
            $new_stk = max(0, (int)($_POST['new_size_stock'] ?? 0));
            if ($new_lbl !== '') {
                mysqli_execute_query($conn, "INSERT INTO item_sizes (item_id, size_label, stock) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE stock = stock + VALUES(stock)", array($item_id, $new_lbl, $new_stk));
                sync_item_total_stock($conn, $item_id);
            }
        }

        $_SESSION['success'] = 'Item and inventory updated successfully.';
        header('Location: items.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['message'] = $e->getMessage();
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
}

$sql = "SELECT * FROM items WHERE item_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, array($item_id));
$item = mysqli_fetch_assoc($result);
if ($item == null) {
    header('Location: items.php');
    exit;
}

// Fetch categories
$categories = array();
$result = mysqli_query($conn, "SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Fetch existing gallery images (MP1 Requirement)
$gallery_images = array();
$g_res = mysqli_execute_query($conn, "SELECT * FROM item_images WHERE item_id = ? ORDER BY sort_order ASC, image_id ASC", array($item_id));
while ($g = mysqli_fetch_assoc($g_res)) {
    $gallery_images[] = $g;
}

// Fetch per-size variants (Option 1)
$item_sizes = get_item_sizes($conn, $item_id);
$size_type = category_supports_sizes($conn, (int)$item['category_id']);
$has_sizes = (count($item_sizes) > 0) || ($size_type !== false);

$category_name = '';
foreach ($categories as $c) {
    if ($c['category_id'] == $item['category_id']) {
        $category_name = $c['name'];
        break;
    }
}

$base = '../';
$page_title = 'Edit: ' . htmlspecialchars($item['name']) . ' | Sportibai Admin';
require '../includes/header.php';
?>

<div class="admin-edit-container reveal in">
    <!-- Top Header Bar -->
    <div class="admin-topbar">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-accent-on-dark);">[ INVENTORY MANAGEMENT • ITEM #<?= $item_id ?> ]</div>
            <h1 class="admin-title">Edit: <?= htmlspecialchars($item['name']) ?></h1>
            <div class="admin-metastrip">
                <span class="meta-tag">Category: <strong><?= htmlspecialchars($category_name ?: 'General') ?></strong></span>
                <span class="meta-sep">•</span>
                <span class="meta-tag">Brand: <strong><?= htmlspecialchars($item['brand'] ?: 'Sportibai') ?></strong></span>
                <span class="meta-sep">•</span>
                <span class="meta-tag">Selling Price: <strong style="color:var(--color-brand-green);"><?= peso($item['price']) ?></strong></span>
                <span class="meta-sep">•</span>
                <span class="meta-tag">Total Stock: <strong><?= (int)$item['stock'] ?> units</strong></span>
            </div>
        </div>
        <div class="admin-topbar-actions">
            <a href="items.php" class="btn ghost sm">← Back to Items</a>
            <a href="../item/detail.php?id=<?= $item_id ?>" target="_blank" class="btn ghost sm">View on Store ↗</a>
            <button class="btn sm" type="button" onclick="document.getElementById('edit-item-form').requestSubmit();" style="box-shadow:0 0 16px rgba(10,132,255,0.35);">
                💾 Save Changes
            </button>
        </div>
    </div>

    <?php require '../includes/alert.php'; ?>

    <form id="edit-item-form" method="POST" action="item_edit.php?id=<?php echo $item_id; ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>

        <div class="admin-edit-grid">
            <!-- ════════ LEFT COLUMN: Product Specifications & Variant Inventory ════════ -->
            <div class="admin-col-left">
                <!-- Card: Basic Information -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="hero-eyebrow">[ PRODUCT DETAILS ]</div>
                        <h2 class="admin-card-title">General Information</h2>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:1.25rem;">
                        <div>
                            <label class="modern-label">Item Name *</label>
                            <div class="admin-input-wrap">
                                <input type="text" name="name" class="admin-input bold" value="<?php echo htmlspecialchars($item['name']); ?>" required placeholder="e.g. NIKE Stride T-shirt">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div>
                                <label class="modern-label">Brand</label>
                                <div class="admin-input-wrap">
                                    <input type="text" name="brand" class="admin-input" value="<?php echo htmlspecialchars($item['brand']); ?>" placeholder="e.g. Nike, Adidas, Wilson">
                                </div>
                            </div>
                            <div>
                                <label class="modern-label">Category *</label>
                                <div class="admin-select-wrap">
                                    <select name="category_id" class="admin-select">
                                        <?php foreach ($categories as $c) {
                                            $sel = ($c['category_id'] == $item['category_id']) ? 'selected' : '';
                                        ?>
                                            <option value="<?php echo $c['category_id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="modern-label">Description</label>
                            <textarea name="description" class="admin-textarea" rows="4" placeholder="Detailed product specifications, materials, fit, care instructions..."><?php echo htmlspecialchars($item['description']); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Card: Size Variant Inventory -->
                <?php if ($has_sizes || $size_type) { ?>
                    <div class="admin-card" style="border-color:rgba(224,35,28,0.35);">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:12px; margin-bottom:1.25rem;">
                            <div>
                                <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ VARIANT INVENTORY BREAKDOWN ]</div>
                                <h2 class="admin-card-title" style="margin-top:2px;">Stock by Size (Total: <?= (int)$item['stock'] ?> units)</h2>
                                <p class="muted" style="font-size:12px; margin-top:3px;">
                                    Manage individual inventory for each size variant. Customers pick their size before checkout.
                                </p>
                            </div>
                            <?php if (count($item_sizes) === 0 && $size_type) { ?>
                                <button type="submit" name="init_default_sizes" class="btn ghost sm" style="font-size:11px; white-space:nowrap;">
                                    ⚡ Auto-generate <?= $size_type === 'footwear' ? 'EU Sizes (39-45)' : 'Sizes (XS-2XL)' ?>
                                </button>
                            <?php } ?>
                        </div>

                        <?php if (count($item_sizes) > 0) { ?>
                            <div style="overflow-x:auto;">
                                <table class="admin-sizes-table">
                                    <thead>
                                        <tr>
                                            <th>Size Variant</th>
                                            <th>Stock Quantity</th>
                                            <th style="text-align:center;">Inventory Status</th>
                                            <th style="text-align:right;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($item_sizes as $sz) { ?>
                                            <tr>
                                                <td style="font-weight:700; font-size:14px; color:var(--color-brand-red);">
                                                    <?= htmlspecialchars($sz['size_label']) ?>
                                                </td>
                                                <td>
                                                    <input type="number" min="0" name="size_stock[<?= (int)$sz['size_id'] ?>]" value="<?= (int)$sz['stock'] ?>" class="size-qty-input">
                                                </td>
                                                <td style="text-align:center;">
                                                    <?php if ($sz['stock'] <= 0) { ?>
                                                        <span class="status-badge badge-out">Out of Stock</span>
                                                    <?php } elseif ($sz['stock'] < 5) { ?>
                                                        <span class="status-badge badge-low">Low Stock (<?= $sz['stock'] ?>)</span>
                                                    <?php } else { ?>
                                                        <span class="status-badge badge-in">In Stock (<?= $sz['stock'] ?>)</span>
                                                    <?php } ?>
                                                </td>
                                                <td style="text-align:right;">
                                                    <button type="submit" name="delete_size_id" value="<?= (int)$sz['size_id'] ?>" onclick="return confirm('Remove size <?= htmlspecialchars($sz['size_label']) ?>?');" class="table-del-btn">
                                                        Remove
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } else { ?>
                            <div class="empty-state-box">
                                <p class="muted" style="font-size:13px; margin:0;">No size variants registered yet. Click "Auto-generate" above or add a custom size below.</p>
                            </div>
                        <?php } ?>

                        <!-- Add a custom size -->
                        <div class="add-size-toolbar">
                            <span style="font-size:12px; font-weight:700; color:var(--color-fg-secondary);">+ Add Size Variant:</span>
                            <input type="text" name="new_size_label" class="admin-input" placeholder="Size (e.g. 3XL, 46)" style="width:130px; font-size:12px; padding:6px 10px !important;">
                            <input type="number" min="0" name="new_size_stock" class="admin-input" placeholder="Initial Qty" value="0" style="width:90px; font-size:12px; padding:6px 10px !important;">
                            <span class="muted" style="font-size:11px; margin-left:auto;">(Saved when clicking "Save Changes")</span>
                        </div>
                    </div>
                <?php } else { ?>
                    <!-- Non-sized inventory input -->
                    <div class="admin-card">
                        <div class="admin-card-header">
                            <div class="hero-eyebrow">[ STOCK LEVEL ]</div>
                            <h2 class="admin-card-title">Inventory Units</h2>
                        </div>
                        <div>
                            <label class="modern-label">Available Stock (Units)</label>
                            <input type="number" min="0" name="stock" class="admin-input" value="<?= (int)$item['stock'] ?>" style="max-width:200px;">
                            <p class="muted" style="font-size:12px; margin-top:6px;">Direct unit stock for non-sized equipment, balls, or gear.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- ════════ RIGHT COLUMN: Financials, Media, Actions ════════ -->
            <div class="admin-col-right">
                <!-- Card: Pricing & Profit Calculator -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="hero-eyebrow" style="color:var(--color-brand-green);">[ FINANCIALS &amp; MARGIN ]</div>
                        <h2 class="admin-card-title">Pricing &amp; Profit</h2>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div>
                            <label class="modern-label" style="color:var(--color-brand-green);">Selling Price (₱) *</label>
                            <div class="price-input-wrap is-selling">
                                <span class="currency-tag">₱</span>
                                <input type="number" step="0.01" id="input-price" name="price" class="admin-input price-input" value="<?php echo $item['price']; ?>" oninput="updateProfitMargin()" required>
                            </div>
                        </div>
                        <div>
                            <label class="modern-label">Cost Price / Capital (₱)</label>
                            <div class="price-input-wrap is-cost">
                                <span class="currency-tag">₱</span>
                                <input type="number" step="0.01" id="input-cost" name="cost_price" class="admin-input price-input" value="<?php echo htmlspecialchars($item['cost_price'] ?? 0); ?>" oninput="updateProfitMargin()">
                            </div>
                        </div>
                    </div>

                    <!-- Gross Profit Metric Display -->
                    <div class="profit-metric-box">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span class="muted" style="font-size:12px; font-weight:600;">Estimated Profit per Unit:</span>
                            <span id="calc-profit" style="font-size:14px; font-weight:800; color:var(--color-brand-green);">--</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:4px;">
                            <span class="muted" style="font-size:12px;">Gross Margin:</span>
                            <span id="calc-margin" style="font-size:12px; font-weight:700; color:var(--color-accent-on-dark);">--</span>
                        </div>
                    </div>
                </div>

                <!-- Card: Primary Cover Image -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="hero-eyebrow">[ PRIMARY MEDIA ]</div>
                        <h2 class="admin-card-title">Cover Image</h2>
                    </div>

                    <div style="display:flex; gap:16px; align-items:center; margin-bottom:1rem;">
                        <div class="cover-preview-box">
                            <?php if (!empty($item['image']) && file_exists(__DIR__ . '/../images/uploads/' . basename($item['image']))) { ?>
                                <img src="../images/uploads/<?= htmlspecialchars(basename($item['image'])) ?>" alt="Cover Image" id="cover-preview-img">
                            <?php } else { ?>
                                <div class="cover-placeholder">NO COVER</div>
                            <?php } ?>
                        </div>
                        <div style="flex:1;">
                            <label class="form-label" style="font-size:11px;">Change Cover Photo</label>
                            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="font-size:12px; padding:6px;">
                            <p class="muted" style="font-size:11px; margin-top:4px;">JPG, PNG, WEBP (Max 10MB). Shown on catalog and tiles.</p>
                        </div>
                    </div>
                </div>

                <!-- Card: Gallery Photos (MP1) -->
                <div class="admin-card">
                    <div class="admin-card-header">
                        <div class="hero-eyebrow">[ GALLERY SHOWCASE ]</div>
                        <h2 class="admin-card-title">Additional Photos (MP1)</h2>
                        <p class="muted" style="font-size:12px; margin-top:2px;">Upload extra angles, packaging, or action shots.</p>
                    </div>

                    <?php if (count($gallery_images) > 0) { ?>
                        <div class="gallery-preview-grid">
                            <?php foreach ($gallery_images as $g) { ?>
                                <div class="gallery-tile">
                                    <img src="../images/uploads/<?= htmlspecialchars($g['image_path']) ?>" alt="Gallery Photo">
                                    <button type="submit" name="delete_gallery_image" value="<?= $g['image_id'] ?>" onclick="return confirm('Delete this gallery photo?');" class="gallery-del-btn" title="Delete photo">✕</button>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <p class="muted" style="font-size:12px; font-style:italic; margin-bottom:1rem;">No additional gallery photos yet.</p>
                    <?php } ?>

                    <div>
                        <label class="form-label" style="font-size:11px;">Add More Photos</label>
                        <input type="file" name="gallery_images[]" multiple accept=".jpg,.jpeg,.png,.webp" style="font-size:12px; padding:6px;">
                    </div>
                </div>

                <!-- Card: Save & Action Controls -->
                <div class="admin-card" style="background:rgba(10,132,255,0.06); border-color:rgba(10,132,255,0.25);">
                    <button class="btn" style="width:100%; padding:0.95rem; font-size:14px; font-weight:700; box-shadow:0 4px 20px rgba(10,132,255,0.4);" type="submit" name="save">
                        💾 Save Item Changes
                    </button>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px;">
                        <a href="items.php" class="btn ghost sm" style="flex:1; text-align:center;">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<style>
.admin-edit-container {
    max-width: 1280px;
    margin: 2rem auto 4rem;
    padding: 0 1.5rem;
}
.admin-topbar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 2rem;
    padding-bottom: 1.25rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}
.admin-title {
    font-size: clamp(22px, 3.2vw, 36px);
    margin: 4px 0 8px;
    font-weight: 800;
    color: #fff;
    line-height: 1.2;
}
.admin-metastrip {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 13px;
    color: var(--color-fg-secondary);
}
.admin-metastrip strong {
    color: #fff;
}
.meta-sep {
    opacity: 0.35;
}
.admin-topbar-actions {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.admin-edit-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
    gap: 1.75rem;
    align-items: start;
}
@media (max-width: 960px) {
    .admin-edit-grid {
        grid-template-columns: 1fr;
    }
}
.admin-col-left,
.admin-col-right {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}
.admin-card {
    background: rgba(22, 24, 29, 0.75);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
}
.admin-card-header {
    margin-bottom: 1.25rem;
}
.admin-card-title {
    font-size: 17px;
    font-weight: 700;
    color: #fff;
    margin: 2px 0 0;
}
.modern-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.8px;
    color: #a1a4ad;
    margin-bottom: 0.5rem;
}
.admin-input-wrap,
.admin-select-wrap {
    position: relative;
    width: 100%;
}
.admin-input,
.admin-select,
.admin-textarea {
    width: 100% !important;
    background: rgba(16, 18, 24, 0.85) !important;
    border: 1px solid rgba(255, 255, 255, 0.12) !important;
    border-radius: 10px !important;
    color: #fff !important;
    font-family: inherit !important;
    font-size: 14px !important;
    padding: 0.8rem 1rem !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1) !important;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.35) !important;
}
.admin-input.bold {
    font-weight: 600;
    font-size: 15px !important;
}
.admin-input:focus,
.admin-select:focus,
.admin-textarea:focus {
    border-color: #0a84ff !important;
    background: rgba(22, 26, 36, 0.95) !important;
    box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.25), inset 0 1px 2px rgba(0,0,0,0.2) !important;
}
.admin-textarea {
    resize: vertical;
    min-height: 105px;
    line-height: 1.6;
}
.admin-select {
    appearance: none !important;
    -webkit-appearance: none !important;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23a1a4ad' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    background-repeat: no-repeat !important;
    background-position: right 1rem center !important;
    padding-right: 2.5rem !important;
    cursor: pointer !important;
}
.admin-select option {
    background: #141720;
    color: #fff;
}
.price-input-wrap {
    display: flex;
    align-items: center;
    background: rgba(16, 18, 24, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 10px;
    overflow: hidden;
    transition: all 0.2s ease;
    box-shadow: inset 0 2px 4px rgba(0,0,0,0.35);
}
.price-input-wrap:focus-within {
    border-color: #0a84ff;
    background: rgba(22, 26, 36, 0.95);
    box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.25);
}
.price-input-wrap.is-selling:focus-within {
    border-color: var(--color-brand-green);
    box-shadow: 0 0 0 3px rgba(48, 209, 88, 0.25);
}
.currency-tag {
    padding: 0 12px;
    font-size: 15px;
    font-weight: 800;
    color: #8e929b;
    user-select: none;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}
.price-input-wrap.is-selling .currency-tag {
    color: var(--color-brand-green);
}
.price-input-wrap .price-input {
    border: none !important;
    background: transparent !important;
    box-shadow: none !important;
    padding-left: 10px !important;
    font-size: 16px !important;
    font-weight: 700 !important;
}
.price-input-wrap.is-selling .price-input {
    color: var(--color-brand-green) !important;
}
.admin-sizes-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.admin-sizes-table th {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    text-align: left;
    color: var(--color-muted);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.admin-sizes-table td {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}
.size-qty-input {
    width: 90px;
    padding: 6px 10px;
    font-size: 13px;
    text-align: center;
    font-weight: 700;
    border-radius: 6px;
    border: 1px solid rgba(255, 255, 255, 0.14);
    background: rgba(10, 11, 14, 0.7);
    color: #fff;
}
.badge-in {
    background: rgba(48, 209, 88, 0.15);
    color: #30d158;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.badge-low {
    background: rgba(255, 180, 50, 0.15);
    color: #ffd166;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.badge-out {
    background: rgba(255, 70, 70, 0.15);
    color: #ff6b6b;
    padding: 3px 8px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
}
.table-del-btn {
    background: none;
    border: none;
    color: #ff6b6b;
    cursor: pointer;
    font-size: 12px;
    text-decoration: underline;
    opacity: 0.8;
}
.table-del-btn:hover {
    opacity: 1;
}
.add-size-toolbar {
    margin-top: 1.25rem;
    padding-top: 1rem;
    border-top: 1px dashed rgba(255, 255, 255, 0.1);
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}
.empty-state-box {
    padding: 1.25rem;
    background: rgba(255, 255, 255, 0.02);
    border: 1px dashed rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    text-align: center;
}
.profit-metric-box {
    margin-top: 1rem;
    padding: 12px 14px;
    background: rgba(48, 209, 88, 0.05);
    border: 1px solid rgba(48, 209, 88, 0.18);
    border-radius: 10px;
}
.cover-preview-box {
    width: 80px;
    height: 80px;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #0b0c0e;
    flex-shrink: 0;
}
.cover-preview-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.cover-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    color: var(--color-muted);
}
.gallery-preview-grid {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.gallery-tile {
    position: relative;
    width: 84px;
    height: 84px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(255, 255, 255, 0.18);
    background: #0b0c0e;
}
.gallery-tile img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gallery-del-btn {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: rgba(255, 50, 50, 0.9);
    color: #fff;
    border: none;
    font-size: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    line-height: 1;
    transition: transform 0.15s;
}
.gallery-del-btn:hover {
    transform: scale(1.1);
}
</style>

<script>
function updateProfitMargin() {
    const price = parseFloat(document.getElementById('input-price').value) || 0;
    const cost = parseFloat(document.getElementById('input-cost').value) || 0;
    const profit = price - cost;
    const margin = price > 0 ? ((profit / price) * 100).toFixed(1) : 0;
    
    const profitEl = document.getElementById('calc-profit');
    const marginEl = document.getElementById('calc-margin');
    if (profitEl) {
        profitEl.innerText = (profit >= 0 ? '+' : '') + '₱' + profit.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        profitEl.style.color = profit >= 0 ? 'var(--color-brand-green)' : '#ff6b6b';
    }
    if (marginEl) {
        marginEl.innerText = margin + '% gross margin';
    }
}
document.addEventListener('DOMContentLoaded', updateProfitMargin);
</script>

<?php require '../includes/footer.php'; ?>
