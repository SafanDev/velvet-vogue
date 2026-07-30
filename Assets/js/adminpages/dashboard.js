/**
 * Velvet Vogue - Dashboard Interactive Engine
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. PERFORMANCE: Enable hardware acceleration
    if (typeof gsap !== "undefined") {
        gsap.config({ force3D: true });
    }
    const isDesktop = window.innerWidth > 991;

    // 2. SCROLL OBSERVER (For BI Matrix Panels)
    const observerOptions = { root: null, rootMargin: '0px', threshold: 0.1 };
    const scrollObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            } else {
                entry.target.classList.remove('visible'); 
            }
        });
    }, observerOptions);

    document.querySelectorAll('.scroll-reveal').forEach(el => {
        scrollObserver.observe(el);
    });

    // 3. CARVE ENTRANCE (Safe GSAP logic with Bug Fix)
    if (typeof gsap !== "undefined" && isDesktop) {
        // JS hides elements safely before animating
        gsap.set(".carve-box", { clipPath: "inset(0 100% 0 0)", visibility: "visible" });
        
        const tl = gsap.timeline();
        tl.to(".laser-trace", { width: "100%", duration: 0.8, ease: "power3.inOut", delay: 0.1 })
          .to(".laser-trace-vertical", { height: "100%", duration: 0.8, ease: "power3.inOut" }, "<")
          // Added clearProps to completely remove clip-path from HTML after animation finishes
          .to(".carve-box", { clipPath: "inset(0 0% 0 0)", duration: 0.1, clearProps: "clipPath" }, "-=0.1")
          .to(".laser-trace, .laser-trace-vertical", { opacity: 0, duration: 0.3 })
          .fromTo(".carve-box > *", { opacity: 0, y: 10 }, { opacity: 1, y: 0, duration: 0.4, stagger: 0.05 }, "-=0.2")
          .call(animateCounters);
    } else {
        // Fallback for mobile / JS failure
        document.querySelectorAll('.carve-box').forEach(el => {
            el.style.visibility = 'visible';
            el.style.clipPath = 'none';
        });
        animateCounters();
    }

    // 4. COUNTER ENGINE 
    function animateCounters() {
        document.querySelectorAll('.hero-panel.active .counter-value, .tile-wrapper.active-tile .counter-value, .bi-panel .counter-value').forEach(counter => {
            const target = +counter.getAttribute('data-target');
            if (target === 0) { counter.innerText = "0"; return; }

            let startTime = null;
            const duration = 1200;

            function animation(currentTime) {
                if (startTime === null) startTime = currentTime;
                const timeElapsed = currentTime - startTime;
                const progress = Math.min(timeElapsed / duration, 1);
                const easeProgress = progress === 1 ? 1 : 1 - Math.pow(2, -10 * progress);
                counter.innerText = Math.floor(easeProgress * target).toLocaleString('en-IN');

                if (timeElapsed < duration) requestAnimationFrame(animation);
                else counter.innerText = target.toLocaleString('en-IN'); 
            }
            requestAnimationFrame(animation);
        });
    }

    if (typeof gsap === "undefined") { animateCounters(); }

    // 5. THE PERFECT DOM SWAP MECHANISM 
    const tiles = document.querySelectorAll('.tile-wrapper');
    const tileContainer = document.getElementById('tileRowContainer');
    
    tiles.forEach(tile => {
        tile.addEventListener('click', function() {
            if(!this.classList.contains('active-tile')) return; 
            
            const targetId = this.getAttribute('data-id');
            const currentHero = document.querySelector('.hero-panel.active');
            const currentId = currentHero.getAttribute('data-id');
            
            if(targetId === currentId) return;

            if (typeof gsap !== "undefined") {
                const swapTl = gsap.timeline();

                swapTl.to(currentHero, { opacity: 0, y: 20, duration: 0.2, ease: "power2.in", onComplete: () => {
                    currentHero.classList.remove('active');
                    const targetHero = document.querySelector(`.hero-panel[data-id="${targetId}"]`);
                    targetHero.classList.add('active');
                    gsap.fromTo(targetHero, { opacity: 0, y: -20 }, { opacity: 1, y: 0, duration: 0.3, ease: "power2.out" });
                    
                    const counters = targetHero.querySelectorAll('.counter-value');
                    counters.forEach(c => c.innerText = "0"); 
                    setTimeout(animateCounters, 50);
                }}, 0);

                swapTl.to(this.querySelector('.data-tile'), { opacity: 0, scale: 0.9, duration: 0.2, ease: "power2.in", onComplete: () => {
                    this.classList.remove('active-tile');
                    this.style.display = 'none';
                    
                    const newTile = document.querySelector(`.tile-wrapper[data-id="${currentId}"]`);
                    tileContainer.insertBefore(newTile, this);
                    newTile.style.display = 'block';
                    newTile.classList.add('active-tile');
                    
                    const tilesArr = Array.from(tileContainer.querySelectorAll('.tile-wrapper'));
                    tilesArr.sort((a, b) => parseInt(a.dataset.order) - parseInt(b.dataset.order));
                    tilesArr.forEach(t => tileContainer.appendChild(t));

                    gsap.fromTo(newTile.querySelector('.data-tile'), { opacity: 0, scale: 0.9 }, { opacity: 1, scale: 1, duration: 0.3, ease: "power2.out" });
                }}, 0);
            }
        });
    });

    // 6. REAL REVENUE CHART 
    const ctx = document.getElementById('revenueChart');
    if (ctx && typeof Chart !== "undefined" && typeof realChartLabels !== "undefined") {
        const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 200);
        gradient.addColorStop(0, 'rgba(212, 175, 55, 0.4)'); 
        gradient.addColorStop(1, 'rgba(212, 175, 55, 0.0)'); 

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: realChartLabels,
                datasets: [{
                    label: 'Revenue',
                    data: realChartValues, 
                    borderColor: 'rgba(212,175,55,0.8)', borderWidth: 2, backgroundColor: gradient,
                    fill: true, pointRadius: 4, pointBackgroundColor: '#000', pointBorderColor: '#D4AF37', tension: 0.3 
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                layout: { padding: { bottom: 10 } },
                plugins: { legend: { display: false }, tooltip: {
                    backgroundColor: 'rgba(10,10,10,0.9)', titleFont: { family: 'Montserrat', size: 10 },
                    bodyFont: { family: 'Courier New', size: 12, weight: 'bold' }, bodyColor: '#D4AF37',
                    borderColor: 'rgba(212,175,55,0.3)', borderWidth: 1, displayColors: false,
                } }, 
                scales: { 
                    x: { grid: { display: false, drawBorder: false }, ticks: { color: '#888', font: { family: 'Montserrat', size: 9 } } }, 
                    y: { 
                        grid: { color: 'rgba(255,255,255,0.05)', drawBorder: true, borderColor: 'rgba(255,255,255,0.1)' }, 
                        ticks: { color: '#888', maxTicksLimit: 4, font: { family: 'Courier New', size: 10 }, callback: function(value) { return (value / 1000) + 'k'; } }, 
                        beginAtZero: true 
                    } 
                },
                interaction: { mode: 'index', intersect: false },
                animation: { duration: 1500, easing: 'easeOutQuart' } 
            }
        });
    }
});