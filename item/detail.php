<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
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
$item_sizes = get_item_sizes($conn, $id);
$has_sizes = count($item_sizes) > 0;

// ── Review Submission / Update (MP4 Requirement: Review CRUD) ───────────────
if (isset($_POST['submit_review'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = 'Please log in to submit a review.';
        header("Location: detail.php?id=$id");
        exit;
    }
    if (!csrf_verify()) {
        $_SESSION['message'] = 'Security verification failed.';
        header("Location: detail.php?id=$id");
        exit;
    }

    $rating = (int)($_POST['rating'] ?? 0);
    if ($rating < 1 || $rating > 5) {
        $_SESSION['message'] = 'Please choose a star rating (1 to 5 stars).';
        header("Location: detail.php?id=$id#reviews");
        exit;
    }
    $comment = strip_tags(trim($_POST['comment'] ?? ''));

    // Optional review photos upload (Up to 5 photos)
    $review_image = '';
    $upload_files = $_FILES['review_images'] ?? ($_FILES['review_image'] ?? null);
    if ($upload_files && (is_array($upload_files['error']) ? !empty(array_filter($upload_files['name'])) : $upload_files['error'] !== UPLOAD_ERR_NO_FILE)) {
        try {
            $review_image = handle_multiple_review_images($upload_files, 5);
        } catch (Exception $e) {
            $_SESSION['message'] = $e->getMessage();
            header("Location: detail.php?id=$id#reviews");
            exit;
        }
    }

    // Eligibility check: Customer can only review a product they availed (order status = Completed)
    $verify_sql = "SELECT oi.order_item_id 
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.order_id
                   JOIN customer c ON o.customer_id = c.customer_id
                   WHERE c.user_id = ? AND oi.item_id = ? AND o.status = 'Completed'
                   LIMIT 1";
    $v_res = mysqli_execute_query($conn, $verify_sql, array($_SESSION['user_id'], $id));
    if (mysqli_num_rows($v_res) == 0) {
        $_SESSION['message'] = 'Only customers who have completed an order for this item may submit a review.';
        header("Location: detail.php?id=$id");
        exit;
    }

    // MP4: User can update their review (ON DUPLICATE KEY UPDATE)
    if ($review_image !== '') {
        $review_sql = "INSERT INTO reviews (item_id, user_id, rating, comment, review_image) 
                       VALUES (?, ?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), review_image = VALUES(review_image), updated_at = CURRENT_TIMESTAMP";
        mysqli_execute_query($conn, $review_sql, array($id, $_SESSION['user_id'], $rating, $comment, $review_image));
    } else {
        $review_sql = "INSERT INTO reviews (item_id, user_id, rating, comment) 
                       VALUES (?, ?, ?, ?)
                       ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), updated_at = CURRENT_TIMESTAMP";
        mysqli_execute_query($conn, $review_sql, array($id, $_SESSION['user_id'], $rating, $comment));
    }

    $_SESSION['success'] = 'Your review has been published. Thank you!';
    header("Location: detail.php?id=$id#reviews");
    exit;
}

// Check if current user is eligible to review and if they already have one
$user_can_review = false;
$user_existing_review = null;
if (isset($_SESSION['user_id']) && $conn != null) {
    $verify_sql = "SELECT oi.order_item_id 
                   FROM order_items oi
                   JOIN orders o ON oi.order_id = o.order_id
                   JOIN customer c ON o.customer_id = c.customer_id
                   WHERE c.user_id = ? AND oi.item_id = ? AND o.status = 'Completed'
                   LIMIT 1";
    $v_res = mysqli_execute_query($conn, $verify_sql, array($_SESSION['user_id'], $id));
    if (mysqli_num_rows($v_res) > 0) {
        $user_can_review = true;
    }

    $ex_res = mysqli_execute_query($conn, "SELECT * FROM reviews WHERE item_id = ? AND user_id = ? LIMIT 1", array($id, $_SESSION['user_id']));
    $user_existing_review = mysqli_fetch_assoc($ex_res);
}

// ── Fetch Multi-photos (MP1 Requirement: Multiple Photos) ───────────────────
$all_photos = array();
if (!empty($item['image'])) {
    $all_photos[] = $item['image'];
}
if ($conn != null) {
    $g_res = mysqli_execute_query($conn, "SELECT image_path FROM item_images WHERE item_id = ? ORDER BY sort_order ASC, image_id ASC", array($id));
    while ($g = mysqli_fetch_assoc($g_res)) {
        if (!empty($g['image_path'])) {
            $all_photos[] = $g['image_path'];
        }
    }
}

// ── Fetch Reviews using MySQL VIEW (Unit 1 & MP4) ───────────────────────────
$reviews = array();
$avg_rating = 0;
$total_reviews = 0;
if ($conn != null) {
    $rev_sql = "SELECT * FROM vw_item_reviews WHERE item_id = ? ORDER BY created_at DESC";
    $rev_res = mysqli_execute_query($conn, $rev_sql, array($id));
    $sum_stars = 0;
    while ($rw = mysqli_fetch_assoc($rev_res)) {
        $reviews[] = $rw;
        $sum_stars += (int)$rw['rating'];
    }
    $total_reviews = count($reviews);
    if ($total_reviews > 0) {
        $avg_rating = round($sum_stars / $total_reviews, 1);
    }
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
    <!-- MP1: Multiple Photo Viewer / Interactive Gallery -->
    <div class="reveal">
        <?php if (count($all_photos) > 0) { ?>
            <div class="item-gallery-container" style="display:flex; flex-direction:column; gap:12px;">
                <!-- Main Featured Photo -->
                <div style="width:100%; aspect-ratio:1/1; background:rgba(255,255,255,0.03); border:1px solid rgba(255,255,255,0.1); border-radius:14px; overflow:hidden; display:flex; align-items:center; justify-content:center;">
                    <img id="main-product-img" src="../images/uploads/<?= htmlspecialchars($all_photos[0]) ?>" alt="<?= htmlspecialchars($item['name']) ?>" style="width:100%; height:100%; object-fit:cover; transition:opacity 0.25s ease;">
                </div>

                <!-- Thumbnail Gallery Strip -->
                <?php if (count($all_photos) > 1) { ?>
                    <div style="display:flex; gap:10px; overflow-x:auto; padding-bottom:6px;">
                        <?php foreach ($all_photos as $idx => $photo) { ?>
                            <button type="button" onclick="var m = document.getElementById('main-product-img'); m.style.opacity='0'; setTimeout(function(){ m.src='../images/uploads/<?= htmlspecialchars($photo) ?>'; m.style.opacity='1'; }, 150);" style="background:transparent; border:1px solid rgba(255,255,255,0.2); border-radius:8px; padding:2px; cursor:pointer; width:64px; height:64px; flex-shrink:0; overflow:hidden;">
                                <img src="../images/uploads/<?= htmlspecialchars($photo) ?>" alt="Angle <?= $idx+1 ?>" style="width:100%; height:100%; object-fit:cover; border-radius:6px;">
                            </button>
                        <?php } ?>
                    </div>
                    <small class="muted" style="font-size:11px;">💡 Click any thumbnail to switch angles</small>
                <?php } ?>
            </div>
        <?php } else { ?>
            <?php item_tile($item, '../'); ?>
        <?php } ?>
    </div>

    <!-- Product Info Stack -->
    <div class="stack reveal">
        <span class="brand-tag" style="font-size:var(--text-label); letter-spacing:var(--tracking-label-wide); text-transform:uppercase; color:var(--color-accent-on-dark); font-weight:600;">
            <?= htmlspecialchars($item['brand'] != '' ? $item['brand'] : $item['category']) ?>
        </span>
        <h1 style="font-size: var(--text-display-l); line-height: 1.1;"><?= htmlspecialchars($item['name']) ?></h1>
        
        <!-- Ratings summary banner -->
        <div style="display:flex; align-items:center; gap:8px; margin-top:2px;">
            <span style="color:#f59e0b; font-size:16px;">
                <?php
                $full = floor($avg_rating);
                for ($s = 1; $s <= 5; $s++) {
                    echo ($s <= $full) ? '★' : '☆';
                }
                ?>
            </span>
            <span style="font-weight:600; font-size:14px;"><?= $avg_rating > 0 ? $avg_rating : 'New' ?></span>
            <span class="muted" style="font-size:12px;">(<?= $total_reviews ?> <?= $total_reviews == 1 ? 'review' : 'reviews' ?>)</span>
            <a href="#reviews" style="font-size:12px; color:var(--color-accent-on-dark); margin-left:6px;">View Reviews ↓</a>
        </div>

        <div class="price" style="margin-top:0.5rem;"><?= peso($item['price']) ?></div>
        
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
            <form method="post" action="../cart_update.php" id="add-to-cart-form" style="margin-top:1rem;">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                <input type="hidden" name="size" id="selected-size-input" value="">
                <input type="hidden" name="return_url" value="item/detail.php?id=<?= $item['item_id'] ?>">

                <?php if ($has_sizes) { ?>
                    <div class="size-selector-block">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
                            <label style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.08em; color:var(--color-fg); display:flex; align-items:center; gap:6px;">
                                <span>Select Size</span>
                                <span style="color:#ff5a3c;">*</span>
                            </label>
                            <span id="selected-size-status" class="muted" style="font-size:12px; font-weight:600;">Choose a size</span>
                        </div>
                        
                        <div class="size-pill-grid" id="size-pill-grid">
                            <?php foreach ($item_sizes as $sz) { 
                                $s_label = htmlspecialchars($sz['size_label']);
                                $s_stock = (int)$sz['stock'];
                                $is_out = ($s_stock <= 0);
                            ?>
                                <button type="button" 
                                        class="size-pill <?= $is_out ? 'disabled' : '' ?>"
                                        data-size="<?= $s_label ?>"
                                        data-stock="<?= $s_stock ?>"
                                        <?= $is_out ? 'disabled' : '' ?>
                                        title="<?= $is_out ? 'Out of stock' : ($s_stock . ' in stock') ?>">
                                    <span class="size-text"><?= $s_label ?></span>
                                    <?php if ($is_out) { ?>
                                        <span style="font-size:10px; opacity:0.6; margin-left:2px;">(0)</span>
                                    <?php } ?>
                                </button>
                            <?php } ?>
                        </div>
                        <div id="size-error-notice" style="display:none; color:#ff9f96; font-size:12px; margin-top:8px; font-weight:600;">
                            ⚠️ Please pick a size before adding to cart.
                        </div>
                    </div>
                <?php } ?>

                <button class="btn" id="add-to-cart-submit" style="width:100%" type="submit">Add to cart</button>
            </form>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var form = document.getElementById('add-to-cart-form');
                var sizeInput = document.getElementById('selected-size-input');
                var sizePills = document.querySelectorAll('.size-pill');
                var statusLabel = document.getElementById('selected-size-status');
                var errorNotice = document.getElementById('size-error-notice');
                var hasSizes = <?= $has_sizes ? 'true' : 'false' ?>;

                sizePills.forEach(function(pill) {
                    pill.addEventListener('click', function() {
                        if (this.disabled || this.classList.contains('disabled')) return;
                        sizePills.forEach(function(p) { p.classList.remove('active'); });
                        this.classList.add('active');
                        var sz = this.getAttribute('data-size');
                        var stk = this.getAttribute('data-stock');
                        if (sizeInput) sizeInput.value = sz;
                        if (statusLabel) {
                            statusLabel.innerHTML = '<strong style="color:var(--color-fg); font-size:13px;">Size ' + sz + '</strong> <span style="opacity:0.75; font-size:11px;">(' + stk + ' left)</span>';
                        }
                        if (errorNotice) errorNotice.style.display = 'none';
                    });
                });

                if (form && hasSizes) {
                    form.addEventListener('submit', function(e) {
                        if (!sizeInput || sizeInput.value.trim() === '') {
                            e.preventDefault();
                            if (errorNotice) errorNotice.style.display = 'block';
                            var grid = document.getElementById('size-pill-grid');
                            if (grid) {
                                grid.style.transform = 'translateX(-5px)';
                                setTimeout(function() { grid.style.transform = 'translateX(5px)'; }, 90);
                                setTimeout(function() { grid.style.transform = 'translateX(-3px)'; }, 180);
                                setTimeout(function() { grid.style.transform = 'translateX(0)'; }, 270);
                            }
                        }
                    });
                }
            });
            </script>
        <?php } ?>

        <div style="margin-top: 0.5rem;">
            <a class="btn ghost sm" href="../shop.php">← Back to shelf</a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     MP4: CUSTOMER REVIEWS & RATINGS SECTION
═══════════════════════════════════════════════════════════════════════════ -->
<section id="reviews" class="reveal in" style="margin-top:3.5rem; padding-top:2.5rem; border-top:1px solid rgba(255,255,255,0.08);">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; flex-wrap:wrap; gap:1rem; margin-bottom:1.75rem;">
        <div>
            <div class="hero-eyebrow">[ ATHLETE FEEDBACK ]</div>
            <h2 style="font-size:var(--text-display-m); margin-top:4px;">Customer Reviews</h2>
            <p class="muted" style="font-size:13px; margin-top:2px;">Real feedback from athletes who purchased and used this gear.</p>
        </div>
        <div style="text-align:right;">
            <div style="font-size:2rem; font-weight:700; color:#f59e0b; line-height:1;">
                <?= $avg_rating > 0 ? $avg_rating : '—' ?> <span style="font-size:1rem; color:inherit;">/ 5.0</span>
            </div>
            <div class="muted" style="font-size:12px; margin-top:4px;"><?= $total_reviews ?> Verified Reviews</div>
        </div>
    </div>

    <!-- Review Form for eligible users (Purchased + Completed order) -->
    <?php if ($user_can_review) { 
        $cur_rating = (int)($user_existing_review['rating'] ?? 5);
    ?>
        <div class="card in" style="padding:1.5rem; margin-bottom:2rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.12); border-radius:12px;">
            <h3 style="font-size:1.05rem; margin-bottom:0.75rem;">
                <?= $user_existing_review ? '✏️ Edit Your Athlete Review' : '⭐ Write a Review for this Gear' ?>
            </h3>
            <form method="POST" action="detail.php?id=<?= $id ?>" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                
                <!-- Interactive 5-Star Rating Widget -->
                <div style="margin-bottom:1.25rem;">
                    <label class="form-label" style="display:block; margin-bottom:6px;">Your Rating <span style="color:#ff9f96;">*</span></label>
                    <div id="star-picker" style="display:inline-flex; gap:8px; cursor:pointer; font-size:1.85rem; user-select:none;">
                        <?php for ($s = 1; $s <= 5; $s++) { ?>
                            <span class="star-btn" data-val="<?= $s ?>" style="color:<?= $s <= $cur_rating ? '#f59e0b' : 'rgba(255,255,255,0.2)' ?>; transition:color 0.15s, transform 0.15s; text-shadow:<?= $s <= $cur_rating ? '0 0 12px rgba(245,158,11,0.5)' : 'none' ?>;">★</span>
                        <?php } ?>
                    </div>
                    <input type="hidden" name="rating" id="review_rating_input" value="<?= $cur_rating ?>">
                    <span id="rating-label" class="muted" style="font-size:13px; margin-left:12px; font-weight:600; vertical-align:middle;">
                        <?= $cur_rating ?> / 5 Stars
                    </span>
                </div>

                <div style="margin-bottom:1.25rem;">
                    <label class="form-label" style="display:block; margin-bottom:6px;">Review Comment <span class="muted" style="font-weight:400; font-size:11px;">(Optional)</span></label>
                    <textarea name="comment" rows="3" placeholder="Share your experience: performance, durability, fit, packaging..." style="width:100%;"><?= htmlspecialchars($user_existing_review['comment'] ?? '') ?></textarea>
                </div>

                <!-- Optional Multi-Photo Upload (Up to 5 photos) -->
                <div style="margin-bottom:1.5rem;">
                    <label class="form-label" style="display:block; margin-bottom:6px;">
                        Attach Photos <span class="muted" style="font-weight:400; font-size:11px;">(Optional — Up to 5 photos)</span>
                    </label>
                    <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap;">
                        <input type="file" name="review_images[]" id="review_photo_input" accept=".jpg,.jpeg,.png,.webp" multiple style="display:none;" onchange="handleReviewFileSelect(this, 'review-photo-preview-box', 'review_photo_name')">
                        <button type="button" class="btn ghost sm" onclick="document.getElementById('review_photo_input').click();">📷 Choose Photos (Max 5)</button>
                        <span id="review_photo_name" class="muted" style="font-size:12px;"></span>
                    </div>
                    <small class="muted" style="font-size:11px; display:block; margin-top:4px;">JPG, PNG, or WEBP (Max 3MB each, up to 5 photos)</small>
                    
                    <!-- Previews of newly selected photos -->
                    <div id="review-photo-preview-box" style="display:flex; gap:10px; flex-wrap:wrap; margin-top:10px;"></div>

                    <!-- Existing photos if user previously submitted photos -->
                    <?php 
                    if ($user_existing_review && !empty($user_existing_review['review_image'])) { 
                        $ex_imgs = parse_review_images($user_existing_review['review_image']);
                        if (!empty($ex_imgs)) { ?>
                            <div style="margin-top:10px; font-size:11px;" class="muted">
                                <span>Current photos (will be kept unless you choose new ones):</span>
                                <div style="display:flex; gap:8px; margin-top:6px; flex-wrap:wrap;">
                                    <?php foreach ($ex_imgs as $ei) { 
                                        if (file_exists(__DIR__ . '/../images/uploads/' . $ei)) { ?>
                                            <img src="../images/uploads/<?= htmlspecialchars($ei) ?>" alt="Current Photo" style="width:54px; height:54px; object-fit:cover; border-radius:6px; border:1px solid rgba(255,255,255,0.15);">
                                    <?php } } ?>
                                </div>
                            </div>
                    <?php } } ?>
                </div>

                <button type="submit" name="submit_review" class="btn" style="padding:0.75rem 1.75rem; font-size:12px;">
                    <?= $user_existing_review ? 'Update My Review' : 'Publish Review' ?>
                </button>
            </form>
        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var picker = document.getElementById('star-picker');
            if (!picker) return;
            var stars = picker.querySelectorAll('.star-btn');
            var input = document.getElementById('review_rating_input');
            var label = document.getElementById('rating-label');
            var currentVal = parseInt(input.value) || 5;

            function highlight(val) {
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
            }

            stars.forEach(function(star) {
                star.addEventListener('mouseenter', function() {
                    var v = parseInt(this.getAttribute('data-val'));
                    highlight(v);
                    if (label) label.innerText = v + ' / 5 Stars';
                });
                star.addEventListener('click', function() {
                    currentVal = parseInt(this.getAttribute('data-val'));
                    input.value = currentVal;
                    highlight(currentVal);
                    if (label) label.innerText = currentVal + ' / 5 Stars';
                });
            });

            picker.addEventListener('mouseleave', function() {
                highlight(currentVal);
                if (label) label.innerText = currentVal + ' / 5 Stars';
            });
        });

        // Multi-photo selection and preview handler
        function handleReviewFileSelect(input, previewContainerId, nameLabelId) {
            var container = document.getElementById(previewContainerId);
            var label = document.getElementById(nameLabelId);
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
                    thumb.style.width = '64px';
                    thumb.style.height = '64px';
                    thumb.style.borderRadius = '8px';
                    thumb.style.overflow = 'hidden';
                    thumb.style.border = '1px solid rgba(255,255,255,0.2)';
                    thumb.style.boxShadow = '0 4px 12px rgba(0,0,0,0.4)';

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
        </script>
    <?php } elseif (!isset($_SESSION['user_id'])) { ?>
        <div style="padding:1rem 1.25rem; background:rgba(255,255,255,0.02); border:1px dashed rgba(255,255,255,0.12); border-radius:10px; margin-bottom:2rem; font-size:13px;" class="muted">
            ℹ️ <a href="../login.php?redirect=item/detail.php?id=<?= $id ?>" style="color:var(--color-accent-on-dark); font-weight:600;">Sign in</a> to leave a review if you have purchased this item.
        </div>
    <?php } else { ?>
        <div style="padding:1rem 1.25rem; background:rgba(255,255,255,0.02); border:1px dashed rgba(255,255,255,0.12); border-radius:10px; margin-bottom:2rem; font-size:13px;" class="muted">
            ℹ️ Verified buyer review requirement: You can write a review once you have purchased and received this gear (Order Status: Completed).
        </div>
    <?php } ?>

    <!-- Reviews List -->
    <?php if (count($reviews) === 0) { ?>
        <div style="text-align:center; padding:3rem 1rem; background:rgba(255,255,255,0.02); border-radius:12px; border:1px solid rgba(255,255,255,0.06);">
            <div style="font-size:2rem; margin-bottom:0.5rem;">💬</div>
            <div style="font-weight:600;">No reviews yet for this product.</div>
            <p class="muted" style="font-size:13px; margin-top:4px;">Be the first athlete to order and review this gear!</p>
        </div>
    <?php } else { ?>
        <div style="display:flex; flex-direction:column; gap:1rem;">
            <?php foreach ($reviews as $rev) { 
                // MP4: Regex filter foul words on display
                $clean_comment = filter_bad_words($rev['comment']);
            ?>
                <div class="card in" style="padding:1.25rem; background:rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.07); border-radius:12px;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:0.5rem;">
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="width:36px; height:36px; border-radius:50%; overflow:hidden; background:rgba(255,255,255,0.08); display:flex; align-items:center; justify-content:center; border:1px solid rgba(255,255,255,0.15); flex-shrink:0;">
                                <?php if (!empty($rev['user_photo']) && file_exists(__DIR__ . '/../images/uploads/' . $rev['user_photo'])) { ?>
                                    <img src="../images/uploads/<?= htmlspecialchars($rev['user_photo']) ?>" alt="Avatar" style="width:100%; height:100%; object-fit:cover;">
                                <?php } else { ?>
                                    <span style="font-size:1.1rem;">👤</span>
                                <?php } ?>
                            </div>
                            <div>
                                <strong style="font-size:13px;"><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                                <span class="status-badge" style="background:rgba(81,175,67,0.15); color:#51af43; font-size:10px; margin-left:6px; padding:1px 6px;">
                                    ✓ Verified Buyer
                                </span>
                                <div class="muted" style="font-size:11px; margin-top:2px;">
                                    <?= date('M d, Y', strtotime($rev['created_at'])) ?>
                                    <?php if ($rev['updated_at'] !== $rev['created_at']) { ?>
                                        <span style="opacity:0.6;">(edited)</span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                        <div style="color:#f59e0b; font-size:15px; letter-spacing:1px;">
                            <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                        </div>
                    </div>
                    <?php if ($clean_comment !== '') { ?>
                        <p style="font-size:13px; line-height:1.6; margin-top:0.5rem; color:#e5e7eb;">
                            <?= nl2br(htmlspecialchars($clean_comment)) ?>
                        </p>
                    <?php } ?>
                    <?php 
                    $rev_imgs = parse_review_images($rev['review_image'] ?? '');
                    if (!empty($rev_imgs)) { ?>
                        <div style="margin-top:0.85rem; display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                            <?php foreach ($rev_imgs as $r_img) {
                                if (file_exists(__DIR__ . '/../images/uploads/' . $r_img)) { ?>
                                    <a href="../images/uploads/<?= htmlspecialchars($r_img) ?>" target="_blank" title="Click to view full photo">
                                        <img src="../images/uploads/<?= htmlspecialchars($r_img) ?>" alt="Customer Review Photo" style="width:78px; height:78px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,0.18); cursor:zoom-in; transition:transform 0.2s, border-color 0.2s;" onmouseover="this.style.transform='scale(1.06)'; this.style.borderColor='#e0231c';" onmouseout="this.style.transform='scale(1)'; this.style.borderColor='rgba(255,255,255,0.18)';">
                                    </a>
                            <?php } } ?>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<?php require '../includes/footer.php'; ?>
