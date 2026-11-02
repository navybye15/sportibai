<?php
require 'includes/db.php';
require 'includes/functions.php';
$base       = '';
$page_title = 'Sportibai — Gear Up. Get After It.';
require 'includes/header.php';


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


$featured_items = array();
$featured_sizes = array();
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

    if (!empty($featured_items)) {
        $feat_ids = array_column($featured_items, 'item_id');
        $in_clause = implode(',', array_map('intval', $feat_ids));
        $sz_res = mysqli_query($conn, "SELECT size_id, item_id, size_label, stock FROM item_sizes WHERE item_id IN ($in_clause) ORDER BY size_id ASC");
        if ($sz_res) {
            while ($srow = mysqli_fetch_assoc($sz_res)) {
                $featured_sizes[$srow['item_id']][] = $srow;
            }
        }
    }
}

// ── Category icon map (SVG icon keys) ───────────────────────────────────────
$cat_icons = array(
    'Balls'      => 'layers',
    'Footwear'   => 'zap',
    'Apparel'    => 'tag',
    'Weights'    => 'award',
    'Equipment'  => 'package',
    'Accessories'=> 'shield',
    'Rackets'    => 'zap',
    'Protection' => 'shield',
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
                <a href="shop.php" class="btn" style="padding:1.05rem 2.5rem; font-size:15px; font-weight:800; box-shadow:0 0 24px rgba(10,132,255,0.4);">
                    Shop Now →
                </a>
                <?php if (!isset($_SESSION['user_id'])) { ?>
                    <a href="auth/register.php" class="btn ghost" style="padding:1.05rem 2.2rem; font-size:15px; font-weight:700;">
                        Create Account
                    </a>
                <?php } else { ?>
                    <a href="orders.php" class="btn ghost" style="padding:1.05rem 2.2rem; font-size:15px; font-weight:700;">
                        My Orders
                    </a>
                <?php } ?>
            </div>

            <!-- Quiz 3: Homepage Search Feature -->
            <form action="shop.php" method="GET" class="hero-search-box" style="margin-top:2rem; max-width:540px; position:relative; display:flex; align-items:center; background:rgba(20,24,33,0.85); border:1px solid rgba(255,255,255,0.14); border-radius:9999px; padding:6px 8px 6px 20px; backdrop-filter:blur(14px); box-shadow:0 12px 35px rgba(0,0,0,0.5); transition:border-color 0.2s, box-shadow 0.2s;">
                <span style="color:var(--color-fg-muted); display:flex; align-items:center; margin-right:8px;"><?= icon('search', '', 20) ?></span>
                <input type="text" name="q" placeholder="Search gear, shoes, balls, brands..." autocomplete="off" style="flex:1; background:transparent; border:none; outline:none; color:#f3f4f6; font-size:15px; font-weight:600; font-family:inherit; padding:10px 6px;">
                <button type="submit" class="btn" style="padding:0.75rem 1.6rem; font-size:13px; font-weight:800; letter-spacing:0.04em; border-radius:9999px; flex-shrink:0; text-transform:uppercase;">
                    Search
                </button>
            </form>
        </div>
        <div class="home-hero-visual reveal">
            <div class="spotibai-3d-wrap">
                <div class="spotibai-3d-glow-bg"></div>
                <div id="spotibai-3d-container"></div>
                <div id="spotibai-3d-loader" class="spotibai-3d-loader" style="font-size:14px; font-weight:600;">
                    <span style="color:var(--color-brand-red); display:inline-flex; align-items:center;"><?= icon('loader', '', 24) ?></span>
                    <span>Loading 3D Spotibai Core…</span>
                </div>
            </div>
        </div>
    </div>
    <!-- Decorative line -->
    <div class="home-divider"></div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     FULL-SCREEN INFINITE HIGHLIGHTS MARQUEE (With Hover Focus)
═══════════════════════════════════════════════════════════════════════════ -->
<?php
$highlight_posters = array(
    array(
        'image' => 'images/highlights/highlight-1.jpg',
        'badge' => 'FITNESS & STRENGTH',
        'title' => 'Heavy Iron & Gym Gear',
        'desc' => 'Dumbbells, weights, and high-performance gym essentials.',
        'link' => 'shop.php?cat=Fitness'
    ),
    array(
        'image' => 'images/highlights/highlight-2.jpg',
        'badge' => 'ATHLETE GEAR',
        'title' => 'Signature SpotiBai Caps',
        'desc' => 'Official athletic caps, training bottles, and pro essentials.',
        'link' => 'shop.php?cat=Accessories'
    ),
    array(
        'image' => 'images/highlights/highlight-3.jpg',
        'badge' => 'FOOTWEAR & KICKS',
        'title' => 'Court & Running Kicks',
        'desc' => 'High-cushion responsive footwear with sizes EU 39 to 45.',
        'link' => 'shop.php?cat=Footwear'
    ),
    array(
        'image' => 'images/highlights/highlight-4.jpg',
        'badge' => 'BALL SPORTS',
        'title' => 'Game Balls & Hoop Tees',
        'desc' => 'Mikasa match balls, basketballs, and signature graphic wear.',
        'link' => 'shop.php?cat=Ball+Sports'
    ),
    array(
        'image' => 'images/highlights/highlight-5.jpg',
        'badge' => 'DRY-FIT APPAREL',
        'title' => 'Elite Athletic Wear',
        'desc' => 'Breathable jerseys, training tanks, and compression gear.',
        'link' => 'shop.php?cat=Apparel'
    ),
);
?>
<section class="highlights-fullscreen-section reveal">
    <div class="highlights-fullscreen-header">
        <div>
            <div class="hero-eyebrow">[ ATHLETE SHOWCASE ]</div>
            <h2 class="home-section-title" style="margin-top:6px;">Highlights</h2>
            <p class="muted" style="max-width:52ch; margin-top:.4rem; font-size:14px;">
                Gear trusted on the court, track, and gym floor. Hover over any poster to pause &amp; spotlight.
            </p>
        </div>
        <div style="display:flex; align-items:center; gap:12px;">
            <a href="shop.php" class="btn ghost sm" style="padding:0.5rem 1.25rem;">
                Browse All Gear →
            </a>
        </div>
    </div>

    <!-- Infinite Carousel Viewport -->
    <div class="highlights-carousel-viewport" id="highlightsViewport" title="Hover over any card to pause and focus">
        <div class="highlights-carousel-track" id="highlightsTrack">
            <!-- Set 1 -->
            <?php foreach ($highlight_posters as $idx => $hp) { ?>
                <a href="<?= $hp['link'] ?>" class="highlight-poster-card" id="hl-card-1-<?= $idx ?>">
                    <div class="highlight-poster-bg" style="background-image: url('<?= $hp['image'] ?>');"></div>
                    <div class="highlight-poster-overlay"></div>
                    <div class="highlight-poster-top">
                        <span class="highlight-poster-badge">
                            <span class="highlight-poster-badge-dot"></span>
                            <?= $hp['badge'] ?>
                        </span>
                        <span style="opacity:0.85; display:inline-flex; align-items:center;"><?= icon('arrow-up-right', '', 20) ?></span>
                    </div>
                    <div class="highlight-poster-bottom">
                        <h3 class="highlight-poster-title"><?= $hp['title'] ?></h3>
                        <p class="highlight-poster-desc"><?= $hp['desc'] ?></p>
                        <span class="highlight-poster-cta">Explore Collection <span>→</span></span>
                    </div>
                </a>
            <?php } ?>

            <!-- Set 2 (Duplicate for seamless infinite marquee loop) -->
            <?php foreach ($highlight_posters as $idx => $hp) { ?>
                <a href="<?= $hp['link'] ?>" class="highlight-poster-card" id="hl-card-2-<?= $idx ?>" aria-hidden="true">
                    <div class="highlight-poster-bg" style="background-image: url('<?= $hp['image'] ?>');"></div>
                    <div class="highlight-poster-overlay"></div>
                    <div class="highlight-poster-top">
                        <span class="highlight-poster-badge">
                            <span class="highlight-poster-badge-dot"></span>
                            <?= $hp['badge'] ?>
                        </span>
                        <span style="opacity:0.85; display:inline-flex; align-items:center;"><?= icon('arrow-up-right', '', 20) ?></span>
                    </div>
                    <div class="highlight-poster-bottom">
                        <h3 class="highlight-poster-title"><?= $hp['title'] ?></h3>
                        <p class="highlight-poster-desc"><?= $hp['desc'] ?></p>
                        <span class="highlight-poster-cta">Explore Collection <span>→</span></span>
                    </div>
                </a>
            <?php } ?>
        </div>
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
                        <p class="muted" style="font-size:14px; line-height:1.6; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">
                            <?= htmlspecialchars($item['description']) ?>
                        </p>
                    <?php } ?>
                </div>
                <?php 
                    $item_sizes_list = $featured_sizes[$item['item_id']] ?? array();
                    $has_item_sizes = count($item_sizes_list) > 0;
                ?>
                <div class="row">
                    <span class="price"><?= peso($item['price']) ?></span>
                    <?php if ($is_admin) { ?>
                        <span class="muted" style="font-size:12.5px; font-weight:700; text-transform:uppercase;">Admin view</span>
                    <?php } elseif ($item['stock'] > 0) { ?>
                        <?php if ($has_item_sizes) { ?>
                            <a class="btn sm" href="item/detail.php?id=<?= $item['item_id'] ?>" style="font-size:13.5px; font-weight:700; padding:0.65rem 1.35rem;">
                                Select Size →
                            </a>
                        <?php } else { ?>
                            <form method="post" action="actions/cart_update.php">
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                <input type="hidden" name="return_url" value="index.php#item-<?= $item['item_id'] ?>">
                                <button class="btn sm" type="submit" style="font-size:13.5px; font-weight:700; padding:0.65rem 1.35rem;">Add to Cart</button>
                            </form>
                        <?php } ?>
                    <?php } else { ?>
                        <span class="muted" style="font-size:12.5px; font-weight:700; text-transform:uppercase; color:#ff6b6b;">Out of Stock</span>
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
                        <span class="brewed-chip"><span style="color:#60a5fa; display:flex; align-items:center;"><?= icon('package', '', 18) ?></span> Official Match Balls</span>
                        <span class="brewed-chip"><span style="color:#a78bfa; display:flex; align-items:center;"><?= icon('layers', '', 18) ?></span> Court &amp; Trail Kicks</span>
                        <span class="brewed-chip"><span style="color:#34d399; display:flex; align-items:center;"><?= icon('award', '', 18) ?></span> Cast Iron &amp; Dumbbells</span>
                        <span class="brewed-chip"><span style="color:#f87171; display:flex; align-items:center;"><?= icon('shield', '', 18) ?></span> Impact Combat Gear</span>
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
                        <span class="brewed-chip"><span style="color:#38bdf8; display:flex; align-items:center;"><?= icon('truck', '', 18) ?></span> Express Doorstep Delivery</span>
                        <span class="brewed-chip"><span style="color:#fbbf24; display:flex; align-items:center;"><?= icon('store', '', 18) ?></span> Free In-Store Pick Up</span>
                        <span class="brewed-chip"><span style="color:#4ade80; display:flex; align-items:center;"><?= icon('banknote', '', 18) ?></span> Cash on Delivery (COD)</span>
                        <span class="brewed-chip"><span style="color:#818cf8; display:flex; align-items:center;"><?= icon('map-pin', '', 18) ?></span> Step-by-Step Order Tracker</span>
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
                        <span class="brewed-chip"><span style="color:#fbbf24; display:flex; align-items:center;"><?= icon('award', '', 18) ?></span> 100% Guaranteed Authentic</span>
                        <span class="brewed-chip"><span style="color:#facc15; display:flex; align-items:center;"><?= icon('zap', '', 18) ?></span> Real-Time Stock Sync</span>
                        <span class="brewed-chip"><span style="color:#38bdf8; display:flex; align-items:center;"><?= icon('message-square', '', 18) ?></span> Real Athlete Support</span>
                        <span class="brewed-chip"><span style="color:#4ade80; display:flex; align-items:center;"><?= icon('lock', '', 18) ?></span> Data Privacy Act Protected</span>
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
            <p class="muted" style="line-height:1.8; font-size:16.5px;">
                Sportibai was built straight out of the court and gym. We know the frustration
                of training with worn-out kicks and low-tier gear. Whether you're grinding on concrete,
                hardwood, or iron — we equip athletes who never make excuses.
            </p>
            <p class="muted" style="margin-top:1rem; line-height:1.8; font-size:16.5px;">
                Every basketball, dumbbell, and strap in our catalog is rigorously verified and approved
                by real athletes who actually compete and train.
            </p>
            <div style="display:flex; gap:1.25rem; align-items:center; flex-wrap:wrap; margin-top:2.25rem;">
                <a href="shop.php" class="btn" style="padding:1.05rem 2.4rem; font-size:14.5px; font-weight:800; box-shadow:0 0 20px rgba(10,132,255,0.35);">Shop All Gear →</a>
                <span class="muted" style="font-size:14px; font-weight:700; letter-spacing:0.06em; color:var(--color-brand-red);">#UnitedUnstoppable</span>
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
<?php 
require 'includes/footer.php'; 
?>
