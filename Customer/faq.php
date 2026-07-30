<?php
require_once __DIR__ . '/../Config/bootstrap.php';
vv_session_start();
// Specific CSS/JS for this page
$page_css = 'faq.css';
$page_js = 'faq.js';

require_once '../Config/db.php';
include '../ReuseableUI/header.php';
?>

<main class="faq-main-wrapper">
    <section class="faq-hero">
        <div class="container">
            <div class="faq-hero-inner scroll-reveal">
                <span class="simple-label text-gold">Help Center</span>
                <h1 class="massive-title text-white">How can we<br><span class="fw-light italic">assist you?</span></h1>
            </div>
        </div>
    </section>

    <section class="faq-content-section container">
        <div class="row g-5">

            <aside class="col-lg-4 d-none d-lg-block">
                <div class="faq-nav-sticky">
                    <ul class="faq-nav-list">
                        <li class="faq-nav-item active" data-target="orders">
                            <span class="nav-num">01</span>
                            <span class="nav-label">Orders & Shipping</span>
                            <div class="nav-line"></div>
                        </li>
                        <li class="faq-nav-item" data-target="returns">
                            <span class="nav-num">02</span>
                            <span class="nav-label">Returns & Refunds</span>
                            <div class="nav-line"></div>
                        </li>
                        <li class="faq-nav-item" data-target="sizing">
                            <span class="nav-num">03</span>
                            <span class="nav-label">Sizing & Fit</span>
                            <div class="nav-line"></div>
                        </li>
                        <li class="faq-nav-item" data-target="payments">
                            <span class="nav-num">04</span>
                            <span class="nav-label">Payments & Security</span>
                            <div class="nav-line"></div>
                        </li>
                    </ul>

                    <div class="faq-contact-card mt-5">
                        <p class="text-silver">Can't find your answer?</p>
                        <a href="contact.php" class="btn-tech">Speak to Concierge</a>
                    </div>
                </div>
            </aside>

            <div class="col-lg-8">

                <div class="faq-category-group" id="orders">
                    <h3 class="category-title scroll-reveal">01. Orders & Shipping</h3>

                    <div class="faq-accordion scroll-reveal">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>How do I track my Velvet Vogue shipment?</span>
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Once your order is dispatched, you will receive a transmission via email containing your unique tracking ID. You can also monitor your status directly within your Customer Dashboard under 'Order History'.</p>
                            </div>
                        </div>

                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Do you offer international delivery?</span>
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Currently, Velvet Vogue operates exclusively within Sri Lanka. However, we are architecting our global logistics network and plan to expand to international waters soon.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category-group mt-5" id="returns">
                    <h3 class="category-title scroll-reveal">02. Returns & Refunds</h3>

                    <div class="faq-accordion scroll-reveal">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>What is the return window for luxury items?</span>
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>We offer a 7-day complimentary return period for all unworn items. The garments must be returned in their original packaging with all security tags intact.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category-group mt-5" id="sizing">
                    <h3 class="category-title scroll-reveal">03. Sizing & Fit</h3>

                    <div class="faq-accordion scroll-reveal">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>How do I find my perfect fit?</span>
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>Each product page features a detailed 'Size Architecture' guide. Our cuts are generally 'True to Fit'—if you prefer a more avant-garde oversized look, we recommend sizing up.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="faq-category-group mt-5" id="payments">
                    <h3 class="category-title scroll-reveal">04. Payments & Security</h3>

                    <div class="faq-accordion scroll-reveal">
                        <div class="faq-item">
                            <div class="faq-question">
                                <span>Which payment methods are accepted?</span>
                                <i class="fa-solid fa-plus"></i>
                            </div>
                            <div class="faq-answer">
                                <p>We accept all major credit cards (Visa, Mastercard, Amex) and bank transfers. Our payment gateway uses end-to-end encryption to ensure your financial data remains classified.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<?php include '../ReuseableUI/footer.php'; ?>