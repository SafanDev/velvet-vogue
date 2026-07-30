<?php
$page_css = "about.css";
$page_js = "about.js";
include '../ReuseableUI/header.php';
?>

<main class="about-page-wrapper">

    <div class="cinematic-grain"></div>

    <section class="abt-hero">

        <div class="abt-marquee-container">
            <div class="abt-marquee marquee-left">
                <span>UNCOMPROMISING LUXURY &mdash; UNCOMPROMISING LUXURY &mdash; UNCOMPROMISING LUXURY &mdash; </span>
            </div>
            <div class="abt-marquee marquee-right outline-text">
                <span>VELVET VOGUE EST. 2026 &mdash; VELVET VOGUE EST. 2026 &mdash; VELVET VOGUE EST. 2026 &mdash; </span>
            </div>
        </div>

        <div class="abt-hero-center text-mask">
            <h1 class="gsap-abt-hero">IDENTITY</h1>
            <p class="gsap-abt-hero text-silver" style="transition-delay: 0.1s;">Redefining the architecture of modern fashion.</p>
        </div>
    </section>

    <section class="abt-artifact-section" id="artifact-trigger">

        <div class="abt-artifact-visual" id="artifact-pinned">
            <div class="fc-bracket fcb-tl"></div><div class="fc-bracket fcb-tr"></div>
            <div class="fc-bracket fcb-bl"></div><div class="fc-bracket fcb-br"></div>
            <div class="artifact-img-wrap">
                <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&q=80&w=1200" alt="The Signature Trench" id="artifact-img">
            </div>
            <div class="artifact-label text-silver">[ REF. VV-001 — THE SIGNATURE TRENCH ]</div>
        </div>

        <div class="abt-artifact-narrative">
            <div class="narrative-block left-block">
                <div class="n-chapter text-silver">[ 01. The Vision ]</div>
                <h2 class="text-white">Obliterate<br>The Line.</h2>
                <p class="text-silver">Velvet Vogue was born from a singular vision: to dismantle the boundary between high-end formal wear and premium casual streetwear. We don't believe in dressing for the occasion; we believe the occasion bends to how you dress.</p>
            </div>

            <div class="narrative-block right-block" style="margin-top: 50vh;">
                <div class="n-chapter text-silver">[ 02. The Material ]</div>
                <h2 class="text-white">Zero<br>Compromise.</h2>
                <p class="text-silver">Every thread, every cut, and every silhouette is meticulously engineered. We source only the darkest onyx silks, the heaviest raw cottons, and the most uncompromising cashmere. If it isn't perfect, it is discarded.</p>
            </div>

            <div class="narrative-block left-block" style="margin-top: 50vh; padding-bottom: 30vh;">
                <div class="n-chapter text-silver">[ 03. The Architecture ]</div>
                <h2 class="text-white">Structural<br>Integrity.</h2>
                <p class="text-silver">Our garments are constructed with sharp lines, deep shadows, and subtle metallic accents that catch the light precisely when they need to. You aren't just wearing clothes; you are wearing confidence.</p>
            </div>
        </div>
    </section>

    <section class="abt-horizontal-wrapper">
        <div class="abt-track" id="abt-track">

            <div class="abt-panel panel-intro">
                <h2>The<br><span class="gold-text">Process.</span></h2>
                <p class="text-silver">A look inside the Velvet Vogue Design Studio.</p>
                <div class="scroll-line" style="margin-top: 40px; transform: rotate(-90deg); position: relative; left: 30px;"></div>
            </div>

            <div class="abt-panel panel-card">
                <div class="p-card-inner">
                    <img loading="lazy" decoding="async" src="https://static.vecteezy.com/system/resources/thumbnails/074/132/200/small/product-black-crewneck-t-shirt-dark-surface-photo.jpg" alt="Drafting">
                    <div class="p-card-content">
                        <h3>01. Drafting</h3>
                        <p class="text-silver">Mathematical precision meets raw creativity. Every design starts as a hand-drawn blueprint.</p>
                    </div>
                </div>
            </div>

            <div class="abt-panel panel-card">
                <div class="p-card-inner">
                    <img loading="lazy" decoding="async" src="https://www.sgtgroup.net/wp-content/uploads/2022/05/Do-Your-Customers-Really-Care-About-Ethical-Sourcing.jpg" alt="Sourcing">
                    <div class="p-card-content">
                        <h3>02. Sourcing</h3>
                        <p class="text-silver">We scour the globe for textiles that possess the exact weight, drape, and feel required for our aesthetic.</p>
                    </div>
                </div>
            </div>

            <div class="abt-panel panel-card">
                <div class="p-card-inner">
                    <img loading="lazy" decoding="async" src="https://images.unsplash.com/photo-1536867520774-5b4f2628a69b?w=500&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Mnx8dGFpbG9yaW5nfGVufDB8fDB8fHww" alt="Tailoring">
                    <div class="p-card-content">
                        <h3>03. Tailoring</h3>
                        <p class="text-silver">Assembled by master artisans. The stitching is flawless, the hardware is custom, the result is definitive.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="abt-founder-section">
        <div class="container">
            <div class="founder-monolith gsap-reveal-founder">
                <div class="row g-0 align-items-center">
                    <div class="col-lg-5">
                        <div class="f-img-box">
                            <picture>
                                <source srcset="../Assets/images/adminVV.webp" type="image/webp">
                                <img loading="lazy" decoding="async" src="../Assets/images/adminVV.webp" width="1024" height="1024" alt="John Finlo">
                            </picture>
                            <div class="f-overlay-gradient"></div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div class="f-text-box">
                            <i class="fa-solid fa-quote-left quote-icon"></i>
                            <blockquote class="text-white">"True style is unapologetic. It demands attention without having to speak. That is what we engineer here."</blockquote>
                            <div class="f-author">
                                <h4 class="gold-text">John Finlo</h4>
                                <span class="text-silver">Founder & Creative Director</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

</main>

<?php include '../ReuseableUI/footer.php'; ?>