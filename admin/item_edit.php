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

if (isset($_POST['save'])) {
    if (!csrf_verify()) {
        header('Location: item_edit.php?id=' . $item_id);
        exit;
    }
    try {
        $name = strip_tags(trim($_POST['name']));
        $brand = strip_tags(trim($_POST['brand']));
        $description = strip_tags(trim($_POST['description']));
        $price = (float) $_POST['price'];
        $cost_price = isset($_POST['cost_price']) ? (float) $_POST['cost_price'] : 0.00;
        $category_id = (int) $_POST['category_id'];

        if ($name == '' || $price <= 0) {
            throw new Exception('Please fill in a valid name and price.');
        }

        // Replacing the image is optional, keep the old one if nothing was chosen
        $new_image = handle_item_image($_FILES['image']);

        if ($new_image != '') {
            // Clean up old image from storage if replaced
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

        $_SESSION['success'] = 'Item updated.';
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

$categories = array();
$result = mysqli_query($conn, "SELECT category_id, name FROM categories WHERE is_active = 1 ORDER BY name");
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

$base = '../';
$page_title = 'Edit Item | Sportibai';
require '../includes/header.php';
?>
<div class="reveal in">
    <form class="form" method="POST" action="item_edit.php?id=<?php echo $item_id; ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ EDIT INVENTORY RECORD ]</div>
            <h1 style="font-size: var(--text-display-m); margin-top:6px;">Edit Item #<?php echo $item_id; ?></h1>
        </div>
        <?php require '../includes/alert.php'; ?>

        <div>
            <label class="form-label">Item Name</label>
            <input type="text" name="name" value="<?php echo htmlspecialchars($item['name']); ?>" required style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Brand</label>
            <input type="text" name="brand" value="<?php echo htmlspecialchars($item['brand']); ?>" style="margin-top:0.75rem;">
        </div>

        <div>
            <label class="form-label">Description</label>
            <input type="text" name="description" value="<?php echo htmlspecialchars($item['description']); ?>" style="margin-top:0.75rem;">
        </div>

        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
            <div>
                <label class="form-label">Cost Price (₱) <small class="muted">Capital</small></label>
                <input type="number" step="0.01" name="cost_price" value="<?php echo htmlspecialchars($item['cost_price'] ?? 0); ?>" style="margin-top:0.75rem;">
            </div>
            <div>
                <label class="form-label">Selling Price (₱)</label>
                <input type="number" step="0.01" name="price" value="<?php echo $item['price']; ?>" required style="margin-top:0.75rem;">
            </div>
        </div>

        <div>
            <label class="form-label">Category</label>
            <select name="category_id" required style="margin-top:0.75rem;">
                <?php foreach ($categories as $c) {
                    $sel = '';
                    if ($c['category_id'] == $item['category_id']) {
                        $sel = 'selected';
                    }
                ?>
                    <option value="<?php echo $c['category_id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($c['name']); ?></option>
                <?php } ?>
            </select>
        </div>

        <div>
            <label class="form-label">Replace Image (optional)</label>
            <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" style="margin-top:0.75rem;">
        </div>

        <div style="display:flex; gap:1rem; margin-top:0.5rem;">
            <button class="btn" style="flex:1;" type="submit" name="save">Save Changes</button>
            <a class="btn ghost" href="items.php">Cancel</a>
        </div>
    </form>
</div>
<?php require '../includes/footer.php'; ?>
