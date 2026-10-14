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

    <div class="lines" style="padding-top:1rem;">
        <div class="rail-section-title" style="margin-bottom:0.5rem;">
            <?= $is_archived_view ? 'Archived Categories' : 'Existing Categories' ?> (<?php echo count($categories); ?> total)
        </div>
        <?php if (count($categories) === 0) { ?>
            <div class="card in" style="text-align:center; padding:3rem;">
                <p class="muted">No categories in this view.</p>
            </div>
        <?php } ?>
        <?php foreach ($categories as $c) { ?>
            <div class="line">
                <div class="tile" style="--h: <?php echo ($c['category_id'] * 47) % 360; ?>; aspect-ratio:1; max-height:64px;"></div>
                <?php if (!$is_archived_view) { ?>
                    <form method="POST" action="categories.php" style="display:flex; gap:.75rem; align-items:center; flex:1;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                        <input type="text" name="name" value="<?php echo htmlspecialchars($c['name']); ?>" required style="flex:1; padding:0.6rem 1rem;">
                        <button class="btn ghost sm" type="submit" name="rename_category">Save Name</button>
                    </form>
                    <form method="POST" action="categories.php"
                          onsubmit="return confirm('Remove this category?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                        <button class="btn ghost sm" type="submit" name="deactivate_category" style="color:#ff9f96; border-color:rgba(255,100,100,0.25);">Remove</button>
                    </form>
                <?php } else { ?>
                    <div style="flex:1;">
                        <strong style="font-size:1.1rem;"><?= htmlspecialchars($c['name']) ?></strong>
                        <span class="muted" style="display:block; font-size:12px;">Archived category</span>
                    </div>
                    <form method="POST" action="categories.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="category_id" value="<?php echo $c['category_id']; ?>">
                        <button class="btn sm" type="submit" name="restore_category" style="background:rgba(81,175,67,0.85);">↺ Restore Category</button>
                    </form>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
