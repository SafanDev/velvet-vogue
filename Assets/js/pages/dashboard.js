/**
 * Velvet Vogue - VIP Dashboard Logic
 */
document.addEventListener("DOMContentLoaded", function () {
    "use strict";
    gsap.registerPlugin(ScrollTrigger);

    const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);

    // Initial Reveal
    gsap.from(".gsap-fade-in", { y: 20, opacity: 0, duration: 1, stagger: 0.2, ease: "power2.out" });

    // ==========================================
    // THE HUD GLITCH DECODE ENGINE
    // ==========================================
    class TextScramble {
        constructor(el) {
            this.el = el;
            this.chars = '!<>-_\\/[]{}—=+*^?#________ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            this.update = this.update.bind(this);
        }
        setText(newText) {
            const oldText = this.el.innerText;
            const length = Math.max(oldText.length, newText.length);
            const promise = new Promise((resolve) => this.resolve = resolve);
            this.queue = [];
            for (let i = 0; i < length; i++) {
                const from = oldText[i] || '';
                const to = newText[i] || '';
                const start = Math.floor(Math.random() * 40);
                const end = start + Math.floor(Math.random() * 40);
                this.queue.push({ from, to, start, end });
            }
            cancelAnimationFrame(this.frameRequest);
            this.frame = 0;
            this.update();
            return promise;
        }
        update() {
            let output = '';
            let complete = 0;
            for (let i = 0, n = this.queue.length; i < n; i++) {
                let { from, to, start, end, char } = this.queue[i];
                if (this.frame >= end) {
                    complete++;
                    output += escapeHtml(to);
                } else if (this.frame >= start) {
                    if (!char || Math.random() < 0.28) {
                        char = this.randomChar();
                        this.queue[i].char = char;
                    }
                    output += `<span class="gold-text opacity-75">${escapeHtml(char)}</span>`;
                } else {
                    output += escapeHtml(from);
                }
            }
            this.el.innerHTML = output;
            if (complete === this.queue.length) {
                this.resolve();
            } else {
                this.frameRequest = requestAnimationFrame(this.update);
                this.frame++;
            }
        }
        randomChar() {
            return this.chars[Math.floor(Math.random() * this.chars.length)];
        }
    }

    const triggerTerminalDecode = (panelId) => {
        const panel = document.getElementById(panelId);
        if (!panel) return;
        
        const scanLine = document.querySelector('.terminal-scan-line');
        if(scanLine) {
            gsap.fromTo(scanLine, { top: "-10%", opacity: 1 }, { top: "110%", opacity: 0, duration: 1.2, ease: "power2.inOut" });
        }

        panel.querySelectorAll('.decode-text').forEach(el => {
            if(!el.dataset.originalText) el.dataset.originalText = el.innerText;
            const fx = new TextScramble(el);
            fx.setText(el.dataset.originalText);
        });

        panel.querySelectorAll('.decode-val').forEach(el => {
            if(!el.dataset.originalVal) el.dataset.originalVal = el.value;
            if(el.hasAttribute('readonly')) return;

            const original = el.dataset.originalVal;
            el.value = '';
            let iteration = 0;
            const interval = setInterval(() => {
                let scramble = '';
                for(let i=0; i<original.length; i++) scramble += 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'[Math.floor(Math.random() * 36)];
                el.value = scramble;
                if(++iteration >= 20) { clearInterval(interval); el.value = original; }
            }, 30);
        });
    };

    setTimeout(() => triggerTerminalDecode('panel-overview'), 400);

    // ==========================================
    // TAB SWITCHING LOGIC
    // ==========================================
    document.querySelectorAll('.dash-nav-btn[data-target]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.dataset.target;
            if(this.classList.contains('active')) return;

            document.querySelectorAll('.dash-nav-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            const currentPanel = document.querySelector('.dash-panel.active');
            const targetPanel = document.getElementById(targetId);

            gsap.to(currentPanel, { y: -15, opacity: 0, duration: 0.3, onComplete: () => {
                currentPanel.classList.remove('active');
                targetPanel.classList.add('active');
                gsap.fromTo(targetPanel, { y: 15, opacity: 0 }, { y: 0, opacity: 1, duration: 0.4, onComplete: () => triggerTerminalDecode(targetId) });
            }});
        });
    });

    // ==========================================
    // AJAX ACTION WIRING & STRICT VALIDATION
    // ==========================================
    
    // Auto-hide error if user clicks Male or Female
    // DO NOT hide if they click "Other" (we want the error to persist or show upon submit)
    document.querySelectorAll('.gender-radio').forEach(radio => {
        radio.addEventListener('change', function() {
            if(this.value === 'Male' || this.value === 'Female') {
                const errorMsg = document.getElementById('genderErrorMsg');
                if(errorMsg) errorMsg.style.display = 'none';
            }
        });
    });

    // 1. Update Identity
    const btnUpdateProfile = document.getElementById('btnUpdateProfile');
    if(btnUpdateProfile) {
        btnUpdateProfile.addEventListener('click', (e) => {
            e.preventDefault(); 
            
            const identityForm = document.getElementById('profileForm');
            const selectedRadio = identityForm.querySelector('input[name="gender"]:checked');
            const errorMsg = document.getElementById('genderErrorMsg');
            
            // Require an explicit gender selection before saving.
            if (!selectedRadio || (selectedRadio.value !== 'Male' && selectedRadio.value !== 'Female')) {
                errorMsg.style.display = 'block';
                gsap.killTweensOf(errorMsg);
                gsap.fromTo(errorMsg, 
                    {opacity: 0, x: -15}, 
                    {opacity: 1, x: 0, duration: 0.4, ease: "bounce.out(2)"}
                );
                return; 
            }
            
            errorMsg.style.display = 'none';

            const formData = new FormData(identityForm);
            formData.append('action', 'update_identity');
            formData.append('fname', document.getElementById('fname_field').value);
            formData.append('lname', document.getElementById('lname_field').value);
            formData.append('phone', document.getElementById('phone_field').value);
            formData.append('gender', selectedRadio.value);

            fetch('../Actions/dashboard_actions.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(typeof Swal !== "undefined") {
                    Swal.fire({ title: data.status.toUpperCase(), text: data.message, icon: data.status, background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37' });
                } else {
                    alert(data.message);
                }
            })
            .catch(() => alert("Network error connecting to the secure terminal."));
        });
    }

    // 2. Change Password
    const btnUpdatePassword = document.getElementById('btnUpdatePassword');
    if(btnUpdatePassword) {
        btnUpdatePassword.addEventListener('click', (e) => {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'change_password');
            formData.append('current_pwd', document.getElementById('current_pwd').value);
            formData.append('new_pwd', document.getElementById('new_pwd').value);

            fetch('../Actions/dashboard_actions.php', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if(typeof Swal !== "undefined") {
                    Swal.fire({ title: data.status.toUpperCase(), text: data.message, icon: data.status, background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37' });
                } else {
                    alert(data.message);
                }
                if(data.status === 'success') {
                    document.getElementById('current_pwd').value = '';
                    document.getElementById('new_pwd').value = '';
                }
            });
        });
    }

    // 3. Save New Address
    window.saveNewAddress = function() {
        const formData = new FormData();
        formData.append('action', 'add_address');
        formData.append('label', document.getElementById('newLabel').value);
        formData.append('name', document.getElementById('newName').value);
        formData.append('street', document.getElementById('newStreet').value);
        formData.append('city', document.getElementById('newCity').value);
        formData.append('zip', document.getElementById('newZip').value);

        fetch('../Actions/dashboard_actions.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            if(typeof Swal !== "undefined") {
                Swal.fire({ title: data.status.toUpperCase(), text: data.message, icon: data.status, background: '#0a0a0a', color: '#fff', confirmButtonColor: '#D4AF37' })
                .then(() => { if(data.status === 'success') location.reload(); });
            } else {
                alert(data.message);
                if(data.status === 'success') location.reload();
            }
        });
    };
});

// Remove Address Function
window.removeAddress = function(id) {
    if(!confirm("Are you sure you want to delete this address?")) return;
    
    const formData = new FormData();
    formData.append('action', 'remove_address');
    formData.append('addressID', id);

    fetch('../Actions/dashboard_actions.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
        if(data.status === 'success') {
            document.getElementById('addr-card-' + id).remove();
        } else {
            alert(data.message);
        }
    });
};