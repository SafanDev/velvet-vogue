<?php
// terms.php - Velvet Vogue Terms of Service
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
$page_title = "Terms of Service | Velvet Vogue";
$page_css = "legal.css";
$page_js = "legal.js";
include '../ReuseableUI/header.php';
?>

<main class="legal-wrapper position-relative">
    <div class="cinematic-grain"></div>
    <div class="transit-grid-bg opacity-25"></div>

    <div class="container py-5 position-relative z-2">

        <div class="text-center mb-5 gsap-fade-in">
            <span class="gold-text tracking-luxury d-block mb-2" style="font-size: 0.75rem;">LEGAL AGREEMENT</span>
            <h1 class="text-white text-uppercase tracking-luxury mb-3" style="font-size: 2.5rem; letter-spacing: 6px;">TERMS OF SERVICE</h1>
            <p class="text-silver font-monospace mx-auto" style="max-width: 600px; font-size: 0.85rem;">
                ESTABLISHED BY JOHN FINLO. LAST REVISED: <?= date('F Y') ?>
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="legal-document gsap-fade-in">

                    <div class="legal-section">
                        <h2 class="legal-heading">1. OUR AGREEMENT</h2>
                        <p class="legal-text text-silver">
                            By accessing the Velvet Vogue website, you agree to be bound by these terms. Our brand specializes in premium casual and formal wear designed for individuals looking to elevate their style. Using this website implies your full acceptance of these terms and conditions.
                        </p>
                    </div>

                    <div class="legal-section">
                        <h2 class="legal-heading">2. PRODUCT ACCURACY & AVAILABILITY</h2>
                        <p class="legal-text text-silver">
                            We use high-quality photography to represent our clothing accurately. However, your device's screen settings may slightly alter the appearance of fabric colors. Velvet Vogue reserves the right to update product descriptions, prices, and size availability at any time without prior notice.
                        </p>
                    </div>

                    <div class="legal-section">
                        <h2 class="legal-heading">3. PAYMENTS & TRANSACTIONS</h2>
                        <p class="legal-text text-silver">
                            When an item is added to your cart and purchased, the transaction undergoes standard security verification. We reserve the right to cancel or refuse any order that is suspected of fraud, unauthorized bot-purchasing, or violations of our store policies.
                        </p>
                    </div>

                    <div class="legal-section">
                        <h2 class="legal-heading">4. ACCOUNT SECURITY</h2>
                        <p class="legal-text text-silver">
                            Users are encouraged to create accounts for a better shopping experience. You are fully responsible for maintaining the confidentiality of your password and login details. Velvet Vogue cannot be held liable for any unauthorized access to your account resulting from a failure to protect your login credentials.
                        </p>
                    </div>

                    <div class="signature-block mt-5 pt-5 border-top-dark text-center">
                        <h3 class="gold-text tracking-luxury mb-3" style="font-size: 1rem;">DIGITAL AUTHORIZATION</h3>
                        <p class="text-silver font-monospace mb-4" style="font-size: 0.8rem;">PLEASE PROVIDE YOUR SIGNATURE TO ACKNOWLEDGE THESE TERMS</p>

                        <div class="signature-pad-container position-relative mx-auto">
                            <canvas id="signatureCanvas" width="400" height="150"></canvas>
                            <div class="sig-placeholder pointer-events-none">SIGN HERE</div>

                            <div id="sigSeal" class="sig-seal pointer-events-none">
                                <i class="fa-solid fa-check-double"></i> AGREEMENT ACCEPTED
                            </div>
                        </div>

                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button class="btn-text-silver border-0 bg-transparent" id="btnClearSig" style="font-size: 0.85rem; letter-spacing: 2px; text-transform: uppercase; cursor: pointer;"><i class="fa-solid fa-eraser me-2"></i> CLEAR PAD</button>
                            <button class="btn-outline-gold px-4 py-2" id="btnSubmitSig">AUTHORIZE</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>