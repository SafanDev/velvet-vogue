<?php
$page_css = "forgot_password.css";
$page_js = "forgot_password.js";
include '../ReuseableUI/header.php';
?>

<main class="recovery-studio-wrapper" id="recovery-wrapper">
    <div class="cursor-aura" id="cursor-aura"></div>

    <div class="cinematic-grain"></div>
    <div class="recovery-bg"></div>
    <div class="recovery-gradient"></div>

    <div class="recovery-container">
        <div class="recovery-glass-box text-mask" id="recovery-glass">

            <div class="recovery-icon-wrapper ai-key-turn">
                <i class="fa-solid fa-key"></i>
            </div>

            <div class="recovery-header">
                <h2 class="gsap-rev-text">Secure Recovery.</h2>
                <p class="gsap-rev-text">Password recovery is not enabled on this portfolio deployment. Contact the site administrator to reset a demo account.</p>
            </div>

            <div class="alert alert-dark border-secondary text-light p-4 mb-4" role="status">
                Automated recovery is intentionally disabled until an authenticated email service is configured.
            </div>

            <div class="recovery-footer mt-4">
                <a href="auth.php" class="return-link ai-arrow-snap">
                    <i class="fa-solid fa-arrow-left"></i> Return to Authentication
                </a>
            </div>

        </div>
    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>