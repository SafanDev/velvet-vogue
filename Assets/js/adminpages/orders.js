/**
 * Velvet Vogue - Order Command Logic (With Spotlight Physics)
 * Handles both Order Status and Payment Status AJAX Updates.
 */

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.8, stagger: 0.1, ease: "power3.out" });

    // ==========================================
    // 1. THE SPOTLIGHT 3D HOVER EFFECT
    // ==========================================
    const cards = document.querySelectorAll('.spotlight-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;

            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            const rotateX = ((y - centerY) / centerY) * -6; 
            const rotateY = ((x - centerX) / centerX) * 6;
            
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-5px)`;
        });
        
        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
            card.style.transition = `transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)`;
        });
        
        card.addEventListener('mouseenter', () => { card.style.transition = `none`; });
    });

    // ==========================================
    // 2. TACTICAL SEARCH (For Orders Ledger)
    // ==========================================
    const searchInput = document.getElementById('orderSearch');
    const orderRows = document.querySelectorAll('.ledger-row');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            orderRows.forEach(row => {
                const searchableText = row.getAttribute('data-search');
                if (searchableText.indexOf(filter) > -1) {
                    row.style.display = '';
                    gsap.to(row, {opacity: 1, duration: 0.3});
                } else {
                    gsap.to(row, {opacity: 0, duration: 0.3, onComplete: () => { row.style.display = 'none'; }});
                }
            });
        });
    }

    // ==========================================
    // 3. INLINE AJAX STATUS UPDATE (Orders & Payments)
    // ==========================================
    const statusSelects = document.querySelectorAll('.elegant-select');
    const toastEl = document.getElementById('statusToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;

    if (toastEl) toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });

    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const newStatus = this.value;
            const orderID = this.getAttribute('data-order-id');
            const updateType = this.getAttribute('data-update-type'); // 'orderStatus' or 'paymentStatus'
            const originalClass = this.className; 
            
            // Generate prefix based on select type
            const prefix = updateType === 'paymentStatus' ? 'pay-status-' : 'status-';
            
            // Remove the old dynamic color class and apply the new one
            this.className = originalClass.replace(new RegExp(`${prefix}[a-z]+`), `${prefix}${newStatus}`);

            const formData = new FormData();
            const actionTarget = updateType === 'paymentStatus' ? 'update_payment_status' : 'update_status';
            
            formData.append('action', actionTarget);
            formData.append('orderID', orderID);
            formData.append('newStatus', newStatus);

            // Fetch to correct endpoint (works for both orders.php and order-view.php)
            const endpoint = window.location.pathname.includes('order-view') ? `order-view.php?id=${orderID}` : 'orders.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    
                    // Specific Logic to update the Glowing Dot if modifying Payment Status
                    if(updateType === 'paymentStatus') {
                        const dot = document.querySelector(`.dot-${orderID}`);
                        if(dot) {
                            let color = '#777';
                            if(newStatus === 'paid') color = '#2ecc71';
                            if(newStatus === 'failed') color = '#e74c3c';
                            if(newStatus === 'refunded') color = '#f39c12';
                            
                            dot.style.backgroundColor = color;
                            dot.style.boxShadow = `0 0 8px ${color}`;
                        }
                    }

                    if (toastMessage && toastInstance) {
                        toastEl.classList.remove('border-danger');
                        toastEl.classList.add('border-success');
                        toastMessage.innerHTML = `<i class="fa-solid fa-check text-success me-2"></i> ${updateType === 'paymentStatus' ? 'Payment' : 'Order'} status updated.`;
                        toastInstance.show();
                    }
                } else {
                    this.className = originalClass; // Revert UI
                    this.value = Array.from(this.options).find(opt => originalClass.includes(opt.value)).value;
                    
                    if (toastMessage && toastInstance) {
                        toastEl.classList.remove('border-success');
                        toastEl.classList.add('border-danger');
                        toastMessage.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-danger me-2"></i> Failed to update.`;
                        toastInstance.show();
                    }
                }
            })
            .catch(error => {
                console.error('Network Error:', error);
                this.className = originalClass; // Revert UI
                this.value = Array.from(this.options).find(opt => originalClass.includes(opt.value)).value;
                
                if (toastMessage && toastInstance) {
                    toastEl.classList.remove('border-success');
                    toastEl.classList.add('border-danger');
                    toastMessage.innerHTML = `<i class="fa-solid fa-wifi text-danger me-2"></i> Network error occurred.`;
                    toastInstance.show();
                }
            });
        });
    });
});