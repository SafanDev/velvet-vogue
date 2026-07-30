/**
 * Velvet Vogue - Final Crown Reveal & Login Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. Cinematic Reveal (Card + Crown Shimmer)
    if (typeof gsap !== "undefined") {
        const tl = gsap.timeline();

        // Fade in the main card
        tl.fromTo("#loginCard",
            { y: 30, opacity: 0 },
            { y: 0, opacity: 1, duration: 1, ease: "power3.out", delay: 0.1 }
        );

        // Pop in the Crown Plate
        tl.fromTo("#crownReveal",
            { scale: 0.8, opacity: 0 },
            { scale: 1, opacity: 1, duration: 0.6, ease: "back.out(1.5)" },
            "-=0.4"
        );

        // Trigger the CSS Shimmer Animation via Class
        tl.call(() => {
            document.getElementById('crownReveal').classList.add('reveal-shimmer');
        });

    } else {
        document.getElementById('loginCard').style.opacity = 1;
        document.getElementById('crownReveal').style.opacity = 1;
        document.getElementById('crownReveal').style.transform = 'scale(1)';
        document.getElementById('crownReveal').classList.add('reveal-shimmer');
    }

    // 2. Thanos Snap Text Transition Effect
    const passwordField = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePassword');
    const eyeIcon = document.getElementById('eyeIcon');
    let isPasswordVisible = false;

    toggleBtn.addEventListener('click', () => {
        passwordField.classList.remove('snap-form-together');
        passwordField.classList.add('snap-dust-away');

        setTimeout(() => {
            isPasswordVisible = !isPasswordVisible;
            passwordField.type = isPasswordVisible ? 'text' : 'password';
            eyeIcon.className = isPasswordVisible ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
            eyeIcon.style.color = isPasswordVisible ? '#D4AF37' : '#444';

            passwordField.classList.remove('snap-dust-away');
            passwordField.classList.add('snap-form-together');
        }, 400); // Matches CSS animation duration
    });

    // 3. AJAX Submission & Cinematic Curtain
    const form = document.getElementById('adminLoginForm');
    const errorBox = document.getElementById('errorBox');
    const errorText = document.getElementById('errorText');
    const btnSubmit = document.getElementById('btnSubmit');
    const btnContainer = document.querySelector('.btn-mask-container');

    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            btnContainer.classList.add('active');
            btnSubmit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i>';
            btnSubmit.style.pointerEvents = "none";
            errorBox.style.display = "none";

            const formData = new FormData(form);

            fetch('adminActions/login_action.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {

                    document.body.classList.add('closing-curtains');

                    setTimeout(() => {
                        document.body.classList.add('sealing-curtains');
                    }, 800);

                    setTimeout(() => {
                        window.location.href = 'dashboard.php';
                    }, 1800);

                } else {
                    errorText.innerText = data.msg || data.message || 'Login failed.';
                    errorBox.style.display = "flex";
                    btnSubmit.innerHTML = 'LOGIN';
                    btnContainer.classList.remove('active');
                    btnSubmit.style.pointerEvents = "auto";
                    if (typeof gsap !== "undefined") gsap.fromTo(errorBox, {x: -4}, {x: 4, duration: 0.1, yoyo: true, repeat: 3});
                }
            })
            .catch(() => {
                errorText.innerText = "System error. Matrix unreachable.";
                errorBox.style.display = "flex";
                btnSubmit.innerHTML = 'LOGIN';
                btnContainer.classList.remove('active');
                btnSubmit.style.pointerEvents = "auto";
            });
        });
    }
});