window.addEventListener('load', () => {
    const wrapper = document.getElementById('recovery-wrapper');
    const aura = document.getElementById('cursor-aura');

    const motionEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (wrapper && aura && window.innerWidth > 991 && typeof gsap !== 'undefined' && motionEnabled) {
        const moveAuraX = gsap.quickTo(aura, 'x', { duration: 0.35, ease: 'power3.out' });
        const moveAuraY = gsap.quickTo(aura, 'y', { duration: 0.35, ease: 'power3.out' });
        wrapper.addEventListener('mousemove', (event) => {
            moveAuraX(event.clientX);
            moveAuraY(event.clientY);
        }, { passive: true });
    }

    if (typeof gsap !== 'undefined') {
        gsap.fromTo('#recovery-glass', { y: -50, opacity: 0, rotationX: 10 }, { y: 0, opacity: 1, rotationX: 0, duration: 1.2, ease: 'power4.out' });
        gsap.fromTo('.gsap-rev-text', { y: 20, opacity: 0 }, { y: 0, opacity: 1, stagger: 0.15, duration: 1, delay: 0.4, ease: 'power3.out' });
    }
});
