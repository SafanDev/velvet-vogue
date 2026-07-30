/**
 * Velvet Vogue - Ultra-Premium Analytics Engine
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. Initial Page Reveal
    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.6, stagger: 0.1, ease: "power3.out" });

    // 2. Spotlight 3D Hover Logic
    const cards = document.querySelectorAll('.spotlight-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
            card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
            
            const centerX = rect.width / 2; const centerY = rect.height / 2;
            const rotateX = ((e.clientY - rect.top - centerY) / centerY) * -1.5; 
            const rotateY = ((e.clientX - rect.left - centerX) / centerX) * 1.5; 
            card.style.transform = `perspective(2000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(2000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
            card.style.transition = `transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)`;
        });
    });

    // 3. Tab Switching
    const tabs = document.querySelectorAll('.luxury-tab');
    const sections = document.querySelectorAll('.report-section');
    const exportBtn = document.getElementById('dynamicExportBtn');
    const exportBtnText = document.getElementById('exportBtnText');

    tabs.forEach(tab => {
        tab.addEventListener('click', function() {
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            sections.forEach(s => { s.classList.add('d-none'); s.classList.remove('active'); });
            const target = document.getElementById(this.getAttribute('data-target'));
            target.classList.remove('d-none');
            setTimeout(() => {
                target.classList.add('active');
                gsap.fromTo(target, { opacity: 0, y: 15 }, { opacity: 1, y: 0, duration: 0.4 });
            }, 10);
            exportBtn.href = exportBaseUrl + this.getAttribute('data-export');
            exportBtnText.innerText = "Export " + this.getAttribute('data-label');
        });
    });

    // ========================================================
    // CHART.JS MASTER CONFIG
    // ========================================================
    if (typeof Chart !== 'undefined') {
        
        Chart.defaults.color = '#888';
        Chart.defaults.font.family = 'Montserrat';

        // ----------------------------------------------------
        // CHART 1: REVENUE (Golden Neon Line)
        // ----------------------------------------------------
        if (document.getElementById('revenueChart') && finLabels.length > 0) {
            const ctxFin = document.getElementById('revenueChart').getContext('2d');
            let gradFin = ctxFin.createLinearGradient(0, 0, 0, 400);
            gradFin.addColorStop(0, 'rgba(212, 175, 55, 0.25)');
            gradFin.addColorStop(0.5, 'rgba(212, 175, 55, 0.05)');
            gradFin.addColorStop(1, 'rgba(0, 0, 0, 0)');

            new Chart(ctxFin, {
                type: 'line',
                data: {
                    labels: finLabels,
                    datasets: [{
                        label: 'Revenue',
                        data: finValues,
                        borderColor: '#d4af37',
                        borderWidth: 3,
                        backgroundColor: gradFin,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 4,
                        pointBackgroundColor: '#000',
                        pointBorderColor: '#d4af37',
                        pointBorderWidth: 2,
                        pointHoverRadius: 7,
                        pointHoverBackgroundColor: '#d4af37',
                        pointHoverBorderColor: '#fff',
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.95)',
                            padding: 15,
                            borderColor: '#d4af37',
                            borderWidth: 1,
                            displayColors: false,
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { family: 'monospace', size: 14 },
                            callbacks: { label: c => 'Rs. ' + c.parsed.y.toLocaleString() }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(255,255,255,0.03)', borderDash: [5,5] },
                            ticks: { font: { family: 'monospace' }, callback: v => 'Rs.' + (v >= 1000 ? v/1000 + 'k' : v) }
                        }
                    }
                }
            });
        }

        // ----------------------------------------------------
        // CHART 3: AUDIENCE (Emerald Clear Stepped Chart)
        // ----------------------------------------------------
        if (document.getElementById('audienceChart') && audLabels.length > 0) {
            const ctxAud = document.getElementById('audienceChart').getContext('2d');
            let gradAud = ctxAud.createLinearGradient(0, 0, 0, 400);
            gradAud.addColorStop(0, 'rgba(46, 204, 113, 0.3)');
            gradAud.addColorStop(1, 'rgba(0, 0, 0, 0)');

            new Chart(ctxAud, {
                type: 'line', // Changed to area line with "stepped" logic for extreme clarity
                data: {
                    labels: audLabels,
                    datasets: [{
                        label: 'New Users',
                        data: audValues,
                        borderColor: '#2ecc71',
                        backgroundColor: gradAud,
                        borderWidth: 3,
                        fill: true,
                        stepped: true, // Stepped lines make daily registration changes easier to read.
                        pointRadius: 2,
                        pointBackgroundColor: '#2ecc71',
                        hoverBackgroundColor: '#fff'
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0,0,0,0.95)',
                            borderColor: '#2ecc71',
                            borderWidth: 1,
                            displayColors: false,
                            callbacks: { label: c => c.parsed.y + ' New Registrations' }
                        }
                    },
                    scales: {
                        x: { grid: { display: false } },
                        y: { 
                            beginAtZero: true, 
                            grid: { color: 'rgba(255,255,255,0.03)', borderDash: [5,5] },
                            ticks: { stepSize: 1 } 
                        }
                    }
                }
            });
        }

        // ----------------------------------------------------
        // CHART 2: ORDERS (Order Status Bar)
        // ----------------------------------------------------
        if (document.getElementById('orderStatusChart')) {
            const ctxOrd = document.getElementById('orderStatusChart').getContext('2d');
            new Chart(ctxOrd, {
                type: 'bar',
                data: {
                    labels: ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled', 'Returned'],
                    datasets: [{
                        data: osData,
                        backgroundColor: ['#fff', '#00f0ff', '#d4af37', '#2ecc71', '#e74c3c', '#a29bfe'],
                        borderRadius: 5,
                        barPercentage: 0.5
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false } },
                        y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.05)' } }
                    }
                }
            });
        }
    }
});