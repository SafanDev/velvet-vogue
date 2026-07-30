/**
 * Velvet Vogue - Lookbook Editorial Experience
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    if (typeof gsap === "undefined" || typeof ScrollTrigger === "undefined") {
        console.error("GSAP or ScrollTrigger missing.");
        return;
    }

    gsap.registerPlugin(ScrollTrigger);

    // 1. Custom Editorial Cursor
    const cursor = document.querySelector('.lb-cursor');
    const interactiveElements = document.querySelectorAll('.lb-image-wrapper, .btn-tech, .lb-hotspot, a');

    const cursorMotionEnabled = !window.matchMedia('(prefers-reduced-motion: reduce)').matches
        && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    if (cursor && window.innerWidth > 991 && cursorMotionEnabled) {
        const moveCursorX = gsap.quickTo(cursor, 'x', { duration: 0.12, ease: 'power2.out' });
        const moveCursorY = gsap.quickTo(cursor, 'y', { duration: 0.12, ease: 'power2.out' });
        document.addEventListener('mousemove', (event) => {
            moveCursorX(event.clientX);
            moveCursorY(event.clientY);
        }, { passive: true });

        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', () => cursor.classList.add('active'));
            el.addEventListener('mouseleave', () => cursor.classList.remove('active'));
        });
    }

    // 2. Hero Animations
    const heroTl = gsap.timeline();
    
    // Slow zoom out of background
    gsap.to('.lb-hero-bg img', { scale: 1, duration: 3, ease: "power2.out" });

    // Staggered text reveal
    heroTl.fromTo(".scroll-reveal-txt", 
        { y: 50, opacity: 0, clipPath: "inset(100% 0 0 0)" },
        { y: 0, opacity: 1, clipPath: "inset(0% 0 0 0)", duration: 1, stagger: 0.2, ease: "power4.out", delay: 0.5 }
    );

    // 3. Statement Text Reveal on Scroll
    gsap.utils.toArray('.reveal-up').forEach(text => {
        gsap.fromTo(text, 
            { y: "100%" }, 
            { y: "0%", duration: 1.2, ease: "power4.out",
              scrollTrigger: { trigger: text.parentElement, start: "top 85%" }
            }
        );
    });

    // 4. THE INFINITE HORIZONTAL SCROLL ENGINE (Desktop Only)
    let mm = gsap.matchMedia();

    mm.add("(min-width: 992px)", () => {
        const wrap = document.querySelector('.lb-horizontal-wrap');
        const container = document.querySelector('.lb-horizontal-container');
        const panels = gsap.utils.toArray('.lb-panel');

        if (panels.length > 1) {
            // Calculate how far to push the container left based on its total width minus 1 screen width
            const scrollDistance = container.scrollWidth - window.innerWidth;

            let scrollTween = gsap.to(container, {
                x: -scrollDistance, // Move horizontally
                ease: "none",
                scrollTrigger: {
                    trigger: wrap,
                    pin: true,           // Pin the wrapper to the screen
                    scrub: 1,            // Smooth scrubbing taking 1 second to catch up
                    end: () => "+=" + scrollDistance, // Pin lasts exactly as long as the width
                    invalidateOnRefresh: true
                }
            });

            // Add internal Parallax to images while scrolling horizontally
            panels.forEach((panel) => {
                let img = panel.querySelector('.lb-parallax-img');
                if(img) {
                    gsap.to(img, {
                        x: "15%", // Move image slightly right as panel moves left
                        ease: "none",
                        scrollTrigger: {
                            trigger: panel,
                            containerAnimation: scrollTween, // Bind this animation to the horizontal timeline
                            start: "left right", // When panel enters from right
                            end: "right left",   // When panel leaves to left
                            scrub: true
                        }
                    });
                }
            });
        }
    });

    // 5. Masonry Grid Fade Up
    gsap.utils.toArray('.lb-masonry-item').forEach(item => {
        gsap.fromTo(item, 
            { y: 100, opacity: 0 },
            { y: 0, opacity: 1, duration: 1.2, ease: "power3.out",
              scrollTrigger: { trigger: item, start: "top 90%" }
            }
        );
    });
});