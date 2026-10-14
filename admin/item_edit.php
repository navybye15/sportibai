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
    $target_image_id = (int)$_POST['image_id'];
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

        $_SESSION['success'] = 'Item and gallery photos updated successfully.';
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

$base = '../';
$page_title = 'Edit Item | Sportibai';
require '../includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="item_edit.php?id=<?php echo $item_id; ?>" enctype="multipart/form-data" novalidate>
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ EDIT INVENTORY RECORD ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top:6px;">Edit Item #<?php echo $item_id; ?></h1>
        </div>
        <?php require '../includes/alert.php'; ?>

        <div>
            <label class="form-label">Item Name *</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" value="<?php echo htmlspecialchars($item['brand']); ?>" style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" style="margin-top:0.75rem;"><?php echo htmlspecialchars($item['description']); ?></textarea>
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label class="form-label">Cost Price (₱) <small class="muted">Capital</small></label>
                <input type="number" step="0.01" name="cost_price" value="<?php echo htmlspecialchars($item['cost_price'] ?? 0); ?>" style="margin-top:0.75rem;">
            </div>
            <div>
                <label class="form-label">Selling Price (₱) *</label>
                <input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>" style="margin-top:0.75rem;">
            </div>
        </div>

        <div>
            <label class="form-label">Category *</label>
            <select name="category_id" style="margin-top:0.75rem;">
                <?php foreach ($categories as $c) {
                    $sel = ($c['category_id'] == $item['category_id']) ? 'selected' : '';
                ?>
                    <option value="<?php echo $c['category_id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php } ?>
            </select>
        </div>

        <!-- Primary Cover Image -->
        <div>
            <label class="form-label">Cover Image</label>
            <?php if (!empty($item['image'])) { ?>
                <div style="display:flex; align-items:center; gap:12px; margin:0.5rem 0;">
                    <img src="../images/uploads/<?= htmlspecialchars($item['image']) ?>" alt="Cover" style="width:50px; height:50px; object-fit:cover; border-radius:6px; border:1px solid rgba(255,255,255,0.15);">
                    <span class="muted" style="font-size:12px;">Current cover photo</span>
                </div>
            <?php } ?>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.5rem;">
        </div>

        <!-- MP1: Multiple Gallery Photos Section -->
        <div style="padding:1rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.08); border-radius:8px; margin-top:0.5rem;">
            <label class="form-label" style="margin-bottom:0.5rem;">Additional Gallery Photos (MP1 Requirement: Multiple Photos)</label>
            <p class="muted" style="font-size:12px; margin-top:-4px;">Upload additional shots (different angles, packaging, in-use).</p>

            <?php if (count($gallery_images) > 0) { ?>
                <div style="display:flex; gap:12px; flex-wrap:wrap; margin:0.75rem 0;">
                    <?php foreach ($gallery_images as $g) { ?>
                        <div style="position:relative; width:80px; height:80px; border-radius:8px; overflow:hidden; border:1px solid rgba(255,255,255,0.2);">
                            <img src="../images/uploads/<?= htmlspecialchars($g['image_path']) ?>" alt="Gallery" style="width:100%; height:100%; object-fit:cover;">
                            <form method="POST" action="item_edit.php?id=<?= $item_id ?>" onsubmit="return confirm('Delete this gallery photo?');" style="position:absolute; top:2px; right:2px; margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="image_id" value="<?= $g['image_id'] ?>">
                                <button type="submit" name="delete_gallery_image" style="background:rgba(255,50,50,0.85); color:#fff; border:none; border-radius:50%; width:20px; height:20px; font-size:10px; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1;" title="Delete this image">✕</button>
                            </form>
                        </div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="muted" style="font-size:12px; font-style:italic;">No additional gallery photos yet.</p>
            <?php } ?>

            <label class="form-label" style="font-size:12px; margin-top:0.75rem;">Add more photos to gallery:</label>
            <input type="file" name="gallery_images[]" multiple accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.25rem;">
        </div>

        <div style="display:flex; gap:1rem; margin-top:1rem;">
            <button class="btn" style="flex:1;" type="submit" name="save">Save Changes</button>
            <a class="btn ghost" href="items.php">Cancel</a>
        </div>
    </form>

    <!-- Separate management of gallery deletion -->
    <?php if (count($gallery_images) > 0) { ?>
        <div class="card in" style="margin-top:2rem; padding:1.25rem;">
            <h3 style="font-size:14px; margin-bottom:1rem; color:#ff9f96;">Delete Gallery Photos</h3>
            <div style="display:flex; gap:1rem; flex-wrap:wrap;">
                <?php foreach ($gallery_images as $g) { ?>
                    <div style="display:flex; flex-direction:column; align-items:center; gap:6px;">
                        <img src="../images/uploads/<?= htmlspecialchars($g['image_path']) ?>" alt="Photo" style="width:70px; height:70px; object-fit:cover; border-radius:6px; border:1px solid rgba(255,255,255,0.15);">
                        <form method="POST" action="item_edit.php?id=<?= $item_id ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="image_id" value="<?= $g['image_id'] ?>">
                            <button type="submit" name="delete_gallery_image" class="btn ghost sm" onclick="return confirm('Remove this image?')" style="padding:2px 8px; font-size:10px; color:#ff9f96; border-color:rgba(255,100,80,0.3);">
                                Delete
                            </button>
                        </form>
                    </div>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
</div>
<?php require '../includes/footer.php'; ?>
