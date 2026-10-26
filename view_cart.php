<?php
require 'includes/customer_guard.php';
require 'includes/db.php';
require 'includes/functions.php';
$base = '';
$page_title = 'Your cart | Sportibai';
require 'includes/header.php';

$cart = array();
if (isset($_SESSION['cart'])) {
    $cart = $_SESSION['cart'];
}
$total = 0;
?>
<nav class="res-crumbs">
    <a href="index.php">Home</a>
    <span class="sep">/</span>
    <a href="shop.php">Shop</a>
    <span class="sep">/</span>
    <span>Cart</span>
</nav>

<section class="lines reveal">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:1rem;">
        <div>
            <div class="hero-eyebrow">[ YOUR SELECTIONS ]</div>
            <h1 style="font-size: var(--text-display-l)">Your Cart</h1>
        </div>
        <?php if (count($cart) > 0) { ?>
            <span class="muted" style="font-size:var(--text-body-sm)"><?= count($cart) ?> unique item<?= count($cart) === 1 ? '' : 's' ?></span>
        <?php } ?>
    </div>

    <?php require 'includes/alert.php'; ?>

    <?php if (count($cart) == 0) { ?>
        <div class="card in" style="text-align:center; padding: 4rem 2rem;">
            <p class="muted" style="font-size:var(--text-body-lead)">Nothing here yet.</p>
            <div style="margin-top:1.5rem">
                <a class="btn" href="shop.php">Browse the gear shelf</a>
            </div>
        </div>
    <?php } ?>

    <?php foreach ($cart as $cart_key => $qty) {
        $parts = explode('_', (string)$cart_key, 2);
        $id = (int)$parts[0];
        $selected_size = $parts[1] ?? '';

        $item = find_item($conn, $id);
        if ($item != null) {
            $max_stock = ($selected_size !== '') ? get_item_size_stock($conn, $id, $selected_size) : (int)$item['stock'];
            $sub = $item['price'] * $qty;
            $total = $total + $sub; ?>
        <div class="line">
            <?php item_tile($item, ''); ?>
            <div>
                <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
                    <?= htmlspecialchars($item['brand'] != '' ? $item['brand'] : $item['category']) ?>
                </span>
                <h2 style="font-size:1.15rem; margin-top:2px; display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                    <?= htmlspecialchars($item['name']) ?>
                    <?php if ($selected_size !== '') { ?>
                        <span class="badge" style="background:rgba(255,255,255,0.08); color:var(--color-fg); border:1px solid rgba(255,255,255,0.2); font-size:11px; padding:2px 8px; vertical-align:middle; letter-spacing:0.04em;">
                            Size: <?= htmlspecialchars($selected_size) ?>
                        </span>
                    <?php } ?>
                </h2>
                <span class="muted" style="font-size:var(--text-body-sm)">
                    <?= peso($item['price']) ?> each &nbsp;•&nbsp; <strong style="color:var(--color-fg)"><?= peso($sub) ?> total</strong>
                    <?php if ($selected_size !== '') { ?>
                        &nbsp;•&nbsp; <span style="font-size:11px; opacity:0.8;"><?= $max_stock ?> in stock (Size <?= htmlspecialchars($selected_size) ?>)</span>
                    <?php } ?>
                </span>
            </div>
            <form method="post" action="actions/cart_update.php" style="display:flex; align-items:center; gap:0.5rem;">
                <input type="hidden" name="cart_key" value="<?= htmlspecialchars($cart_key) ?>">
                <input type="hidden" name="item_id" value="<?= $id ?>">
                <input type="hidden" name="size" value="<?= htmlspecialchars($selected_size) ?>">
                <input type="hidden" name="back" value="cart">
                <input class="qty" type="number" name="qty" min="0" max="<?= $max_stock ?>" value="<?= $qty ?>">
                <button class="btn ghost sm" type="submit" name="action" value="update">Update</button>
                <button class="btn ghost sm" type="submit" name="action" value="remove" style="color:#ff9f96; border-color:rgba(255,100,100,0.2);">Remove</button>
            </form>
        </div>
    <?php } } ?>
</section>

<?php if (count($cart) > 0) { ?>
<div class="total reveal">
    <div>
        <span class="muted" style="font-size:var(--text-label); letter-spacing:var(--tracking-label); text-transform:uppercase;">Order Total</span>
        <h2 style="font-size: 2rem; color:var(--color-accent-on-dark);"><?= peso($total) ?></h2>
    </div>
    <div style="display:flex; gap:1rem;">
        <a class="btn ghost" href="shop.php">Continue Shopping</a>
        <a class="btn" href="checkout.php">Proceed to Checkout →</a>
    </div>
</div>
<?php } ?>
<?php require 'includes/footer.php'; ?>
