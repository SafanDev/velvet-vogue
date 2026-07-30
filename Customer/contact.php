<?php
$page_css = "contact.css";
$page_js = "contact.js";
include '../ReuseableUI/header.php';
?>

<main class="contact-studio-wrapper">
    <div class="cinematic-grain"></div>

    <section class="studio-hero">
        <div class="studio-hero-bg gsap-parallax-s-hero" style="background-image: url('https://images.unsplash.com/photo-1509319117193-57bab727e09d?q=80&w=2000&auto=format&fit=crop');"></div>
        <div class="studio-hero-overlay"></div>

        <div class="container h-100 d-flex flex-column justify-content-center align-items-center text-center position-relative">
            <div class="studio-hero-content">
                <div class="text-mask">
                    <span class="c-overline gsap-s-text">Customer Support</span>
                </div>

                <div class="text-mask" style="overflow: visible;">
                    <h1 class="gsap-s-text hover-wave-text">
                        <span style="--i:1">C</span><span style="--i:2">o</span><span style="--i:3">n</span><span style="--i:4">t</span><span style="--i:5">a</span><span style="--i:6">c</span><span style="--i:7">t</span><span style="--i:8" class="white-text">.</span>
                    </h1>
                </div>

                <div class="text-mask">
                    <p class="c-subtitle gsap-s-text mx-auto text-silver">We are here to help. Contact us for custom orders, private appointments, or any general questions you may have.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="studio-main-section">
        <div class="container">
            <div class="row">

                <div class="col-lg-4 studio-info-col">
                    <div class="info-premium-card gsap-s-panel">

                        <div class="text-mask">
                            <h2 class="gsap-s-item section-title">Details</h2>
                        </div>
                        <div class="s-divider gsap-s-item"></div>

                        <div class="s-info-block gsap-s-item">
                            <h3>Address</h3>
                            <p class="text-silver">No. 45, Galle Road,<br>Colombo 03, Sri Lanka</p>
                        </div>

                        <div class="s-info-block gsap-s-item">
                            <h3>Contact Info</h3>
                            <p><a href="tel:+94112345678" class="s-hover-link">+94 11 234 5678</a></p>
                            <p><a href="mailto:support@velvetvogue.lk" class="s-hover-link">support@velvetvogue.lk</a></p>
                        </div>

                        <div class="s-info-block gsap-s-item" style="margin-bottom: 50px;">
                            <h3>Business Hours</h3>
                            <p class="text-silver">Monday — Friday<br>09:00 — 18:00 (GMT+5:30)</p>
                        </div>

                        <div class="s-social-block gsap-s-item">
                            <h3>Follow Us</h3>
                            <div class="s-social-grid">
                                <a href="#" class="s-soc-icon insta" title="Instagram"><i class="fa-brands fa-instagram"></i></a>
                                <a href="#" class="s-soc-icon fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                                <a href="#" class="s-soc-icon x" title="X"><i class="fa-brands fa-x-twitter"></i></a>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="col-lg-7 offset-lg-1 studio-form-col">

                    <div class="form-preamble text-mask mb-5">
                        <h2 class="gsap-s-form text-white">Send us a message.</h2>
                        <p class="gsap-s-form text-silver">Fill out the form below and our team will get back to you as soon as possible.</p>
                    </div>

                    <div class="studio-form-wrapper gsap-s-form">

                        <form id="vv-contact-form" method="post" class="vv-form-oversized">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">

                            <div class="row">
                                <div class="col-md-6 s-form-group">
                                    <span class="field-number">01</span>
                                    <input type="text" class="s-input" placeholder=" " id="c_name" required>
                                    <label class="s-label" for="c_name">Full Name</label>
                                    <div class="s-input-line"></div>
                                </div>
                                <div class="col-md-6 s-form-group">
                                    <span class="field-number">02</span>
                                    <input type="email" class="s-input" placeholder=" " id="c_email" required>
                                    <label class="s-label" for="c_email">Email Address</label>
                                    <div class="s-input-line"></div>
                                </div>
                            </div>

                            <div class="s-form-group">
                                <span class="field-number">03</span>
                                <input type="text" class="s-input" placeholder=" " id="c_subject" required>
                                <label class="s-label" for="c_subject">Subject</label>
                                <div class="s-input-line"></div>
                            </div>

                            <div class="s-form-group">
                                <span class="field-number">04</span>
                                <textarea class="s-input s-textarea" placeholder=" " id="c_message" rows="1" required></textarea>
                                <label class="s-label" for="c_message">Your Message</label>
                                <div class="s-input-line"></div>
                            </div>

                            <div class="s-form-submit mt-5">
                                <button type="submit" class="btn-tech massive-btn btn-shine">
                                    Send Message <i class="fa-solid fa-arrow-right ms-2"></i>
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </section>
</main>

<?php include '../ReuseableUI/footer.php'; ?>