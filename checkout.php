<?php
if (session_status() == PHP_SESSION_NONE) session_start();
require 'includes/customer_guard.php';
require 'includes/db.php';
require 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}
if ($conn == null) {
    $_SESSION['message'] = 'Database is not connected yet.';
    header('Location: view_cart.php');
    exit;
}

// ── Must have a customer profile ──────────────────────────────────────────────
$sql = "SELECT customer_id, first_name, last_name, phone, address,
               province, postal_code, landmark
        FROM customer WHERE user_id = ? LIMIT 1";
$result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
if (mysqli_num_rows($result) != 1) {
    $_SESSION['message'] = 'Please complete your profile before checking out.';
    header('Location: profile.php');
    exit;
}
$customer    = mysqli_fetch_assoc($result);
$customer_id = $customer['customer_id'];

// ── Must have cart items ───────────────────────────────────────────────────────
$cart = isset($_SESSION['cart']) ? $_SESSION['cart'] : array();
if (count($cart) === 0) {
    header('Location: view_cart.php');
    exit;
}

// ── Handle POST: Place Order ───────────────────────────────────────────────────
if (isset($_POST['place_order'])) {
    if (!csrf_verify()) {
        header('Location: checkout.php');
        exit;
    }

    $order_type      = $_POST['order_type'] ?? 'delivery';
    $contact_number  = strip_tags(trim($_POST['contact_number'] ?? ''));
    $note            = strip_tags(trim($_POST['note'] ?? ''));
    $save_to_profile = isset($_POST['save_to_profile']);

    // Build the delivery address from the posted fields
    $street    = strip_tags(trim($_POST['street'] ?? ''));
    $city      = strip_tags(trim($_POST['city'] ?? ''));
    $province  = strip_tags(trim($_POST['province'] ?? ''));
    $postal    = strip_tags(trim($_POST['postal_code'] ?? ''));

    $delivery_address = null;
    if ($order_type === 'delivery') {
        $parts = array_filter(array($street, $city, $province, $postal ? 'Postal: ' . $postal : ''));
        $delivery_address = implode(', ', $parts);
        if ($note !== '') $delivery_address .= "\nNote: " . $note;
    }

    // Validate
    if ($order_type !== 'pickup' && $order_type !== 'delivery') {
        $_SESSION['message'] = 'Please choose Pickup or Delivery.';
        header('Location: checkout.php'); exit;
    }
    if ($order_type === 'delivery' && trim($street) === '') {
        $_SESSION['message'] = 'Please provide your street / barangay address.';
        header('Location: checkout.php'); exit;
    }
    if ($contact_number === '') {
        $_SESSION['message'] = 'Please provide a contact number.';
        header('Location: checkout.php'); exit;
    }
    if (!preg_match('/^09\d{9}$/', $contact_number)) {
        $_SESSION['message'] = 'Please provide a valid 11-digit mobile number (e.g. 09171234567).';
        header('Location: checkout.php'); exit;
    }

    // Optional: Update customer profile if requested
    if ($save_to_profile && $conn != null) {
        $up_addr = ($order_type === 'delivery') ? $street : $customer['address'];
        $up_sql  = "UPDATE customer SET phone = ?, address = ?, province = ?, postal_code = ?, landmark = ? WHERE customer_id = ?";
        @mysqli_execute_query($conn, $up_sql, array($contact_number, $up_addr, $province, $postal, $note, $customer_id));
    }

    try {
        mysqli_begin_transaction($conn);

        // Re-check prices and stock from DB — never trust the cart
        $total      = 0;
        $line_items = array();
        foreach ($cart as $cart_key => $qty) {
            if ($qty < 1) throw new Exception('An item in your cart has an invalid quantity.');
            $parts = explode('_', (string)$cart_key, 2);
            $item_id = (int)$parts[0];
            $selected_size = $parts[1] ?? '';

            $sql    = "SELECT item_id, name, price, stock FROM items WHERE item_id = ? AND is_active = 1 LIMIT 1";
            $result = mysqli_execute_query($conn, $sql, array($item_id));
            $item   = mysqli_fetch_assoc($result);
            if ($item === null) throw new Exception('An item in your cart is no longer available.');

            if ($selected_size !== '') {
                $size_stock = get_item_size_stock($conn, $item_id, $selected_size);
                if ($qty > $size_stock) {
                    throw new Exception('Not enough stock left for ' . $item['name'] . ' (Size: ' . $selected_size . '). Available: ' . $size_stock);
                }

                // Deduct from item_sizes
                $size_sql = "UPDATE item_sizes SET stock = stock - ? WHERE item_id = ? AND size_label = ? AND stock >= ?";
                mysqli_execute_query($conn, $size_sql, array($qty, $item_id, $selected_size, $qty));
                if (mysqli_affected_rows($conn) !== 1) {
                    throw new Exception('Could not update size stock for ' . $item['name'] . ' (Size: ' . $selected_size . ').');
                }
            }

            // Deduct from items overall stock
            $sql = "UPDATE items SET stock = stock - ? WHERE item_id = ? AND stock >= ?";
            mysqli_execute_query($conn, $sql, array($qty, $item_id, $qty));
            if (mysqli_affected_rows($conn) !== 1) {
                throw new Exception('Not enough stock left for ' . $item['name'] . '.');
            }

            $subtotal     = $item['price'] * $qty;
            $total       += $subtotal;
            $line_items[] = array('item_id' => $item_id, 'selected_size' => $selected_size, 'qty' => $qty, 'price' => $item['price']);
        }
        $recipient_name = strip_tags(trim($_POST['recipient_name'] ?? ''));
        if ($recipient_name === '') {
            $recipient_name = trim($customer['first_name'] . ' ' . $customer['last_name']);
        }
        $snapshot_name = $recipient_name ?: 'Customer';

        // Insert order row
        $sql = "INSERT INTO orders
                    (customer_id, order_type, delivery_address, contact_number,
                     snapshot_name, payment_method, total, status)
                VALUES (?, ?, ?, ?, ?, 'COD', ?, 'Pending')";
        mysqli_execute_query($conn, $sql, array(
            $customer_id, $order_type, $delivery_address,
            $contact_number, $snapshot_name, $total
        ));
        $order_id = mysqli_insert_id($conn);

        // Generate & save reference number
        $ref_number = generate_ref($order_id);
        $sql = "UPDATE orders SET reference_number = ? WHERE order_id = ?";
        mysqli_execute_query($conn, $sql, array($ref_number, $order_id));

        // Insert order items with selected size
        foreach ($line_items as $line) {
            $sql = "INSERT INTO order_items (order_id, item_id, selected_size, quantity, price) VALUES (?, ?, ?, ?, ?)";
            $sz_val = $line['selected_size'] !== '' ? $line['selected_size'] : null;
            mysqli_execute_query($conn, $sql, array($order_id, $line['item_id'], $sz_val, $line['qty'], $line['price']));
        }

        // Log the initial Pending status
        $sql = "INSERT INTO order_status_log (order_id, status, changed_by) VALUES (?, 'Pending', 'customer')";
        mysqli_execute_query($conn, $sql, array($order_id));

        mysqli_commit($conn);

        // Send order confirmation email via PHPMailer
        require_once 'includes/mailer.php';
        @send_order_confirmation_email($order_id, $conn);

        unset($_SESSION['cart']);
        $_SESSION['success'] = 'Thank you! Your order ' . $ref_number . ' has been placed.';
        header('Location: orders.php');
        exit;
    } catch (mysqli_sql_exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = 'Something went wrong while placing your order. Please try again.';
        header('Location: checkout.php'); exit;
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = $e->getMessage();
        header('Location: checkout.php'); exit;
    }
}

// ── Build cart lines for display ──────────────────────────────────────────────
$total = 0;
$lines = array();
foreach ($cart as $cart_key => $qty) {
    $parts = explode('_', (string)$cart_key, 2);
    $item_id = (int)$parts[0];
    $selected_size = $parts[1] ?? '';

    $item = find_item($conn, $item_id);
    if ($item !== null) {
        $subtotal = $item['price'] * $qty;
        $total   += $subtotal;
        $lines[]  = array('item' => $item, 'selected_size' => $selected_size, 'qty' => $qty, 'subtotal' => $subtotal);
    }
}

$base = '';
$page_title = 'Checkout | Sportibai';
require 'includes/header.php';
?>

<nav class="res-crumbs">
    <a href="index.php">Home</a>
    <span class="sep">/</span>
    <a href="view_cart.php">Cart</a>
    <span class="sep">/</span>
    <span>Checkout</span>
</nav>

<?php require 'includes/alert.php'; ?>

<section class="reveal in">
    <div class="hero-eyebrow">[ PLACE YOUR ORDER ]</div>
    <h1 style="font-size:var(--text-display-m); margin:6px 0 2rem;">Checkout</h1>

    <form method="POST" action="checkout.php">
        <?= csrf_field() ?>
        <div class="checkout-grid">

            <!-- ── LEFT COLUMN ─────────────────────────────────────────── -->
            <div class="checkout-left">

                <!-- FULFILLMENT METHOD (Shopee segmented switch at very top) -->
                <div class="checkout-block">
                    <div class="checkout-block-title">Fulfillment Method</div>
                    <div class="fulfillment-options">
                        <label class="fulfillment-option" id="opt-delivery">
                            <input type="radio" name="order_type" value="delivery"
                                onchange="toggleDelivery(this)"
                                <?= (!isset($_POST['order_type']) || $_POST['order_type']==='delivery') ? 'checked' : '' ?>>
                            <div class="fulfillment-icon">🚚</div>
                            <div>
                                <strong>Delivery</strong>
                                <p class="muted" style="font-size:12px; margin-top:2px;">Delivered to your doorstep.</p>
                            </div>
                        </label>
                        <label class="fulfillment-option" id="opt-pickup">
                            <input type="radio" name="order_type" value="pickup"
                                onchange="toggleDelivery(this)"
                                <?= (isset($_POST['order_type']) && $_POST['order_type']==='pickup') ? 'checked' : '' ?>>
                            <div class="fulfillment-icon">🏪</div>
                            <div>
                                <strong>Store Pick Up</strong>
                                <p class="muted" style="font-size:12px; margin-top:2px;">Pick up at our store (Free).</p>
                            </div>
                        </label>
                    </div>

                    <!-- Pickup note notice -->
                    <div id="pickup-note" style="display:none; margin-top:1.25rem; padding:1rem 1.25rem; border-radius:var(--radius-lg); background:rgba(0, 74, 172, 0.15); border:var(--hairline) solid rgba(111, 155, 224, 0.3);">
                        <strong style="color:var(--color-fg); font-size:13px;">📍 Pick Up Location &amp; Hours:</strong>
                        <p class="muted" style="font-size:12px; margin:4px 0 0; line-height:1.5;">
                            Sportibai Hub, Ground Floor, Central Mall, Cebu City<br>
                            Open Monday to Saturday: 9:00 AM – 7:00 PM. Please bring your Reference Number.
                        </p>
                    </div>
                </div>

                <!-- DELIVERY ADDRESS (hidden for pickup) -->
                <div class="checkout-block" id="delivery-fields"
                    style="display:<?= (!isset($_POST['order_type']) || $_POST['order_type']==='delivery') ? 'block' : 'none' ?>;">
                    <div class="checkout-block-title">Delivery Address</div>

                    <div class="address-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <span class="muted" style="font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:1px;">
                                Delivery Details
                            </span>
                            <a href="profile.php?return=checkout" class="btn ghost sm" style="font-size:10px; padding:4px 10px;">Edit Saved Profile</a>
                        </div>
                        <div style="margin-bottom:1rem;">
                            <label class="form-label" style="margin-bottom:6px; display:block;">Recipient Name</label>
                            <input type="text" name="recipient_name" class="co-input"
                                placeholder="Full Name of receiver"
                                value="<?= htmlspecialchars($_POST['recipient_name'] ?? ($customer['first_name'] . ' ' . $customer['last_name'])) ?>">
                            <small class="muted" style="font-size:11px; display:block; margin-top:3px;">You can set a different recipient name if this order is for someone else.</small>
                        </div>
                        <div class="co-form-row">
                            <div>
                                <label class="form-label" style="margin-bottom:6px; display:block;">Street / Barangay</label>
                                <input type="text" name="street" class="co-input"
                                    placeholder="123 Mabini St., Brgy. Lahug"
                                    value="<?= htmlspecialchars($_POST['street'] ?? $customer['address']) ?>">
                            </div>
                            <div>
                                <label class="form-label" style="margin-bottom:6px; display:block;">City / Municipality</label>
                                <input type="text" name="city" class="co-input"
                                    placeholder="Cebu City"
                                    value="<?= htmlspecialchars($_POST['city'] ?? 'Cebu City') ?>">
                            </div>
                        </div>
                        <div class="co-form-row">
                            <div>
                                <label class="form-label" style="margin-bottom:6px; display:block;">Province</label>
                                <input type="text" name="province" class="co-input"
                                    placeholder="Cebu"
                                    value="<?= htmlspecialchars($customer['province']) ?>">
                            </div>
                            <div>
                                <label class="form-label" style="margin-bottom:6px; display:block;">Postal Code</label>
                                <input type="text" name="postal_code" class="co-input"
                                    placeholder="6000"
                                    value="<?= htmlspecialchars($customer['postal_code']) ?>">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:1rem;">
                        <label class="form-label" style="margin-bottom:6px; display:block;">Landmark / Delivery Note (optional)</label>
                        <textarea name="note" class="co-input" rows="2"
                            placeholder="e.g. Near the blue gate, call when near"
                            style="resize:vertical;"><?= htmlspecialchars($customer['landmark']) ?></textarea>
                    </div>

                    <div style="margin-top:1rem; display:flex; align-items:center; gap:8px;">
                        <input type="checkbox" id="save_profile" name="save_to_profile" value="1" style="width:auto; cursor:pointer;" checked>
                        <label for="save_profile" style="font-size:12px; color:var(--color-fg-secondary); cursor:pointer;">
                            I-save sa profile ko para sa susunod na order
                        </label>
                    </div>
                </div>

                <!-- CONTACT NUMBER -->
                <div class="checkout-block">
                    <div class="checkout-block-title">Contact Number</div>
                    <input type="tel" name="contact_number" class="co-input"
                        placeholder="09171234567"
                        pattern="09[0-9]{9}" maxlength="11" title="Please enter an 11-digit mobile number starting with 09"
                        value="<?= htmlspecialchars($customer['phone']) ?>" required>
                </div>

                <!-- PAYMENT -->
                <div class="checkout-block">
                    <div class="checkout-block-title">Payment Method</div>
                    <div class="cod-badge">
                        <span style="font-size:1.25rem;">💵</span>
                        <div>
                            <strong>Cash on Delivery (COD)</strong>
                            <p class="muted" style="font-size:12px; margin-top:2px;">Pay in cash when you receive your order.</p>
                        </div>
                    </div>
                </div>

            </div><!-- /checkout-left -->

            <!-- ── RIGHT COLUMN: Order Summary ─────────────────────────── -->
            <div class="checkout-right">
                <div class="checkout-summary-card">
                    <div class="checkout-block-title" style="margin-bottom:1.25rem;">Order Summary</div>

                    <!-- Items list -->
                    <?php foreach ($lines as $line) { ?>
                        <div class="co-item-row">
                            <?php item_tile($line['item'], ''); ?>
                            <div class="co-item-info">
                                <span style="font-weight:600; line-height:1.3;">
                                    <?= htmlspecialchars($line['item']['name']) ?>
                                    <?php if (!empty($line['selected_size'])) { ?>
                                        <span class="badge" style="background:rgba(255,255,255,0.08); color:var(--color-fg); border:1px solid rgba(255,255,255,0.2); font-size:10px; padding:1px 6px; margin-left:4px;">
                                            Size: <?= htmlspecialchars($line['selected_size']) ?>
                                        </span>
                                    <?php } ?>
                                </span>
                                <span class="muted" style="font-size:var(--text-body-sm);">Qty: <?= $line['qty'] ?></span>
                            </div>
                            <span style="font-weight:700; color:var(--color-fg); white-space:nowrap;">
                                <?= peso($line['subtotal']) ?>
                            </span>
                        </div>
                    <?php } ?>

                    <div class="co-summary-divider"></div>

                    <!-- Totals -->
                    <div class="co-summary-row">
                        <span class="muted">Subtotal</span>
                        <span><?= peso($total) ?></span>
                    </div>
                    <div class="co-summary-row">
                        <span class="muted">Shipping</span>
                        <span class="muted">Calculated by seller</span>
                    </div>
                    <div class="co-summary-row co-grand-total">
                        <span>Total</span>
                        <span style="color:var(--color-accent-on-dark);"><?= peso($total) ?></span>
                    </div>

                    <button class="btn" style="width:100%; margin-top:1.5rem; padding:1rem;" type="submit" name="place_order">
                        Place Order →
                    </button>
                    <p class="muted" style="font-size:11px; text-align:center; margin-top:0.75rem; line-height:1.6;">
                        By placing your order you agree to pay in cash upon delivery or pickup.
                    </p>
                </div>
            </div>

        </div><!-- /checkout-grid -->
    </form>
</section>

<script>
function toggleDelivery(radio) {
    const df = document.getElementById('delivery-fields');
    const pn = document.getElementById('pickup-note');
    const isDelivery = radio.value === 'delivery';
    
    if (df) df.style.display = isDelivery ? 'block' : 'none';
    if (pn) pn.style.display = isDelivery ? 'none' : 'block';
    
    // Remove required from address fields when pickup is chosen
    if (df) {
        df.querySelectorAll('input[name="street"], input[name="city"]').forEach(el => {
            el.required = isDelivery;
        });
    }
}
// Set initial required state on page load
document.addEventListener('DOMContentLoaded', function() {
    const checked = document.querySelector('input[name="order_type"]:checked');
    if (checked) toggleDelivery(checked);
});
</script>

<?php require 'includes/footer.php'; ?>
