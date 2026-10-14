<?php
http_response_code(404);
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$base = '';
$page_title = 'Page Not Found | Sportibai';
require 'includes/header.php';
?>
<section class="hero reveal in" style="text-align:center; padding: 5rem 1rem 4rem;">
    <div class="hero-eyebrow" style="color:#ff9f96;">[ OUT OF BOUNDS · 404 ]</div>
    <h1 style="font-size:clamp(2.5rem, 6vw, 4.5rem); margin: 0.5rem 0 1rem; line-height:1.1;">
        Page <span class="highlight">Not Found</span>
    </h1>
    <p class="muted" style="max-width: 520px; margin: 0 auto 2rem; font-size:var(--text-body); line-height: 1.6;">
        The play was whistled dead. The page or equipment you are looking for has been moved, removed, or never existed in the court.
    </p>
    <div style="display:flex; justify-content:center; gap:12px; flex-wrap:wrap;">
        <a href="index.php" class="btn" style="min-width:140px;">
            🏠 Back to Home
        </a>
        <a href="shop.php" class="btn ghost" style="min-width:140px;">
            🏀 Browse Catalog
        </a>
    </div>
</section>
<?php require 'includes/footer.php'; ?>
