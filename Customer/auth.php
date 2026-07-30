<?php
$page_css = "auth.css";
$page_js = "auth.js";
include '../ReuseableUI/header.php';
?>

<main class="auth-studio-wrapper" id="auth-wrapper">
    <div class="cursor-aura" id="cursor-aura"></div>

    <div class="cinematic-grain"></div>
    <div class="auth-container" id="auth-container">

        <div class="auth-form-container sign-in-container">
            <div class="auth-form-content">
                <div class="auth-header">
                    <h2>Welcome Back.</h2>
                    <p>Sign in to access your Velvet Vogue account.</p>
                </div>

                <form id="vv-login-form" method="post" class="vv-form-oversized">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                    <input type="hidden" name="action" value="login">

                    <div class="s-form-group">
                        <input type="email" name="email" class="s-input" placeholder=" " id="login_email" maxlength="254" autocomplete="email" required>
                        <label class="s-label" for="login_email">Email Address</label>
                        <div class="s-input-line"></div>
                    </div>

                    <div class="s-form-group">
                        <input type="password" name="password" class="s-input" placeholder=" " id="login_password" maxlength="72" autocomplete="current-password" required>
                        <label class="s-label" for="login_password">Password</label>
                        <div class="s-input-line"></div>
                    </div>

                    <div class="auth-options">
                        <label class="vv-toggle-wrapper">
                            <input type="checkbox" id="login_remember" name="remember">
                            <span class="vv-toggle-slider"></span>
                            <span class="vv-toggle-label text-silver">Remember Me</span>
                        </label>
                        <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
                    </div>

                    <div class="s-form-submit">
                        <button type="submit" class="btn-tech massive-btn btn-shine w-100" style="justify-content: center;">
                            Login <i class="fa-solid fa-right-to-bracket ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="auth-form-container sign-up-container">
            <div class="auth-form-content">
                <div class="auth-header">
                    <h2>Create an Account.</h2>
                    <p>Join Velvet Vogue to unlock exclusive fashion perks.</p>
                </div>

                <form id="vv-register-form" method="post" class="vv-form-oversized">
                    <input type="hidden" name="_csrf" value="<?= vv_e(vv_csrf_token()) ?>">
                    <input type="hidden" name="action" value="register">

                    <div class="row">
                        <div class="col-md-6 s-form-group">
                            <input type="text" name="fname" class="s-input" placeholder=" " id="reg_fname" required>
                            <label class="s-label" for="reg_fname">First Name</label>
                            <div class="s-input-line"></div>
                        </div>
                        <div class="col-md-6 s-form-group">
                            <input type="text" name="lname" class="s-input" placeholder=" " id="reg_lname" required>
                            <label class="s-label" for="reg_lname">Last Name</label>
                            <div class="s-input-line"></div>
                        </div>
                    </div>

                    <div class="s-form-group">
                        <input type="email" name="email" class="s-input" placeholder=" " id="reg_email" maxlength="254" autocomplete="email" required>
                        <label class="s-label" for="reg_email">Email Address</label>
                        <div class="s-input-line"></div>
                    </div>

                    <div class="s-form-group" style="margin-bottom: 30px;">
                        <input type="password" name="password" class="s-input" placeholder=" " id="reg_password" minlength="10" maxlength="72" autocomplete="new-password" required>
                        <label class="s-label" for="reg_password">Password</label>
                        <div class="s-input-line"></div>
                        <div class="password-strength-bar" id="pwd-strength"></div>
                    </div>

                    <div class="s-form-submit mt-2">
                        <button type="submit" class="btn-tech massive-btn btn-shine w-100" style="justify-content: center;">
                            Join The Trend <i class="fa-solid fa-arrow-right ms-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="auth-overlay-container">
            <div class="auth-overlay">

                <div class="overlay-panel overlay-left">
                    <div class="overlay-bg" style="background-image: url('https://images.unsplash.com/photo-1507702553912-a15641e827c8?q=80&w=1200&auto=format&fit=crop');"></div>
                    <div class="overlay-gradient"></div>
                    <div class="overlay-glass-box text-mask">
                        <h2 class="gsap-auth-text">Already a Member?</h2>
                        <p class="gsap-auth-text text-silver">If you already have an account, sign in to view your orders and saved items.</p>
                        <button type="button" class="btn-premium btn-pri mt-4" id="btn-slide-login">Sign In</button>
                    </div>
                </div>

                <div class="overlay-panel overlay-right">
                    <div class="overlay-bg" style="background-image: url('https://images.unsplash.com/photo-1539109136881-3be0616acf4b?q=80&w=1200&auto=format&fit=crop');"></div>
                    <div class="overlay-gradient"></div>
                    <div class="overlay-glass-box text-mask">
                        <h2 class="gsap-auth-text">New Here?</h2>
                        <ul class="atelier-perks gsap-auth-text text-silver">
                            <li><i class="fa-solid fa-check text-gold me-2"></i> Express Worldwide Shipping</li>
                            <li><i class="fa-solid fa-check text-gold me-2"></i> Save your bespoke measurements</li>
                            <li><i class="fa-solid fa-check text-gold me-2"></i> Early access to new collections</li>
                        </ul>
                        <button type="button" class="btn-premium btn-pri mt-4" id="btn-slide-register">Join The Trend</button>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <div class="exit-transition" id="exit-transition">
        <div class="exit-curtain ec-top"></div>
        <div class="exit-curtain ec-bottom"></div>
        <div class="exit-content text-mask">
            <h1 class="gsap-exit-text" id="exit-welcome-text"></h1>
        </div>
    </div>
</main>

<?php include '../ReuseableUI/footer.php'; ?>