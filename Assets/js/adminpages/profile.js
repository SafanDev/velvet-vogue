/**
 * Velvet Vogue - Admin Dossier Interactive Engine
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. Initial Cinematic Reveal (Safely forces visibility)
    gsap.fromTo(".scroll-reveal", 
        { y: 20, opacity: 0 }, 
        { y: 0, opacity: 1, duration: 0.8, stagger: 0.15, ease: "power3.out" }
    );

    // 2. The 3D Digital ID Card Physics
    const idCard = document.querySelector('.id-card-panel');
    if (idCard && window.innerWidth > 991) {
        idCard.addEventListener('mousemove', e => {
            const rect = idCard.getBoundingClientRect();
            // Calculate mouse position relative to the center of the card
            const rotateX = ((e.clientY - rect.top - rect.height / 2) / (rect.height / 2)) * -6; 
            const rotateY = ((e.clientX - rect.left - rect.width / 2) / (rect.width / 2)) * 6;
            
            // GSAP handles the smoothing of the 3D transform
            gsap.to(idCard, { rotationX: rotateX, rotationY: rotateY, transformPerspective: 1000, transformOrigin: "center center", ease: "power2.out", duration: 0.4 });
            
            // Move the watermark slightly for a parallax effect
            gsap.to('.watermark-icon', { x: rotateY * -2, y: rotateX * 2, duration: 0.4, ease: "power2.out" });
        });
        
        idCard.addEventListener('mouseleave', () => {
            // Snap back to flat when mouse leaves
            gsap.to(idCard, { rotationX: 0, rotationY: 0, ease: "power3.out", duration: 0.8 });
            gsap.to('.watermark-icon', { x: 0, y: 0, duration: 0.8 });
        });
    }

    // 3. Avatar Upload Mechanics (Instant Preview)
    const avatarTrigger = document.getElementById('avatarTrigger');
    const avatarInput = document.getElementById('avatarInput');
    const avatarPreviewImg = document.getElementById('avatarPreviewImg');
    const avatarFallbackIcon = document.getElementById('avatarFallbackIcon');

    if (avatarTrigger && avatarInput) {
        // Trigger hidden file input
        avatarTrigger.addEventListener('click', () => {
            avatarInput.click();
        });

        // Load preview locally before saving
        avatarInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    avatarPreviewImg.src = e.target.result;
                    avatarPreviewImg.classList.remove('d-none');
                    if(avatarFallbackIcon) avatarFallbackIcon.classList.add('d-none');
                    
                    // Remind user to save by pulsing the save button
                    gsap.fromTo('#saveProfileBtn', 
                        { backgroundColor: 'rgba(212, 175, 55, 0.2)', boxShadow: '0 0 20px rgba(212, 175, 55, 0.4)' },
                        { backgroundColor: '#080808', boxShadow: 'none', duration: 1.5, repeat: -1, yoyo: true }
                    );
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // 4. Real-Time Security Protocol Validation (Password Matching)
    const newPass = document.querySelector('input[name="newPassword"]');
    const confPass = document.querySelector('input[name="confirmPassword"]');
    
    if (newPass && confPass) {
        function validateSecurityKeys() {
            const p1 = newPass.value;
            const p2 = confPass.value;
            const confBox = confPass.closest('.custom-input-box');
            const confIcon = confBox.querySelector('.box-icon');
            
            // If empty, reset to default dark styling
            if (p1 === "" && p2 === "") {
                gsap.to(confBox, { borderColor: "rgba(255,255,255,0.15)", boxShadow: "none", duration: 0.3 });
                gsap.to(confIcon, { color: "#555", borderRightColor: "rgba(255,255,255,0.05)", duration: 0.3 });
                return;
            }

            // Real-time evaluation
            if (p2.length > 0) {
                if (p1 === p2 && p1.length >= 8) {
                    // Match & Valid Length -> Glowing Green Success
                    gsap.to(confBox, { borderColor: "#2ecc71", boxShadow: "0 0 15px rgba(46, 204, 113, 0.2)", duration: 0.3 });
                    gsap.to(confIcon, { color: "#2ecc71", borderRightColor: "rgba(46, 204, 113, 0.3)", duration: 0.3 });
                } else {
                    // Mismatch or Too Short -> Glowing Red Danger
                    gsap.to(confBox, { borderColor: "#e74c3c", boxShadow: "0 0 15px rgba(231, 76, 60, 0.2)", duration: 0.3 });
                    gsap.to(confIcon, { color: "#e74c3c", borderRightColor: "rgba(231, 76, 60, 0.3)", duration: 0.3 });
                }
            }
        }

        // Listen for typing events
        newPass.addEventListener('input', validateSecurityKeys);
        confPass.addEventListener('input', validateSecurityKeys);
    }
});