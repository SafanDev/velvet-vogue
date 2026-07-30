<?php
// privacy.php - Velvet Vogue Privacy Policy
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
$page_title = "Privacy Policy | Velvet Vogue";
$page_css = "legal.css";
$page_js = "legal.js";
include '../ReuseableUI/header.php';
?>

<main class="legal-wrapper position-relative">
    <div class="cinematic-grain"></div>
    <div class="transit-grid-bg opacity-25"></div>

    <div class="container py-5 position-relative z-2">

        <div class="text-center mb-5 gsap-fade-in">
            <span class="gold-text tracking-luxury d-block mb-2" style="font-size: 0.75rem;">OUR COMMITMENT</span>
            <h1 class="text-white text-uppercase tracking-luxury mb-3" style="font-size: 2.5rem; letter-spacing: 6px;">PRIVACY POLICY</h1>
            <p class="text-silver font-monospace mx-auto" style="max-width: 600px; font-size: 0.85rem;">
                YOUR PERSONAL INFORMATION IS SECURE. REVIEW OUR POLICIES BELOW.
            </p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="legal-document">

                    <div class="legal-section redact-container">
                        <h2 class="legal-heading">1. INFORMATION WE COLLECT</h2>
                        <div class="redacted-text-wrapper">
                            <span class="redact-block"></span>
                            <p class="legal-text text-silver">
                                When you create an account or make a purchase at Velvet Vogue, we securely collect your essential details: your name, contact information, shipping address, and style preferences. This information is strictly used to provide you with a seamless and personalized shopping experience.
                            </p>
                        </div>
                    </div>

                    <div class="legal-section redact-container">
                        <h2 class="legal-heading">2. DATA SECURITY</h2>
                        <div class="redacted-text-wrapper">
                            <span class="redact-block"></span>
                            <p class="legal-text text-silver">
                                Your personal and financial information is protected by industry-standard security measures. Velvet Vogue does not store your raw credit card data on our servers. All payments are securely processed through verified, encrypted third-party payment gateways.
                            </p>
                        </div>
                    </div>

                    <div class="legal-section redact-container">
                        <h2 class="legal-heading">3. COOKIES & TRACKING</h2>
                        <div class="redacted-text-wrapper">
                            <span class="redact-block"></span>
                            <p class="legal-text text-silver">
                                We use cookies to improve your browsing experience. These allow us to remember the items in your shopping cart, understand site traffic, and tailor our website to your preferences. You can disable cookies in your browser settings, though it may affect some website features.
                            </p>
                        </div>
                    </div>

                    <div class="legal-section redact-container">
                        <h2 class="legal-heading">4. THIRD-PARTY DISCLOSURE</h2>
                        <div class="redacted-text-wrapper">
                            <span class="redact-block"></span>
                            <p class="legal-text text-silver">
                                Velvet Vogue respects your privacy. We do not sell, trade, or share your personal information with outside parties. The only exceptions are our trusted shipping partners who need this information to deliver your orders, and who are legally bound to keep your details strictly confidential.
                            </p>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>