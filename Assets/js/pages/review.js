/**
 * Velvet Vogue - Review Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    // Initial Reveal
    gsap.from(".gsap-fade-in", { y: 30, opacity: 0, duration: 1, ease: "power3.out" });

    // Text Scramble Engine
    const decodeElements = document.querySelectorAll('.decode-text');
    decodeElements.forEach(el => {
        const original = el.innerText;
        el.innerText = '';
        let iteration = 0;
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        const interval = setInterval(() => {
            let scramble = '';
            for(let i=0; i<original.length; i++) scramble += chars[Math.floor(Math.random() * chars.length)];
            el.innerText = scramble;
            if(++iteration >= 15) { clearInterval(interval); el.innerText = original; }
        }, 40);
    });

    // Auto-expanding textarea
    const textarea = document.getElementById('reviewComment');
    if(textarea) {
        textarea.addEventListener('input', function() {
            this.style.height = '50px'; 
            this.style.height = (this.scrollHeight) + 'px';
        });
    }

    // ==========================================
    // ANIMATED FACE LOGIC
    // ==========================================
    const stars = document.querySelectorAll('.star-rating input');
    const core = document.getElementById('reactionCore');
    const coreStatusText = document.getElementById('coreStatusText');

    const statusMessages = {
        '1': 'VERY POOR',
        '2': 'POOR',
        '3': 'AVERAGE',
        '4': 'GOOD',
        '5': 'EXCELLENT'
    };

    // Aligned to the new Face CSS colors
    const statusColors = {
        '1': '#ff3333', // Crimson
        '2': '#ff9f43', // Orange
        '3': '#feca57', // Yellow
        '4': '#00f0ff', // Sci-Fi Cyan
        '5': '#D4AF37'  // Gold
    };

    stars.forEach(star => {
        star.addEventListener('change', function() {
            const val = this.value;
            
            core.className = 'reaction-core state-' + val;
            
            coreStatusText.innerText = statusMessages[val];
            coreStatusText.style.color = statusColors[val];
            
            // GSAP Physical Bounce Feedback on the Glass Dome
            const dome = core.querySelector('.core-center-dome');
            if (dome) {
                gsap.killTweensOf(dome);
                gsap.fromTo(dome, 
                    { scale: 0.85 }, 
                    { scale: 1, duration: 0.8, ease: "elastic.out(1.2, 0.3)" }
                );
            }

            // Glitch text effect on status label update
            let iter = 0;
            const origStatus = statusMessages[val];
            const intv = setInterval(() => {
                let sc = '';
                for(let i=0; i<origStatus.length; i++) sc += '01X'[Math.floor(Math.random()*3)];
                coreStatusText.innerText = sc;
                if(++iter > 8) { clearInterval(intv); coreStatusText.innerText = origStatus; }
            }, 30);
        });
    });

    // ==========================================
    // AJAX SUBMISSION
    // ==========================================
    const reviewForm = document.getElementById('reviewForm');
    if (reviewForm && typeof Swal !== "undefined") {
        reviewForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const selectedRating = document.querySelector('input[name="rating"]:checked');
            if (!selectedRating) {
                Swal.fire({ 
                    title: "RATING REQUIRED", 
                    text: "Please select a star rating before submitting.", 
                    icon: "warning", 
                    background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37' 
                });
                gsap.fromTo("#starRatingSystem", {x: -10}, {x: 0, duration: 0.3, ease: "bounce.out(2)"});
                return;
            }

            const btn = document.getElementById('btnSubmitReview');
            const ogText = btn.innerHTML;
            btn.innerHTML = 'SUBMITTING... <i class="fa-solid fa-circle-notch fa-spin ms-2"></i>';
            btn.style.pointerEvents = 'none';

            const formData = new FormData(reviewForm);

            fetch('../Actions/submit_review.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({ 
                        title: "REVIEW SUBMITTED", 
                        text: data.message, 
                        icon: "success", 
                        background: '#0a0a0a', color: '#fff', iconColor: '#D4AF37', confirmButtonColor: '#D4AF37' 
                    }).then(() => {
                        window.location.href = 'dashboard.php';
                    });
                } else {
                    Swal.fire({ title: "ERROR", text: data.message, icon: "error", background: '#0a0a0a', color: '#fff' });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({ title: "NETWORK ERROR", text: "Please try again.", icon: "error", background: '#0a0a0a', color: '#fff' });
            })
            .finally(() => {
                btn.innerHTML = ogText;
                btn.style.pointerEvents = 'auto';
            });
        });
    }
});