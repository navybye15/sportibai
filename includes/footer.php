</main>
<footer class="site-footer">
    <div class="footer-inner">
        <!-- Brand -->
        <div class="footer-brand-col">
            <span class="footer-brand">SPORTI<span style="color:var(--color-accent-on-dark)">BAI</span></span>
            <p class="muted" style="font-size:var(--text-body-sm); max-width:28ch; line-height:1.7; margin-top:.75rem;">
                Gear for every court, gym, and road.
                Trusted by athletes who show up.
            </p>
        </div>

        <!-- Quick Links -->
        <div class="footer-col">
            <h4 class="footer-col-title">Quick Links</h4>
            <ul class="footer-links">
                <li><a href="<?= $base ?>index.php">Home</a></li>
                <li><a href="<?= $base ?>shop.php">Shop</a></li>
                <li><a href="<?= $base ?>orders.php">My Orders</a></li>
                <li><a href="<?= $base ?>profile.php">Profile</a></li>
            </ul>
        </div>

        <!-- Info -->
        <div class="footer-col">
            <h4 class="footer-col-title">Info</h4>
            <ul class="footer-links">
                <li><a href="<?= $base ?>index.php#about">About Us</a></li>
                <li><a href="<?= $base ?>index.php#contact">Contact</a></li>
                <li><a href="#terms">Terms &amp; Conditions</a></li>
                <li><a href="#privacy">Privacy Policy</a></li>
            </ul>
        </div>

        <!-- Contact -->
        <div class="footer-col">
            <h4 class="footer-col-title">Contact</h4>
            <ul class="footer-contact-list">
                <li><span class="f-icon">📍</span> Cebu City, Philippines</li>
                <li><span class="f-icon">📞</span> +63 917 000 0000</li>
                <li><span class="f-icon">✉️</span> <a href="mailto:hello@sportibai.ph">hello@sportibai.ph</a></li>
            </ul>
        </div>
    </div>

    <!-- Bottom bar -->
    <div class="footer-bottom">
        <span class="muted" style="font-size:11px;">
            © <?= date('Y') ?> Sportibai. All rights reserved.
        </span>
        <span class="muted" style="font-size:11px; max-width:56ch; text-align:right; line-height:1.6;">
            In compliance with the <strong>Data Privacy Act of 2012 (RA 10173)</strong>,
            we collect your name, address, and phone number solely to process and deliver your orders.
            Your data is never sold or shared with third parties.
        </span>
    </div>
</footer>

<script src="<?= $base ?>js/app.js"></script>
</body>
</html>
