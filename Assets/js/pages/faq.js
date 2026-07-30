/**
 * Velvet Vogue - FAQ Interactive Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. Initial Page Reveal
    gsap.from(".scroll-reveal", {
        y: 30, opacity: 0, duration: 1, stagger: 0.2, ease: "power3.out"
    });

    // 2. Accordion Mechanism
    const faqItems = document.querySelectorAll('.faq-item');

    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        const answer = item.querySelector('.faq-answer');

        question.addEventListener('click', () => {
            const isOpen = item.classList.contains('open');

            // Close all other items (Optional: remove if you want multiple open)
            faqItems.forEach(i => {
                i.classList.remove('open');
                gsap.to(i.querySelector('.faq-answer'), { height: 0, duration: 0.5, ease: "power2.inOut" });
            });

            if (!isOpen) {
                item.classList.add('open');
                gsap.to(answer, { height: "auto", duration: 0.5, ease: "power2.inOut" });
            }
        });
    });

    // 3. Category Dial (Smooth Scroll & Highlighting)
    const navItems = document.querySelectorAll('.faq-nav-item');
    
    navItems.forEach(nav => {
        nav.addEventListener('click', function() {
            const targetID = this.getAttribute('data-target');
            const targetSection = document.getElementById(targetID);

            if(targetSection) {
                // Remove active from others
                navItems.forEach(n => n.classList.remove('active'));
                this.classList.add('active');

                // Smooth scroll to section
                window.scrollTo({
                    top: targetSection.offsetTop - 120,
                    behavior: 'smooth'
                });
            }
        });
    });

    // 4. Scroll Spy (Highlighting nav based on scroll position)
    window.addEventListener('scroll', () => {
        let current = "";
        const sections = document.querySelectorAll('.faq-category-group');
        
        sections.forEach(section => {
            const sectionTop = section.offsetTop;
            if (pageYOffset >= sectionTop - 150) {
                current = section.getAttribute('id');
            }
        });

        navItems.forEach(nav => {
            nav.classList.remove('active');
            if (nav.getAttribute('data-target') === current) {
                nav.classList.add('active');
            }
        });
    });
});