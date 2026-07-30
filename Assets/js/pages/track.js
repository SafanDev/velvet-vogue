/**
 * Velvet Vogue - Logistics Tracking Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    // Initial Reveal
    gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, stagger: 0.2, ease: "power2.out" });

    // Focus input on load if search state
    const trackInput = document.querySelector('.tracking-input');
    if (trackInput) {
        setTimeout(() => trackInput.focus(), 500);
    }

    // ==========================================
    // THE PHOTONIC PULSE & NODE REVEAL
    // ==========================================
    const timelineContainer = document.querySelector('.tracking-timeline-container');
    
    if (timelineContainer) {
        const nodes = document.querySelectorAll('.t-node');
        const pulse = document.querySelector('.timeline-pulse-traveler');
        
        if (pulse && pulse.dataset.status !== 'cancelled' && pulse.dataset.status !== 'returned') {
            const status = pulse.dataset.status;

            // Map status to Node Index
            const statusMap = { 'pending': 0, 'processing': 1, 'shipped': 2, 'delivered': 3 };
            let targetIndex = statusMap[status];
            if (targetIndex === undefined) targetIndex = -1;

            // Reveal Nodes Sequentially
            gsap.fromTo(nodes, 
                { y: 30, opacity: 0 }, 
                { y: 0, opacity: (i) => (i <= targetIndex ? 1 : 0.3), duration: 0.8, stagger: 0.2, ease: "power3.out", delay: 0.5 }
            );

            // Animate the Photonic Pulse down the physical track
            if (targetIndex >= 0) {
                let targetY = 0;
                if(targetIndex > 0) {
                    const firstNode = nodes[0].querySelector('.node-indicator');
                    const targetNode = nodes[targetIndex].querySelector('.node-indicator');
                    targetY = targetNode.getBoundingClientRect().top - firstNode.getBoundingClientRect().top;
                }

                gsap.to(pulse, {
                    y: targetY,
                    opacity: 1,
                    duration: 1.5 + (targetIndex * 0.4), 
                    ease: "power2.inOut",
                    delay: 1,
                    onComplete: () => {
                        // Pulsing glow at destination
                        gsap.to(pulse, { scale: 1.6, opacity: 0.3, duration: 0.8, yoyo: true, repeat: -1, ease: "sine.inOut" });
                    }
                });
            }
        }

        // Cybernetic Text Decode on the Order ID
        const idElement = document.getElementById('trackedIdVal');
        if (idElement) {
            const original = idElement.innerText;
            idElement.innerText = '';
            let iteration = 0;
            const chars = '01010101XYZABCDEF';
            
            const interval = setInterval(() => {
                let scramble = '';
                for(let i=0; i<original.length; i++) {
                    scramble += chars[Math.floor(Math.random() * chars.length)];
                }
                idElement.innerText = scramble;
                iteration++;
                if(iteration >= 20) {
                    clearInterval(interval);
                    idElement.innerText = original;
                }
            }, 40);
        }

        // ==========================================
        // DYNAMIC GPS TELEMETRY DATA
        // ==========================================
        const coordsEl = document.getElementById('liveCoords');
        const speedEl = document.getElementById('liveSpeed');
        const etaEl = document.getElementById('liveEta');

        if (coordsEl && speedEl && etaEl) {
            // Base coordinates (e.g., somewhere in the Indian Ocean / approaching Sri Lanka)
            let baseLat = 6.9271;
            let baseLng = 79.8612;

            setInterval(() => {
                // Slightly jitter the coordinates to simulate active GPS tracking
                let currentLat = (baseLat + (Math.random() * 0.005)).toFixed(4);
                let currentLng = (baseLng + (Math.random() * 0.005)).toFixed(4);
                coordsEl.innerText = `${currentLat}° N, ${currentLng}° E`;

                // Slightly jitter the speed
                let currentSpeed = (60 + (Math.random() * 8)).toFixed(1);
                speedEl.innerText = `${currentSpeed} KM/H`;

            }, 2000); // Update every 2 seconds

            // Simple ETA Countdown
            let etaSeconds = 4 * 3600 + 22 * 60 + 15; // Starting at 4 hours, 22 mins, 15 secs
            setInterval(() => {
                etaSeconds--;
                if(etaSeconds < 0) etaSeconds = 0;
                
                let h = Math.floor(etaSeconds / 3600).toString().padStart(2, '0');
                let m = Math.floor((etaSeconds % 3600) / 60).toString().padStart(2, '0');
                let s = (etaSeconds % 60).toString().padStart(2, '0');
                
                etaEl.innerText = `T-MINUS ${h}:${m}:${s}`;
            }, 1000);
        }
    }
});