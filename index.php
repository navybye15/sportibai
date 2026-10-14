<?php
require 'includes/db.php';
require 'includes/functions.php';
$base       = '';
$page_title = 'Sportibai — Gear Up. Get After It.';
require 'includes/header.php';

// ── Load categories for highlights ────────────────────────────────────────────
$categories = array();
if ($conn != null) {
    $sql = "SELECT c.name, COUNT(i.item_id) AS item_count
            FROM categories c
            LEFT JOIN items i ON c.category_id = i.category_id AND i.is_active = 1
            WHERE c.is_active = 1
            GROUP BY c.category_id
            ORDER BY item_count DESC
            LIMIT 6";
    $result = mysqli_query($conn, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
}

// ── Load 4 Featured / Best-Selling Items ──────────────────────────────────────
$featured_items = array();
if ($conn != null) {
    $sql_feat = "SELECT i.item_id, i.name, i.brand, i.description, i.price, i.stock, i.image, c.name AS category
                 FROM items i
                 JOIN categories c ON i.category_id = c.category_id
                 WHERE i.is_active = 1 AND c.is_active = 1
                 ORDER BY i.item_id DESC
                 LIMIT 4";
    $res_feat = mysqli_query($conn, $sql_feat);
    if ($res_feat) {
        while ($row = mysqli_fetch_assoc($res_feat)) {
            $featured_items[] = $row;
        }
    }
}

// ── Category icon map (emoji fallback) ───────────────────────────────────────
$cat_icons = array(
    'Balls'      => '⚽',
    'Footwear'   => '👟',
    'Apparel'    => '👕',
    'Weights'    => '🏋️',
    'Equipment'  => '🏅',
    'Accessories'=> '🎽',
    'Rackets'    => '🏸',
    'Protection' => '🥊',
);
// Category gradient map (hue)
$cat_hues = array(200, 260, 40, 160, 310, 20, 180, 340);
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     HERO SECTION
═══════════════════════════════════════════════════════════════════════════ -->
<section class="home-hero">
    <div class="home-hero-inner">
        <div class="home-hero-text reveal">
            <div class="hero-eyebrow">[ PERFORMANCE &amp; STYLE ]</div>
            <h1 class="home-headline">
                Gear Up.<br>
                Get <span class="highlight">After It.</span>
            </h1>
            <p class="home-hero-sub">
                Premium sports gear for every court, gym, and road.
                Balls, weights, kicks, and apparel — built for athletes
                who show up.
            </p>
            <div class="home-hero-ctas">
                <a href="shop.php" class="btn" style="padding:0.9rem 2.25rem; font-size:12px;">
                    Shop Now →
                </a>
                <?php if (!isset($_SESSION['user_id'])) { ?>
                    <a href="register.php" class="btn ghost" style="padding:0.9rem 1.75rem; font-size:12px;">
                        Create Account
                    </a>
                <?php } else { ?>
                    <a href="orders.php" class="btn ghost" style="padding:0.9rem 1.75rem; font-size:12px;">
                        My Orders
                    </a>
                <?php } ?>
            </div>

            <!-- Quiz 3: Homepage Search Feature -->
            <form action="shop.php" method="GET" class="hero-search-box" style="margin-top:1.75rem; max-width:480px; position:relative; display:flex; align-items:center; background:rgba(20,24,33,0.85); border:1px solid rgba(255,255,255,0.12); border-radius:9999px; padding:4px 6px 4px 18px; backdrop-filter:blur(12px); box-shadow:0 8px 30px rgba(0,0,0,0.45); transition:border-color 0.2s, box-shadow 0.2s;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="opacity:0.6; flex-shrink:0; margin-right:8px;"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                <input type="text" name="q" placeholder="Search gear, shoes, balls, brands..." autocomplete="off" style="flex:1; background:transparent; border:none; outline:none; color:#f3f4f6; font-size:13px; font-weight:500; font-family:inherit; padding:8px 4px;">
                <button type="submit" class="btn" style="padding:0.65rem 1.4rem; font-size:11px; letter-spacing:0.04em; border-radius:9999px; flex-shrink:0; text-transform:uppercase;">
                    Search
                </button>
            </form>
            <div style="margin-top:0.65rem; font-size:11px; opacity:0.55; display:flex; gap:0.5rem; flex-wrap:wrap; align-items:center;">
                <span>Popular:</span>
                <a href="shop.php?q=Basketball" style="color:inherit; text-decoration:underline;">Basketball</a>
                <span>•</span>
                <a href="shop.php?q=Shoes" style="color:inherit; text-decoration:underline;">Shoes</a>
                <span>•</span>
                <a href="shop.php?q=Nike" style="color:inherit; text-decoration:underline;">Nike</a>
                <span>•</span>
                <a href="shop.php?q=Dumbbell" style="color:inherit; text-decoration:underline;">Dumbbell</a>
            </div>
        </div>
        <div class="home-hero-visual reveal">
            <div class="spotibai-3d-wrap">
                <div class="spotibai-3d-glow-bg"></div>
                <div id="spotibai-3d-container"></div>
                <div id="spotibai-3d-loader" class="spotibai-3d-loader">
                    <span style="font-size:1.75rem;">⚙️</span>
                    <span>Loading 3D Spotibai Core…</span>
                </div>
                <div class="spotibai-3d-badge">
                    <span class="spotibai-3d-badge-dot"></span>
                    <span>Hold &amp; Drag to Rotate in 3D</span>
                </div>
            </div>
        </div>
    </div>
    <!-- Decorative line -->
    <div class="home-divider"></div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     CATEGORY HIGHLIGHTS
═══════════════════════════════════════════════════════════════════════════ -->
<section class="home-section">
    <div class="home-section-header reveal-blur">
        <div class="hero-eyebrow">[ SHOP BY CATEGORY ]</div>
        <h2 class="home-section-title">Highlights</h2>
        <p class="muted" style="max-width:48ch; margin-top:.5rem;">
            Browse our curated selection of sports gear, from court to track.
        </p>
    </div>

    <div class="highlights-grid">
        <?php if (count($categories) === 0) {
            $static = array(
                array('name'=>'Balls','item_count'=>0),
                array('name'=>'Footwear','item_count'=>0),
                array('name'=>'Apparel','item_count'=>0),
                array('name'=>'Weights','item_count'=>0),
            );
            $categories = $static;
        }
        foreach ($categories as $i => $cat) {
            $icon = $cat_icons[$cat['name']] ?? '🏅';
            $hue  = $cat_hues[$i % count($cat_hues)];
        ?>
            <a href="shop.php?cat=<?= urlencode($cat['name']) ?>" class="highlight-card reveal-blur" style="--ch:<?= $hue ?>; transition-delay:<?= $i * 65 ?>ms;">
                <div class="highlight-glow"></div>
                <div class="highlight-card-icon-wrap">
                    <span class="highlight-card-icon"><?= $icon ?></span>
                </div>
                <div class="highlight-card-info">
                    <span class="highlight-card-name"><?= htmlspecialchars($cat['name']) ?></span>
                    <span class="highlight-card-count">
                        <?= $cat['item_count'] ?> item<?= $cat['item_count'] !== 1 ? 's' : '' ?>
                    </span>
                </div>
                <span class="highlight-card-arrow">→</span>
            </a>
        <?php } ?>
    </div>

    <div style="text-align:center; margin-top:2.5rem;" class="reveal-blur">
        <a href="shop.php" class="btn ghost">View All Gear →</a>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FEATURED GEAR (Best Sellers)
═══════════════════════════════════════════════════════════════════════════ -->
<?php if (count($featured_items) > 0) { ?>
<section class="home-section">
    <div class="home-section-header reveal-blur">
        <div class="hero-eyebrow">[ BEST SELLERS &amp; DROPS ]</div>
        <h2 class="home-section-title">Featured Gear</h2>
        <p class="muted" style="max-width:48ch; margin-top:.5rem;">
            Top picks favored by athletes this week. Grab them before stock runs out.
        </p>
    </div>

    <div class="grid" style="margin-top:2rem;">
        <?php foreach ($featured_items as $i => $item) { ?>
            <article class="card reveal-blur" id="item-<?= $item['item_id'] ?>" style="transition-delay:<?= $i * 75 ?>ms;">
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
                <div class="row">
                    <span class="price"><?= peso($item['price']) ?></span>
                    <?php if ($is_admin) { ?>
                        <span class="muted" style="font-size:11px; text-transform:uppercase;">Admin view</span>
                    <?php } elseif ($item['stock'] > 0) { ?>
                        <form method="post" action="cart_update.php">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                            <input type="hidden" name="return_url" value="index.php#item-<?= $item['item_id'] ?>">
                            <button class="btn sm" type="submit">Add to Cart</button>
                        </form>
                    <?php } else { ?>
                        <span class="muted" style="font-size:11px; text-transform:uppercase;">Out of Stock</span>
                    <?php } ?>
                </div>
            </article>
        <?php } ?>
    </div>
</section>
<?php } ?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     WHY SPORTIBAI (BrewedOps Interactive Horizontal Stage)
═══════════════════════════════════════════════════════════════════════════ -->
<section class="home-section">
    <div class="home-section-header reveal-blur">
        <div class="hero-eyebrow">[ WHY SPORTIBAI ]</div>
        <h2 class="home-section-title">Built for Athletes</h2>
        <p class="muted" style="max-width:52ch; margin-top:.5rem;">
            Discover what sets Sportibai apart. Interactive breakdown of our 3 core pillars.
        </p>
    </div>

    <div class="brewed-stage-container reveal-blur">
        <div class="brewed-stage-glow"></div>

        <!-- Top Progress Tracker (Catch - Brew - Pour style) -->
        <div class="brewed-tracker">
            <div class="brewed-tracker-line-bg"></div>
            <div class="brewed-tracker-line-fill"></div>

            <button type="button" class="brewed-step-btn active" data-step="0">
                <span class="brewed-step-dot"></span>
                <span class="brewed-step-label">01 / GEAR</span>
            </button>
            <button type="button" class="brewed-step-btn" data-step="1">
                <span class="brewed-step-dot"></span>
                <span class="brewed-step-label">02 / SPEED</span>
            </button>
            <button type="button" class="brewed-step-btn" data-step="2">
                <span class="brewed-step-dot"></span>
                <span class="brewed-step-label">03 / POWER</span>
            </button>
        </div>

        <!-- Horizontal Slides Track Viewport -->
        <div class="brewed-viewport">
            <div class="brewed-track">
                <!-- Slide 01: Gear -->
                <div class="brewed-slide active">
                    <span class="brewed-step-index">01 / 03 · TOURNAMENT GRADE</span>
                    <h2 class="brewed-big-title">Gear.</h2>
                    <p class="brewed-subtitle">
                        Every ball, kick, weight, and strap is rigorously chosen.
                        Engineered to withstand grueling training sessions and high-stakes matches.
                    </p>
                    <div class="brewed-chips-row">
                        <span class="brewed-chip"><span class="brewed-chip-icon">🏀</span> Official Match Balls</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">👟</span> Court &amp; Trail Kicks</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">🏋️</span> Cast Iron &amp; Dumbbells</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">🥊</span> Impact Combat Gear</span>
                    </div>
                </div>

                <!-- Slide 02: Speed -->
                <div class="brewed-slide">
                    <span class="brewed-step-index">02 / 03 · FULFILLMENT VELOCITY</span>
                    <h2 class="brewed-big-title">Speed.</h2>
                    <p class="brewed-subtitle">
                        Get geared up without delays. Fast doorstep delivery nationwide or
                        instant store pick up at our Central Mall hub right before game time.
                    </p>
                    <div class="brewed-chips-row">
                        <span class="brewed-chip"><span class="brewed-chip-icon">🚚</span> Express Doorstep Delivery</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">🏪</span> Free In-Store Pick Up</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">💵</span> Cash on Delivery (COD)</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">📦</span> Step-by-Step Order Tracker</span>
                    </div>
                </div>

                <!-- Slide 03: Power -->
                <div class="brewed-slide">
                    <span class="brewed-step-index">03 / 03 · AUTHENTIC INTEGRITY</span>
                    <h2 class="brewed-big-title">Power.</h2>
                    <p class="brewed-subtitle">
                        Built for athletes who actually show up. Zero knock-offs, verified inventory,
                        and direct community support by people who train.
                    </p>
                    <div class="brewed-chips-row">
                        <span class="brewed-chip"><span class="brewed-chip-icon">🏅</span> 100% Guaranteed Authentic</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">⚡</span> Real-Time Stock Sync</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">💬</span> Real Athlete Support</span>
                        <span class="brewed-chip"><span class="brewed-chip-icon">🛡️</span> Data Privacy Act Protected</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Arrows Controls -->
        <div class="brewed-nav-controls">
            <button type="button" class="brewed-nav-btn" id="brewed-prev-btn" title="Previous Slide">←</button>
            <span class="muted" style="font-size:12px; font-weight:600; letter-spacing:0.05em;">Swipe or click to scrub</span>
            <button type="button" class="brewed-nav-btn" id="brewed-next-btn" title="Next Slide">→</button>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     ABOUT US (Animated Background & Sportibai Athletes Team Photo)
═══════════════════════════════════════════════════════════════════════════ -->
<section id="about" class="home-section home-about-section reveal-blur">
    <!-- Dynamic Animated Background Canvas & Floating Orbs -->
    <div class="about-bg-motion" aria-hidden="true">
        <div class="about-orb about-orb-1"></div>
        <div class="about-orb about-orb-2"></div>
        <div class="about-grid-cyber"></div>
        <div class="about-glow-sweep"></div>
    </div>

    <div class="about-card-wrap">
        <div class="about-text">
            <div class="hero-eyebrow">[ THE SQUAD · UNITED &amp; UNSTOPPABLE ]</div>
            <h2 class="home-section-title">Built by Athletes, for Athletes.</h2>
            <p class="muted" style="line-height:1.75; font-size:15px;">
                Sportibai was built straight out of the court and gym. We know the frustration
                of training with worn-out kicks and low-tier gear. Whether you're grinding on concrete,
                hardwood, or iron — we equip athletes who never make excuses.
            </p>
            <p class="muted" style="margin-top:1rem; line-height:1.75; font-size:15px;">
                Every basketball, dumbbell, and strap in our catalog is rigorously verified and approved
                by real athletes who actually compete and train.
            </p>
            <div style="display:flex; gap:1rem; align-items:center; flex-wrap:wrap; margin-top:2rem;">
                <a href="shop.php" class="btn" style="padding:0.85rem 2rem;">Shop All Gear →</a>
                <span class="muted" style="font-size:13px; font-weight:600; letter-spacing:0.04em;">#UnitedUnstoppable</span>
            </div>
        </div>

        <div class="about-visual">
            <div class="about-team-frame">
                <div class="about-frame-glow"></div>
                <img src="images/about-athletes.jpg?v=<?= filemtime(__DIR__ . '/images/about-athletes.jpg') ?>"
                     alt="Sportibai Athletes Team - United. Unstoppable."
                     class="about-team-img"
                     loading="lazy">
                <div class="about-badge-float">
                    <span class="about-badge-pulse"></span>
                    <span>The Sportibai Team · Est. 2024</span>
                </div>
            </div>
        </div>
    </div>
</section>

<script src="js/three.min.js"></script>
<script src="js/spotibai-3d.js?v=<?= filemtime(__DIR__ . '/js/spotibai-3d.js') ?>"></script>
<?php require 'includes/footer.php'; ?>
