<?php
require '../includes/admin_guard.php';
require '../includes/db.php';
require '../includes/functions.php';

if ($conn == null) {
    die('Database is not connected.');
}

// Add a new category
if (isset($_POST['add_category'])) {
    if (!csrf_verify()) {
        header('Location: categories.php');
        exit;
    }
    $name = strip_tags(trim($_POST['name']));
    if ($name == '') {
        $_SESSION['message'] = 'Please enter a category name.';
    } else {
        // Prevent duplicate category names (case-insensitive)
        $sql_check = "SELECT category_id FROM categories WHERE LOWER(name) = LOWER(?) LIMIT 1";
        $res_check = mysqli_execute_query($conn, $sql_check, array($name));
        if (mysqli_num_rows($res_check) > 0) {
            $_SESSION['message'] = 'Category "' . $name . '" already exists.';
        } else {
            $sql = "INSERT INTO categories (name) VALUES (?)";
            mysqli_execute_query($conn, $sql, array($name));
            $_SESSION['success'] = 'Category added.';
        }
    }
    header('Location: categories.php');
    exit;
}

// Rename a category
if (isset($_POST['rename_category'])) {
    if (!csrf_verify()) {
        header('Location: categories.php');
        exit;
    }
    $category_id = (int) $_POST['category_id'];
    $name = strip_tags(trim($_POST['name']));
    if ($name == '') {
        $_SESSION['message'] = 'Please enter a category name.';
    } else {
        // Prevent duplicate category names excluding this category
        $sql_check = "SELECT category_id FROM categories WHERE LOWER(name) = LOWER(?) AND category_id != ? LIMIT 1";
        $res_check = mysqli_execute_query($conn, $sql_check, array($name, $category_id));
        if (mysqli_num_rows($res_check) > 0) {
            $_SESSION['message'] = 'Another category is already named "' . $name . '".';
        } else {
            $sql = "UPDATE categories SET name = ? WHERE category_id = ?";
            mysqli_execute_query($conn, $sql, array($name, $category_id));
            $_SESSION['success'] = 'Category updated.';
        }
    }
    header('Location: categories.php');
    exit;
}

// Soft delete: hide the category, the items under it stay as they were
if (isset($_POST['deactivate_category'])) {
    if (!csrf_verify()) {
        header('Location: categories.php');
        exit;
    }
    $category_id = (int) $_POST['category_id'];

    // Does this category still have active items? If yes, block the delete,
    // so the shop never ends up with items whose category disappeared.
    $sql = "SELECT COUNT(*) AS n FROM items WHERE category_id = ? AND is_active = 1";
    $result = mysqli_execute_query($conn, $sql, array($category_id));
    $row = mysqli_fetch_assoc($result);

    if ($row['n'] > 0) {
        $_SESSION['message'] = 'This category still has active items. Move or remove them first.';
    } else {
        $sql = "UPDATE categories SET is_active = 0 WHERE category_id = ?";
        mysqli_execute_query($conn, $sql, array($category_id));
        $_SESSION['success'] = 'Category removed.';
    }
    header('Location: categories.php');
    exit;
}

// Restore category
if (isset($_POST['restore_category'])) {
    if (!csrf_verify()) {
        header('Location: categories.php?view=archived');
        exit;
    }
    $category_id = (int) $_POST['category_id'];
    $sql = "UPDATE categories SET is_active = 1 WHERE category_id = ?";
    mysqli_execute_query($conn, $sql, array($category_id));
    $_SESSION['success'] = 'Category restored.';
    header('Location: categories.php?view=archived');
    exit;
}

$view = $_GET['view'] ?? 'active';
$is_archived_view = ($view === 'archived');

$active_val = $is_archived_view ? 0 : 1;
$categories = array();
$result = mysqli_execute_query($conn, "SELECT category_id, name FROM categories WHERE is_active = ? ORDER BY name", array($active_val));
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

$res_act = mysqli_query($conn, "SELECT COUNT(*) AS c FROM categories WHERE is_active = 1");
$active_cat_count = mysqli_fetch_assoc($res_act)['c'] ?? 0;
$res_arc = mysqli_query($conn, "SELECT COUNT(*) AS c FROM categories WHERE is_active = 0");
$archived_cat_count = mysqli_fetch_assoc($res_arc)['c'] ?? 0;

$item_counts = array();
$res_counts = mysqli_query($conn, "SELECT category_id, COUNT(*) AS cnt FROM items WHERE is_active = 1 GROUP BY category_id");
if ($res_counts) {
    while ($r = mysqli_fetch_assoc($res_counts)) {
        $item_counts[$r['category_id']] = (int)$r['cnt'];
    }
}

$base = '../';
$page_title = 'Manage Categories | Sportibai';
require '../includes/header.php';
?>
<section class="reveal">
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow">[ TAXONOMY MANAGEMENT ]</div>
            <h1 style="font-size: var(--text-display-l)">Manage Categories</h1>
        </div>
        <div class="order-tabs" style="margin-bottom:0; border-bottom:none;">
            <a href="categories.php?view=active" class="order-tab <?= !$is_archived_view ? 'active' : '' ?>">
                Active Categories <span class="tab-badge"><?= $active_cat_count ?></span>
            </a>
            <a href="categories.php?view=archived" class="order-tab <?= $is_archived_view ? 'active' : '' ?>">
                Archived <span class="tab-badge"><?= $archived_cat_count ?></span>
            </a>
        </div>
    </div>
    <?php require '../includes/alert.php'; ?>

    <?php if (!$is_archived_view) { ?>
    <form class="form" method="POST" action="categories.php" style="margin: 1.5rem 0 2rem; max-width: 100%;">
        <?= csrf_field() ?>
        <div>
            <div class="hero-eyebrow">[ CREATE CLASSIFICATION ]</div>
            <h2 style="font-size:1.35rem; margin-top:4px;">Add New Category</h2>
        </div>
        <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
            <input type="text" name="name" placeholder="Category name (e.g. Footwear, Training Gear)" required style="flex:1; min-width:14rem;">
            <button class="btn" type="submit" name="add_category">+ Create Category</button>
        </div>
    </form>
    <?php } ?>

    <div style="padding-top:1rem;">
        <div class="rail-section-title" style="margin-bottom:1rem;">
            <?= $is_archived_view ? 'Archived Categories' : 'Active Catalog Categories' ?> (<?php echo count($categories); ?> total)
        </div>
        <?php if (count($categories) === 0) { ?>
            <div class="card in" style="text-align:center; padding:3rem;">
                <p class="muted">No categories in this view.</p>
            </div>
        <?php } else { ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:1rem;">
                <?php foreach ($categories as $c) { 
                    $count = $item_counts[$c['category_id']] ?? 0;
                    $hue = ($c['category_id'] * 47) % 360;
                ?>
                    <div class="card in" style="display:flex; flex-direction:column; justify-content:space-between; padding:1.25rem; border:1px solid rgba(255,255,255,0.08); border-radius:12px; background:rgba(255,255,255,0.02);">
                        <!-- Header with Color Accent & Item Count Badge -->
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <div style="width:28px; height:28px; border-radius:6px; background:hsl(<?= $hue ?>, 70%, 50%); display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; color:#fff;">
                                <?= strtoupper(substr($c['name'], 0, 1)) ?>
                            </div>
                            <span class="status-badge" style="font-size:11px; padding:2px 8px; background:rgba(255,255,255,0.06); border-color:rgba(255,255,255,0.12);">
                                <?= $count ?> <?= $count === 1 ? 'item' : 'items' ?>
                            </span>
                        </div>

                        <!-- Category Name / Edit Form -->
                        <div style="flex-grow:1; margin-bottom:1rem;">
                            <?php if (!$is_archived_view) { ?>
                                <form method="POST" action="categories.php" id="form_cat_<?= $c['category_id'] ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                                    <label class="form-label" style="font-size:11px; margin-bottom:4px; display:block;">Category Name</label>
                                    <input type="text" name="name" value="<?php echo htmlspecialchars($c['name']); ?>" style="padding:0.45rem 0.65rem; font-size:13px; font-weight:600; width:100%;">
                                </form>
                            <?php } else { ?>
                                <div style="font-size:1.1rem; font-weight:700; color:var(--color-fg);"><?= htmlspecialchars($c['name']) ?></div>
                                <span class="muted" style="font-size:11px;">Archived Category</span>
                            <?php } ?>
                        </div>

                        <!-- Card Footer Quick Actions -->
                        <div style="display:flex; gap:0.5rem; justify-content:space-between; align-items:center; border-top:1px solid rgba(255,255,255,0.06); padding-top:0.75rem;">
                            <?php if (!$is_archived_view) { ?>
                                <button class="btn ghost sm" type="submit" form="form_cat_<?= $c['category_id'] ?>" name="rename_category" style="font-size:11px; padding:4px 10px;">💾 Save</button>
                                <form method="POST" action="categories.php" onsubmit="return confirm('Archive category \'<?= htmlspecialchars(addslashes($c['name'])) ?>\'?');" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                                    <button class="btn ghost sm" type="submit" name="deactivate_category" style="color:#ff9f96; border-color:rgba(255,100,100,0.25); font-size:11px; padding:4px 10px;">Archive</button>
                                </form>
                            <?php } else { ?>
                                <form method="POST" action="categories.php" style="width:100%;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                                    <button class="btn sm" type="submit" name="restore_category" style="width:100%; font-size:11px; padding:5px 10px; background:rgba(81,175,67,0.85);">↺ Restore Category</button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
