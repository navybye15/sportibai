<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$base = '';
require_once 'includes/customer_guard.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// ── Fetch all orders for this customer ───────────────────────────────────────
$orders = array();
if ($conn != null) {
    $sql = "SELECT o.order_id, o.reference_number, o.order_type, o.total,
                   o.status, o.date_ordered, o.snapshot_name,
                   o.delivery_address, o.contact_number
            FROM orders o
            JOIN customer c ON o.customer_id = c.customer_id
            WHERE c.user_id = ?
            ORDER BY o.order_id DESC";
    $result = mysqli_execute_query($conn, $sql, array($_SESSION['user_id']));
    while ($row = mysqli_fetch_assoc($result)) {
        $orders[] = $row;
    }
}

// ── Fetch items for each order (one query, grouped by order_id) ──────────────
$order_items = array();
if ($conn != null && count($orders) > 0) {
    $ids = implode(',', array_column($orders, 'order_id'));
    $sql = "SELECT oi.order_id, i.item_id, i.name, i.image, oi.quantity, oi.price, c.name AS category
            FROM order_items oi
            JOIN items i ON oi.item_id = i.item_id
            JOIN categories c ON i.category_id = c.category_id
            WHERE oi.order_id IN ($ids)
            ORDER BY oi.order_item_id";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $order_items[$row['order_id']][] = $row;
    }
}

// ── Handle Review Submission from Orders page (MP4) ─────────────────────────
if (isset($_POST['submit_review'])) {
    if (!csrf_verify()) {
        header('Location: orders.php?tab=completed');
        exit;
    }
    $item_id = (int)($_POST['item_id'] ?? 0);
    $rating  = (int)($_POST['rating'] ?? 0);
    $comment = strip_tags(trim($_POST['comment'] ?? ''));

    if ($rating < 1 || $rating > 5) {
        $_SESSION['message'] = 'Please choose a star rating (1 to 5 stars).';
        header('Location: orders.php?tab=completed');
        exit;
    }

    // Verify user actually purchased and completed order with this item
    $verify_sql = "SELECT oi.order_item_id 
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.order_id
                   JOIN customer c ON o.customer_id = c.customer_id
                   WHERE c.user_id = ? AND oi.item_id = ? AND o.status = 'Completed'
                   LIMIT 1";
    $v_res = mysqli_execute_query($conn, $verify_sql, array($_SESSION['user_id'], $item_id));
    if (mysqli_num_rows($v_res) > 0) {
        $review_img = '';
        $upload_files = $_FILES['review_images'] ?? ($_FILES['review_image'] ?? null);
        if ($upload_files && (is_array($upload_files['error']) ? !empty(array_filter($upload_files['name'])) : $upload_files['error'] !== UPLOAD_ERR_NO_FILE)) {
            try {
                $review_img = handle_multiple_review_images($upload_files, 5);
            } catch (Exception $e) {
                $_SESSION['message'] = $e->getMessage();
                header('Location: orders.php?tab=completed');
                exit;
            }
        }

        if ($review_img !== '') {
            $ins_sql = "INSERT INTO reviews (item_id, user_id, rating, comment, review_image)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), review_image = VALUES(review_image), updated_at = NOW()";
            mysqli_execute_query($conn, $ins_sql, array($item_id, $_SESSION['user_id'], $rating, $comment, $review_img));
        } else {
            $ins_sql = "INSERT INTO reviews (item_id, user_id, rating, comment)
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = NOW()";
            mysqli_execute_query($conn, $ins_sql, array($item_id, $_SESSION['user_id'], $rating, $comment));
        }
        $_SESSION['success'] = 'Your review has been published! Thank you for sharing athlete feedback.';
    } else {
        $_SESSION['message'] = 'You can only review items from completed orders.';
    }
    header('Location: orders.php?tab=completed');
    exit;
}

// ── Fetch existing reviews by this customer ──────────────────────────────────
$user_reviews = array();
if ($conn != null) {
    $rev_res = mysqli_execute_query($conn, "SELECT item_id, rating, comment, review_image FROM reviews WHERE user_id = ?", array($_SESSION['user_id']));
    while ($rw = mysqli_fetch_assoc($rev_res)) {
        $user_reviews[$rw['item_id']] = $rw;
    }
}

// ── Tab counts ───────────────────────────────────────────────────────────────
$tab_counts = array('all' => 0, 'topay' => 0, 'toreceive' => 0, 'completed' => 0, 'cancelled' => 0);
foreach ($orders as $o) {
    $tab_counts['all']++;
    $t = status_to_tab($o['status']);
    if (isset($tab_counts[$t])) $tab_counts[$t]++;
}

// ── Active tab from URL ───────────────────────────────────────────────────────
$active_tab = 'all';
$valid_tabs = array('all', 'topay', 'toreceive', 'completed', 'cancelled');
if (isset($_GET['tab']) && in_array($_GET['tab'], $valid_tabs)) {
    $active_tab = $_GET['tab'];
}

$page_title = 'My Orders | Sportibai';
require 'includes/header.php';
?>

<section class="reveal">
    <div style="margin-bottom:1.5rem;">
        <div class="hero-eyebrow">[ HISTORY &amp; TRACKING ]</div>
        <h1 style="font-size:var(--text-display-l)">My Orders</h1>
    </div>
    <?php require 'includes/alert.php'; ?>

    <!-- Order Tabs -->
    <div class="order-tabs">
        <?php
        $tab_labels = array(
            'all'       => 'All',
            'topay'     => 'To Pay',
            'toreceive' => 'To Receive',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
        );
        foreach ($tab_labels as $key => $label) {
            $cls = ($active_tab === $key) ? 'order-tab active' : 'order-tab';
            $count = $tab_counts[$key];
        ?>
            <a href="orders.php?tab=<?= $key ?>" class="<?= $cls ?>">
                <?= $label ?>
                <?php if ($count > 0) { ?>
                    <span class="tab-badge"><?= $count ?></span>
                <?php } ?>
            </a>
        <?php } ?>
    </div>

    <!-- Orders List -->
    <?php
    $visible = array_filter($orders, function($o) use ($active_tab) {
        if ($active_tab === 'all') return true;
        return status_to_tab($o['status']) === $active_tab;
    });
    ?>

    <?php if (count($visible) === 0) { ?>
        <div class="order-empty reveal in">
            <div class="order-empty-icon">📦</div>
            <p>No orders in this tab yet.</p>
            <a class="btn" href="shop.php">Browse the Shop</a>
        </div>
    <?php } ?>

    <div class="order-list">
    <?php foreach ($visible as $o) {
        $ref = $o['reference_number'] ?? ('SB-' . str_pad($o['order_id'], 7, '0', STR_PAD_LEFT));
        $items = $order_items[$o['order_id']] ?? array();
        $item_count = count($items);
        $first_item = $items[0] ?? null;
        $more_count = $item_count > 1 ? ($item_count - 1) : 0;
        
        $can_cancel  = in_array($o['status'], array('Pending', 'Prepared'));
        $can_receive = ($o['status'] === 'Delivered');
    ?>
        <div class="order-card reveal" style="cursor:pointer;" onclick="if(!event.target.closest('button, form, a')) { window.location.href='order_detail.php?id=<?= $o['order_id'] ?>'; }">
            <!-- Card Header -->
            <div class="order-card-header">
                <div class="order-ref-row">
                    <span class="order-ref"><?= htmlspecialchars($ref) ?></span>
                    <span class="status-badge" style="<?= status_badge_style($o['status']) ?>">
                        <?= htmlspecialchars($o['status']) ?>
                    </span>
                </div>
                <div class="order-meta">
                    <?= date('M d, Y · g:i A', strtotime($o['date_ordered'])) ?>
                    &nbsp;·&nbsp;
                    <?= ucfirst($o['order_type']) ?>
                    &nbsp;·&nbsp;
                    <strong style="color:var(--color-fg); font-size:1.05rem;"><?= peso($o['total']) ?></strong>
                </div>
            </div>

            <!-- Single Item Preview Body (Shopee/Lazada style) -->
            <div class="order-card-body" style="padding:1.25rem 1.5rem; display:flex; gap:1.25rem; align-items:center;">
                <?php if ($first_item) { ?>
                    <div style="width:68px; height:68px; flex-shrink:0;">
                        <?php item_tile($first_item, ''); ?>
                    </div>
                    <div style="flex-grow:1; display:flex; flex-direction:column; gap:4px;">
                        <span style="font-weight:600; color:var(--color-fg);"><?= htmlspecialchars($first_item['name']) ?></span>
                        <span class="muted" style="font-size:var(--text-body-sm);">
                            Qty: <?= $first_item['quantity'] ?> &nbsp;•&nbsp; <?= peso($first_item['price']) ?>
                        </span>
                        <?php if ($more_count > 0) { ?>
                            <span class="brand-tag" style="font-size:11px; color:var(--color-accent-on-dark); font-weight:600;">
                                + <?= $more_count ?> more <?= $more_count === 1 ? 'item' : 'items' ?>
                            </span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>

            <!-- Actions -->
            <div class="order-card-actions">
                <a href="order_detail.php?id=<?= $o['order_id'] ?>" class="btn ghost sm">View Details</a>
                <a href="download_receipt.php?order_id=<?= $o['order_id'] ?>" target="_blank" class="btn ghost sm" style="font-size:11px;">📄 PDF Receipt</a>
                
                <?php if ($o['status'] === 'Completed') { 
                    $items_in_order = $order_items[$o['order_id']] ?? array();
                    $all_reviewed = true;
                    foreach ($items_in_order as $it_chk) {
                        if (!isset($user_reviews[$it_chk['item_id']])) {
                            $all_reviewed = false;
                            break;
                        }
                    }
                ?>
                    <button type="button" class="btn sm" onclick='openReviewModal(<?= htmlspecialchars(json_encode($items_in_order), ENT_QUOTES, "UTF-8") ?>)' style="background:rgba(245,158,11,0.2); color:#f59e0b; border:1px solid rgba(245,158,11,0.45); font-size:11px; box-shadow:0 0 10px rgba(245,158,11,0.2);">
                        <?= $all_reviewed ? '✓ Reviewed (Edit)' : '⭐ Review Gear' ?>
                    </button>
                <?php } ?>

                <?php if ($can_receive) { ?>
                    <form method="POST" action="receive_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn sm" type="submit"
                            onclick="return confirm('Confirm that you received this order?')"
                            style="background:rgba(81,175,67,0.85);box-shadow:0 2px 14px rgba(81,175,67,0.3);">
                            ✓ Order Received
                        </button>
                    </form>
                <?php } elseif ($o['status'] === 'Shipped' || $o['status'] === 'Out for Delivery' || $o['status'] === 'Ready for Pick Up') { ?>
                    <button class="btn ghost sm" disabled style="opacity:0.6; cursor:not-allowed;" title="Waiting for delivery confirmation">
                        Waiting for Delivery
                    </button>
                <?php } ?>
                <?php if ($can_cancel) { ?>
                    <form method="POST" action="cancel_order.php" style="display:inline;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order_id" value="<?= $o['order_id'] ?>">
                        <button class="btn ghost sm" type="submit"
                            onclick="return confirm('Cancel this order?')"
                            style="color:#ff9f96;border-color:rgba(255,100,80,0.3);">
                            Cancel
                        </button>
                    </form>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     INTERACTIVE 5-STAR REVIEW MODAL (MP4)
═══════════════════════════════════════════════════════════════════════════ -->
<div id="review-modal-backdrop" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); backdrop-filter:blur(6px); z-index:9999; align-items:center; justify-content:center; padding:1.25rem;">
    <div class="card in" style="max-width:520px; width:100%; max-height:90vh; overflow-y:auto; background:#12151c; border:1px solid rgba(255,255,255,0.15); border-radius:16px; padding:1.75rem; box-shadow:0 25px 60px rgba(0,0,0,0.8); position:relative;">
        <button type="button" onclick="closeReviewModal()" style="position:absolute; top:16px; right:16px; background:transparent; border:none; color:#888; font-size:1.5rem; cursor:pointer; line-height:1;">✕</button>

        <div>
            <div class="hero-eyebrow">[ ATHLETE FEEDBACK ]</div>
            <h2 style="font-size:1.35rem; margin-top:4px;">Rate &amp; Review Your Gear</h2>
            <p class="muted" style="font-size:13px; margin-top:2px;">Your feedback helps other athletes choose the right gear.</p>
        </div>

        <!-- Item Selector if order has multiple items -->
        <div id="modal-item-selector-container" style="display:none; margin-top:1rem;">
            <label class="form-label" style="font-size:11px;">Select Item to Review:</label>
            <select id="modal-item-dropdown" class="search" style="width:100%; margin-top:4px; padding:0.5rem 0.75rem; font-size:13px;" onchange="onSelectItemToReview(this.value)">
            </select>
        </div>

        <!-- Selected Item Preview Card -->
        <div style="display:flex; align-items:center; gap:12px; padding:0.75rem 1rem; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.08); border-radius:10px; margin-top:1.25rem;">
            <img id="modal-item-img" src="images/placeholder.svg" alt="" style="width:48px; height:48px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.1); flex-shrink:0;">
            <div>
                <div id="modal-item-name" style="font-weight:700; font-size:14px; color:var(--color-fg);"></div>
                <div id="modal-item-price" class="muted" style="font-size:12px; margin-top:2px;"></div>
            </div>
        </div>

        <form method="POST" action="orders.php" enctype="multipart/form-data" novalidate style="margin-top:1.25rem;">
            <?= csrf_field() ?>
            <input type="hidden" name="item_id" id="modal-hidden-item-id" value="">

            <!-- Interactive 5-Star Rating Widget (Required) -->
            <div style="margin-bottom:1.25rem;">
                <label class="form-label" style="display:block; margin-bottom:6px;">Your Rating <span style="color:#ff9f96;">*</span></label>
                <div id="modal-star-picker" style="display:inline-flex; gap:8px; cursor:pointer; font-size:2rem; user-select:none;">
                    <?php for ($s = 1; $s <= 5; $s++) { ?>
                        <span class="modal-star-btn" data-val="<?= $s ?>" style="color:#f59e0b; transition:color 0.15s, transform 0.15s; text-shadow:0 0 12px rgba(245,158,11,0.5);">★</span>
                    <?php } ?>
                </div>
                <input type="hidden" name="rating" id="modal-rating-input" value="5">
                <span id="modal-rating-label" class="muted" style="font-size:13px; margin-left:12px; font-weight:600; vertical-align:middle;">5 / 5 Stars</span>
            </div>

            <!-- Optional Review Text Comment -->
            <div style="margin-bottom:1.25rem;">
                <label class="form-label" style="display:block; margin-bottom:6px;">Review Comment <span class="muted" style="font-weight:400; font-size:11px;">(Optional)</span></label>
                <textarea name="comment" id="modal-comment-input" rows="3" placeholder="Share your experience: grip, bounce, durability, fit, packaging..." style="width:100%;"></textarea>
            </div>

            <!-- Optional Multi-Photo Upload (Up to 5 photos) -->
            <div style="margin-bottom:1.5rem;">
                <label class="form-label" style="display:block; margin-bottom:6px;">
                    Attach Product Photos <span class="muted" style="font-weight:400; font-size:11px;">(Optional — Up to 5 photos)</span>
                </label>
                <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                    <input type="file" name="review_images[]" id="modal_review_photo_input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;" onchange="handleModalReviewFileSelect(this)">
                    <button type="button" class="btn ghost sm" onclick="document.getElementById('modal_review_photo_input').click();">📷 Choose Photos (Max 5)</button>
                    <span id="modal_review_photo_name" class="muted" style="font-size:12px;"></span>
                </div>
                <small class="muted" style="font-size:11px; display:block; margin-top:4px;">JPG, PNG, or WEBP (Max 3MB each, up to 5 photos)</small>

                <div id="modal_review_photo_preview_box" style="display:flex; gap:8px; flex-wrap:wrap; margin-top:10px;"></div>
                <div id="modal_existing_photos_box" style="display:none; margin-top:10px; font-size:11px;" class="muted">
                    <span>Current photos:</span>
                    <div id="modal_existing_photos_strip" style="display:flex; gap:6px; margin-top:4px; flex-wrap:wrap;"></div>
                </div>
            </div>

            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                <button type="button" class="btn ghost sm" onclick="closeReviewModal()">Cancel</button>
                <button type="submit" name="submit_review" class="btn sm" style="background:#51af43; padding:0.65rem 1.5rem;">Publish Review</button>
            </div>
        </form>
    </div>
</div>

<script>
var currentOrderItems = [];
var userReviewsMap = <?= json_encode($user_reviews) ?>;

function openReviewModal(items) {
    if (!items || items.length === 0) return;
    currentOrderItems = items;

    var selectorContainer = document.getElementById('modal-item-selector-container');
    var dropdown = document.getElementById('modal-item-dropdown');

    if (items.length > 1) {
        selectorContainer.style.display = 'block';
        dropdown.innerHTML = '';
        items.forEach(function(it, idx) {
            var opt = document.createElement('option');
            opt.value = idx;
            opt.innerText = (it.quantity ? it.quantity + 'x ' : '') + it.name;
            dropdown.appendChild(opt);
        });
    } else {
        selectorContainer.style.display = 'none';
    }

    populateItemReview(items[0]);

    var backdrop = document.getElementById('review-modal-backdrop');
    backdrop.style.display = 'flex';
}

function onSelectItemToReview(index) {
    var it = currentOrderItems[parseInt(index)];
    if (it) populateItemReview(it);
}

function populateItemReview(item) {
    document.getElementById('modal-hidden-item-id').value = item.item_id;
    document.getElementById('modal-item-name').innerText = item.name;
    document.getElementById('modal-item-price').innerText = '₱' + parseFloat(item.price).toFixed(2);
    
    var imgSrc = item.image ? ('images/uploads/' + item.image) : 'images/placeholder.svg';
    document.getElementById('modal-item-img').src = imgSrc;

    // Pre-fill if already reviewed
    var existing = userReviewsMap[item.item_id];
    var rating = existing ? parseInt(existing.rating) : 5;
    var comment = existing ? (existing.comment || '') : '';

    document.getElementById('modal-rating-input').value = rating;
    document.getElementById('modal-comment-input').value = comment;
    document.getElementById('modal_review_photo_name').innerText = '';
    
    // Clear & setup photo previews
    var previewBox = document.getElementById('modal_review_photo_preview_box');
    if (previewBox) previewBox.innerHTML = '';
    var existingBox = document.getElementById('modal_existing_photos_box');
    var existingStrip = document.getElementById('modal_existing_photos_strip');
    if (existingBox && existingStrip) {
        existingStrip.innerHTML = '';
        if (existing && existing.review_image) {
            var raw = (existing.review_image || '').trim();
            var imgs = [];
            if (raw.startsWith('[') || raw.startsWith('{')) {
                try { imgs = JSON.parse(raw); } catch(e) { imgs = [raw]; }
            } else if (raw !== '') {
                imgs = [raw];
            }
            if (imgs && imgs.length > 0) {
                existingBox.style.display = 'block';
                imgs.forEach(function(imgName) {
                    var mThumb = document.createElement('img');
                    mThumb.src = 'images/uploads/' + imgName;
                    mThumb.style.width = '48px';
                    mThumb.style.height = '48px';
                    mThumb.style.objectFit = 'cover';
                    mThumb.style.borderRadius = '6px';
                    mThumb.style.border = '1px solid rgba(255,255,255,0.15)';
                    existingStrip.appendChild(mThumb);
                });
            } else {
                existingBox.style.display = 'none';
            }
        } else {
            existingBox.style.display = 'none';
        }
    }
    
    highlightModalStars(rating);
}

// Multi-photo selection and preview handler for modal
function handleModalReviewFileSelect(input) {
    var container = document.getElementById('modal_review_photo_preview_box');
    var label = document.getElementById('modal_review_photo_name');
    if (!container) return;
    container.innerHTML = '';

    var files = Array.from(input.files || []);
    if (files.length === 0) {
        if (label) label.innerText = '';
        return;
    }

    if (files.length > 5) {
        alert('You can upload a maximum of 5 photos. Showing the first 5 photos.');
        files = files.slice(0, 5);
    }

    if (label) {
        label.innerText = files.length + (files.length === 1 ? ' photo selected' : ' photos selected');
    }

    files.forEach(function(file) {
        if (!file.type.match('image.*')) return;
        var reader = new FileReader();
        reader.onload = function(e) {
            var thumb = document.createElement('div');
            thumb.style.position = 'relative';
            thumb.style.width = '52px';
            thumb.style.height = '52px';
            thumb.style.borderRadius = '6px';
            thumb.style.overflow = 'hidden';
            thumb.style.border = '1px solid rgba(255,255,255,0.2)';
            thumb.style.boxShadow = '0 2px 8px rgba(0,0,0,0.4)';

            var img = document.createElement('img');
            img.src = e.target.result;
            img.style.width = '100%';
            img.style.height = '100%';
            img.style.objectFit = 'cover';

            thumb.appendChild(img);
            container.appendChild(thumb);
        };
        reader.readAsDataURL(file);
    });
}

function closeReviewModal() {
    document.getElementById('review-modal-backdrop').style.display = 'none';
}

function highlightModalStars(val) {
    var stars = document.querySelectorAll('.modal-star-btn');
    var label = document.getElementById('modal-rating-label');
    stars.forEach(function(star, idx) {
        var sVal = idx + 1;
        if (sVal <= val) {
            star.style.color = '#f59e0b';
            star.style.textShadow = '0 0 12px rgba(245,158,11,0.55)';
            star.style.transform = 'scale(1.15)';
        } else {
            star.style.color = 'rgba(255,255,255,0.2)';
            star.style.textShadow = 'none';
            star.style.transform = 'scale(1)';
        }
    });
    if (label) label.innerText = val + ' / 5 Stars';
}

document.addEventListener('DOMContentLoaded', function() {
    var picker = document.getElementById('modal-star-picker');
    if (!picker) return;
    var stars = picker.querySelectorAll('.modal-star-btn');
    var input = document.getElementById('modal-rating-input');

    stars.forEach(function(star) {
        star.addEventListener('mouseenter', function() {
            var v = parseInt(this.getAttribute('data-val'));
            highlightModalStars(v);
        });
        star.addEventListener('click', function() {
            var v = parseInt(this.getAttribute('data-val'));
            input.value = v;
            highlightModalStars(v);
        });
    });

    picker.addEventListener('mouseleave', function() {
        var lockedVal = parseInt(input.value) || 5;
        highlightModalStars(lockedVal);
    });

    // Auto-open review modal if order just marked received
    var urlParams = new URLSearchParams(window.location.search);
    var autoReviewOrderId = urlParams.get('open_review');
    if (autoReviewOrderId) {
        var allOrderItems = <?= json_encode($order_items) ?>;
        if (allOrderItems[autoReviewOrderId]) {
            openReviewModal(allOrderItems[autoReviewOrderId]);
        }
    }
});
</script>
<?php require 'includes/footer.php'; ?>
