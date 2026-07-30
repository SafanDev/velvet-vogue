/**
 * Velvet Vogue - Client Management Logic
 */

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.8, stagger: 0.1, ease: "power3.out" });

    // Spotlight 3D Hover
    const cards = document.querySelectorAll('.spotlight-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
            card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
            const centerX = rect.width / 2; const centerY = rect.height / 2;
            card.style.transform = `perspective(1000px) rotateX(${((e.clientY - rect.top - centerY) / centerY) * -4}deg) rotateY(${((e.clientX - rect.left - centerX) / centerX) * 4}deg) translateY(-3px)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
            card.style.transition = `transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)`;
        });
        card.addEventListener('mouseenter', () => { card.style.transition = `none`; });
    });

    // Tactical Search
    const searchInput = document.getElementById('userSearch');
    const userRows = document.querySelectorAll('.ledger-row');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            userRows.forEach(row => {
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

    // AJAX Globals
    const toastEl = document.getElementById('actionToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;
    if (toastEl) { toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 }); }

    // THE MASTER RECALCULATOR: Scans DOM, recalculates 4 metrics exactly.
    function recalculateMetrics() {
        let total = 0, admins = 0, customers = 0, suspended = 0;

        document.querySelectorAll('.ledger-row').forEach(row => {
            total++;
            const role = row.getAttribute('data-role');
            const isActive = row.getAttribute('data-active') === '1';

            if (role === 'admin') admins++;
            if (role === 'customer' && isActive) customers++;
            if (!isActive) suspended++;
        });

        const ids = ['countTotal', 'countAdmins', 'countCustomers', 'countSuspended'];
        const values = [total, admins, customers, suspended];

        ids.forEach((id, index) => {
            const el = document.getElementById(id);
            if(parseInt(el.innerText) !== values[index]) {
                el.innerText = values[index];
                gsap.fromTo(el, { scale: 1.5, color: '#fff' }, { scale: 1, clearProps: "color", duration: 0.5 });
            }
        });
        document.getElementById('totalUsersBadge').innerText = total + " Profiles";
    }

    function sendUpdate(data, successCallback, revertCallback) {
        fetch('users.php', { method: 'POST', body: data })
        .then(res => res.json())
        .then(res => {
            if (res.status === 'success') {
                if(toastInstance) {
                    toastEl.classList.remove('border-danger');
                    toastEl.classList.add('border-success');
                    window.vvSetMessage(toastMessage, res.message, { iconClass: 'fa-solid fa-check text-success me-2' });
                    toastInstance.show();
                }
                if(successCallback) successCallback();
                recalculateMetrics();
            } else { throw new Error(res.message); }
        })
        .catch(err => {
            if(toastInstance) {
                toastEl.classList.remove('border-success');
                toastEl.classList.add('border-danger');
                window.vvSetMessage(toastMessage, err.message, { iconClass: 'fa-solid fa-triangle-exclamation text-danger me-2' });
                toastInstance.show();
            }
            if(revertCallback) revertCallback();
        });
    }

    // 1. Role Select
    document.querySelectorAll('.role-select').forEach(select => {
        let previousValue = select.value;
        select.addEventListener('change', function() {
            const row = this.closest('.ledger-row');
            const newValue = this.value;

            const formData = new FormData();
            formData.append('action', 'change_role');
            formData.append('userID', this.getAttribute('data-user-id'));
            formData.append('role', newValue);

            sendUpdate(formData,
                () => {
                    row.setAttribute('data-role', newValue);
                    previousValue = newValue;
                },
                () => { this.value = previousValue; }
            );
        });
    });

    // 2. Active / Ban Toggle Switch
    document.querySelectorAll('.status-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const isChecked = this.checked;
            const row = this.closest('.ledger-row');
            const statusText = this.nextElementSibling.nextElementSibling; // The text span after the slider

            statusText.innerText = isChecked ? 'ACTIVE' : 'BANNED';
            if(!isChecked) row.classList.add('row-suspended');
            else row.classList.remove('row-suspended');

            const formData = new FormData();
            formData.append('action', 'toggle_status');
            formData.append('userID', this.getAttribute('data-user-id'));
            formData.append('isActive', isChecked ? '1' : '0');

            sendUpdate(formData,
                () => { row.setAttribute('data-active', isChecked ? '1' : '0'); },
                () => {
                    this.checked = !isChecked;
                    statusText.innerText = !isChecked ? 'ACTIVE' : 'BANNED';
                    if(isChecked) row.classList.add('row-suspended');
                    else row.classList.remove('row-suspended');
                }
            );
        });
    });

    // Add Identity Slide Panel
    const panel = document.getElementById('sidePanel');
    const overlay = document.getElementById('sidePanelOverlay');
    const openBtn = document.getElementById('openAddUserModal');
    const closeBtn = document.getElementById('closeSidePanel');
    const addForm = document.getElementById('addUserForm');
    const submitBtn = document.getElementById('submitUserBtn');

    function closePanel() {
        panel.classList.remove('active');
        overlay.classList.remove('active');
        addForm.reset();
    }

    if(openBtn) { openBtn.addEventListener('click', () => { panel.classList.add('active'); overlay.classList.add('active'); }); }
    if(closeBtn && overlay) { closeBtn.addEventListener('click', closePanel); overlay.addEventListener('click', closePanel); }

    if(addForm) {
        addForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const originalBtnText = submitBtn.innerText;
            submitBtn.innerText = 'AUTHORIZING...';
            submitBtn.disabled = true;

            fetch('users.php', { method: 'POST', body: new FormData(this) })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    closePanel();
                    if(toastInstance) {
                        toastEl.classList.remove('border-danger');
                        toastEl.classList.add('border-success');
                        window.vvSetMessage(toastMessage, res.message, { iconClass: 'fa-solid fa-check text-success me-2', detail: 'Refresh page to view in ledger.' });
                        toastInstance.show();
                    }
                } else { throw new Error(res.message); }
            })
            .catch(err => {
                if(toastInstance) {
                    toastEl.classList.remove('border-success');
                    toastEl.classList.add('border-danger');
                    window.vvSetMessage(toastMessage, err.message, { iconClass: 'fa-solid fa-triangle-exclamation text-danger me-2' });
                    toastInstance.show();
                }
            })
            .finally(() => { submitBtn.innerText = originalBtnText; submitBtn.disabled = false; });
        });
    }
});