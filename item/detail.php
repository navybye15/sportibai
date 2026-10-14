<?php
require '../includes/db.php';
require '../includes/functions.php';
$base = '../';
$id = 0;
if (isset($_GET['id'])) {
    $id = (int) $_GET['id'];
}
$item = find_item($conn, $id);
if ($item == null) {
    header('Location: ../index.php');
    exit;
}
$page_title = $item['name'] . ' | Sportibai';
require '../includes/header.php';
?>
<nav class="res-crumbs">
    <a href="../index.php">Home</a>
    <span class="sep">/</span>
    <a href="../shop.php">Shop</a>
    <span class="sep">/</span>
    <span><?= htmlspecialchars($item['name']) ?></span>
</nav>

<?php require '../includes/alert.php'; ?>

<section class="detail">
    <div class="reveal">
        <?php item_tile($item, '../'); ?>
    </div>
    <div class="stack reveal">
        <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label-wide); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
            <?= htmlspecialchars($item['brand'] != '' ? $item['brand'] : $item['category']) ?>
        </span>
        <h1 style="font-size: var(--text-display-l); line-height: 1.1;"><?= htmlspecialchars($item['name']) ?></h1>
        <div class="price"><?= peso($item['price']) ?></div>
        
        <?php if (isset($item['description']) && $item['description'] != '') { ?>
            <p class="muted" style="line-height:1.7; font-size:var(--text-body);"><?= htmlspecialchars($item['description']) ?></p>
        <?php } ?>

        <div style="padding: 1rem 0; border-top: var(--hairline) solid var(--color-border); border-bottom: var(--hairline) solid var(--color-border); display:flex; align-items:center; justify-content:space-between;">
            <span class="muted" style="font-size:var(--text-body-sm)">Availability</span>
            <?php if ($item['stock'] > 0) { ?>
                <span style="color:var(--color-brand-green); font-weight:600; font-size:var(--text-body-sm); display:flex; align-items:center; gap:6px;">
                    <span style="width:6px; height:6px; border-radius:50%; background:var(--color-brand-green); display:inline-block;"></span>
                    <?= $item['stock'] ?> in stock
                </span>
            <?php } else { ?>
                <span class="muted" style="font-size:var(--text-body-sm)">Out of stock</span>
            <?php } ?>
        </div>

        <?php if ($item['stock'] > 0) { ?>
            <form method="post" action="../cart_update.php" style="margin-top:0.5rem">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                <input type="hidden" name="return_url" value="item/detail.php?id=<?= $item['item_id'] ?>">
                <button class="btn" style="width:100%" type="submit">Add to cart</button>
            </form>
        <?php } ?>

        <div style="margin-top: 0.5rem;">
            <a class="btn ghost sm" href="../shop.php">← Back to shelf</a>
        </div>
    </div>
</section>
<?php require '../includes/footer.php'; ?>
