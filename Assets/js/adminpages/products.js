/**
 * Velvet Vogue - Products Table Engine
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // 1. TOP CARD REVEAL
    const isDesktop = window.innerWidth > 991;

    if (typeof gsap !== "undefined" && isDesktop) {
        gsap.set(".top-glow-card", { y: 20, opacity: 0 });
        gsap.set(".ledger-row", { opacity: 0, y: 10 });

        const tl = gsap.timeline();
        tl.to(".top-glow-card", { y: 0, opacity: 1, duration: 0.6, ease: "power3.out", delay: 0.1 })
          .to(".ledger-row", { opacity: 1, y: 0, duration: 0.4, stagger: 0.04, ease: "power2.out" }, "-=0.2");
    } else {
        document.querySelectorAll('.ledger-row').forEach(row => {
            row.style.opacity = 1; row.style.transform = 'none';
        });
        const card = document.querySelector('.top-glow-card');
        if(card) { card.style.opacity = 1; card.style.transform = 'none'; }
    }

    // 2. INSTANT SEARCH
    const searchInput = document.getElementById('productSearch');
    if(searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const term = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.ledger-row');

            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                if(text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }

    // 3. CUSTOM DELETE MODAL LOGIC
    let currentDeleteId = null;
    let currentDeleteBtn = null;

    const modalOverlay = document.getElementById('deleteModalOverlay');
    const modalBox = document.getElementById('deleteModalBox');
    const btnCancel = document.getElementById('cancelDeleteBtn');
    const btnConfirm = document.getElementById('confirmDeleteBtn');

    // Toast Setup
    const toastEl = document.getElementById('actionToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;
    if (toastEl) toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });

    // Function called by the Trash Can icon
    window.triggerDeleteModal = function(productID, btnElement) {
        currentDeleteId = productID;
        currentDeleteBtn = btnElement;

        modalOverlay.classList.add('active');
        modalBox.classList.add('active');
    };

    // Close Modal Function
    function closeModal() {
        modalOverlay.classList.remove('active');
        modalBox.classList.remove('active');
        currentDeleteId = null;
        currentDeleteBtn = null;
    }

    // Event Listeners for closing
    if(btnCancel) btnCancel.addEventListener('click', closeModal);
    if(modalOverlay) modalOverlay.addEventListener('click', closeModal);

    // Confirm Deletion Execution
    if(btnConfirm) {
        btnConfirm.addEventListener('click', function() {
            if(!currentDeleteId) return;

            const btnText = this.innerText;
            this.innerText = 'PURGING...';
            this.disabled = true;

            const formData = new FormData();
            formData.append('productID', currentDeleteId);

            fetch('product-delete.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if(data.status === 'success') {

                    // Show success toast
                    if(toastInstance) {
                        toastEl.classList.remove('border-danger');
                        toastEl.classList.add('border-success');
                        toastMessage.innerHTML = `<i class="fa-solid fa-check me-2"></i> Record Purged Successfully`;
                        toastInstance.show();
                    }

                    // Animate the row removal
                    const row = currentDeleteBtn.closest('.ledger-row');
                    gsap.to(row, {
                        opacity: 0, scaleY: 0, height: 0, padding: 0, margin: 0,
                        duration: 0.4, ease: "power3.in",
                        onComplete: () => {
                            row.remove();
                            // Update total count dynamically
                            const countBadge = document.getElementById('totalProductsBadge');
                            if(countBadge) {
                                let currentCount = parseInt(countBadge.innerText);
                                countBadge.innerText = (currentCount - 1) + " Registered";
                            }
                        }
                    });

                    closeModal();

                } else {
                    throw new Error(data.message || "Failed to delete.");
                }
            })
            .catch(error => {
                if(toastInstance) {
                    toastEl.classList.remove('border-success');
                    toastEl.classList.add('border-danger');
                    window.vvSetMessage(toastMessage, `Error: ${error.message}`, { iconClass: 'fa-solid fa-triangle-exclamation me-2' });
                    toastInstance.show();
                }
                closeModal();
            })
            .finally(() => {
                this.innerText = btnText;
                this.disabled = false;
            });
        });
    }

});