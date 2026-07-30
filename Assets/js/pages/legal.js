/**
 * Velvet Vogue - Legal Protocols Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // Register GSAP plugins if they exist
    if (typeof gsap !== "undefined" && typeof ScrollTrigger !== "undefined") {
        gsap.registerPlugin(ScrollTrigger);

        // General Fade in
        gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, stagger: 0.2, ease: "power2.out" });

        // ==========================================
        // PRIVACY POLICY: REDACTION REVEAL
        // ==========================================
        const redactBlocks = document.querySelectorAll('.redact-block');
        if (redactBlocks.length > 0) {
            redactBlocks.forEach(block => {
                gsap.to(block, {
                    width: "0%", 
                    duration: 1.2, 
                    ease: "power3.inOut",
                    scrollTrigger: {
                        trigger: block,
                        start: "top 85%", // Triggers when the block is 85% down the viewport
                        toggleActions: "play none none none"
                    }
                });
            });
        }
    }

    // ==========================================
    // TERMS OF SERVICE: SIGNATURE PAD
    // ==========================================
    const canvas = document.getElementById('signatureCanvas');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const btnClear = document.getElementById('btnClearSig');
        const btnSubmit = document.getElementById('btnSubmitSig');
        const placeholder = document.querySelector('.sig-placeholder');
        const seal = document.getElementById('sigSeal');

        let isDrawing = false;
        let hasDrawn = false;

        // Styling the pen
        ctx.strokeStyle = '#D4AF37'; // Gold ink
        ctx.lineWidth = 3;
        ctx.lineJoin = 'round';
        ctx.lineCap = 'round';

        // Mouse Events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);

        // Touch Events for Mobile
        canvas.addEventListener('touchstart', handleTouchStart, {passive: false});
        canvas.addEventListener('touchmove', handleTouchMove, {passive: false});
        canvas.addEventListener('touchend', stopDrawing);

        function startDrawing(e) {
            isDrawing = true;
            hasDrawn = true;
            if(placeholder) placeholder.style.opacity = '0'; // Hide "SIGN HERE"
            
            // Allow drawing again if they already stamped it but want to redo
            if (seal.classList.contains('active')) {
                seal.classList.remove('active');
            }

            draw(e);
        }

        function draw(e) {
            if (!isDrawing) return;
            
            // Calculate accurate mouse position based on canvas bounds
            const rect = canvas.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            ctx.lineTo(x, y);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(x, y);
        }

        function stopDrawing() {
            isDrawing = false;
            ctx.beginPath();
        }

        function handleTouchStart(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent("mousedown", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }

        function handleTouchMove(e) {
            e.preventDefault();
            const touch = e.touches[0];
            const mouseEvent = new MouseEvent("mousemove", {
                clientX: touch.clientX,
                clientY: touch.clientY
            });
            canvas.dispatchEvent(mouseEvent);
        }

        // Action Buttons
        if (btnClear) {
            btnClear.addEventListener('click', () => {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                hasDrawn = false;
                if(placeholder) placeholder.style.opacity = '1';
                seal.classList.remove('active');
            });
        }

        if (btnSubmit) {
            btnSubmit.addEventListener('click', () => {
                if (!hasDrawn) {
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: 'SIGNATURE REQUIRED',
                            text: 'Please provide your digital signature to authorize the agreement.',
                            icon: 'warning',
                            background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37'
                        });
                    } else {
                        alert("Please sign before submitting.");
                    }
                    return;
                }

                // Trigger the golden stamp animation
                seal.classList.add('active');

                // Simulate saving to database
                setTimeout(() => {
                    if (typeof Swal !== "undefined") {
                        Swal.fire({
                            title: 'AUTHORIZATION SECURED',
                            text: 'Your signature has been cryptographically logged into the Velvet Vogue matrix.',
                            icon: 'success',
                            background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37'
                        });
                    }
                }, 800);
            });
        }
    }
});