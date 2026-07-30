/**
 * Velvet Vogue - Support Desk Logic
 */

document.addEventListener("DOMContentLoaded", function () {
    "use strict";

    // Intro Animations
    gsap.from(".scroll-reveal", { y: 20, opacity: 0, duration: 0.6, stagger: 0.1, ease: "power3.out" });

    // 3D Tilt Effect on Metrics
    const cards = document.querySelectorAll('.spotlight-card');
    cards.forEach(card => {
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            card.style.transform = `perspective(1000px) rotateX(${((y - centerY) / centerY) * -4}deg) rotateY(${((x - centerX) / centerX) * 4}deg) translateY(-2px)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) translateY(0)`;
            card.style.transition = `transform 0.5s cubic-bezier(0.16, 1, 0.3, 1)`;
        });
        card.addEventListener('mouseenter', () => { card.style.transition = `none`; });
    });

    // Search Logic
    const searchInput = document.getElementById('ticketSearch');
    const inboxItems = document.querySelectorAll('.inbox-item');

    if (searchInput) {
        searchInput.addEventListener('keyup', function() {
            const filter = this.value.toLowerCase();
            inboxItems.forEach(item => {
                const text = item.getAttribute('data-search');
                item.style.display = text.indexOf(filter) > -1 ? '' : 'none';
            });
        });
    }

    // DOM Elements
    const emptyState = document.getElementById('emptyState');
    const activeView = document.getElementById('activeTicketView');
    const replyForm = document.getElementById('replyForm');
    const toastEl = document.getElementById('actionToast');
    const toastMessage = document.getElementById('toastMessage');
    let toastInstance = null;
    if (toastEl) toastInstance = new bootstrap.Toast(toastEl, { delay: 3000 });

    // Dynamic Metric Math
    function recalculateMetrics() {
        let total = 0, open = 0, inProg = 0, resolvedClosed = 0;

        document.querySelectorAll('.inbox-item').forEach(item => {
            total++;
            const status = item.getAttribute('data-status');
            if (status === 'open') open++;
            if (status === 'in_progress') inProg++;
            if (status === 'resolved' || status === 'closed') resolvedClosed++;
        });

        const ids = ['countTotal', 'countOpen', 'countProgress', 'countResolved'];
        const values = [total, open, inProg, resolvedClosed];

        ids.forEach((id, index) => {
            const el = document.getElementById(id);
            if(parseInt(el.innerText) !== values[index]) {
                el.innerText = values[index];
                // Subtle scale bounce for the minimalist cards
                gsap.fromTo(el, { scale: 1.3 }, { scale: 1, duration: 0.5, ease: "back.out(1.7)" });
            }
        });
        document.getElementById('totalBadge').innerText = total + " Tickets";
    }

    // 1. SELECT TICKET (AJAX LOAD)
    inboxItems.forEach(item => {
        item.addEventListener('click', function() {

            // UI Active State
            inboxItems.forEach(i => i.classList.remove('active-ticket'));
            this.classList.add('active-ticket');

            const ticketID = this.getAttribute('data-id');

            const formData = new FormData();
            formData.append('action', 'get_ticket');
            formData.append('inquiryID', ticketID);

            fetch('inquiries.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {
                    const data = res.data;

                    // Populate DOM
                    document.getElementById('c_subject').innerText = data.subject;
                    document.getElementById('c_name').innerText = data.senderName;
                    document.getElementById('c_email').innerText = data.senderEmail;
                    document.getElementById('c_ticketID').innerText = 'Ticket #' + data.inquiryID;

                    const dateObj = new Date(data.createdAt);
                    document.getElementById('c_date').innerText = dateObj.toLocaleDateString() + ' ' + dateObj.toLocaleTimeString();

                    if(data.phoneNo) {
                        document.getElementById('c_phone_wrapper').classList.remove('d-none');
                        document.getElementById('c_phone').innerText = data.phoneNo;
                    } else {
                        document.getElementById('c_phone_wrapper').classList.add('d-none');
                    }

                    // Format message
                    const messageElement = document.getElementById('c_message');
                    messageElement.textContent = data.inquiryMessage;
                    messageElement.style.whiteSpace = 'pre-wrap';

                    // Populate Reply Area if exists
                    const replyContainer = document.getElementById('adminReplyContainer');
                    if (data.reply) {
                        replyContainer.classList.remove('d-none');
                        const replyElement = document.getElementById('c_reply');
                        replyElement.textContent = data.reply;
                        replyElement.style.whiteSpace = 'pre-wrap';
                        const replyDate = new Date(data.repliedAt);
                        document.getElementById('c_replyDate').innerText = replyDate.toLocaleDateString() + ' ' + replyDate.toLocaleTimeString();

                        document.getElementById('f_replyText').value = data.reply;
                    } else {
                        replyContainer.classList.add('d-none');
                        document.getElementById('f_replyText').value = '';
                    }

                    // Force form label active state
                    document.getElementById('f_replyText').dispatchEvent(new Event('input'));

                    // Set hidden ID and dropdown
                    document.getElementById('f_inquiryID').value = data.inquiryID;
                    document.getElementById('f_status').value = data.inquiryStatus;

                    // Cinematic Transition
                    gsap.to(emptyState, { opacity: 0, duration: 0.3, onComplete: () => {
                        emptyState.classList.add('d-none');
                        activeView.classList.remove('d-none');
                        activeView.classList.add('d-flex');
                        gsap.fromTo(activeView, {opacity: 0, x: 20}, {opacity: 1, x: 0, duration: 0.4});
                    }});

                } else { throw new Error(res.message); }
            })
            .catch(err => {
                if(toastInstance) {
                    toastEl.classList.remove('border-success');
                    toastEl.classList.add('border-danger');
                    toastMessage.textContent = 'Error loading ticket.';
                    toastInstance.show();
                }
            });
        });
    });

    // 2. SUBMIT REPLY (AJAX)
    if(replyForm) {
        replyForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const btn = document.getElementById('submitReplyBtn');
            const originalText = btn.innerHTML;
            btn.innerHTML = 'SENDING...';
            btn.disabled = true;

            const formData = new FormData(this);

            fetch('inquiries.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(res => {
                if(res.status === 'success') {

                    if(toastInstance) {
                        toastEl.classList.remove('border-danger');
                        toastEl.classList.add('border-success');
                        toastMessage.textContent = res.message;
                        toastInstance.show();
                    }

                    // Instantly show reply
                    const replyContainer = document.getElementById('adminReplyContainer');
                    replyContainer.classList.remove('d-none');
                    const replyElement = document.getElementById('c_reply');
                    replyElement.textContent = document.getElementById('f_replyText').value;
                    replyElement.style.whiteSpace = 'pre-wrap';
                    document.getElementById('c_replyDate').innerText = 'Just now';

                    // Update UI state in Left Inbox Pane
                    const activeItem = document.querySelector('.inbox-item.active-ticket');
                    if(activeItem) {
                        const newStat = document.getElementById('f_status').value;

                        // Update attribute for math recount
                        activeItem.setAttribute('data-status', newStat);

                        // Update dot color visually
                        const dot = activeItem.querySelector('.status-dot');
                        dot.className = 'status-dot'; // reset
                        if(newStat === 'open') dot.classList.add('status-red');
                        if(newStat === 'in_progress') dot.classList.add('status-gold');
                        if(newStat === 'resolved') dot.classList.add('status-green');
                        if(newStat === 'closed') dot.classList.add('status-grey');
                    }

                    // Trigger Dynamic DOM math
                    recalculateMetrics();

                } else { throw new Error(res.message); }
            })
            .catch(err => {
                if(toastInstance) {
                    toastEl.classList.remove('border-success');
                    toastEl.classList.add('border-danger');
                    toastMessage.textContent = err.message || 'The request failed.';
                    toastInstance.show();
                }
            })
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    }
});