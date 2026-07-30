/**
 * Velvet Vogue - Reviews Moderation Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // Staggered reveal for grid cards
    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.6, stagger: 0.05, ease: "power3.out" });

    // Spotlight 3D Hover (Subtle Breath Effect)
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
            const rotateX = ((y - centerY) / centerY) * -1.5;
            const rotateY = ((x - centerX) / centerX) * 1.5;

            card.style.transform = `perspective(2000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-2px)`;
        });

        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(2000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
            card.style.transition = `transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)`;
        });

        card.addEventListener('mouseenter', () => {
            card.style.transition = `none`;
        });
    });

    // Instant Search Filter
    const searchInput = document.getElementById('reviewSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.review-card').forEach(card => {
                const text = card.getAttribute('data-search');
                card.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        });
    }

    const toastEl = document.getElementById('actionToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;
    if (toastEl) toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });

    // Dynamic Metric Recalculation
    function recalculateMetrics() {
        let total = 0, pending = 0, approved = 0, totalStars = 0;

        document.querySelectorAll('.review-card').forEach(card => {
            total++;
            const status = card.getAttribute('data-status');
            const rating = parseInt(card.getAttribute('data-rating'));

            if (status === '0') pending++;
            if (status === '1') approved++;
            totalStars += rating;
        });

        let avg = total > 0 ? (totalStars / total).toFixed(1) : "0.0";

        const ids = ['countTotal', 'countPending', 'countApproved', 'countAvg'];
        const values = [total, pending, approved, avg];

        ids.forEach((id, index) => {
            const el = document.getElementById(id);
            if(el.innerText !== values[index].toString()) {
                el.innerText = values[index];
                gsap.fromTo(el, { scale: 1.5, color: '#fff' }, { scale: 1, clearProps: "color", duration: 0.5 });
            }
        });
        document.getElementById('totalBadge').innerText = total + " Submissions";
    }

    // Toggle Approval Status
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const isChecked = this.checked;
            const card = this.closest('.review-card');
            const textSpan = this.parentElement.nextElementSibling;

            // Instantly update UI locally
            textSpan.innerText = isChecked ? 'PUBLISHED' : 'PENDING';
            textSpan.className = 'status-text ' + (isChecked ? 'text-success' : 'text-danger');

            if(isChecked) {
                card.classList.remove('card-pending');
                card.setAttribute('data-status', '1');
            } else {
                card.classList.add('card-pending');
                card.setAttribute('data-status', '0');
            }

            const formData = new FormData();
            formData.append('action', 'toggle_approval');
            formData.append('reviewID', this.getAttribute('data-id'));
            formData.append('isApproved', isChecked ? '1' : '0');

            fetch('reviews.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    recalculateMetrics(); // Update top numbers
                } else throw new Error(res.message);
            })
            .catch(err => {
                // Revert on failure
                this.checked = !isChecked;
                textSpan.innerText = !isChecked ? 'PUBLISHED' : 'PENDING';
                textSpan.className = 'status-text ' + (!isChecked ? 'text-success' : 'text-danger');
                if(!isChecked) card.classList.remove('card-pending'); else card.classList.add('card-pending');
            });
        });
    });

    // Custom Delete Modal
    let currentDeleteId = null;
    let currentDeleteBtn = null;
    const modalOverlay = document.getElementById('deleteModalOverlay');
    const modalBox = document.getElementById('deleteModalBox');

    window.triggerDeleteModal = function(id, btn) {
        currentDeleteId = id; currentDeleteBtn = btn;
        modalOverlay.classList.add('active'); modalBox.classList.add('active');
    };

    function closeDelete() {
        modalOverlay.classList.remove('active'); modalBox.classList.remove('active');
        currentDeleteId = null; currentDeleteBtn = null;
    }

    document.getElementById('cancelDeleteBtn').addEventListener('click', closeDelete);
    modalOverlay.addEventListener('click', closeDelete);

    document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
        if(!currentDeleteId) return;
        const btnText = this.innerText;
        this.innerText = 'PURGING...';
        this.disabled = true;

        const formData = new FormData();
        formData.append('action', 'delete_review');
        formData.append('reviewID', currentDeleteId);

        fetch('reviews.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                const card = currentDeleteBtn.closest('.review-card');

                // Animate card shrinking out of the masonry grid
                gsap.to(card, {
                    opacity: 0, scale: 0.8, duration: 0.4, ease: "back.in(1.7)",
                    onComplete: () => {
                        card.remove();
                        recalculateMetrics();
                    }
                });

                closeDelete();

                if(toastInstance) {
                    toastEl.classList.remove('border-danger'); toastEl.classList.add('border-success');
                    window.vvSetMessage(toastMessage, res.message, { iconClass: 'fa-solid fa-check me-2' });
                    toastInstance.show();
                }
            } else throw new Error();
        })
        .finally(() => { this.innerText = btnText; this.disabled = false; });
    });
});