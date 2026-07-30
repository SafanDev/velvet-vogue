<footer class="main-footer">
    <div class="footer-separator"></div>

    <div class="container footer-content">
        <div class="row">
            
            <div class="col-lg-4 col-md-6 mb-5">
                <a href="home.php" class="footer-logo">Velvet Vogue</a>
                <p class="footer-desc">
                    Express your identity through style. We specialize in trendy casualwear and formal wear for the ambitious and the bold.
                </p>
            </div>

            <div class="col-lg-2 col-md-6 mb-5 offset-lg-1">
                <h4 class="footer-heading">Shop</h4>
                <a href="shop.php?category=men" class="footer-link">Men's Collection</a>
                <a href="shop.php?category=women" class="footer-link">Women's Collection</a>
                <a href="shop.php?category=unisex" class="footer-link">Unisex</a>
                <a href="shop.php?new_arrival=1" class="footer-link">New Arrivals</a>
                <a href="shop.php?featured=1" class="footer-link">Featured Items</a>
            </div>

            <div class="col-lg-2 col-md-6 mb-5">
                <h4 class="footer-heading">Support</h4>
                <a href="faq.php" class="footer-link">FAQ</a>
                <a href="contact.php" class="footer-link">Contact Us</a>
                <a href="terms.php" class="footer-link">Terms & Conditions</a>
                <a href="privacy.php" class="footer-link">Privacy Policy</a>
            </div>

            <div class="col-lg-3 col-md-6 mb-5">
                <h4 class="footer-heading">Get in Touch</h4>
                <p class="footer-desc" style="margin-bottom: 8px;">
                    <i class="fa-solid fa-location-dot" style="color: #D4AF37; margin-right: 8px;"></i> 
                    No. 45, Galle Road, Colombo 03, Sri Lanka
                </p>
                <p class="footer-desc" style="margin-bottom: 8px;">
                    <i class="fa-solid fa-phone" style="color: #D4AF37; margin-right: 8px;"></i> 
                    +94 11 234 5678
                </p>
                <p class="footer-desc">
                    <i class="fa-solid fa-envelope" style="color: #D4AF37; margin-right: 8px;"></i> 
                    support@velvetvogue.lk
                </p>

                <div class="social-wrapper">
                    <a href="#" class="social-icon si-insta" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" class="social-icon si-fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                    <a href="#" class="social-icon si-tiktok" title="TikTok"><i class="fa-brands fa-tiktok"></i></a>
                    <a href="#" class="social-icon si-x" title="X (Twitter)"><i class="fa-brands fa-x-twitter"></i></a>
                </div>
            </div>

        </div>

        <div class="footer-bottom">
            &copy; <?php echo date("Y"); ?> Velvet Vogue. All Rights Reserved. Designed for Excellence.
        </div>
    </div>
</footer>

<?php
$needsSweetAlert = isset($page_js) && in_array((string) $page_js, [
    'auth.js',
    'contact.js',
    'dashboard.js',
    'legal.js',
    'product_detail.js',
    'review.js',
    'shop.js',
], true);
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
<?php if ($needsSweetAlert): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php endif; ?>

<script src="<?= vv_e(vv_versioned_asset('../Assets/js/main.js')) ?>"></script>

<?php if(isset($page_js)): ?>
    <script src="<?= vv_e(vv_versioned_asset('../Assets/js/pages/' . (string) $page_js)) ?>"></script>
<?php endif; ?>

</body>
</html>