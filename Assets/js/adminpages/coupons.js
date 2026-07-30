/**
 * Velvet Vogue - Coupons Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.8, stagger: 0.1, ease: "power3.out" });

    // 1. Spotlight 3D Hover (Subtle Breath Effect)
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

    // 2. Search
    const searchInput = document.getElementById('couponSearch');
    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            document.querySelectorAll('.ledger-row').forEach(row => {
                const text = row.getAttribute('data-search');
                row.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        });
    }

    const toastEl = document.getElementById('actionToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;
    if (toastEl) toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });

    // 3. Slide Panel & Auto-Date Logic
    const panel = document.getElementById('sidePanel');
    const overlay = document.getElementById('sidePanelOverlay');

    // Auto-Date Formatter
    function getLocalISOString(date) {
        const offset = date.getTimezoneOffset() * 60000;
        return (new Date(date - offset)).toISOString().slice(0, 16);
    }

    function closePanel() {
        panel.classList.remove('active');
        overlay.classList.remove('active');
        document.getElementById('addForm').reset();
    }

    document.getElementById('openAddCouponModal').addEventListener('click', () => {
        panel.classList.add('active');
        overlay.classList.add('active');

        // Auto-fill Dates instantly (Current Time -> Next Week)
        const now = new Date();
        document.getElementById('startsAt').value = getLocalISOString(now);

        const nextWeek = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
        document.getElementById('expiresAt').value = getLocalISOString(nextWeek);
    });

    document.getElementById('closeSidePanel').addEventListener('click', closePanel);
    overlay.addEventListener('click', closePanel);

    // 4. Form Submit
    document.getElementById('addForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const btnTextSpan = btn.querySelector('.btn-text');
        const ogText = btnTextSpan.innerText;
        btnTextSpan.innerText = 'SAVING...';
        btn.disabled = true;

        fetch('coupons.php', { method: 'POST', body: new FormData(this) })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                closePanel();
                if(toastInstance) {
                    toastEl.classList.remove('border-danger'); toastEl.classList.add('border-success');
                    window.vvSetMessage(toastMessage, res.message, { iconClass: 'fa-solid fa-check me-2', detail: 'Refresh to view ledger.' });
                    toastInstance.show();
                }
            } else throw new Error(res.message);
        })
        .catch(err => {
            if(toastInstance) {
                toastEl.classList.remove('border-success'); toastEl.classList.add('border-danger');
                window.vvSetMessage(toastMessage, err.message, { iconClass: 'fa-solid fa-triangle-exclamation me-2' });
                toastInstance.show();
            }
        })
        .finally(() => { btnTextSpan.innerText = ogText; btn.disabled = false; });
    });

    // 5. Toggle Status
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const isChecked = this.checked;
            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('couponID', this.getAttribute('data-id'));
            formData.append('isActive', isChecked ? '1' : '0');

            fetch('coupons.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status !== 'success') throw new Error(res.message);
            })
            .catch(err => { this.checked = !isChecked; });
        });
    });

    // 6. Custom Delete Modal
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
        this.innerText = 'DELETING...';
        this.disabled = true;

        const formData = new FormData();
        formData.append('action', 'delete_coupon');
        formData.append('couponID', currentDeleteId);

        fetch('coupons.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(res => {
            if(res.status === 'success') {
                const row = currentDeleteBtn.closest('tr');
                gsap.to(row, { opacity: 0, scaleY: 0, height: 0, duration: 0.4, onComplete: () => row.remove() });
                closeDelete();
            } else throw new Error();
        })
        .finally(() => { this.innerText = btnText; this.disabled = false; });
    });
});