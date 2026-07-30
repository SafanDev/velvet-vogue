/**
 * Velvet Vogue - Wishlist Interaction Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    // ==========================================
    // 1. INITIAL REVEALS
    // ==========================================
    gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, stagger: 0.2, ease: "power2.out" });

    // ==========================================
    // 2. HEADER BADGE SYNCHRONIZATION
    // ==========================================
    const initWishCountObj = document.getElementById('initialWishlistCount');
    const initWishCount = initWishCountObj ? parseInt(initWishCountObj.value) : 0;
    
    // Call the global function if it exists (from Header)
    if (typeof window.updateGlobalWishBadge === 'function') {
        window.updateGlobalWishBadge(initWishCount, false); // false = no bounce on load
    }

    const initCartCountObj = document.getElementById('initialCartCount');
    const initCartCount = initCartCountObj ? parseInt(initCartCountObj.value) : 0;
    
    if (typeof window.updateGlobalCartBadge === 'function') {
        window.updateGlobalCartBadge(initCartCount, false);
    }

    // ==========================================
    // 3. THE BULLETPROOF FABRIC UNROLL
    // ==========================================
    const initFabricUnroll = () => {
        const tops = document.querySelectorAll('.fabric-top');
        const bottoms = document.querySelectorAll('.fabric-bottom');
        const imgs = document.querySelectorAll('.g-artifact-img');
        
        if(tops.length > 0) {
            gsap.to([tops, bottoms], {
                scaleY: 0,
                duration: 1.2,
                stagger: 0.1,
                ease: "power3.inOut",
                delay: 0.2
            });
            
            gsap.to(imgs, {
                scale: 1,
                duration: 1.5,
                stagger: 0.1,
                ease: "power3.out",
                delay: 0.2
            });
        }
    };
    initFabricUnroll();

    // ==========================================
    // 4. REMOVE GARMENT (VAPORIZE EFFECT)
    // ==========================================
    const setupRemoveButtons = () => {
        document.querySelectorAll('.btn-remove-garment').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                const wrapper = this.closest('.gallery-item-wrapper');
                const productID = wrapper.dataset.id;

                // Animate removal physically from the grid
                gsap.to(wrapper, {
                    scale: 0.9,
                    opacity: 0,
                    y: 40,
                    filter: "blur(10px)",
                    duration: 0.4,
                    ease: "power2.in",
                    onComplete: () => {
                        gsap.to(wrapper, {
                            width: 0,
                            padding: 0,
                            margin: 0,
                            duration: 0.3,
                            ease: "power2.out",
                            onComplete: () => {
                                wrapper.remove();
                                updateGalleryCount();
                            }
                        });
                    }
                });

                // Background AJAX to Database
                let formData = new FormData();
                formData.append('id', productID);
                
                fetch('../Actions/wishlist_action.php', { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => {
                    // Update header badge dynamically
                    if (typeof window.updateGlobalWishBadge === 'function') {
                        window.updateGlobalWishBadge(data.count, true);
                    }
                    // Sync across other open tabs
                    localStorage.setItem('vv_wishlist_sync', Date.now() + '|' + data.count);
                })
                .catch(err => console.error("Wishlist sync failed", err));
            });
        });
    };
    setupRemoveButtons();

    // Handle empty state gracefully
    const updateGalleryCount = () => {
        const countEl = document.getElementById('galleryCount');
        const remainingItems = document.querySelectorAll('.gallery-item-wrapper').length;
        
        if(countEl) {
            gsap.fromTo(countEl, 
                { opacity: 0, y: -10 }, 
                { opacity: 1, y: 0, duration: 0.3, onStart: () => countEl.innerText = remainingItems }
            );
        }

        // If the wishlist becomes entirely empty, reload to trigger the empty state UI
        if(remainingItems === 0) {
            setTimeout(() => {
                location.reload();
            }, 600);
        }
    };

    // ==========================================
    // 5. VIEW PRODUCT OPTIONS
    // ==========================================
    document.querySelectorAll('.btn-move-to-bag').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const productUrl = this.dataset.productUrl;
            if (productUrl) {
                window.location.href = productUrl;
            }
        });
    });

});