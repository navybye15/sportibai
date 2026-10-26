<?php
require 'includes/db.php';
require 'includes/functions.php';
$base = '';
$page_title = 'Shop | Sportibai — Gear for Every Court';
require 'includes/header.php';

// ── Filter & Sort Parameters from URL ─────────────────────────────────────────
$q     = trim($_GET['q'] ?? '');
$cat   = trim($_GET['cat'] ?? '');
$brand = trim($_GET['brand'] ?? '');
$sort  = $_GET['sort'] ?? 'newest';

$min_price = ''; $has_min = false;
$max_price = ''; $has_max = false;
if (isset($_GET['min']) && is_numeric($_GET['min'])) { $min_price = (float)$_GET['min']; $has_min = true; }
if (isset($_GET['max']) && is_numeric($_GET['max'])) { $max_price = (float)$_GET['max']; $has_max = true; }

// Pagination variables
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 12; // 12 items per page grid
$offset   = ($page - 1) * $per_page;

// ── Fetch dynamic categories and brands for filter bars ─────────────────────
$categories = array();
$res_cat = mysqli_query($conn, "SELECT name FROM categories WHERE is_active = 1 ORDER BY name ASC");
while ($r = mysqli_fetch_assoc($res_cat)) { $categories[] = $r['name']; }

$brands = array();
$res_br = mysqli_query($conn, "SELECT DISTINCT brand FROM items WHERE is_active = 1 AND brand != '' ORDER BY brand ASC");
while ($r = mysqli_fetch_assoc($res_br)) { $brands[] = $r['brand']; }

// ── Build SQL Query for filtered items ──────────────────────────────────────
$where  = array("i.is_active = 1", "c.is_active = 1");
$params = array();

if ($cat !== '') {
    $where[]  = "c.name = ?";
    $params[] = $cat;
}
if ($brand !== '') {
    $where[]  = "i.brand = ?";
    $params[] = $brand;
}
if ($q !== '') {
    $where[]  = "(i.name LIKE ? OR i.brand LIKE ? OR i.description LIKE ?)";
    $like     = '%' . $q . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($has_min) {
    $where[]  = "i.price >= ?";
    $params[] = $min_price;
}
if ($has_max) {
    $where[]  = "i.price <= ?";
    $params[] = $max_price;
}

$where_sql = "WHERE " . implode(" AND ", $where);

// Count total matching items for pagination
$count_sql = "SELECT COUNT(*) AS total
              FROM items i
              JOIN categories c ON i.category_id = c.category_id
              $where_sql";
$count_res = count($params) > 0 ? mysqli_execute_query($conn, $count_sql, params: $params) : mysqli_query($conn, $count_sql);
$total_matching = (int)(mysqli_fetch_assoc($count_res)['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_matching / $per_page));
if ($page > $total_pages) $page = $total_pages;

// Sort ordering
switch ($sort) {
    case 'price_asc':
        $order_sql = "ORDER BY i.price ASC";
        break;
    case 'price_desc':
        $order_sql = "ORDER BY i.price DESC";
        break;
    case 'alpha':
        $order_sql = "ORDER BY i.name ASC";
        break;
    case 'newest':
    default:
        $order_sql = "ORDER BY i.item_id DESC";
        $sort = 'newest';
        break;
}

// Fetch current page items
$items_sql = "SELECT i.item_id, i.name, i.brand, i.description, i.price, i.cost_price, i.stock, i.image, c.name AS category
              FROM items i
              JOIN categories c ON i.category_id = c.category_id
              $where_sql
              $order_sql
              LIMIT $per_page OFFSET $offset";
$items_res = count($params) > 0 ? mysqli_execute_query($conn, $items_sql, $params) : mysqli_query($conn, $items_sql);
$shown = array();
while ($row = mysqli_fetch_assoc($items_res)) {
    $shown[] = $row;
}

// Fetch per-size variants for displayed items to support quick-add modal
$shop_sizes = array();
if (!empty($shown)) {
    $sh_ids = array_column($shown, 'item_id');
    $in_clause = implode(',', array_map('intval', $sh_ids));
    $sz_res = mysqli_query($conn, "SELECT size_id, item_id, size_label, stock FROM item_sizes WHERE item_id IN ($in_clause) ORDER BY size_id ASC");
    if ($sz_res) {
        while ($srow = mysqli_fetch_assoc($sz_res)) {
            $shop_sizes[$srow['item_id']][] = $srow;
        }
    }
}

// Build URL query state for links
$keep = array();
if ($q !== '')        $keep['q']     = $q;
if ($cat !== '')      $keep['cat']   = $cat;
if ($brand !== '')    $keep['brand'] = $brand;
if ($has_min)         $keep['min']   = $min_price;
if ($has_max)         $keep['max']   = $max_price;
if ($sort !== 'newest') $keep['sort'] = $sort;

$all_link = count(array_filter($keep, fn($k) => $k !== 'cat', ARRAY_FILTER_USE_KEY)) > 0 
    ? 'shop.php?' . http_build_query(array_filter($keep, fn($k) => $k !== 'cat', ARRAY_FILTER_USE_KEY)) 
    : 'shop.php';
?>

<nav class="res-crumbs">
    <a href="index.php">Home</a>
    <span class="sep">/</span>
    <span>Shop</span>
</nav>

<?php require 'includes/alert.php'; ?>

<section class="hero reveal">
    <div class="hero-eyebrow">[ PERFORMANCE &amp; APPAREL ]</div>
    <h1>The <span class="highlight">Sportibai</span> Shelf.</h1>
    <p>Premium balls, weights, kicks, and essentials. Filter by category, sort by price, or search for what you need.</p>
</section>

<section id="shop">
    <form class="filter-search-bar reveal" method="get" action="shop.php#shop">
        <input class="search" type="search" name="q" placeholder="Search gear or brand…" value="<?= htmlspecialchars($q) ?>">
        <?php if ($cat !== '') { ?><input type="hidden" name="cat" value="<?= htmlspecialchars($cat) ?>"><?php } ?>
        <select class="search" style="flex:0 1 12rem" name="brand">
            <option value="">All brands</option>
            <?php foreach ($brands as $b) { ?>
                <option value="<?= htmlspecialchars($b) ?>" <?= ($b === $brand) ? 'selected' : '' ?>><?= htmlspecialchars($b) ?></option>
            <?php } ?>
        </select>
        <select class="search" style="flex:0 1 12rem" name="sort">
            <option value="newest" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest First</option>
            <option value="price_asc" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_desc" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="alpha" <?= ($sort === 'alpha') ? 'selected' : '' ?>>Alphabetical (A-Z)</option>
        </select>
        <input class="search" style="flex:0 1 8rem" type="number" step="any" min="0" name="min" placeholder="Min ₱" value="<?= htmlspecialchars($min_price) ?>">
        <input class="search" style="flex:0 1 8rem" type="number" step="any" min="0" name="max" placeholder="Max ₱" value="<?= htmlspecialchars($max_price) ?>">
        <button class="btn" type="submit">Filter</button>
        <?php if ($cat !== '' || count($keep) > 0) { ?>
            <a class="btn ghost sm" href="shop.php#shop">Reset</a>
        <?php } ?>
    </form>

    <div class="shop-shell">
        <!-- Category Rail -->
        <aside class="filter-rail reveal">
            <div class="rail-section-title">Categories</div>
            <nav class="rail-categories">
                <?php $all_cls = ($cat === '') ? 'rail-chip on' : 'rail-chip'; ?>
                <a class="<?= $all_cls ?>" href="<?= htmlspecialchars($all_link) ?>#shop">
                    <span>All Gear</span>
                    <span class="muted" style="font-size:10px;"><?= $total_matching ?></span>
                </a>
                <?php foreach ($categories as $c) {
                    $cls  = ($c === $cat) ? 'rail-chip on' : 'rail-chip';
                    $link = $keep; $link['cat'] = $c; unset($link['page']);
                ?>
                    <a class="<?= $cls ?>" href="shop.php?<?= htmlspecialchars(http_build_query($link)) ?>#shop">
                        <span><?= htmlspecialchars($c) ?></span>
                    </a>
                <?php } ?>
            </nav>
        </aside>

        <!-- Product Grid -->
        <div class="shop-content">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; padding-bottom:0.5rem; border-bottom:var(--hairline) solid var(--color-border-soft);">
                <span class="muted" style="font-size:var(--text-body-sm);">Showing <?= count($shown) ?> of <?= $total_matching ?> products</span>
                <?php if ($total_pages > 1) { ?>
                    <span class="muted" style="font-size:var(--text-body-sm);">Page <?= $page ?> of <?= $total_pages ?></span>
                <?php } ?>
            </div>

            <?php if (count($shown) === 0) { ?>
                <div class="card reveal in" style="text-align:center; padding:3.5rem 2rem;">
                    <p class="muted">No gear found matching your filters.</p>
                    <div style="margin-top:1.5rem"><a class="btn ghost" href="shop.php#shop">Clear filters</a></div>
                </div>
            <?php } else { ?>
                <div class="grid">
                <?php foreach ($shown as $item) { ?>
                    <article class="card reveal" id="item-<?= $item['item_id'] ?>">
                        <a class="tile-link" href="item/detail.php?id=<?= $item['item_id'] ?>">
                            <?php item_tile($item, ''); ?>
                        </a>
                        <div class="info">
                            <span class="brand-tag"><?= htmlspecialchars($item['brand'] !== '' ? $item['brand'] : $item['category']) ?></span>
                            <h2><?= htmlspecialchars($item['name']) ?></h2>
                            <?php if (!empty($item['description'])) { ?>
                                <p class="muted" style="font-size:13px; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                                    <?= htmlspecialchars($item['description']) ?>
                                </p>
                            <?php } ?>
                        </div>
                        <?php 
                            $item_sizes_list = $shop_sizes[$item['item_id']] ?? array();
                            $has_item_sizes = count($item_sizes_list) > 0;
                        ?>
                        <div class="row">
                            <span class="price"><?= peso($item['price']) ?></span>
                            <?php if ($is_admin) { ?>
                                <span class="muted" style="font-size:11px; text-transform:uppercase;">Admin view</span>
                            <?php } elseif ($item['stock'] > 0) { ?>
                                <?php if ($has_item_sizes) { ?>
                                    <button class="btn sm" type="button" onclick='openVariantModal(<?= htmlspecialchars(json_encode([
                                        "id" => (int)$item["item_id"],
                                        "name" => $item["name"],
                                        "brand" => $item["brand"] !== "" ? $item["brand"] : $item["category"],
                                        "category" => $item["category"],
                                        "price" => peso($item["price"]),
                                        "image" => !empty($item["image"]) ? "images/uploads/" . basename($item["image"]) : "",
                                        "sizes" => $item_sizes_list,
                                        "return_url" => "shop.php" . (count($_GET) > 0 ? ("?" . http_build_query($_GET)) : "") . "#item-" . $item["item_id"]
                                    ]), ENT_QUOTES, "UTF-8") ?>)'>
                                        Add to Cart
                                    </button>
                                <?php } else { ?>
                                    <form method="post" action="actions/cart_update.php">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="return_url" value="shop.php<?= count($_GET) > 0 ? ('?' . http_build_query($_GET)) : '' ?>#item-<?= $item['item_id'] ?>">
                                        <button class="btn sm" type="submit">Add to Cart</button>
                                    </form>
                                <?php } ?>
                            <?php } else { ?>
                                <span class="muted" style="font-size:11px; text-transform:uppercase;">Out of Stock</span>
                            <?php } ?>
                        </div>
                    </article>
                <?php } ?>
                </div>

                <!-- Pagination Controls -->
                <?php if ($total_pages > 1) { ?>
                    <div style="display:flex; justify-content:center; align-items:center; gap:0.5rem; margin-top:2.5rem; flex-wrap:wrap;">
                        <?php if ($page > 1) { 
                            $prev_link = $keep; $prev_link['page'] = $page - 1;
                        ?>
                            <a href="shop.php?<?= http_build_query($prev_link) ?>#shop" class="btn ghost sm">← Prev</a>
                        <?php } ?>

                        <?php for ($p = 1; $p <= $total_pages; $p++) { 
                            $p_link = $keep; $p_link['page'] = $p;
                            $is_cur = ($p === $page);
                        ?>
                            <a href="shop.php?<?= http_build_query($p_link) ?>#shop" 
                               class="btn sm <?= $is_cur ? '' : 'ghost' ?>"
                               style="<?= $is_cur ? 'background:var(--color-accent);' : 'min-width:36px; padding:0.4rem 0.75rem;' ?>">
                                <?= $p ?>
                            </a>
                        <?php } ?>

                        <?php if ($page < $total_pages) { 
                            $next_link = $keep; $next_link['page'] = $page + 1;
                        ?>
                            <a href="shop.php?<?= http_build_query($next_link) ?>#shop" class="btn ghost sm">Next →</a>
                        <?php } ?>
                    </div>
                <?php } ?>
            <?php } ?>
        </div>
    </div>
</section>
<?php 
require 'includes/variant_modal.php';
require 'includes/footer.php'; 
?>
