/**
 * Velvet Vogue - Contact Studio Logic
 */
window.addEventListener('load', function() {
    "use strict";

    if (document.querySelector('.contact-studio-wrapper')) {
        // 1. Entrance Animations
        gsap.fromTo(".gsap-s-text", 
            { y: 80, opacity: 0, skewY: 2 }, 
            { y: 0, opacity: 1, skewY: 0, duration: 1.5, stagger: 0.1, ease: "power4.out", delay: 0.2 }
        );
        
        gsap.to(".gsap-parallax-s-hero", {
            scrollTrigger: { trigger: ".studio-hero", start: "top top", end: "bottom top", scrub: true },
            y: 150, scale: 1.05, ease: "none"
        });

        gsap.fromTo(".gsap-s-panel", 
            { y: 60, opacity: 0 }, 
            { scrollTrigger: { trigger: ".studio-main-section", start: "top 80%" }, y: 0, opacity: 1, duration: 1.5, ease: "power3.out", onComplete: () => {
                gsap.to(".gsap-s-panel", { y: -12, repeat: -1, yoyo: true, duration: 3, ease: "sine.inOut" });
            }}
        );

        // 2. Auto-expanding Textarea UX
        const textarea = document.getElementById('c_message');
        if(textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = '60px'; 
                this.style.height = (this.scrollHeight) + 'px';
            });
        }

        // 3. AJAX Submission Engine
        const contactForm = document.getElementById('vv-contact-form');
        if (contactForm && typeof Swal !== "undefined") {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault(); 

                const btn = this.querySelector('button[type="submit"]');
                const originalContent = btn.innerHTML;
                
                // UX Feedback: Disable and show sending state
                btn.innerHTML = 'TRANSMITTING... <i class="fa-solid fa-circle-notch fa-spin ms-2"></i>';
                btn.style.pointerEvents = 'none';

                // Gather Data
                const formData = new FormData();
                formData.append('name', document.getElementById('c_name').value);
                formData.append('email', document.getElementById('c_email').value);
                formData.append('subject', document.getElementById('c_subject').value);
                formData.append('message', document.getElementById('c_message').value);

                // Fetch Action
                fetch('../Actions/submit_inquiry.php', {
                    method: 'POST',
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            title: "DOSSIER RECEIVED.",
                            text: `Thank you, ${data.fname}. Your inquiry has been sent to our team.`,
                            icon: "success", 
                            background: "#0a0a0a", 
                            color: "#FFFFFF", 
                            iconColor: "#D4AF37", 
                            confirmButtonColor: "#D4AF37", 
                            confirmButtonText: "CLOSE", 
                            customClass: { popup: 'border border-secondary', title: 'font-playfair' }
                        });
                        contactForm.reset();
                        if(textarea) textarea.style.height = '60px';
                    } else {
                        Swal.fire({
                            title: "TRANSMISSION ERROR",
                            text: data.message,
                            icon: "error",
                            background: "#0a0a0a",
                            color: "#fff"
                        });
                    }
                })
                .catch(err => {
                    console.error("Inquiry Error:", err);
                })
                .finally(() => {
                    btn.innerHTML = originalContent;
                    btn.style.pointerEvents = 'auto';
                    document.activeElement.blur(); 
                });
            });
        }
    }
});