window.addEventListener('load', function() {
    if (document.querySelector('.auth-studio-wrapper')) {

        // 1. THE MOUSE AURA TRACKING
        const wrapper = document.getElementById('auth-wrapper');
        const aura = document.getElementById('cursor-aura');

        const motionEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
            && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (wrapper && aura && window.innerWidth > 991 && motionEnabled) {
            const moveAuraX = gsap.quickTo(aura, 'x', { duration: 0.35, ease: 'power3.out' });
            const moveAuraY = gsap.quickTo(aura, 'y', { duration: 0.35, ease: 'power3.out' });
            wrapper.addEventListener('mousemove', (event) => {
                moveAuraX(event.clientX);
                moveAuraY(event.clientY);
            }, { passive: true });
        }

        // 2. INITIAL REVEAL
        gsap.fromTo(".auth-container", { y: 40, opacity: 0, scale: 0.98 }, { y: 0, opacity: 1, scale: 1, duration: 1.2, ease: "power4.out" });

        // 3. PARALLAX SLIDING LOGIC
        const overlayContainer = document.querySelector('.auth-overlay-container');
        const overlayInner = document.querySelector('.auth-overlay');
        const signInForm = document.querySelector('.sign-in-container');
        const signUpForm = document.querySelector('.sign-up-container');
        const btnReg = document.getElementById('btn-slide-register');
        const btnLog = document.getElementById('btn-slide-login');

        const triggerRegisterView = () => {
            gsap.to(overlayContainer, { left: "0%", duration: 1, ease: "power4.inOut" });
            gsap.to(overlayInner, { left: "0%", duration: 1, ease: "power4.inOut" });
            gsap.to(signInForm, { opacity: 0, x: -30, duration: 0.5, onComplete: () => { signInForm.style.pointerEvents = "none"; }});
            gsap.to(signUpForm, { opacity: 1, x: 0, duration: 0.7, delay: 0.3 });
            signUpForm.style.pointerEvents = "auto";
        };

        const triggerLoginView = () => {
            gsap.to(overlayContainer, { left: "50%", duration: 1, ease: "power4.inOut" });
            gsap.to(overlayInner, { left: "-100%", duration: 1, ease: "power4.inOut" });
            gsap.to(signUpForm, { opacity: 0, x: 30, duration: 0.5, onComplete: () => { signUpForm.style.pointerEvents = "none"; }});
            gsap.to(signInForm, { opacity: 1, x: 0, duration: 0.7, delay: 0.3 });
            signInForm.style.pointerEvents = "auto";
        };

        if(btnReg) btnReg.addEventListener('click', (e) => { e.preventDefault(); triggerRegisterView(); });
        if(btnLog) btnLog.addEventListener('click', (e) => { e.preventDefault(); triggerLoginView(); });

        // 4. URL DEEP LINKING
        const params = new URLSearchParams(window.location.search);
        if (params.get('action') === 'register') {
            gsap.set(overlayContainer, { left: "0%" });
            gsap.set(overlayInner, { left: "0%" });
            gsap.set(signInForm, { opacity: 0, pointerEvents: "none" });
            gsap.set(signUpForm, { opacity: 1, pointerEvents: "auto" });
        }

        // 5. PASSWORD STRENGTH
        const regPassword = document.getElementById('reg_password');
        const strengthBar = document.getElementById('pwd-strength');
        if(regPassword && strengthBar) {
            regPassword.addEventListener('input', function() {
                let val = this.value; let strength = 0;
                if(val.length > 5) strength += 25; if(/[A-Z]/.test(val)) strength += 25;
                if(/[0-9]/.test(val)) strength += 25; if(/[^A-Za-z0-9]/.test(val)) strength += 25;
                strengthBar.style.width = strength + '%';
                if(strength <= 25) { strengthBar.style.background = '#ff4d4d'; strengthBar.style.boxShadow = '0 0 8px #ff4d4d'; }
                else if(strength <= 50) { strengthBar.style.background = '#feca57'; strengthBar.style.boxShadow = '0 0 8px #feca57'; }
                else { strengthBar.style.background = '#D4AF37'; strengthBar.style.boxShadow = '0 0 12px #D4AF37'; }
                if(val.length === 0) strengthBar.style.width = '0%';
            });
        }

        // 6. CINEMATIC EXIT ANIMATION
        function triggerCinematicExit(name, redirectUrl) {
            const exitOverlay = document.getElementById('exit-transition');
            const exitText = document.getElementById('exit-welcome-text');
            exitText.replaceChildren(document.createTextNode('Welcome,'), document.createElement('br'), document.createTextNode(`${name}.`));
            exitOverlay.style.display = 'flex';

            const tl = gsap.timeline({ onComplete: () => { window.location.href = redirectUrl; } });
            tl.to(".ec-top", { top: "0", duration: 0.7, ease: "power4.inOut" })
              .to(".ec-bottom", { bottom: "0", duration: 0.7, ease: "power4.inOut" }, "<")
              .fromTo(".gsap-exit-text", { y: 40, opacity: 0 }, { y: 0, opacity: 1, duration: 0.8, ease: "power3.out" }, "+=0.1")
              .to(".gsap-exit-text", { scale: 1.05, duration: 1.5, ease: "none" }, "<")
              .to(".gsap-exit-text", { opacity: 0, duration: 0.4 }, "+=0.3");
        }

        // 7. AJAX SUBMISSIONS
        const loginForm = document.getElementById('vv-login-form');
        const registerForm = document.getElementById('vv-register-form');

        // Handle Login
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = "Authenticating..."; btn.style.pointerEvents = "none";

                // Fetch form data including our new Remember Me toggle
                const formData = new FormData(this);
                const rememberBox = document.getElementById('login_remember');
                formData.append('remember', rememberBox.checked ? 1 : 0);

                fetch('../Actions/auth_action.php', { method: 'POST', body: formData })
                .then(r => r.json()).then(data => {
                    if (data.status === 'success') {
                        triggerCinematicExit(data.fname, 'shop.php');
                    } else {
                        btn.innerHTML = originalText; btn.style.pointerEvents = "auto";
                        Swal.fire({ title: 'Access Denied', text: data.message, icon: 'error', background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37', customClass: { popup: 'border border-secondary', title: 'font-playfair' }});
                    }
                }).catch(() => {
                    btn.innerHTML = originalText; btn.style.pointerEvents = "auto";
                    Swal.fire({ title: 'System Error', text: 'Server disruption.', icon: 'error', background: '#0a0a0a', color: '#fff' });
                });
            });
        }

        // Handle Registration
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('button');
                const originalText = btn.innerHTML;
                btn.innerHTML = "Initializing..."; btn.style.pointerEvents = "none";

                fetch('../Actions/auth_action.php', { method: 'POST', body: new FormData(this) })
                .then(r => r.json()).then(data => {
                    btn.innerHTML = originalText; btn.style.pointerEvents = "auto";
                    if (data.status === 'success') {
                        registerForm.reset();
                        if(strengthBar) strengthBar.style.width = '0%';
                        Swal.fire({ title: 'Identity Established', text: data.message, icon: 'success', background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37', customClass: { popup: 'border border-secondary', title: 'font-playfair' }})
                        .then(() => { btnLog.click(); });
                    } else {
                        Swal.fire({ title: 'Transmission Failed', text: data.message, icon: 'error', background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37', customClass: { popup: 'border border-secondary', title: 'font-playfair' }});
                    }
                }).catch(() => {
                    btn.innerHTML = originalText; btn.style.pointerEvents = "auto";
                    Swal.fire({ title: 'System Error', text: 'Server disruption.', icon: 'error', background: '#0a0a0a', color: '#fff' });
                });
            });
        }
    }
});