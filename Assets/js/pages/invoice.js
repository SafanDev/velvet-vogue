/**
 * Velvet Vogue - Invoice Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    // Initial Cinematic Reveal
    gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, ease: "power2.out" });

    // Text Scramble Engine for Titles
    const decodeElements = document.querySelectorAll('.decode-text');
    decodeElements.forEach(el => {
        const original = el.innerText;
        el.innerText = '';
        let iteration = 0;
        const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        const interval = setInterval(() => {
            let scramble = '';
            for(let i=0; i<original.length; i++) scramble += chars[Math.floor(Math.random() * chars.length)];
            el.innerText = scramble;
            if(++iteration >= 15) { clearInterval(interval); el.innerText = original; }
        }, 40);
    });

    // Hash Security Scramble
    const hashEl = document.getElementById('cryptoHash');
    if (hashEl) {
        const original = hashEl.getAttribute('data-original');
        hashEl.innerText = '';
        let iter = 0;
        const chars = '0123456789abcdef';
        const intv = setInterval(() => {
            let scramble = '0x';
            for(let i=0; i<30; i++) scramble += chars[Math.floor(Math.random() * chars.length)];
            hashEl.innerText = scramble;
            if(++iter >= 20) { clearInterval(intv); hashEl.innerText = original; }
        }, 40);
    }

    // Dynamic Barcode Generator
    const barcodeContainer = document.getElementById('digitalBarcode');
    if (barcodeContainer) {
        // Generate 45 random width vertical bars to look like a barcode
        for(let i = 0; i < 45; i++) {
            const bar = document.createElement('div');
            bar.className = 'bar-line';
            
            // Random width between 1px and 4px
            const width = Math.floor(Math.random() * 4) + 1;
            bar.style.width = width + 'px';
            
            // Random opacity for a scanned/realistic look
            bar.style.opacity = (Math.random() * 0.6) + 0.4;

            barcodeContainer.appendChild(bar);
        }
    }
});