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
<section class="reveal in">
    <!-- Header & Taxonomy Stats -->
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1.25rem; margin-bottom:1.5rem;">
        <div>
            <div class="hero-eyebrow" style="color:var(--color-brand-red);">[ TAXONOMY &amp; CLASSIFICATION ]</div>
            <h1 style="font-size:var(--text-display-l); line-height:1.15; margin:2px 0 0;">Manage Categories</h1>
            <p class="muted" style="font-size:13px; margin:4px 0 0;">Organize catalog equipment, sports wear, and gear into customer-navigable departments.</p>
        </div>
        <div class="order-tabs admin-tabs" style="margin-bottom:0;">
            <a href="categories.php?view=active" class="order-tab <?= !$is_archived_view ? 'active' : '' ?>">
                Active Categories <span class="tab-badge"><?= $active_cat_count ?></span>
            </a>
            <a href="categories.php?view=archived" class="order-tab <?= $is_archived_view ? 'active' : '' ?>">
                Archived <span class="tab-badge"><?= $archived_cat_count ?></span>
            </a>
        </div>
    </div>

    <?php require '../includes/alert.php'; ?>

    <!-- KPI Summary Strip -->
    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(210px, 1fr)); gap:1rem; margin-bottom:1.75rem;">
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Active Categories</div>
            <div style="font-size:1.75rem; font-weight:800; color:#fff; margin-top:4px;"><?= $active_cat_count ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;">Visible in shop navigation</div>
        </div>
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Categorized Products</div>
            <div style="font-size:1.75rem; font-weight:800; color:var(--color-brand-green); margin-top:4px;"><?= array_sum($item_counts) ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;">Total items across active categories</div>
        </div>
        <div class="card in" style="padding:1.25rem; border-radius:14px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08);">
            <div style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Archived Depts</div>
            <div style="font-size:1.75rem; font-weight:800; color:#ff9f96; margin-top:4px;"><?= $archived_cat_count ?></div>
            <div class="muted" style="font-size:11px; margin-top:2px;">Hidden from storefront</div>
        </div>
    </div>

    <!-- Add New Category Intake Panel -->
    <?php if (!$is_archived_view) { ?>
    <div class="card in" style="padding:1.5rem; border-radius:16px; background:rgba(22, 24, 29, 0.7); border:1px solid rgba(255,255,255,0.08); margin-bottom:2rem; box-shadow:0 8px 24px rgba(0,0,0,0.25);">
        <div style="display:flex; align-items:center; gap:10px; margin-bottom:1rem;">
            <div style="width:32px; height:32px; border-radius:8px; background:rgba(224, 35, 28, 0.15); border:1px solid rgba(224, 35, 28, 0.3); display:flex; align-items:center; justify-content:center; font-size:14px;">
                🏷️
            </div>
            <div>
                <h2 style="font-size:1.15rem; font-weight:700; color:#fff; margin:0;">Create New Category</h2>
                <div class="muted" style="font-size:12px;">Add a new taxonomy label for your gear catalog.</div>
            </div>
        </div>
        <form method="POST" action="categories.php" style="display:flex; gap:0.75rem; align-items:center; flex-wrap:wrap; margin:0;">
            <?= csrf_field() ?>
            <div style="position:relative; flex:1; min-width:16rem;">
                <input type="text" name="name" placeholder="Category name (e.g. Footwear, Training Gear, Rackets)..." required
                    class="search" style="width:100%; padding:0.75rem 1rem 0.75rem 2.2rem; font-size:13px; border-radius:10px;">
                <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:0.5; font-size:13px;">➕</span>
            </div>
            <button class="btn" type="submit" name="add_category" style="padding:0.75rem 1.5rem; font-size:13px; font-weight:700; white-space:nowrap;">
                + Create Category
            </button>
        </form>
    </div>
    <?php } ?>

    <!-- Catalog Categories Grid -->
    <div>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:8px;">
            <div class="rail-section-title" style="margin:0;">
                <?= $is_archived_view ? 'Archived Categories' : 'Active Catalog Categories' ?> (<?= count($categories) ?> total)
            </div>
            <?php if (!$is_archived_view && count($categories) > 0) { ?>
                <span class="muted" style="font-size:12px;">Click <strong>Rename</strong> on any category to update its name inline.</span>
            <?php } ?>
        </div>

        <?php if (count($categories) === 0) { ?>
            <div class="card in" style="text-align:center; padding:4rem 2rem; border-radius:16px;">
                <div style="font-size:2.5rem; margin-bottom:0.75rem;">🏷️</div>
                <h3 style="font-size:1.15rem; font-weight:700; color:#fff; margin-bottom:4px;">No Categories Found</h3>
                <p class="muted" style="font-size:13px; margin:0;">
                    <?= $is_archived_view ? 'There are currently no archived categories.' : 'No active categories found. Add your first category using the form above.' ?>
                </p>
            </div>
        <?php } else { ?>
            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:1.25rem;">
                <?php foreach ($categories as $c) { 
                    $cid   = (int)$c['category_id'];
                    $count = $item_counts[$cid] ?? 0;
                    $hue   = ($cid * 67 + 25) % 360;
                ?>
                    <div class="card in" id="cat_card_<?= $cid ?>" style="display:flex; flex-direction:column; justify-content:space-between; padding:1.25rem; border:1px solid rgba(255,255,255,0.08); border-radius:14px; background:rgba(22, 24, 29, 0.7); box-shadow:0 6px 18px rgba(0,0,0,0.2); transition:transform 0.18s ease, border-color 0.18s ease;">
                        <div>
                            <!-- Header with Dynamic Avatar & Product Count Badge -->
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                                <div style="display:flex; align-items:center; gap:10px;">
                                    <div style="width:36px; height:36px; border-radius:10px; background:hsl(<?= $hue ?>, 65%, 45%); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:800; color:#fff; box-shadow:0 2px 10px hsla(<?= $hue ?>, 65%, 45%, 0.35); flex-shrink:0;">
                                        <?= strtoupper(substr($c['name'], 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:0.8px; color:var(--color-muted);">Category ID #<?= $cid ?></div>
                                    </div>
                                </div>
                                <span class="status-badge" style="font-size:11px; padding:3px 9px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); color:var(--color-fg-secondary);">
                                    <?= $count ?> <?= $count === 1 ? 'item' : 'items' ?>
                                </span>
                            </div>

                            <!-- Display Mode -->
                            <div id="cat_display_<?= $cid ?>" style="margin-bottom:1.25rem;">
                                <h3 style="font-size:1.15rem; font-weight:700; color:#fff; margin:0 0 6px; letter-spacing:-0.01em;">
                                    <?= htmlspecialchars($c['name']) ?>
                                </h3>
                                <?php if (!$is_archived_view) { ?>
                                    <a href="items.php?cat=<?= $cid ?>" style="font-size:12px; color:var(--color-accent-on-dark); font-weight:600; text-decoration:none; display:inline-flex; align-items:center; gap:4px;">
                                        Browse <?= $count ?> in catalog &rarr;
                                    </a>
                                <?php } else { ?>
                                    <span class="muted" style="font-size:11px;">Archived Department</span>
                                <?php } ?>
                            </div>

                            <!-- Inline Edit Form (Hidden by Default) -->
                            <?php if (!$is_archived_view) { ?>
                            <form method="POST" action="categories.php" id="cat_edit_<?= $cid ?>" style="display:none; margin:0 0 1.25rem;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="category_id" value="<?= $cid ?>">
                                <label style="font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:var(--color-muted); display:block; margin-bottom:4px;">Rename Category</label>
                                <div style="display:flex; gap:6px; align-items:center;">
                                    <input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" required
                                           class="search" style="padding:0.45rem 0.75rem; font-size:12px; font-weight:600; flex:1;">
                                    <button class="btn sm" type="submit" name="rename_category" style="padding:0.45rem 0.8rem; font-size:11px;">Save</button>
                                    <button class="btn ghost sm" type="button" onclick="cancelCatEdit(<?= $cid ?>)" style="padding:0.45rem 0.65rem; font-size:11px;">✕</button>
                                </div>
                            </form>
                            <?php } ?>
                        </div>

                        <!-- Card Action Footer -->
                        <div style="display:flex; gap:0.5rem; justify-content:space-between; align-items:center; border-top:1px solid rgba(255,255,255,0.06); padding-top:0.75rem; margin-top:auto;">
                            <?php if (!$is_archived_view) { ?>
                                <button class="btn ghost sm" type="button" onclick="openCatEdit(<?= $cid ?>)" style="font-size:11px; padding:5px 12px; border-radius:8px;">
                                    ✏️ Rename
                                </button>
                                <form method="POST" action="categories.php" onsubmit="return confirmArchive(<?= $count ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>');" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="category_id" value="<?= $cid ?>">
                                    <button class="btn ghost sm" type="submit" name="deactivate_category" style="color:#ff9f96; border-color:rgba(255,100,100,0.25); font-size:11px; padding:5px 12px; border-radius:8px;">
                                        Archive
                                    </button>
                                </form>
                            <?php } else { ?>
                                <form method="POST" action="categories.php" style="width:100%; margin:0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="category_id" value="<?= $cid ?>">
                                    <button class="btn sm" type="submit" name="restore_category" style="width:100%; font-size:11px; padding:6px 12px; border-radius:8px; background:rgba(81,175,67,0.85);">
                                        ↺ Restore Category
                                    </button>
                                </form>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</section>

<script>
function openCatEdit(id) {
    const disp = document.getElementById('cat_display_' + id);
    const form = document.getElementById('cat_edit_' + id);
    if (disp && form) {
        disp.style.display = 'none';
        form.style.display = 'block';
        const input = form.querySelector('input[name="name"]');
        if (input) {
            input.focus();
            input.select();
        }
    }
}

function cancelCatEdit(id) {
    const disp = document.getElementById('cat_display_' + id);
    const form = document.getElementById('cat_edit_' + id);
    if (disp && form) {
        form.style.display = 'none';
        disp.style.display = 'block';
    }
}

function confirmArchive(itemCount, name) {
    if (itemCount > 0) {
        alert('Cannot archive category "' + name + '" because it currently has ' + itemCount + ' active items. Please reassign or archive those products first.');
        return false;
    }
    return confirm('Archive category "' + name + '"? It will be hidden from the public catalog.');
}
</script>

<?php require '../includes/footer.php'; ?>
